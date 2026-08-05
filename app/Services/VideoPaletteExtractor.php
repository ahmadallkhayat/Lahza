<?php

namespace App\Services;

use App\Support\Color;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Derives palettes from a template's intro video.
 *
 * Frames are sampled with ffmpeg, downscaled, then quantised into colour
 * buckets. Out of those come three things: the dominant dark tone, the dominant
 * bright tone, and a ranked list of colours that are neither.
 *
 * Those get combined into several complete palettes — light page or dark page,
 * crossed with each accent worth using — so the studio picks a direction rather
 * than being handed one answer. Every variant has the same contrast floors
 * enforced, so whichever is chosen is legible.
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
     * A distinct colour only counts as an accent if it covers at least this
     * much of the sampled pixels and is meaningfully more colourful than the
     * footage's own bright tone. Ranking on raw chroma alone picks whatever
     * single cluster is most saturated, which on warm footage means a stray
     * coral rather than the champagne the clip is actually built from.
     */
    private const ACCENT_MIN_WEIGHT = 0.05;

    private const ACCENT_CHROMA_FACTOR = 1.5;

    /** How many accents to offer variants for. */
    private const MAX_ACCENTS = 2;

    /**
     * Target lightness per role, per theme. Set in HSL rather than by
     * lightening toward white, which washes the hue out to grey.
     *
     * On a dark page the roles invert: the panel fill lifts above the
     * background instead of sitting below it, and text runs light.
     */
    private const LIGHTNESS = [
        'light' => [
            'paper' => 0.93, 'paper_deep' => 0.87,
            'ink' => 0.12, 'ink_soft' => 0.38,
            'gold' => 0.45, 'curtain' => 0.07,
        ],
        'dark' => [
            'paper' => 0.11, 'paper_deep' => 0.17,
            'ink' => 0.93, 'ink_soft' => 0.68,
            'gold' => 0.58, 'curtain' => 0.06,
        ],
    ];

    /**
     * Every palette worth offering for this clip.
     *
     * @return array<int, array{key: string, label: string, palette: array<string, string>}>
     */
    public function variants(string $videoPath): array
    {
        $candidates = $this->candidates($videoPath);

        if ($candidates === []) {
            return [];
        }

        $best = fn (callable $score) => collect($candidates)->sortByDesc($score)->first()['rgb'];

        // Weighted against lightness, so these follow the footage rather than
        // picking the single darkest or brightest stray cluster.
        $dark = $best(fn ($c) => $c['weight'] * (1 - $c['lightness']));
        $bright = $best(fn ($c) => $c['weight'] * $c['lightness']);

        $accents = $this->accents($candidates, $dark, $bright);

        // Warm monochrome footage yields only one accent, which would leave two
        // near-identical variants to choose between. The complement of that
        // accent is still anchored to the clip's own hue — the opposite side of
        // the wheel is a harmony, not an unrelated colour — so it gives a
        // genuinely different direction that still coordinates.
        $accents[] = Color::rotateHue($accents[0], 180);

        $variants = [];

        foreach (['light', 'dark'] as $theme) {
            foreach ($accents as $i => $accent) {
                $variants[] = [
                    'key' => $theme.'-'.$i,
                    'label' => $this->label($theme, $i, count($accents), $accent, $bright),
                    'palette' => $this->build($theme, $dark, $bright, $accent),
                ];
            }
        }

        // Two clips can produce the same palette from different routes; there is
        // no point offering the same swatches twice.
        return array_values(collect($variants)
            ->unique(fn ($v) => implode(',', $v['palette']))
            ->all());
    }

    /**
     * The single best palette — the first variant.
     *
     * @return array<string, string>|null
     */
    public function extract(string $videoPath): ?array
    {
        return $this->variants($videoPath)[0]['palette'] ?? null;
    }

    private function label(string $theme, int $index, int $total, array $accent, array $bright): string
    {
        $page = $theme === 'light' ? 'Light page' : 'Dark page';

        // The complement is always appended last.
        if ($index === $total - 1) {
            return $page.', complementary accent';
        }

        // An accent that is really just the footage's own bright tone reads as
        // tone-on-tone rather than as a contrasting accent.
        if (Color::distance($accent, $bright) <= self::DISTINCT) {
            return $page.', tone on tone';
        }

        return $page.($index === 0 ? ', main accent' : ', alternate accent');
    }

    /**
     * Assemble one complete palette.
     *
     * Each role is the footage's own hue at the lightness that role needs, then
     * nudged only as far as contrast demands. ensureContrast picks its direction
     * from the background, so the same calls work on a light or dark page.
     *
     * @param  array{0:int,1:int,2:int}  $dark
     * @param  array{0:int,1:int,2:int}  $bright
     * @param  array{0:int,1:int,2:int}  $accent
     * @return array<string, string>
     */
    private function build(string $theme, array $dark, array $bright, array $accent): array
    {
        $l = self::LIGHTNESS[$theme];

        // On a light page the background comes from the bright tone and the text
        // from the dark one. On a dark page they swap.
        [$pageSource, $textSource] = $theme === 'light' ? [$bright, $dark] : [$dark, $bright];

        $paper = Color::withLightness($pageSource, $l['paper']);
        $paperDeep = Color::withLightness($pageSource, $l['paper_deep']);

        $ink = Color::ensureContrast(Color::withLightness($textSource, $l['ink']), $paper, self::CONTRAST_INK);
        $inkSoft = Color::ensureContrast(Color::withLightness($textSource, $l['ink_soft']), $paper, self::CONTRAST_INK_SOFT);

        $gold = Color::ensureContrast(Color::withLightness($accent, $l['gold']), $paper, self::CONTRAST_GOLD);
        $goldDeep = Color::ensureContrast($gold, $paper, self::CONTRAST_GOLD_DEEP);

        // The intro backdrop matches the clip's opening and carries the tap
        // prompt in gold, so gold has to read against it.
        $curtain = Color::ensureContrast(
            Color::withLightness($dark, $l['curtain']),
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
     * Accents worth offering, best first.
     *
     * Falls back to the bright tone when nothing in the footage is distinct
     * enough — which is how a monochrome clip still yields a usable tone-on-tone
     * palette instead of nothing.
     *
     * @param  array<int, array{rgb: array{0:int,1:int,2:int}, weight: float, chroma: float, lightness: float}>  $candidates
     * @param  array{0:int,1:int,2:int}  $dark
     * @param  array{0:int,1:int,2:int}  $bright
     * @return array<int, array{0:int,1:int,2:int}>
     */
    private function accents(array $candidates, array $dark, array $bright): array
    {
        $floor = Color::chroma($bright) * self::ACCENT_CHROMA_FACTOR;

        $distinct = collect($candidates)
            ->filter(fn ($c) => Color::distance($c['rgb'], $dark) > self::DISTINCT
                && Color::distance($c['rgb'], $bright) > self::DISTINCT
                && $c['weight'] >= self::ACCENT_MIN_WEIGHT
                && $c['chroma'] >= $floor)
            ->sortByDesc(fn ($c) => $c['chroma'] * $c['weight'] ** 0.5)
            ->pluck('rgb')
            ->values();

        // Drop near-duplicates so two variants are not offered for what is
        // effectively the same colour.
        $accents = [];

        foreach ($distinct as $rgb) {
            $tooClose = collect($accents)->contains(
                fn ($kept) => Color::distance($kept, $rgb) <= self::DISTINCT
            );

            if (! $tooClose) {
                $accents[] = $rgb;
            }

            if (count($accents) >= self::MAX_ACCENTS) {
                break;
            }
        }

        return $accents !== [] ? $accents : [$bright];
    }

    /**
     * Sample the clip and quantise it into weighted colour candidates.
     *
     * @return array<int, array{rgb: array{0:int,1:int,2:int}, weight: float, chroma: float, lightness: float}>
     */
    private function candidates(string $videoPath): array
    {
        if (! is_file($videoPath)) {
            return [];
        }

        $frames = $this->sampleFrames($videoPath);

        if ($frames === []) {
            return [];
        }

        $buckets = [];

        foreach ($frames as $frame) {
            $this->accumulate($frame, $buckets);
            @unlink($frame);
        }

        @rmdir(dirname($frames[0]));

        if ($buckets === []) {
            return [];
        }

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
}
