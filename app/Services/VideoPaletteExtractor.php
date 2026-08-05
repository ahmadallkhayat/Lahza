<?php

namespace App\Services;

use App\Support\Color;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Derives a template's palette from its intro video.
 *
 * Frames are sampled with ffmpeg, downscaled, then quantised into colour
 * buckets. Three anchors are picked out of those buckets — the dominant dark
 * tone, the dominant bright tone, and the most colourful thing that is neither
 * — and the seven palette roles are derived from them with contrast ratios
 * enforced, so the result is always legible whatever the footage looks like.
 */
class VideoPaletteExtractor
{
    /** Channel bucket width used to quantise sampled pixels. */
    private const BUCKET = 24;

    /** A candidate must sit this far from an anchor to count as a distinct accent. */
    private const DISTINCT = 60.0;

    /** Contrast floors. Text pairs get AA or better; accents get large-text AA. */
    private const CONTRAST_INK = 7.0;

    private const CONTRAST_INK_SOFT = 4.5;

    private const CONTRAST_GOLD_DEEP = 4.5;

    private const CONTRAST_GOLD = 3.0;

    /**
     * Target HSL lightness per role. Set in HSL rather than by lightening
     * toward white, which would wash the hue out to grey.
     */
    private const L_PAPER = 0.93;

    private const L_PAPER_DEEP = 0.87;

    private const L_INK = 0.12;

    private const L_INK_SOFT = 0.38;

    private const L_GOLD = 0.45;

    private const L_CURTAIN = 0.07;

    /**
     * A distinct colour only becomes the accent if it covers at least this
     * much of the sampled pixels and is meaningfully more colourful than the
     * footage's own bright tone. Otherwise the bright tone deepened is the
     * accent — which is how ivory-and-gold happens naturally.
     */
    private const ACCENT_MIN_WEIGHT = 0.05;

    private const ACCENT_CHROMA_FACTOR = 1.5;

    /**
     * @return array<string, string>|null Palette keyed by role, or null when
     *                                    ffmpeg is unavailable or no frame
     *                                    could be read.
     */
    public function extract(string $videoPath): ?array
    {
        if (! is_file($videoPath)) {
            return null;
        }

        $frames = $this->sampleFrames($videoPath);

        if ($frames === []) {
            return null;
        }

        $buckets = [];

        foreach ($frames as $frame) {
            $this->accumulate($frame, $buckets);
            @unlink($frame);
        }

        @rmdir(dirname($frames[0]));

        return $buckets === [] ? null : $this->derive($this->candidates($buckets));
    }

    /**
     * Write evenly spaced frames to a temp directory as PNGs.
     *
     * @return array<int, string> absolute paths
     */
    private function sampleFrames(string $videoPath): array
    {
        $count = max(1, (int) config('templates.palette_frames', 6));
        $dir = storage_path('app/tmp/palette-'.Str::random(12));

        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return [];
        }

        $duration = $this->duration($videoPath);
        $frames = [];

        for ($i = 0; $i < $count; $i++) {
            // Spread across the middle of the clip. The very first and last
            // frames are often a fade and would skew everything dark.
            $at = $duration > 0
                ? round($duration * ($i + 1) / ($count + 1), 2)
                : $i;

            $out = $dir.DIRECTORY_SEPARATOR.'frame-'.$i.'.png';

            $result = Process::timeout(30)->run([
                config('templates.ffmpeg'),
                '-hide_banner',
                '-loglevel', 'error',
                '-ss', (string) $at,
                '-i', $videoPath,
                '-frames:v', '1',
                '-vf', 'scale=160:-1',
                '-y', $out,
            ]);

            if ($result->successful() && is_file($out) && filesize($out) > 0) {
                $frames[] = $out;
            }
        }

        if ($frames === []) {
            @rmdir($dir);
            Log::warning('Palette extraction read no frames.', ['video' => $videoPath]);
        }

