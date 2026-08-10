<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Creates a boomerang (palindrome) loop from an intro clip.
 *
 * Forward, then the same footage backward, joined into one file. Looped, it
 * plays out to the end and comes back the way it came, so there is never a cut
 * from the last frame to the first — seamless infinite loop.
 *
 * It has to happen here because no browser can play a video backwards.
 * `playbackRate` refuses negative values, and stepping `currentTime` down frame
 * by frame stutters badly on a phone.
 *
 * Full resolution, unlike the ambient backdrop (which is heavily blurred).
 */
class BoomerangVideoEncoder
{
    /**
     * Longest clip worth reversing.
     *
     * ffmpeg's `reverse` filter buffers every frame it will emit into memory.
     * Intro clips run a few seconds; anything longer is refused rather than
     * allowed to exhaust the box.
     */
    private const MAX_SECONDS = 15.0;

    /**
     * Output frame rate.
     *
     * Set explicitly for consistency. Intro videos should maintain quality.
     */
    private const FPS = 30;

    /**
     * Rate the footage is normalised to before it is reversed.
     *
     * Lower than the output rate on purpose: this is the count `reverse` has to
     * hold in memory, and the fps filter afterwards interpolates back up to FPS.
     */
    private const SOURCE_FPS = 24;

    /**
     * Encode a boomerang loop for a stored intro clip.
     *
     * @param  string  $url  public URL of the source, as stored on the template
     * @return string|null public URL of the boomerang loop, or null if one was not made
     */
    public function encode(string $url): ?string
    {
        $source = $this->absolute($url);

        if ($source === null) {
            return null;
        }

        $duration = $this->duration($source);

        if ($duration <= 0.0 || $duration > self::MAX_SECONDS) {
            return null;
        }

        $relative = 'templates/boomerang/'.Str::lower(Str::random(20)).'.mp4';
        $target = Storage::disk('public')->path($relative);

        Storage::disk('public')->makeDirectory(dirname($relative));

        $result = Process::timeout(300)->run([
            config('templates.ffmpeg'),
            '-y',
            '-i', $source,

            /* A palindrome: the footage forward, then the same footage
               backward, joined. Looped, it runs out to the end and comes back
               rather than cutting to a first frame, so there is no seam to see. */
            '-filter_complex', '[0:v]'
                .'fps='.self::SOURCE_FPS.','
                .'split[fwd][pre];'
                .'[pre]reverse[rev];'
                .'[fwd][rev]concat=n=2:v=1:a=0,'
                .'fps='.self::FPS
                .'[out]',
            '-map', '[out]',

            // No audio at all: the intro manages audio separately
            '-an',

            // faststart so the first frame arrives without the whole file
            '-movflags', '+faststart',
            '-pix_fmt', 'yuv420p',
            '-c:v', 'libx264',
            '-preset', 'fast',
            '-crf', '23',

            $target,
        ]);

        if (! $result->successful() || ! Storage::disk('public')->exists($relative)) {
            // Half-written output would be worse than none.
            Storage::disk('public')->delete($relative);

            return null;
        }

        return Storage::disk('public')->url($relative);
    }

    /** Remove a previously encoded boomerang. */
    public function forget(?string $url): void
    {
        if (blank($url) || ! str_contains($url, 'templates/boomerang/')) {
            return;
        }

        Storage::disk('public')->delete(
            ltrim(Str::after($url, '/storage/'), '/')
        );
    }

    /**
     * Absolute path behind a public URL, or null when it is not ours to read.
     *
     * Only paths on the public disk qualify. A remote or crafted URL must not
     * become an argument to ffmpeg.
     */
    private function absolute(string $url): ?string
    {
        if (! str_contains($url, '/storage/')) {
            return null;
        }

        $relative = ltrim(Str::after($url, '/storage/'), '/');

        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        return Storage::disk('public')->exists($relative)
            ? Storage::disk('public')->path($relative)
            : null;
    }

    private function duration(string $path): float
    {
        $result = Process::timeout(15)->run([
            config('templates.ffprobe'),
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $path,
        ]);

        return $result->successful() ? (float) trim($result->output()) : 0.0;
    }
}
