<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Makes the backdrop loop: the intro clip as a slow palindrome.
 *
 * Forward, then the same footage backward, joined into one file. Looped, it
 * plays out to the end and comes back the way it came, so there is never a cut
 * from the last frame to the first — which is the jump that made plain looping
 * unusable as a backdrop.
 *
 * It has to happen here because no browser can play a video backwards.
 * `playbackRate` refuses negative values, and stepping `currentTime` down frame
 * by frame stutters badly on a phone.
 *
 * Small because the layer is only ever seen through a 26px blur. At 480px wide
 * it is indistinguishable from the full-size clip once blurred, and it costs a
 * fraction of the decode — which matters, because the glass panels above it
 * recomposite their backdrop-filter on every frame it produces.
 *
 * Absent output is not an error. ffmpeg may not be installed, the clip may be
 * too long to reverse, the encode may fail: the design falls back to blurring
 * the intro clip in place, so the page is never worse than it was.
 */
class AmbientVideoEncoder
{
    /** Width to encode at. Blurred beyond recognition, so detail is waste. */
    private const WIDTH = 480;

    /**
     * Longest clip worth reversing.
     *
     * ffmpeg's `reverse` filter buffers every frame it will emit into memory,
     * so this is a memory ceiling rather than a preference — twelve seconds at
     * the source rate below is a couple of hundred megabytes held at once.
     * Intro clips run a few seconds; anything longer is refused rather than
     * allowed to exhaust the box.
     */
    private const MAX_SECONDS = 12.0;

    /**
     * How much to stretch the footage out.
     *
     * Slowing it here rather than with `playbackRate` in the browser, which is
     * the whole reason this stretch exists. At a rate of 0.35 the browser has
     * to present each decoded frame across three refreshes, and it does not
     * space them evenly — the result visibly stutters no matter how gentle the
     * footage is. Stretched at encode time the loop plays at rate 1.0, one new
     * frame per refresh, and the judder has nowhere to come from.
     */
    private const SLOWDOWN = 2.8;

    /**
     * Output frame rate.
     *
     * Set explicitly because `setpts` alone stretches timestamps without adding
     * frames, leaving a clip that is long but still only has the original
     * frames in it — smooth playback needs them interpolated up to a steady
     * cadence, which is what `fps` does here.
     */
    private const FPS = 30;

    /**
     * Rate the footage is normalised to before it is reversed.
     *
     * Lower than the output rate on purpose: this is the count `reverse` has to
     * hold in memory, and the slowdown afterwards interpolates back up to FPS.
     */
    private const SOURCE_FPS = 24;

    /**
     * Encode a backdrop loop for a stored intro clip.
     *
     * @param  string  $url  public URL of the source, as stored on the template
     * @return string|null public URL of the loop, or null if one was not made
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

        $relative = 'templates/ambient/'.Str::lower(Str::random(20)).'.mp4';
        $target = Storage::disk('public')->path($relative);

        Storage::disk('public')->makeDirectory(dirname($relative));

        $result = Process::timeout(180)->run([
            config('templates.ffmpeg'),
            '-y',
            '-i', $source,

            /* A palindrome: the footage forward, then the same footage
               backward, joined. Looped, it runs out to the end and comes back
               rather than cutting to a first frame, so there is no seam to see.

               Order matters twice over. Scaling comes first because `reverse`
               holds every frame it will emit in memory, and it should be
               holding 480px frames rather than full-size ones. And the slowdown
               comes *last*, after the join — stretching before it would triple
               the frame count that reverse has to buffer for no benefit. */
            '-filter_complex', '[0:v]'
                .'scale='.self::WIDTH.':-2,'
                .'fps='.self::SOURCE_FPS.','
                .'split[fwd][pre];'
                .'[pre]reverse[rev];'
                .'[fwd][rev]concat=n=2:v=1:a=0,'
                .'setpts='.self::SLOWDOWN.'*PTS,'
                .'fps='.self::FPS
                .'[out]',
            '-map', '[out]',

            // No audio at all: this layer is silent, and the music owns sound.
            '-an',

            // faststart so the first frame arrives without the whole file.
            '-movflags', '+faststart',
            '-pix_fmt', 'yuv420p',
            '-c:v', 'libx264',
            '-preset', 'veryfast',
            '-crf', '30',

            $target,
        ]);

        if (! $result->successful() || ! Storage::disk('public')->exists($relative)) {
            // Half-written output would be worse than none.
            Storage::disk('public')->delete($relative);

            return null;
        }

        return Storage::disk('public')->url($relative);
    }

    /** Remove a previously encoded loop. */
    public function forget(?string $url): void
    {
        if (blank($url) || ! str_contains($url, 'templates/ambient/')) {
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
