<?php

namespace Tests\Feature\Admin;

use App\Models\Asset;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Picking a template's media out of the library rather than uploading it.
 *
 * The case worth guarding hardest is the shared file: several templates can
 * point at one asset, so a template that swaps its clip must not delete the
 * file underneath the others.
 */
class TemplateAssetPickerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->actingAs(User::factory()->create());
    }

    /** An asset with a real file behind it on the faked disk. */
    private function storedAsset(string $kind = Asset::KIND_VIDEO): Asset
    {
        $path = $kind === Asset::KIND_VIDEO
            ? 'assets/videos/library.mp4'
            : 'assets/music/library.mp3';

        Storage::disk('public')->put($path, 'bytes');

        return Asset::factory()->{$kind === Asset::KIND_VIDEO ? 'video' : 'music'}()->create([
            'path' => '/storage/'.$path,
            'name' => 'Library media',
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'A Theme',
            'base_template' => 'luxury-wedding-v1',
            'is_active' => '1',
        ], $overrides);
    }

    /* ── the form ─────────────────────────────────────────────────── */

    public function test_the_form_offers_the_library(): void
    {
        $this->storedAsset();

        $this->get('/admin/templates/create')
            ->assertOk()
            ->assertSee('Library media')
            ->assertSee('intro_video_asset', false);
    }

    public function test_the_form_says_when_the_library_is_empty(): void
    {
        $this->get('/admin/templates/create')
            ->assertOk()
            ->assertSee('Nothing in the library for this design yet');
    }

    /** Only assets this design can use: its own and the shared ones. */
    public function test_another_designs_assets_are_not_offered(): void
    {
        Asset::factory()->video()->forBase('luxury-wedding-v1')->create(['name' => 'Mine']);
        Asset::factory()->video()->forBase('some-other-design')->create(['name' => 'Theirs']);

        $template = Template::factory()->create(['base_template' => 'luxury-wedding-v1']);

        $this->get("/admin/templates/{$template->id}/edit")
            ->assertOk()
            ->assertSee('Mine')
            ->assertDontSee('Theirs');
    }

    public function test_an_inactive_asset_is_not_offered(): void
    {
        Asset::factory()->video()->inactive()->create(['name' => 'Retired clip']);

        $this->get('/admin/templates/create')
            ->assertOk()
            ->assertDontSee('Retired clip');
    }

    public function test_the_edit_form_names_the_asset_currently_in_use(): void
    {
        $asset = $this->storedAsset();
        $template = Template::factory()->create(['intro_video' => $asset->path]);

        $this->get("/admin/templates/{$template->id}/edit")
            ->assertOk()
            ->assertSee('From the library:', false);
    }

    /* ── selecting ────────────────────────────────────────────────── */

    public function test_a_new_template_can_take_its_media_from_the_library(): void
    {
        $video = $this->storedAsset(Asset::KIND_VIDEO);
        $music = $this->storedAsset(Asset::KIND_MUSIC);

        $this->post('/admin/templates', $this->payload([
            'intro_video_asset' => $video->id,
            'music_asset' => $music->id,
        ]))->assertSessionHasNoErrors();

        $template = Template::sole();

        $this->assertSame($video->path, $template->intro_video);
        $this->assertSame($music->path, $template->music);
    }

    public function test_an_existing_template_can_be_moved_onto_a_library_asset(): void
    {
        $asset = $this->storedAsset();
        $template = Template::factory()->create(['intro_video' => '/storage/templates/videos/own.mp4']);

        $this->put("/admin/templates/{$template->id}", $this->payload([
            'intro_video_asset' => $asset->id,
        ]))->assertSessionHasNoErrors();

        $this->assertSame($asset->path, $template->fresh()->intro_video);
    }

    /** Asking for both most likely means "use this one instead". */
    public function test_an_upload_wins_over_a_library_choice(): void
    {
        $asset = $this->storedAsset();

        $this->post('/admin/templates', $this->payload([
            'intro_video_asset' => $asset->id,
            'intro_video' => UploadedFile::fake()->create('own.mp4', 40, 'video/mp4'),
        ]))->assertSessionHasNoErrors();

        $this->assertNotSame($asset->path, Template::sole()->intro_video);
    }

    /** A music asset in the video slot would give a guest a video playing an MP3. */
    public function test_the_wrong_kind_of_asset_is_rejected(): void
    {
        $music = $this->storedAsset(Asset::KIND_MUSIC);

        $this->post('/admin/templates', $this->payload([
            'intro_video_asset' => $music->id,
        ]))->assertSessionHasErrors('intro_video_asset');
    }

    public function test_leaving_the_dropdown_blank_keeps_the_stored_media(): void
    {
        $template = Template::factory()->create(['intro_video' => '/storage/templates/videos/own.mp4']);

        $this->put("/admin/templates/{$template->id}", $this->payload([
            'intro_video_asset' => '',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('/storage/templates/videos/own.mp4', $template->fresh()->intro_video);
    }

    /* ── the shared file ──────────────────────────────────────────── */

    /**
     * The bug this guards: two templates on one library clip, one of them
     * changes its video, and the file disappears from under the other — and
     * from the library.
     */
    public function test_swapping_away_from_a_library_asset_leaves_its_file_alone(): void
    {
        $asset = $this->storedAsset();

        $a = Template::factory()->create(['intro_video' => $asset->path]);
        Template::factory()->create(['intro_video' => $asset->path]);

        $this->put("/admin/templates/{$a->id}", $this->payload([
            'intro_video' => UploadedFile::fake()->create('own.mp4', 40, 'video/mp4'),
        ]))->assertSessionHasNoErrors();

        Storage::disk('public')->assertExists('assets/videos/library.mp4');
        $this->assertNotNull($asset->fresh());
    }

    public function test_removing_a_library_asset_from_a_template_leaves_its_file_alone(): void
    {
        $asset = $this->storedAsset();
        $template = Template::factory()->create(['intro_video' => $asset->path]);

        $this->put("/admin/templates/{$template->id}", $this->payload([
            'remove_video' => '1',
        ]))->assertSessionHasNoErrors();

        $this->assertNull($template->fresh()->intro_video);
        Storage::disk('public')->assertExists('assets/videos/library.mp4');
    }

    public function test_deleting_a_template_leaves_a_library_file_alone(): void
    {
        $asset = $this->storedAsset();
        $template = Template::factory()->create(['intro_video' => $asset->path, 'music' => null]);

        $this->delete("/admin/templates/{$template->id}")->assertRedirect('/admin/templates');

        Storage::disk('public')->assertExists('assets/videos/library.mp4');
    }

    /** A file the template uploaded for itself has no such claim on it. */
    public function test_a_templates_own_upload_is_still_deleted_when_replaced(): void
    {
        Storage::disk('public')->put('templates/videos/own.mp4', 'bytes');

        $template = Template::factory()->create(['intro_video' => '/storage/templates/videos/own.mp4']);

        $this->put("/admin/templates/{$template->id}", $this->payload([
            'intro_video' => UploadedFile::fake()->create('replacement.mp4', 40, 'video/mp4'),
        ]))->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing('templates/videos/own.mp4');
    }
}
