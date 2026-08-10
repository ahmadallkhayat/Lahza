<?php

namespace App\Services;

use App\Models\Invitation;
use App\Services\Cards\CardEngine;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The card a shared link shows: the couple's names, in the theme's colours.
 *
 * This is a recipe, not the machinery — colours, wording, layout, and which
 * font to shape it in. Everything that actually touches disk or ffmpeg (a
 * working directory it can prove it owns, fonts cached by family, retried
 * and verified renders, detailed logging on failure) lives once in
 * CardEngine, so a future base template wanting its own card writes a recipe
 * like this one rather than re-earning that machinery from scratch. See the
 * class docblock there for why each piece of it exists.
 *
 * Built with ffmpeg rather than GD, and the reason is Arabic. GD's
 * `imagettftext` draws codepoints one after another in the order given — no
 * joining, no ligatures, no bidi — so أَحْمَد comes out as five disconnected
 * isolated letterforms running the wrong way. ffmpeg's `drawtext` shapes
 * properly when the build has HarfBuzz and FriBidi, which is what `text_shaping`
 * below switches on. Imagick with Pango would also do, but it is not installed
 * and ffmpeg already is.
 *
 * Text arrives through a scratch file, not the filter string — CardEngine's
 * job, not this class's — because `drawtext` treats `:`, `'`, `%` and `\` as
 * syntax, and a name is user input. Escaping it correctly inside a
 * filtergraph is a trap where getting it wrong means either a broken image
 * or an injected filter argument.
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
     * Amiri and not Aref Ruqaa, which the page uses for names.
     *
     * Aref Ruqaa's TTF carries the glyphs and a GSUB table, and freetype
     * still resolved every Arabic letter to .notdef through drawtext — the
     * card came out as a row of hollow boxes with the tashkeel floating
     * around them. Amiri shapes correctly in the same command, and it is
     * already this design's serif, so the card stays in the family.
     */
    private const FONT_FAMILY = 'Amiri';

    private const FONT_FILE = 'amiri.ttf';

    public function __construct(
        private readonly CardEngine $engine = new CardEngine(self::WIDTH, self::HEIGHT),
    ) {}

    /**
     * Draw the card, or give up — CardEngine has already logged why.
     *
     * A card is decorative and an invitation is not, so nothing here is
     * allowed to cost the studio the save it just made.
     */
    public function generate(Invitation $invitation): ?string
    {
        $names = $this->names($invitation);

        if ($names === '') {
            return null;
        }

        // Resolved once here to size the text, and again inside render() to
        // build the filtergraph — both calls land on the same directory and
        // the same cached file, so the second is effectively free.
        $dir = $this->engine->resolveWorkDir();

        if ($dir === null) {
            // Already logged by the engine with the exact reason.
            return null;
        }

        $font = $this->engine->face(self::FONT_FAMILY, self::FONT_FILE, $dir);

        if ($font === null) {
            return null;
        }

        $palette = $invitation->template?->palette() ?? [];
        $paper = $this->hex($palette['paper'] ?? '#f7f1e8');
        $ink = $this->hex($palette['gold_deep'] ?? '#856a3c');
        $soft = $this->hex($palette['ink_soft'] ?? '#6b5648');
        $rule = $this->hex($palette['gold'] ?? '#a8874f');

        $date = $invitation->formattedDate();
        $available = self::WIDTH - 2 * (self::FRAME_INSET + self::SAFE_PADDING);

        $nameSize = $this->engine->fit($names, $font, self::NAME_MAX, self::NAME_MIN, $available);
        $dateSize = $this->engine->fit($date, $font, self::DATE_MAX, self::DATE_MIN, $available);

        $relative = 'invitations/og/'.$invitation->slug.'-'.Str::lower(Str::random(8)).'.jpg';

        return $this->engine->render(
            logContext: $invitation->slug,
            background: $paper,
            texts: ['name' => $names, 'date' => $date],
            fonts: [self::FONT_FAMILY => self::FONT_FILE],
            buildFilterGraph: fn (array $textFiles, array $fontFiles) => implode(',', [
                // A hairline frame just inside the edge.
                'drawbox=x='.self::FRAME_INSET.':y='.self::FRAME_INSET
                    .':w='.(self::WIDTH - 2 * self::FRAME_INSET)
                    .':h='.(self::HEIGHT - 2 * self::FRAME_INSET)
                    .':color='.$rule.'@0.45:t=2',

                // The names, a little above centre to leave the date room.
                'drawtext=textfile='.$textFiles['name']
                    .':fontfile='.$fontFiles[self::FONT_FAMILY]
                    .':text_shaping=1'
                    .':fontcolor='.$ink
                    .':fontsize='.$nameSize
                    .':x=(w-text_w)/2:y=(h-text_h)/2-56',

                'drawbox=x=(iw-140)/2:y=ih/2+72:w=140:h=1:color='.$rule.'@0.7:t=fill',

                'drawtext=textfile='.$textFiles['date']
                    .':fontfile='.$fontFiles[self::FONT_FAMILY]
                    .':text_shaping=1'
                    .':fontcolor='.$soft
                    .':fontsize='.$dateSize
                    // drawtext exposes the frame as w/h. iw/ih are
                    // drawbox's names for it and are a parse error here.
                    .':x=(w-text_w)/2:y=h/2+112',
            ]),
            outputRelativePath: $relative,
        );
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

    /**
     * Remove combining diacritical marks (U+064B to U+0652).
     *
     * `\x{...}`, not `\u{...}` — the latter is a PHP *string* escape and
     * means nothing to PCRE, which read it as the literal two characters
     * `\u` followed by a brace group and refused to compile the pattern.
     * Every single card failed on this until the log actually said why.
     */
    private function stripDiacritics(string $text): string
    {
        return preg_replace('/[\x{064B}-\x{0652}]/u', '', $text) ?? $text;
    }

    /** ffmpeg wants 0xRRGGBB; the palette stores #rrggbb. */
    private function hex(string $colour): string
    {
        return '0x'.ltrim($colour, '#');
    }
}
