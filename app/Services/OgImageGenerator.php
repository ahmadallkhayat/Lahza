<?php

namespace App\Services;

use App\Models\Invitation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The card a shared link shows: the couple's names, in the theme's colours.
 *
 * Built with ffmpeg rather than GD, and the reason is Arabic. GD's
 * `imagettftext` draws codepoints one after another in the order given — no
 * joining, no ligatures, no bidi — so أَحْمَد comes out as five disconnected
 * isolated letterforms running the wrong way. ffmpeg's `drawtext` shapes
 * properly when the build has HarfBuzz and FriBidi, which is what `text_shaping`
 * below switches on. Imagick with Pango would also do, but it is not installed
 * and ffmpeg already is.
 *
 * Text arrives through a file, not the filter string. `drawtext` treats `:`,
 * `'`, `%` and `\` as syntax, and a name is user input — escaping it correctly
 * inside a filtergraph is a trap where getting it wrong means either a broken
 * image or an injected filter argument.
 *
 * A missing card is not an error. Without ffmpeg or the render face the page
 * simply carries no og:image, which is where it started.
 */
class OgImageGenerator
{
    /** Facebook and WhatsApp both want 1.91:1; this is the size they document. */
    private const WIDTH = 1200;

    private const HEIGHT = 630;

    /** Where the hairline frame sits. */
    private const FRAME_INSET = 40;

    /**
     * Clear space between that frame and any text.
     *
     * drawtext neither wraps nor shrinks — it draws at the size given and lets
     * the result run off the edge — so a long pair of names went straight
     * through the frame. Nothing here is centred-and-hoped: the size is
     * measured down until it fits inside this margin.
     */
    private const SAFE_PADDING = 56;

    /** Largest and smallest the names are allowed to be. */
    private const NAME_MAX = 104;

    private const NAME_MIN = 44;

    private const DATE_MAX = 42;

    private const DATE_MIN = 24;

    /**
     * Faces `fonts:fetch` downloads as whole TTFs for this renderer.
     *
     * @var array<string, string>
     */
    public const FACES = [
        'Amiri' => 'amiri.ttf',
    ];

    /**
     * Draw the card for an invitation, returning its public URL.
     *
     * @return string|null null when it could not be drawn
     */
    /**
     * Draw the card, or give up quietly.
     *
     * A card is decorative and an invitation is not, so nothing in here is
     * allowed to cost the studio the save it just made. The controller calls
     * this straight after the write: an exception meant the row was already
     * committed and the studio still got a 500, which read as "it failed" for
     * something that had in fact worked.
     *
     * Only the ffmpeg call used to be guarded. Everything before it — writing
     * the scratch files, making the target directory, formatting the date —
     * ran unprotected, which is exactly where a server missing a writable
     * storage/app/fonts throws.
     */
    public function generate(Invitation $invitation): ?string
    {
        try {
            return $this->draw($invitation);
        } catch (Throwable $e) {
            // Logged rather than swallowed: a card that silently never draws is
            // a preview that silently never appears, and this is the only place
            // that knows why.
            Log::warning('The invitation card could not be drawn.', [
                'invitation' => $invitation->slug,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function draw(Invitation $invitation): ?string
    {
        $names = $this->names($invitation);

        if ($names === '') {
            return null;
        }

        // Amiri and not Aref Ruqaa, which the page uses for names.
        //
        // Aref Ruqaa's TTF carries the glyphs and a GSUB table, and freetype
        // still resolved every Arabic letter to .notdef through drawtext — the
        // card came out as a row of hollow boxes with the tashkeel floating
        // around them. Amiri shapes correctly in the same command, and it is
        // already this design's serif, so the card stays in the family.
        $face = $this->face('Amiri');

        if ($face === null) {
            return null;
        }

        $palette = $invitation->template?->palette() ?? [];
        $paper = $this->hex($palette['paper'] ?? '#f7f1e8');
        $ink = $this->hex($palette['gold_deep'] ?? '#856a3c');
        $soft = $this->hex($palette['ink_soft'] ?? '#6b5648');
        $rule = $this->hex($palette['gold'] ?? '#a8874f');

        $date = $invitation->formattedDate();

        // Sized to fit before anything is drawn, since drawtext will happily
        // run a long line straight through the frame and off the canvas.
        $nameSize = $this->fit($names, $face, self::NAME_MAX, self::NAME_MIN);
        $dateSize = $this->fit($date, $face, self::DATE_MAX, self::DATE_MIN);

        $relative = 'invitations/og/'.$invitation->slug.'-'.Str::lower(Str::random(8)).'.jpg';
        $target = Storage::disk('public')->path($relative);

        Storage::disk('public')->makeDirectory(dirname($relative));

        // Written per render and removed after: ffmpeg reads them, nothing else.
        $nameFile = $this->scratch($names);
        $dateFile = $this->scratch($date);

        try {
            // Run from the font directory and refer to everything by bare
            // filename. A filtergraph splits its options on `:`, so an absolute
            // Windows path breaks the graph at the drive letter — and neither
            // quoting nor backslash-escaping survives every ffmpeg build. With
            // no path there is no colon, and the same command works unchanged
            // on the Linux server where the problem never existed.
            $result = Process::path($this->faceDir())->timeout(60)->run([
                config('templates.ffmpeg'),
                '-y',
                '-f', 'lavfi',
                '-i', 'color=c='.$paper.':s='.self::WIDTH.'x'.self::HEIGHT,
                '-vf', implode(',', [
                    // A hairline frame just inside the edge.
                    'drawbox=x='.self::FRAME_INSET.':y='.self::FRAME_INSET
                        .':w='.(self::WIDTH - 2 * self::FRAME_INSET)
                        .':h='.(self::HEIGHT - 2 * self::FRAME_INSET)
                        .':color='.$rule.'@0.45:t=2',

                    // The names, a little above centre to leave the date room.
                    'drawtext=textfile='.basename($nameFile)
                        .':fontfile='.self::FACES['Amiri']
                        .':text_shaping=1'
                        .':fontcolor='.$ink
                        .':fontsize='.$nameSize
                        .':x=(w-text_w)/2:y=(h-text_h)/2-56',

                    'drawbox=x=(iw-140)/2:y=ih/2+72:w=140:h=1:color='.$rule.'@0.7:t=fill',

                    'drawtext=textfile='.basename($dateFile)
                        .':fontfile='.self::FACES['Amiri']
                        .':text_shaping=1'
                        .':fontcolor='.$soft
                        .':fontsize='.$dateSize
                        // drawtext exposes the frame as w/h. iw/ih are
                        // drawbox's names for it and are a parse error here.
                        .':x=(w-text_w)/2:y=h/2+112',
                ]),
                '-frames:v', '1',
                // Without this the image2 muxer treats the filename as a
                // sequence pattern, finds none, and refuses to write.
                '-update', '1',
                '-q:v', '3',
                $target,
            ]);
        } finally {
            File::delete([$nameFile, $dateFile]);
        }

        if (! $result->successful() || ! Storage::disk('public')->exists($relative)) {
            // The only failure in draw() that generate()'s catch cannot see —
            // a non-zero exit is not an exception, ffmpeg reports it on
            // stderr instead. Logged here or it vanishes the same way a
            // missing render face used to.
            Log::warning('ffmpeg could not draw the invitation card.', [
                'invitation' => $invitation->slug,
                'exit_code' => $result->exitCode(),
                'stderr' => Str::limit($result->errorOutput(), 2000),
            ]);

            Storage::disk('public')->delete($relative);

            return null;
        }

        return Storage::disk('public')->url($relative);
    }

    /** Remove a previously drawn card. */
    public function forget(?string $url): void
    {
        if (blank($url) || ! str_contains($url, 'invitations/og/')) {
            return;
        }

        Storage::disk('public')->delete(ltrim(Str::after($url, '/storage/'), '/'));
    }

    /**
     * Whether a stored og_image points at a file that is actually still
     * there.
     *
     * The column and the disk can drift apart — a release that lost
     * shared/storage, a file deleted by hand, disk cleanup elsewhere — and
     * nothing else ever notices, since Eloquent has no idea the URL it is
     * holding no longer resolves to anything. This is what lets a save
     * re-check that rather than trusting the column.
     */
    public function exists(?string $url): bool
    {
        if (blank($url) || ! str_contains($url, 'invitations/og/')) {
            return false;
        }

        return Storage::disk('public')->exists(ltrim(Str::after($url, '/storage/'), '/'));
    }

    /**
     * The names as the card says them.
     *
     * Joined with the Arabic wāw rather than an ampersand. Aref Ruqaa is an
     * Arabic display face and its Latin coverage is not the point of it — a
     * missing `&` would draw as a hollow box in the middle of the two names.
     * "و" is both certain to exist and how the pairing is actually written.
     */
    private function names(Invitation $invitation): string
    {
        $groom = trim((string) $invitation->groom_ar);
        $bride = trim((string) $invitation->bride_ar);

        // Strip combining diacritical marks (harakat/tashkeel) if present.
        // They can cause rendering issues in ffmpeg. The invitation page shows
        // them; the og:image is just for the preview card.
        $groom = $this->stripDiacritics($groom);
        $bride = $this->stripDiacritics($bride);

        if ($groom === '' || $bride === '') {
            return trim($groom.$bride);
        }

        return $groom.' و '.$bride;
    }

    /** Remove combining diacritical marks (U+064B to U+0652). */
    private function stripDiacritics(string $text): string
    {
        return preg_replace('/[\u{064B}-\u{0652}]/u', '', $text) ?? $text;
    }

    /**
     * Absolute path of the render face, downloading it first if this server
     * has never had one.
     *
     * `fonts:fetch` normally does this ahead of time, but nothing in the
     * deploy pipeline ever runs it — it is a step someone has to remember to
     * SSH in and do by hand. Forget it once (or rebuild the box, or lose
     * `shared/storage`) and every card fails forever with nothing louder than
     * a log line, because generate() catches exactly this and turns it into a
     * quiet null. Fetching here means the card never depends on that manual
     * step existing: the first invitation saved on a bare server pays the
     * one-off download, storage/ is persisted across deploys, and every save
     * after that reads the cached file.
     */
    private function face(string $family): ?string
    {
        $filename = self::FACES[$family] ?? null;

        if ($filename === null) {
            return null;
        }

        $path = storage_path('app/fonts/'.$filename);

        if (File::exists($path)) {
            return $path;
        }

        return $this->downloadFace($family, $path) ? $path : null;
    }

    /**
     * Fetch a whole TTF straight from Google Fonts.
     *
     * Google serves woff2 to a modern user agent and TTF to one it believes
     * cannot handle that — ffmpeg needs the latter, hence the old Mozilla/4.0
     * string, same trick `fonts:fetch` uses.
     */
    private function downloadFace(string $family, string $target): bool
    {
        try {
            $url = 'https://fonts.googleapis.com/css2?family='
                .str_replace('%20', '+', rawurlencode($family)).':wght@400;700';

            $css = Http::timeout(15)->withHeaders(['User-Agent' => 'Mozilla/4.0'])->get($url);

            if (! $css->successful()) {
                return false;
            }

            preg_match_all('#https://fonts\.gstatic\.com/[^)\'"]+\.ttf#', $css->body(), $found);
            $ttf = $found[0][0] ?? null;

            if ($ttf === null) {
                return false;
            }

            $file = Http::timeout(15)->withHeaders(['User-Agent' => 'Mozilla/4.0'])->get($ttf);

            if (! $file->successful()) {
                return false;
            }

            File::ensureDirectoryExists(dirname($target));
            File::put($target, $file->body());

            return true;
        } catch (Throwable $e) {
            Log::warning('Could not download the render face.', [
                'family' => $family,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** Where the render faces and the scratch text files live. */
    private function faceDir(): string
    {
        return storage_path('app/fonts');
    }

    /**
     * A UTF-8 scratch file holding one line of text for drawtext to read.
     *
     * Beside the faces so it can be named without a path — see the comment on
     * the working directory above.
     */
    private function scratch(string $text): string
    {
        $path = $this->faceDir().DIRECTORY_SEPARATOR.'.text-'.Str::lower(Str::random(12));

        File::put($path, $text);

        return $path;
    }

    /**
     * The largest size at which a line still fits the safe area.
     *
     * Measured with GD rather than guessed from a character count, because
     * "أَحْمَد" and "عَبْد الرَّحْمَن" are nothing like the same width and a
     * per-character estimate is wrong for both.
     *
     * GD cannot *shape* Arabic — that is the whole reason ffmpeg draws this —
     * but imagettfbbox still sums the advances of the glyphs it is given, and
     * that sum is a fair measure of the line. It errs high, since joined
     * letterforms are narrower than isolated ones, so anything it accepts as
     * fitting genuinely fits. Erring small is the harmless direction.
     *
     * Width scales linearly with point size, so one measurement is enough to
     * solve for the size rather than searching for it.
     */
    private function fit(string $text, string $face, int $max, int $min): int
    {
        $available = self::WIDTH - 2 * (self::FRAME_INSET + self::SAFE_PADDING);

        if (! function_exists('imagettfbbox')) {
            // No measuring available. Assume the worst rather than overflow.
            return $min;
        }

        $box = @imagettfbbox($max, 0, $face, $text);

        if ($box === false) {
            return $min;
        }

        $width = abs($box[2] - $box[0]);

        if ($width <= 0 || $width <= $available) {
            return $max;
        }

        return max($min, (int) floor($max * $available / $width));
    }

    /** ffmpeg wants 0xRRGGBB; the palette stores #rrggbb. */
    private function hex(string $colour): string
    {
        return '0x'.ltrim($colour, '#');
    }
}
