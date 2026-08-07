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
 * The asset library: media stored once and picked from a dropdown, plus blocks
 * of ready-written text.
 */
class AssetLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->actingAs(User::factory()->create());
    }

    /* ── access ───────────────────────────────────────────────────── */

    public function test_the_library_is_behind_sign_in(): void
    {
        auth()->logout();

        $this->get('/admin/assets')->assertRedirect('/admin/login');
        $this->post('/admin/assets')->assertRedirect('/admin/login');
    }

    public function test_the_library_page_renders(): void
    {
        Asset::factory()->create(['name' => 'A hosting line']);
        Asset::factory()->video()->forBase()->create(['name' => 'Ring box opening']);

        $this->get('/admin/assets')
            ->assertOk()
            ->assertSee('A hosting line')
            ->assertSee('Ring box opening')
            // Which design owns a row is a column on it, not the grouping.
            ->assertSee('Shared — all templates');
    }

    /**
     * A verse and a hosting line are both "text" the way a clip and a track
     * are both "media" — which is to say not usefully. One Text pile mixing
     * them is the same nesting the per-design grouping had, one level down.
     */
    public function test_text_is_sectioned_by_what_it_is_for(): void
    {
        Asset::factory()->create(['name' => 'The garden', 'field' => 'venue_name']);
        Asset::factory()->create(['name' => 'Both families', 'field' => 'hosts']);

        $html = $this->get('/admin/assets')->assertOk()->getContent();

        $this->assertStringContainsString('>Venue</h2>', $html);
        $this->assertStringContainsString('>Hosting line</h2>', $html);

        // Each heading precedes its own rows — that is what proves they are
        // separate sections rather than one list with two headings above it.
        $this->assertLessThan(
            strpos($html, 'Both families'),
            strpos($html, '>Hosting line</h2>')
        );
        $this->assertLessThan(
            strpos($html, 'The garden'),
            strpos($html, '>Venue</h2>')
        );
    }

    public function test_a_section_with_nothing_in_it_is_not_drawn(): void
    {
        Asset::factory()->create(['field' => 'hosts']);

        $html = $this->get('/admin/assets')->assertOk()->getContent();

        $this->assertStringContainsString('>Hosting line</h2>', $html);
        // Declared by the design, but empty — so it is not a heading over
        // nothing, and "not filed" stays out of sight until it is needed.
        $this->assertStringNotContainsString('>Venue</h2>', $html);
        $this->assertStringNotContainsString('Text — not filed', $html);
    }

    public function test_text_filed_under_nothing_gets_its_own_section(): void
    {
        Asset::factory()->create(['name' => 'Odd one out', 'field' => null]);

        $this->get('/admin/assets')
            ->assertOk()
            ->assertSee('Text — not filed')
            ->assertSee('Odd one out');
    }

    public function test_adding_from_a_section_arrives_already_filed(): void
    {
        // So a second verse never means answering "what is this" again.
        $this->get('/admin/assets/create?kind=text&field=hosts')
            ->assertOk()
            ->assertSee('value="hosts" selected', false);
    }

    public function test_a_category_no_design_declares_is_not_offered(): void
    {
        $this->get('/admin/assets/create?kind=text')
            ->assertOk()
            ->assertSee('value="hosts"', false)
            ->assertDontSee('value="programme"', false)
            // A verse is its own kind now, not a category of text.
            ->assertDontSee('value="verse" >', false);
    }

    /* ── verses ───────────────────────────────────────────────────── */

    /**
     * A verse is its own kind, not text filed under a category. The verse, its
     * translation and its reference are shown together and are useless apart,
     * so they are one row carrying three parts — this used to be an Arabic row
     * and an English row with nothing recording they were the same verse.
     */
    public function test_a_verse_stores_its_translation_and_reference(): void
    {
        $this->post('/admin/assets', [
            'kind' => Asset::KIND_VERSE,
            'name' => 'Ar-Rum 30:21',
            'body' => 'وَمِنْ آيَاتِهِ أَنْ خَلَقَ لَكُم',
            'translation' => ['en' => 'And among His signs…', 'ar' => ''],
            'reference' => ['ar' => 'سورة الروم — ٢١', 'en' => 'QURAN 30:21'],
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $asset = Asset::sole();

        $this->assertSame(Asset::KIND_VERSE, $asset->kind);
        $this->assertSame('And among His signs…', $asset->translation['en']);
        $this->assertSame('QURAN 30:21', $asset->reference['en']);

        // A language left blank is absent, not stored empty.
        $this->assertArrayNotHasKey('ar', $asset->translation);

        // Being a verse is the whole of what it is for, so it is filed under
        // nothing and carries no single language.
        $this->assertNull($asset->field);
        $this->assertNull($asset->locale);
    }

    public function test_a_verse_needs_the_verse_itself(): void
    {
        $this->post('/admin/assets', [
            'kind' => Asset::KIND_VERSE,
            'name' => 'Empty',
            'reference' => ['en' => 'QURAN 30:21'],
        ])->assertSessionHasErrors('body');
    }

    public function test_plain_text_never_carries_a_translation(): void
    {
        // Only a verse has parts. A hosting line posting them gets them
        // dropped rather than stored on a row that will never read them.
        $this->post('/admin/assets', [
            'kind' => Asset::KIND_TEXT,
            'name' => 'Both families',
            'body' => 'The families of {groom} and {bride}',
            'translation' => ['en' => 'smuggled'],
            'reference' => ['en' => 'smuggled'],
        ])->assertSessionHasNoErrors();

        $asset = Asset::sole();

        $this->assertNull($asset->translation);
        $this->assertNull($asset->reference);
    }

    public function test_verses_get_their_own_section(): void
    {
        Asset::factory()->verse()->create(['name' => 'Ar-Rum 30:21']);
        Asset::factory()->create(['name' => 'Both families', 'field' => 'hosts']);

        $html = $this->get('/admin/assets')->assertOk()->getContent();

        $this->assertStringContainsString('>Religious verse</h2>', $html);
        $this->assertLessThan(
            strpos($html, 'Ar-Rum 30:21'),
            strpos($html, '>Religious verse</h2>')
        );
    }

    public function test_the_type_picker_offers_all_four_kinds(): void
    {
        $html = $this->get('/admin/assets/create')->assertOk()->getContent();

        foreach (['Intro video', 'Music', 'Text', 'Religious verse'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
    }

    public function test_the_invitation_form_offers_a_verse_from_the_library(): void
    {
        Template::factory()->create(['base_template' => 'luxury-wedding-v1']);
        Asset::factory()->verse()->create(['name' => 'Ar-Rum 30:21']);

        $this->get('/admin/invitations/create')
            ->assertOk()
            // One picker for the whole block, not three.
            ->assertSee('insertGroup(', false)
            ->assertSee('Ar-Rum 30:21');
    }

    public function test_the_nav_offers_the_library(): void
    {
        $this->get('/admin')
            ->assertOk()
            ->assertSee('/admin/assets', false);
    }

    /* ── creating ─────────────────────────────────────────────────── */

    public function test_a_video_asset_stores_its_file(): void
    {
        $this->post('/admin/assets', [
            'kind' => Asset::KIND_VIDEO,
            'name' => 'Ring box opening',
            'base_template' => 'luxury-wedding-v1',
            'file' => UploadedFile::fake()->create('intro.mp4', 120, 'video/mp4'),
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $asset = Asset::sole();

        $this->assertSame(Asset::KIND_VIDEO, $asset->kind);
        $this->assertSame('luxury-wedding-v1', $asset->base_template);
        $this->assertNotNull($asset->path);
        $this->assertNull($asset->body);

        Storage::disk('public')->assertExists(
            ltrim(str_replace('/storage/', '', $asset->path), '/')
        );
    }

    public function test_a_text_asset_stores_its_body(): void
    {
        $this->post('/admin/assets', [
            'kind' => Asset::KIND_TEXT,
            'name' => 'Both families',
            'body' => 'The families of {groom} and {bride} invite you',
            'locale' => 'en',
            'field' => 'hosts',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $asset = Asset::sole();

        $this->assertSame('The families of {groom} and {bride} invite you', $asset->body);
        $this->assertSame('en', $asset->locale);
        $this->assertSame('hosts', $asset->field);

        // No base template chosen means shared, which is one value and not two.
        $this->assertNull($asset->base_template);
        $this->assertNull($asset->path);
    }

    public function test_a_media_asset_needs_a_file(): void
    {
        $this->post('/admin/assets', [
            'kind' => Asset::KIND_VIDEO,
            'name' => 'No file',
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, Asset::count());
    }

    public function test_a_text_asset_needs_a_body(): void
    {
        $this->post('/admin/assets', [
            'kind' => Asset::KIND_TEXT,
            'name' => 'Empty',
        ])->assertSessionHasErrors('body');
    }

    public function test_the_wrong_file_type_is_rejected(): void
    {
        $this->post('/admin/assets', [
            'kind' => Asset::KIND_MUSIC,
            'name' => 'Not audio',
            'file' => UploadedFile::fake()->create('notes.txt', 4, 'text/plain'),
        ])->assertSessionHasErrors('file');
    }

    public function test_an_unknown_base_template_is_rejected(): void
    {
        $this->post('/admin/assets', [
            'kind' => Asset::KIND_TEXT,
            'name' => 'Nowhere',
            'body' => 'Text',
            'base_template' => 'no-such-design',
        ])->assertSessionHasErrors('base_template');
    }

    /* ── editing ──────────────────────────────────────────────────── */

    public function test_the_body_can_be_edited_without_re_uploading_anything(): void
    {
        $asset = Asset::factory()->create(['body' => 'Old text']);

        $this->put("/admin/assets/{$asset->id}", [
            'kind' => Asset::KIND_TEXT,
            'name' => $asset->name,
            'body' => 'New text with {bride}',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame('New text with {bride}', $asset->fresh()->body);
    }

    /** A rename must not require re-uploading a 300MB clip. */
    public function test_a_media_asset_keeps_its_file_when_none_is_sent(): void
    {
        $asset = Asset::factory()->video()->create(['name' => 'Old name']);
        $path = $asset->path;

        $this->put("/admin/assets/{$asset->id}", [
            'name' => 'New name',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $asset->refresh();

        $this->assertSame('New name', $asset->name);
        $this->assertSame($path, $asset->path);
    }

    /**
     * The kind is fixed once created: a video row holds a file and no body, and
     * letting it become text would orphan the upload and leave any template
     * that picked it pointing at nothing.
     */
    public function test_the_kind_cannot_be_changed_after_creation(): void
    {
        $asset = Asset::factory()->video()->create();

        $this->put("/admin/assets/{$asset->id}", [
            'kind' => Asset::KIND_TEXT,
            'name' => $asset->name,
            'body' => 'Trying to become text',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $asset->refresh();

        $this->assertSame(Asset::KIND_VIDEO, $asset->kind);
        $this->assertNull($asset->body);
    }

    /**
     * Templates hold the URL rather than a foreign key, so replacing the file
     * has to move them across or they are left on a file the library no longer
     * lists.
     */
    public function test_replacing_the_file_moves_the_templates_using_it(): void
    {
        $asset = Asset::factory()->video()->create();
        $template = Template::factory()->create(['intro_video' => $asset->path]);

        $this->put("/admin/assets/{$asset->id}", [
            'name' => $asset->name,
            'file' => UploadedFile::fake()->create('new.mp4', 60, 'video/mp4'),
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $asset->refresh();

        $this->assertNotNull($asset->path);
        $this->assertSame($asset->path, $template->fresh()->intro_video);
    }

    /* ── deleting ─────────────────────────────────────────────────── */

    public function test_deleting_an_asset_removes_its_file(): void
    {
        Storage::disk('public')->put('assets/videos/clip.mp4', 'bytes');

        $asset = Asset::factory()->video()->create(['path' => '/storage/assets/videos/clip.mp4']);

        $this->delete("/admin/assets/{$asset->id}")->assertRedirect('/admin/assets');

        $this->assertSame(0, Asset::count());
        Storage::disk('public')->assertMissing('assets/videos/clip.mp4');
    }

    /**
     * Refused rather than allowed: a template left pointing at a file that is
     * gone renders a guest page with no intro clip and no explanation.
     */
    public function test_an_asset_a_template_still_uses_cannot_be_deleted(): void
    {
        $asset = Asset::factory()->video()->create();
        Template::factory()->create(['intro_video' => $asset->path, 'name' => 'Gold Theme']);

        $this->delete("/admin/assets/{$asset->id}")
            ->assertSessionHas('error')
            // Flashed as an error, not a status — a refusal in success green
            // reads as though the delete worked.
            ->assertSessionMissing('status');

        $this->assertSame(1, Asset::count());
    }

    public function test_the_library_page_says_why_an_asset_cannot_be_deleted(): void
    {
        $asset = Asset::factory()->video()->create();
        Template::factory()->create(['intro_video' => $asset->path, 'name' => 'Gold Theme']);

        $this->get('/admin/assets')
            ->assertOk()
            ->assertSee('Cannot delete — used by Gold Theme');
    }

    /* ── filtering ────────────────────────────────────────────────── */

    public function test_the_library_can_be_filtered_by_design_and_kind(): void
    {
        Asset::factory()->create(['name' => 'Shared text']);
        Asset::factory()->video()->forBase()->create(['name' => 'Design clip']);

        $this->get('/admin/assets?base=shared')
            ->assertOk()
            ->assertSee('Shared text')
            ->assertDontSee('Design clip');

        $this->get('/admin/assets?kind=video')
            ->assertOk()
            ->assertSee('Design clip')
            ->assertDontSee('Shared text');
    }

    /* ── scoping ──────────────────────────────────────────────────── */

    /** A shared asset is available to every design; a scoped one is not. */
    public function test_scoping_includes_the_shared_library(): void
    {
        $shared = Asset::factory()->video()->create();
        $mine = Asset::factory()->video()->forBase('luxury-wedding-v1')->create();
        $theirs = Asset::factory()->video()->forBase('some-other-design')->create();

        $ids = Asset::forBaseTemplate('luxury-wedding-v1')->pluck('id');

        $this->assertTrue($ids->contains($shared->id));
        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
    }
}