        return $frames;
    }

    /** Duration in seconds, or 0.0 when ffprobe cannot say. */
    private function duration(string $videoPath): float
    {
        $result = Process::timeout(15)->run([
            config('templates.ffprobe'),
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $videoPath,
        ]);

        return $result->successful() ? (float) trim($result->output()) : 0.0;
    }

    /**
     * Fold one frame's pixels into the shared tally.
     *
     * @param  array<string, array{count:int, r:int, g:int, b:int}>  $buckets
     */
    private function accumulate(string $framePath, array &$buckets): void
    {
        $image = @imagecreatefrompng($framePath);

        if ($image === false) {
            return;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);

                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                // Letterboxing and blown highlights dominate by area without
                // describing the mood.
                if (max($r, $g, $b) < 12 || min($r, $g, $b) > 244) {
                    continue;
                }

                $key = intdiv($r, self::BUCKET).','.intdiv($g, self::BUCKET).','.intdiv($b, self::BUCKET);

                $buckets[$key] ??= ['count' => 0, 'r' => 0, 'g' => 0, 'b' => 0];

                $buckets[$key]['count']++;
                $buckets[$key]['r'] += $r;
                $buckets[$key]['g'] += $g;
                $buckets[$key]['b'] += $b;
            }
        }

        imagedestroy($image);
    }

    /**
     * @param  array<string, array{count:int, r:int, g:int, b:int}>  $buckets
     * @return array<int, array{rgb: array{0:int,1:int,2:int}, weight: float, chroma: float, lightness: float}>
     */
    private function candidates(array $buckets): array
    {
        $total = array_sum(array_column($buckets, 'count'));
        $candidates = [];

        foreach ($buckets as $bucket) {
            $rgb = [
                (int) round($bucket['r'] / $bucket['count']),
                (int) round($bucket['g'] / $bucket['count']),
                (int) round($bucket['b'] / $bucket['count']),
            ];

            $candidates[] = [
                'rgb' => $rgb,
                'weight' => $bucket['count'] / $total,
                'chroma' => Color::chroma($rgb),
                'lightness' => Color::lightness($rgb),
            ];
        }

        return $candidates;
    }

    /**
     * Turn the anchors into the seven palette roles.
     *
     * @param  array<int, array{rgb: array{0:int,1:int,2:int}, weight: float, chroma: float, lightness: float}>  $candidates
     * @return array<string, string>
     */
    private function derive(array $candidates): array
    {
        $best = fn (callable $score) => collect($candidates)
            ->sortByDesc($score)
            ->first()['rgb'];

        // Weight against lightness, so these follow the footage rather than
        // picking the single darkest or brightest stray cluster.
        $dark = $best(fn ($c) => $c['weight'] * (1 - $c['lightness']));
        $bright = $best(fn ($c) => $c['weight'] * $c['lightness']);
        $accent = $this->accentSource($candidates, $dark, $bright);

        // Each role is the footage's own hue at the lightness that role needs,
        // then nudged only as far as contrast demands.
        $paper = Color::withLightness($bright, self::L_PAPER);
        $paperDeep = Color::withLightness($bright, self::L_PAPER_DEEP);

        $ink = Color::ensureContrast(Color::withLightness($dark, self::L_INK), $paper, self::CONTRAST_INK);
        $inkSoft = Color::ensureContrast(Color::withLightness($dark, self::L_INK_SOFT), $paper, self::CONTRAST_INK_SOFT);

        $gold = Color::ensureContrast(Color::withLightness($accent, self::L_GOLD), $paper, self::CONTRAST_GOLD);
        $goldDeep = Color::ensureContrast($gold, $paper, self::CONTRAST_GOLD_DEEP);

        // The intro backdrop matches the clip's opening and carries the tap
        // prompt in gold, so gold has to read against it.
        $curtain = Color::ensureContrast(
            Color::withLightness($dark, self::L_CURTAIN),
            $gold,
            self::CONTRAST_GOLD
        );

        return [
            'paper' => Color::toHex($paper),
            'paper_deep' => Color::toHex($paperDeep),
            'ink' => Color::toHex($ink),
            'ink_soft' => Color::toHex($inkSoft),
            'gold' => Color::toHex($gold),
            'gold_deep' => Color::toHex($goldDeep),
            'curtain' => Color::toHex($curtain),
        ];
    }

    /**
     * The hue the accent should come from.
     *
     * Ranking purely on chroma finds the single most saturated cluster, which
     * on warm footage means a stray coral rather than the champagne the clip is
     * actually about. So a distinct colour has to earn it: far enough from both
     * anchors, covering a real share of the frame, and clearly more colourful
     * than the footage's own bright tone.
     *
     * Failing that, the accent is the bright tone itself — deepened later by
     * the caller, which is exactly how ivory pages get gold accents.
     *
     * @param  array<int, array{rgb: array{0:int,1:int,2:int}, weight: float, chroma: float, lightness: float}>  $candidates
     * @param  array{0:int,1:int,2:int}  $dark
     * @param  array{0:int,1:int,2:int}  $bright
     * @return array{0:int,1:int,2:int}
     */
    private function accentSource(array $candidates, array $dark, array $bright): array
    {
        $floor = Color::chroma($bright) * self::ACCENT_CHROMA_FACTOR;

        $distinct = array_values(array_filter(
            $candidates,
            fn ($c) => Color::distance($c['rgb'], $dark) > self::DISTINCT
                && Color::distance($c['rgb'], $bright) > self::DISTINCT
                && $c['weight'] >= self::ACCENT_MIN_WEIGHT
                && $c['chroma'] >= $floor
        ));

        if ($distinct === []) {
            return $bright;
        }

        return collect($distinct)
            ->sortByDesc(fn ($c) => $c['chroma'] * $c['weight'] ** 0.5)
            ->first()['rgb'];
    }
}
