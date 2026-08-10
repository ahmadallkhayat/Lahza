<?php

namespace App\Services\Cards;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The hardened, template-agnostic machinery behind every share card this
 * studio draws with ffmpeg: a working directory this process can prove it
 * owns, render fonts cached by family, scratch text files ffmpeg can read
 * safely, and a result that is actually verified before it is trusted.
 *
 * Nothing in here knows what a "couple" or an "invitation" is — that belongs
 * to whichever generator calls it. OgImageGenerator is luxury-wedding-v1's
 * caller today; a future base template wanting its own card — different
 * colours, a different font, different wording, even a different layout —
 * gets every guarantee below by building its own filtergraph and handing it
 * to render(), rather than re-earning each of these the way this one had to:
 *
 *   - a render face that fetches and caches itself instead of depending on a
 *     manual `fonts:fetch` nobody's deploy pipeline ever ran
 *   - a working directory it can prove — by writing to it — rather than
 *     inheriting one whose ownership or ACL turned out to be wrong, which no
 *     amount of chmod from inside PHP can fix if the process is not that
 *     directory's owner
 *   - an ffmpeg failure that reaches the log with its real exit code and
 *     stderr, since a non-zero exit is not a PHP exception and generate()'s
 *     catch alone never saw it
 *   - a result that is actually decoded and measured before being trusted,
 *     since ffmpeg has exited 0 while writing a blank or truncated frame
 *   - one retry for whichever of the above turns out to be transient
 *
 * Each of those was a real, separate failure on the live server before it
 * was a line item here.
 */
class CardEngine
{
    /** A blank or truncated frame is nowhere near this large. */
    private const MIN_VALID_BYTES = 2000;

    /** One retry for a transient hiccup; a second failure in a row is real. */
    private const MAX_ATTEMPTS = 2;

    /**
     * Where this studio used to keep render faces, back when callers trusted
     * that directory's permissions instead of workDir() proving them. Read
     * from only, and only to skip a redownload — see workDir().
     */
    private const LEGACY_DIR = 'app/fonts';

