<?php

namespace Tests\Feature;

use App\Services\VideoPaletteExtractor;
use App\Support\Color;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\TestCase;

class VideoPaletteExtractorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = storage_path('app/tmp/palette-test-'.Str::random(6));
        File::ensureDirectoryExists($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    private function ffmpegAvailable(): bool
    {
        return Process::run([config('templates.ffmpeg'), '-version'])->successful();
    }

    /**
     * Generates a clip with a known dominant colour and a known accent block,
     * so the extraction has a right answer to be judged against.
     */
    private function clip(string $background, ?string $accent = null): string
    {
        $path = $this->dir.'/clip-'.Str::random(6).'.mp4';

        $args = [
            config('templates.ffmpeg'), '-hide_banner', '-loglevel', 'error',
            '-f', 'lavfi', '-i', "color=c={$background}:s=320x180:d=4",
        ];

        if ($accent !== null) {
            $args[] = '-vf';
            $args[] = "drawbox=x=90:y=45:w=140:h=90:color={$accent}@1:t=fill";
        }

        $args = array_merge($args, ['-pix_fmt', 'yuv420p', '-y', $path]);

        Process::timeout(60)->run($args);

        return $path;
    }

    public function test_returns_null_for_a_missing_file(): void
    {
        $this->assertNull(app(VideoPaletteExtractor::class)->extract($this->dir.'/nope.mp4'));
    }

    public function test_extracts_every_palette_role(): void
    {
        if (! $this->ffmpegAvailable()) {
            $this->markTestSkipped('ffmpeg is not installed.');
        }

        $palette = app(VideoPaletteExtractor::class)->extract(
            $this->clip('0x1a0f0d', '0xd4af37')
        );

        $this->assertNotNull($palette);
        $this->assertSame(
            ['paper', 'paper_deep', 'ink', 'ink_soft', 'gold', 'gold_deep', 'curtain'],
            array_keys($palette)
        );

        foreach ($palette as $role => $hex) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $hex, "{$role} is not a hex colour");
        }
    }

    /**
     * The whole point of the derivation: whatever the footage looks like, the
     * result has to stay readable.
     */
    public function test_derived_palette_always_meets_its_contrast_floors(): void
    {
        if (! $this->ffmpegAvailable()) {
            $this->markTestSkipped('ffmpeg is not installed.');
        }

        $clips = [
            'warm gold on chocolate' => ['0x2a201a', '0xd4af37'],
            'rose on plum' => ['0x3f1d2b', '0xf4c1cf'],
            'bright cream, no accent' => ['0xe8dcc8', null],
            'monochrome grey' => ['0x1a1a1a', null],
            'saturated red on navy' => ['0x0d1b2a', '0xe03030'],
        ];

        foreach ($clips as $label => [$bg, $accent]) {
            $p = app(VideoPaletteExtractor::class)->extract($this->clip($bg, $accent));
            $this->assertNotNull($p, "{$label}: extraction returned null");

            $rgb = fn (string $role) => Color::fromHex($p[$role]);
            $ratio = fn (string $a, string $b) => Color::contrast($rgb($a), $rgb($b));

            $this->assertGreaterThanOrEqual(7.0, $ratio('ink', 'paper'), "{$label}: ink on paper");
            $this->assertGreaterThanOrEqual(4.5, $ratio('ink_soft', 'paper'), "{$label}: ink_soft on paper");
            $this->assertGreaterThanOrEqual(4.5, $ratio('gold_deep', 'paper'), "{$label}: gold_deep on paper");
            $this->assertGreaterThanOrEqual(3.0, $ratio('gold', 'paper'), "{$label}: gold on paper");
            // The tap prompt is gold on the curtain.
            $this->assertGreaterThanOrEqual(3.0, $ratio('gold', 'curtain'), "{$label}: gold on curtain");
            // Paper must actually be light, curtain actually dark.
            $this->assertGreaterThan(0.6, Color::luminance($rgb('paper')), "{$label}: paper is not light");
            $this->assertLessThan(0.06, Color::luminance($rgb('curtain')), "{$label}: curtain is not dark");
        }
    }

    public function test_extraction_is_deterministic(): void
    {
        if (! $this->ffmpegAvailable()) {
            $this->markTestSkipped('ffmpeg is not installed.');
        }

        $clip = $this->clip('0x2a201a', '0xd4af37');
        $extractor = app(VideoPaletteExtractor::class);

        $this->assertSame($extractor->extract($clip), $extractor->extract($clip));
    }

    /**
     * A stray saturated cluster should not become the accent over the tone the
     * clip is actually built from — that is what turned champagne footage coral.
     */
    public function test_a_tiny_saturated_patch_does_not_beat_the_dominant_tone(): void
    {
        if (! $this->ffmpegAvailable()) {
            $this->markTestSkipped('ffmpeg is not installed.');
        }

        $path = $this->dir.'/tiny.mp4';

        Process::timeout(60)->run([
            config('templates.ffmpeg'), '-hide_banner', '-loglevel', 'error',
            '-f', 'lavfi', '-i', 'color=c=0xc5ab85:s=320x180:d=4',
            // ~1% of the frame, vivid red.
            '-vf', 'drawbox=x=10:y=10:w=20:h=18:color=0xff0000@1:t=fill',
            '-pix_fmt', 'yuv420p', '-y', $path,
        ]);

        $palette = app(VideoPaletteExtractor::class)->extract($path);
        [$hue] = Color::toHsl(Color::fromHex($palette['gold']));

        // Champagne sits around 30-45 degrees; pure red is at 0.
        $this->assertGreaterThan(15, $hue, 'The 1% red patch hijacked the accent.');
        $this->assertLessThan(70, $hue);
    }

    public function test_temp_frames_are_cleaned_up(): void
    {
        if (! $this->ffmpegAvailable()) {
            $this->markTestSkipped('ffmpeg is not installed.');
        }

        app(VideoPaletteExtractor::class)->extract($this->clip('0x2a201a', '0xd4af37'));

        $leftovers = File::isDirectory(storage_path('app/tmp'))
            ? collect(File::directories(storage_path('app/tmp')))
                ->filter(fn ($d) => str_contains(basename($d), 'palette-') && ! str_contains(basename($d), 'test'))
            : collect();

        $this->assertCount(0, $leftovers, 'Frame directories were left behind.');
    }
}
