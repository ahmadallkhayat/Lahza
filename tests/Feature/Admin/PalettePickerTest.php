<?php

namespace Tests\Feature\Admin;

use App\Models\Template;
use App\Models\User;
use App\Services\VideoPaletteExtractor;
use App\Support\Color;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PalettePickerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Process::run([config('templates.ffmpeg'), '-version'])->successful()) {
            $this->markTestSkipped('ffmpeg is not installed.');
        }

        Storage::fake('public');
        $this->actingAs(User::factory()->create());
    }

    /** A real clip on disk with a known dominant colour and accent. */
    private function clip(string $background = '0x2a201a', string $accent = '0xd4af37'): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pal-'.Str::random(6).'.mp4';

        Process::timeout(60)->run([
            config('templates.ffmpeg'), '-hide_banner', '-loglevel', 'error',
            '-f', 'lavfi', '-i', "color=c={$background}:s=320x180:d=3",
            '-vf', "drawbox=x=90:y=45:w=140:h=90:color={$accent}@1:t=fill",
            '-pix_fmt', 'yuv420p', '-y', $path,
        ]);

        return $path;
    }

    /* ── the variants themselves ─────────────────────────────────── */

    public function test_it_offers_several_complete_palettes(): void
    {
        $variants = app(VideoPaletteExtractor::class)->variants($this->clip());

        $this->assertGreaterThanOrEqual(2, count($variants), 'One option is not a choice.');

        foreach ($variants as $variant) {
            $this->assertNotEmpty($variant['key']);
            $this->assertNotEmpty($variant['label']);
            $this->assertSame(
                ['paper', 'paper_deep', 'ink', 'ink_soft', 'gold', 'gold_deep', 'curtain'],
                array_keys($variant['palette']),
                "{$variant['label']} is missing a role"
            );
        }
    }

    /**
     * The point of offering options rather than one answer: whichever is picked
     * has to stay readable, so every variant carries the same floors.
     */
    public function test_every_variant_meets_the_contrast_floors(): void
    {
        $clips = [
            'warm gold on chocolate' => $this->clip(),
            'red on navy' => $this->clip('0x0d1b2a', '0xe03030'),
            'flat grey' => $this->clip('0x1a1a1a', '0x1a1a1a'),
            'bright cream' => $this->clip('0xe8dcc8', '0xe8dcc8'),
        ];

        foreach ($clips as $clip) {
            foreach (app(VideoPaletteExtractor::class)->variants($clip) as $variant) {
                $rgb = fn (string $role) => Color::fromHex($variant['palette'][$role]);
                $ratio = fn (string $a, string $b) => Color::contrast($rgb($a), $rgb($b));
                $where = $variant['label'];

                $this->assertGreaterThanOrEqual(7.0, $ratio('ink', 'paper'), "{$where}: ink on paper");
                $this->assertGreaterThanOrEqual(4.5, $ratio('ink_soft', 'paper'), "{$where}: ink_soft on paper");
                $this->assertGreaterThanOrEqual(4.5, $ratio('gold_deep', 'paper'), "{$where}: gold_deep on paper");
                $this->assertGreaterThanOrEqual(3.0, $ratio('gold', 'paper'), "{$where}: gold on paper");
                $this->assertGreaterThanOrEqual(3.0, $ratio('gold', 'curtain'), "{$where}: gold on curtain");
            }
        }
    }

    public function test_it_offers_both_a_light_and_a_dark_page(): void
    {
        $variants = app(VideoPaletteExtractor::class)->variants($this->clip());
        $labels = array_column($variants, 'label');

        $this->assertNotEmpty(array_filter($labels, fn ($l) => str_contains($l, 'Light page')));
        $this->assertNotEmpty(array_filter($labels, fn ($l) => str_contains($l, 'Dark page')));

        // A dark page must actually be dark, and a light one light.
        foreach ($variants as $variant) {
            $paper = Color::luminance(Color::fromHex($variant['palette']['paper']));
            str_contains($variant['label'], 'Dark page')
                ? $this->assertLessThan(0.1, $paper, $variant['label'])
                : $this->assertGreaterThan(0.6, $paper, $variant['label']);
        }
    }

    /**
     * Monochrome footage yields only one accent, which would leave two
     * near-identical options. The complement keeps a real choice on offer.
     */
    public function test_a_monochrome_clip_still_offers_a_contrasting_option(): void
    {
        $variants = app(VideoPaletteExtractor::class)->variants($this->clip('0x2a201a', '0x2a201a'));

        $labels = array_column($variants, 'label');
        $this->assertNotEmpty(
            array_filter($labels, fn ($l) => str_contains($l, 'complementary')),
            'A monochrome clip should still offer a complementary accent.'
        );

        // And that accent must be a genuinely different hue.
        $accents = array_map(fn ($v) => Color::toHsl(Color::fromHex($v['palette']['gold']))[0], $variants);
        $this->assertGreaterThan(60, max($accents) - min($accents), 'The options are all the same hue.');
    }

    public function test_variants_are_not_duplicated(): void
    {
        $variants = app(VideoPaletteExtractor::class)->variants($this->clip());
        $signatures = array_map(fn ($v) => implode(',', $v['palette']), $variants);

        $this->assertSame($signatures, array_unique($signatures));
    }

    public function test_extract_still_returns_the_first_variant(): void
    {
        $clip = $this->clip();
        $extractor = app(VideoPaletteExtractor::class);

        $this->assertSame($extractor->variants($clip)[0]['palette'], $extractor->extract($clip));
    }

    /* ── the endpoint ────────────────────────────────────────────── */

    public function test_the_endpoint_reads_an_unsaved_upload(): void
    {
        // The create page has nothing stored yet, so the file itself is sent.
        $this->postJson('/admin/templates/palettes', [
            'intro_video' => new UploadedFile($this->clip(), 'clip.mp4', 'video/mp4', null, true),
        ])
            ->assertOk()
            ->assertJsonStructure(['variants' => [['key', 'label', 'palette']]]);
    }

    public function test_the_endpoint_reads_a_stored_video(): void
    {
        Storage::disk('public')->put('templates/videos/stored.mp4', file_get_contents($this->clip()));

        $template = Template::factory()->create([
            'intro_video' => '/storage/templates/videos/stored.mp4',
        ]);

        $this->postJson('/admin/templates/palettes', ['template_id' => $template->id])
            ->assertOk()
            ->assertJsonStructure(['variants' => [['key', 'label', 'palette']]]);
    }

    public function test_it_says_so_when_there_is_no_video(): void
    {
        $template = Template::factory()->create(['intro_video' => null]);

        $this->postJson('/admin/templates/palettes', ['template_id' => $template->id])
            ->assertStatus(422)
            ->assertJsonPath('variants', [])
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'intro video'));
    }

    public function test_it_rejects_a_file_that_is_not_a_video(): void
    {
        $this->postJson('/admin/templates/palettes', [
            'intro_video' => UploadedFile::fake()->createWithContent('clip.mp4', '<?php echo 1;'),
        ])->assertJsonValidationErrors('intro_video');
    }

    public function test_it_requires_authentication(): void
    {
        auth()->logout();

        $this->postJson('/admin/templates/palettes', ['template_id' => 1])
            ->assertUnauthorized();
    }

    public function test_the_literal_segment_is_not_read_as_a_template_id(): void
    {
        // templates/palettes must not be matched by templates/{template}.
        $this->assertSame(
            'admin.templates.palettes',
            app('router')->getRoutes()->match(
                Request::create('/admin/templates/palettes', 'POST')
            )->getName()
        );
    }
}
