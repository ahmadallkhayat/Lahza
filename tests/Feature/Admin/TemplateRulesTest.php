<?php

namespace Tests\Feature\Admin;

use App\Models\Invitation;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TemplateRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->actingAs(User::factory()->create());
    }

    /** Generates a real clip on disk with a known dominant colour. */
    private function clip(string $background, string $accent): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'probe-'.Str::random(6).'.mp4';

        Process::timeout(60)->run([
            config('templates.ffmpeg'), '-hide_banner', '-loglevel', 'error',
            '-f', 'lavfi', '-i', "color=c={$background}:s=320x180:d=3",
            '-vf', "drawbox=x=90:y=45:w=140:h=90:color={$accent}@1:t=fill",
            '-pix_fmt', 'yuv420p', '-y', $path,
        ]);

        return $path;
    }

    /** Puts a real clip on the faked public disk and returns its URL. */
    private function storeClip(): string
    {
        Storage::disk('public')->put(
            'templates/videos/probe.mp4',
            file_get_contents($this->clip('0x2a201a', '0xd4af37'))
        );

        return '/storage/templates/videos/probe.mp4';
    }

    /** @return array<string, mixed> */
    private function valid(array $overrides = []): array
    {
        return array_merge([
            'name' => 'A Theme',
            'base_template' => 'luxury-wedding-v1',
            'is_active' => '1',
        ], $overrides);
    }

    /* ── what must be rejected ──────────────────────────────────── */

    public function test_a_base_template_with_no_directory_is_rejected(): void
    {
        $this->post('/admin/templates', $this->valid(['base_template' => '../../../etc']))
            ->assertSessionHasErrors('base_template');

        $this->assertSame(0, Template::count());
    }

    public function test_a_non_video_is_rejected_even_when_named_like_one(): void
    {
        // mimetypes: inspects content, not the filename.
        $this->post('/admin/templates', $this->valid([
            'intro_video' => UploadedFile::fake()->createWithContent('clip.mp4', '<?php echo "x";'),
        ]))->assertSessionHasErrors('intro_video');

        $this->assertSame(0, Template::count());
    }

    public function test_a_stored_upload_never_keeps_a_dangerous_extension(): void
    {
        // A real video handed over with a .php name must not land as .php in a
        // publicly served directory.
        $this->post('/admin/templates', $this->valid([
            'intro_video' => UploadedFile::fake()->create('payload.php', 8, 'video/mp4'),
        ]));

        $template = Template::first();

        if ($template?->intro_video) {
            $this->assertStringNotContainsString('.php', $template->intro_video);
        } else {
            // Rejected outright is also a correct outcome.
            $this->assertTrue(true);
        }
    }

    public function test_palette_values_must_be_hex(): void
    {
        $this->post('/admin/templates', $this->valid([
            'palette' => ['gold' => 'javascript:alert(1)'],
        ]))->assertSessionHasErrors('palette.gold');
    }

    public function test_unknown_palette_and_settings_keys_are_discarded(): void
    {
        $this->post('/admin/templates', $this->valid([
            'palette' => ['gold' => '#a8874f', 'evil' => '#000000'],
            'settings' => ['fogHz' => 500, 'somethingElse' => 9],
        ]));

        $template = Template::first();

        $this->assertArrayHasKey('gold', $template->palette);
        $this->assertArrayNotHasKey('evil', $template->palette);
        $this->assertArrayNotHasKey('somethingElse', $template->settings);
    }

    public function test_settings_outside_their_range_are_rejected(): void
    {
        $this->post('/admin/templates', $this->valid([
            'settings' => ['duckTo' => 5, 'fogHz' => 999999],
        ]))->assertSessionHasErrors(['settings.duckTo', 'settings.fogHz']);
    }

    public function test_a_duplicate_slug_is_rejected(): void
    {
        Template::factory()->create(['slug' => 'taken']);

        $this->post('/admin/templates', $this->valid(['slug' => 'taken']))
            ->assertSessionHasErrors('slug');
    }

    public function test_a_template_with_invitations_cannot_be_deleted(): void
    {
        $template = Template::factory()->create();
        Invitation::factory()->for($template)->create();

        $this->delete("/admin/templates/{$template->id}");

        $this->assertDatabaseHas('templates', ['id' => $template->id]);
    }

    /* ── what must be preserved ─────────────────────────────────── */

    /**
     * A save that does not mention colours should leave them alone. Wiping
     * derived colours because a field was absent is the kind of quiet data loss
     * that is hard to notice.
     */
    public function test_an_update_that_omits_colours_leaves_them_alone(): void
    {
        $template = Template::factory()->create([
            'palette' => ['gold' => '#123456'],
            'settings' => ['fogHz' => 400],
        ]);

        $this->put("/admin/templates/{$template->id}", $this->valid(['name' => 'Renamed']));

        $template->refresh();

        $this->assertSame('#123456', $template->palette['gold'] ?? null);
        $this->assertSame(400, $template->settings['fogHz'] ?? null);
    }

    public function test_an_update_without_a_new_video_keeps_the_existing_one(): void
    {
        $template = Template::factory()->create(['intro_video' => '/storage/templates/videos/keep.mp4']);

        $this->put("/admin/templates/{$template->id}", $this->valid());

        $this->assertSame('/storage/templates/videos/keep.mp4', $template->refresh()->intro_video);
    }

    /* ── what must be possible ──────────────────────────────────── */

    /**
     * Music can be removed, so a video should be too — otherwise a template
     * that got the wrong clip can only ever have it replaced, never cleared.
     */
    public function test_an_intro_video_can_be_removed(): void
    {
        Storage::disk('public')->put('templates/videos/old.mp4', 'x');

        $template = Template::factory()->create(['intro_video' => '/storage/templates/videos/old.mp4']);

        $this->put("/admin/templates/{$template->id}", $this->valid(['remove_video' => '1']));

        $this->assertNull($template->refresh()->intro_video);
        Storage::disk('public')->assertMissing('templates/videos/old.mp4');
    }

    /**
     * The intro clip defines how the invitation looks, so uploading one sets
     * the colours from it. Requiring a separate tick made uploading a video
     * look like it had done nothing.
     */
    public function test_a_new_video_sets_the_palette_by_itself(): void
    {
        $template = Template::factory()->create([
            'intro_video' => null,
            'palette' => ['paper' => '#ff0000', 'ink' => '#00ff00'],
        ]);

        $this->put("/admin/templates/{$template->id}", $this->valid([
            'intro_video' => new UploadedFile(
                $this->clip('0x1a3a5a', '0x33ccff'), 'clip.mp4', 'video/mp4', null, true
            ),
        ]));

        $palette = $template->refresh()->palette;

        $this->assertNotSame('#ff0000', $palette['paper'] ?? null, 'The old colours survived a new video.');
        $this->assertCount(7, $palette, 'All seven roles should be derived.');
    }

    public function test_the_checkbox_rereads_colours_from_the_attached_video(): void
    {
        $template = Template::factory()->create([
            'intro_video' => $this->storeClip(),
            'palette' => ['paper' => '#ff0000'],
        ]);

        $this->put("/admin/templates/{$template->id}", $this->valid(['derive_palette' => '1']));

        $this->assertNotSame('#ff0000', $template->refresh()->palette['paper'] ?? null);
    }

    public function test_removing_a_video_does_not_try_to_read_colours_from_it(): void
    {
        $template = Template::factory()->create([
            'intro_video' => $this->storeClip(),
            'palette' => ['paper' => '#ff0000'],
        ]);

        $this->put("/admin/templates/{$template->id}", $this->valid(['remove_video' => '1']))
            ->assertSessionHas('status', fn ($note) => ! str_contains($note, 'no intro video'));

        $this->assertNull($template->refresh()->intro_video);
    }

    public function test_replacing_a_video_deletes_the_old_file(): void
    {
        Storage::disk('public')->put('templates/videos/old.mp4', 'x');

        $template = Template::factory()->create(['intro_video' => '/storage/templates/videos/old.mp4']);

        $this->put("/admin/templates/{$template->id}", $this->valid([
            'intro_video' => UploadedFile::fake()->create('new.mp4', 8, 'video/mp4'),
        ]));

        Storage::disk('public')->assertMissing('templates/videos/old.mp4');
        $this->assertNotSame('/storage/templates/videos/old.mp4', $template->refresh()->intro_video);
    }
}