    public function __construct(
        private readonly int $width = 1200,
        private readonly int $height = 630,
    ) {}

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    /**
     * Draw one JPEG and return its public URL, or null with the reason
     * already in the log.
     *
     * @param  string  $logContext  How this render names itself in a log line — an invitation slug today, whatever a future caller has tomorrow.
     * @param  string  $background  0xRRGGBB.
     * @param  array<string, string>  $texts  Logical name => literal text. Written to scratch files and never interpolated into the filtergraph directly — drawtext treats `:`, `'`, `%` and `\` as syntax, and this text is user input.
     * @param  array<string, string>  $fonts  Google Fonts family name => the filename to cache its TTF as, e.g. ['Amiri' => 'amiri.ttf']. Any family Google Fonts serves works here — nothing below is specific to Amiri or to Arabic.
     * @param  callable(array<string, string> $textFiles, array<string, string> $fontFiles): string  $buildFilterGraph  Receives logical name => bare filename for each text and font (bare, not a path — see the note on the working directory in attempt()) and returns the complete -vf value.
     * @param  string  $outputRelativePath  Where the result lands on the "public" disk.
     */
    public function render(
        string $logContext,
        string $background,
        array $texts,
        array $fonts,
        callable $buildFilterGraph,
        string $outputRelativePath,
    ): ?string {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $url = $this->attempt(
                    $logContext, $attempt, $background, $texts, $fonts, $buildFilterGraph, $outputRelativePath,
                );

                if ($url !== null) {
                    return $url;
                }
            } catch (Throwable $e) {
                Log::warning('A share card could not be drawn.', [
                    'context' => $logContext,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $texts
     * @param  array<string, string>  $fonts
     * @param  callable(array<string, string>, array<string, string>): string  $buildFilterGraph
     */
    private function attempt(
        string $logContext,
        int $attempt,
        string $background,
        array $texts,
        array $fonts,
        callable $buildFilterGraph,
        string $outputRelativePath,
    ): ?string {
        $dir = $this->resolveWorkDir();

        if ($dir === null) {
            // Already logged by workDir() with the exact reason.
            return null;
        }

        $fontFiles = [];

        foreach ($fonts as $family => $filename) {
            $path = $this->face($family, $filename, $dir);

            if ($path === null) {
                return null;
            }

            $fontFiles[$family] = basename($path);
        }

        $scratchPaths = [];
        $textFiles = [];

        foreach ($texts as $name => $text) {
            $path = $this->scratch($text, $dir);
            $scratchPaths[] = $path;
            $textFiles[$name] = basename($path);
        }

        $target = Storage::disk('public')->path($outputRelativePath);
        Storage::disk('public')->makeDirectory(dirname($outputRelativePath));

        try {
            // Run from the working directory and refer to everything by bare
            // filename. A filtergraph splits its options on `:`, so an
            // absolute Windows path breaks the graph at the drive letter —
            // and neither quoting nor backslash-escaping survives every
            // ffmpeg build. With no path there is no colon, and the same
            // command works unchanged on the Linux server where the problem
            // never existed.
            $result = Process::path($dir)->timeout(60)->run([
                config('templates.ffmpeg'),
                '-y',
                '-f', 'lavfi',
                '-i', 'color=c='.$background.':s='.$this->width.'x'.$this->height,
                '-vf', $buildFilterGraph($textFiles, $fontFiles),
                '-frames:v', '1',
                // Without this the image2 muxer treats the filename as a
                // sequence pattern, finds none, and refuses to write.
                '-update', '1',
                '-q:v', '3',
                $target,
            ]);
        } finally {
            File::delete($scratchPaths);
        }

        if (! $result->successful()) {
            // The one failure that is not a PHP exception — ffmpeg reports a
            // non-zero exit on stderr, not by throwing.
            Log::warning('ffmpeg could not draw the share card.', [
                'context' => $logContext,
                'attempt' => $attempt,
                'exit_code' => $result->exitCode(),
                'stderr' => Str::limit($result->errorOutput(), 4000),
            ]);

            Storage::disk('public')->delete($outputRelativePath);

            return null;
        }

        if (! $this->isValidImage($target)) {
            Log::warning('ffmpeg exited cleanly but wrote no usable image.', [
                'context' => $logContext,
                'attempt' => $attempt,
                'target' => $target,
                'bytes' => File::exists($target) ? File::size($target) : 0,
            ]);

            Storage::disk('public')->delete($outputRelativePath);

            return null;
        }

        return Storage::disk('public')->url($outputRelativePath);
    }

    /**
     * The largest size at which a line still fits within $available pixels.
     *
     * Measured with GD rather than guessed from a character count, because
     * two strings of the same length are rarely the same width — accented
     * Latin, wide Arabic ligatures, and CJK are each wrong for the others.
     *
     * GD cannot *shape* Arabic — that is what ffmpeg's text_shaping is for —
     * but imagettfbbox still sums the advances of the glyphs it is given, and
     * that sum is a fair measure of the line. It errs high, since joined
     * letterforms are narrower than isolated ones, so anything it accepts as
     * fitting genuinely fits. Erring small is the harmless direction.
     *
     * Width scales linearly with point size, so one measurement is enough to
     * solve for the size rather than searching for it.
     */
    public function fit(string $text, string $fontPath, int $max, int $min, int $available): int
    {
        if (! function_exists('imagettfbbox')) {
            // No measuring available. Assume the worst rather than overflow.
            return $min;
        }

        $box = @imagettfbbox($max, 0, $fontPath, $text);

        if ($box === false) {
            return $min;
        }

        $width = abs($box[2] - $box[0]);

        if ($width <= 0 || $width <= $available) {
            return $max;
        }

        return max($min, (int) floor($max * $available / $width));
    }

    /**
     * The absolute path of a render face this process can read, downloading
     * and caching it first if $dir has never had one for this family.
     *
     * Checked in order: already in $dir (the common case after the first
     * call anywhere); left over from the old storage/app/fonts location
     * (readable even though it was not writable, so reusing it skips a
     * redownload); or fetched fresh from Google Fonts. storage/ is persisted
     * across deploys, so whichever path succeeds only ever pays its cost once
     * per server, per family.
     */
    public function face(string $family, string $filename, string $dir): ?string
    {
        $path = $dir.DIRECTORY_SEPARATOR.$filename;

        if (File::exists($path)) {
            return $path;
        }

        $legacy = storage_path(self::LEGACY_DIR.DIRECTORY_SEPARATOR.$filename);

        if (File::exists($legacy) && File::isReadable($legacy)) {
            try {
                File::copy($legacy, $path);

                return $path;
            } catch (Throwable) {
                // Falls through to a fresh download.
            }
        }

        return $this->downloadFace($family, $path) ? $path : null;
    }

    /**
     * Fetch a whole TTF straight from Google Fonts.
     *
     * Google serves woff2 to a modern user agent and TTF to one it believes
     * cannot handle that — ffmpeg needs the latter, hence the old Mozilla/4.0
     * string. Works for any family name Google Fonts recognises; nothing
     * here is specific to one face.
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

            File::put($target, $file->body());

            return true;
        } catch (Throwable $e) {
            Log::warning('Could not download a render face.', [
                'family' => $family,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * A directory this process can prove — not assume — it can write to, or
     * null with the reason already logged.
     *
     * storage/app/fonts was the original home for this, and on the live
     * server it turned out to be owned by root with a POSIX ACL mask that
     * silently capped php-fpm's own `rwx` grant down to read-only. Chmod
     * could never fix that from here: only a file's owner or root may change
     * its permissions or its ACL, and the web server was neither for a
     * directory it did not create.
     *
     * So this creates its own: storage/app/private is Laravel's own local
     * disk root, and a fresh subdirectory under it is made by this very
     * process, which makes php-fpm the owner from the moment it exists —
     * unlike the old one, there is no inherited ownership to get wrong.
     *
     * That alone was not quite enough. The live server showed that Laravel's
     * own ensureDirectoryExists(..., 0755) leaves *this* directory's ACL mask
     * at r-x too, reproducibly, regardless of who runs it — the parent's
     * default ACL grants rwx but the requested mode does not carry it
     * through. The difference from the old directory is that this process
     * now owns the one it just created, so — unlike last time — chmod is
     * actually allowed to correct that, and does, before this gives up.
     *
     * A write is attempted and reversed on every call rather than trusting
     * is_writable(), which reasons from permission bits and can be wrong
     * about exactly this kind of ACL nuance. Actually writing is the only
     * test that cannot lie.
     *
     * Public so a caller that needs this same directory ahead of render() —
     * OgImageGenerator sizes text with a font before it knows the final
     * filtergraph — resolves it once, the same way, rather than guessing at
     * a path this class already owns the decision for.
     */
    public function resolveWorkDir(): ?string
    {
        $dir = storage_path('app/private/cards');

        try {
            File::ensureDirectoryExists($dir);
        } catch (Throwable $e) {
            Log::warning('The card working directory could not be created.', [
                'dir' => $dir,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($this->probeWritable($dir)) {
            return $dir;
        }

        // Found on the live server: Laravel's own mkdir(..., 0755) leaves
        // this exact directory's ACL mask at r-x even though its parent's
        // default ACL grants rwx — reproduced identically whether php-fpm or
        // this process created it. Unlike the old storage/app/fonts
        // location, this directory is one this process (or an earlier run of
        // it) created itself, so — unlike that one — it is actually the
        // owner here and chmod is allowed to succeed. Retried once after,
        // since that is the only thing this attempt changed.
        @chmod($dir, 0775);

        if ($this->probeWritable($dir)) {
            return $dir;
        }

        // Still not writable after owning it and asking nicely: this is not
        // the ACL-inheritance quirk above, since that always yields to
        // chmod. Something else is wrong — wrong owner despite this process
        // having created it (a leftover from before this code existed, say),
        // a full disk, a read-only mount — and it needs a person, not
        // another retry.
        Log::warning('The card working directory exists but is not writable.', [
            'dir' => $dir,
            'owner_uid' => fileowner($dir),
            'process_uid' => function_exists('posix_geteuid') ? posix_geteuid() : null,
        ]);

        return null;
    }

    /** Whether $dir can actually be written to right now — the only test that cannot lie about it. */
    private function probeWritable(string $dir): bool
    {
        $probe = $dir.DIRECTORY_SEPARATOR.'.write-test-'.Str::lower(Str::random(8));

        try {
            File::put($probe, 'ok');
            File::delete($probe);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * A UTF-8 scratch file holding one line of text for drawtext to read.
     *
     * Beside the fonts so it can be named without a path — see the note on
     * the working directory in attempt().
     */
    private function scratch(string $text, string $dir): string
    {
        $path = $dir.DIRECTORY_SEPARATOR.'.text-'.Str::lower(Str::random(12));

        File::put($path, $text);

        return $path;
    }

    /**
     * A real check that the file ffmpeg wrote is actually a usable card, not
     * just that the process exited 0.
     *
     * ffmpeg has exited successfully while writing a truncated or blank frame
     * before now — a 0 exit code says the muxer finished, not that what it
     * wrote decodes to the exact photo this promised. getimagesize() reads
     * the real header rather than trusting the extension, and the byte-count
     * floor catches an all-background frame ffmpeg happily calls valid JPEG.
     */
    private function isValidImage(string $path): bool
    {
        if (! File::exists($path) || File::size($path) < self::MIN_VALID_BYTES) {
            return false;
        }

        $info = @getimagesize($path);

        return $info !== false && $info[0] === $this->width && $info[1] === $this->height;
    }
}
