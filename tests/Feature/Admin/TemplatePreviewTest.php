<?php

namespace Tests\Feature\Admin;

use App\Models\Invitation;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class TemplatePreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge(['base_template' => 'luxury-wedding-v1'], $overrides);
    }

    public function test_it_renders_the_real_base_template(): void
    {
        // Not a mock-up: the same markup and JavaScript a guest gets, so the
        // preview cannot drift from the invitation.
        $this->post('/admin/templates/preview', $this->payload())
            ->assertOk()
            ->assertSee('armFadeOut', false)      // the reveal sequence
            ->assertSee('const TARGET', false)    // the countdown
            ->assertSee('id="curtain"', false);
    }

    /**
     * The preview frame is a srcdoc document, whose URL is about:srcdoc. A
     * guard written as "http or https" therefore excluded it, the Web Audio
     * graph was never built, and the preview quietly fell back to a plain
     * volume duck with none of the muffle the real page has. Only file:// —
     * where createMediaElementSource yields silence and cannot be undone —
     * may be excluded.
     */
    public function test_the_muffle_filter_is_not_gated_out_of_the_preview_frame(): void
    {
        $html = $this->post('/admin/templates/preview', $this->payload())
            ->assertOk()->getContent();

        $this->assertStringContainsString("location.protocol !== 'file:'", $html);
        $this->assertStringNotContainsString("location.protocol === 'http:'", $html);
        // And the filter itself is still wired up behind that guard.
        $this->assertStringContainsString("fogNode.type = 'lowpass'", $html);
        $this->assertStringContainsString('ramp(fogNode.frequency', $html);
    }

    public function test_unsaved_colours_are_what_render(): void
    {
        Template::factory()->create(['palette' => ['paper' => '#ffffff']]);

        $html = $this->post('/admin/templates/preview', $this->payload([
            'palette' => ['paper' => '#101820', 'gold' => '#c9a227'],
        ]))->assertOk()->getContent();

        // Matched loosely: the stylesheet aligns these values with padding, and
        // asserting on the exact spacing makes the test brittle.
        $this->assertMatchesRegularExpression('/--paper:\s*#101820/', $html);
        $this->assertMatchesRegularExpression('/--gold:\s*#c9a227/', $html);
        // The saved value must not leak through.
        $this->assertStringNotContainsString('--paper: #ffffff', $html);
    }

    public function test_unsaved_timings_are_what_render(): void
    {
        $html = $this->post('/admin/templates/preview', $this->payload([
            'settings' => ['crossfadeMs' => 900],
        ]))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/--fade:\s*900ms/', $html);
        // And the JavaScript gets it as a number, not the string a form sends.
        $this->assertStringContainsString('"crossfadeMs":900', $html);
    }

    public function test_nothing_is_persisted(): void
    {
        $this->post('/admin/templates/preview', $this->payload([
            'palette' => ['paper' => '#101820'],
        ]))->assertOk();

        $this->assertDatabaseCount('templates', 0);
        $this->assertDatabaseCount('invitations', 0);
    }

    public function test_it_borrows_real_content_when_there_is_any(): void
    {
        $invitation = Invitation::factory()->create(['groom_ar' => 'كَرَم']);

        $this->post('/admin/templates/preview', $this->payload())
            ->assertOk()
            ->assertSee($invitation->groom_ar, false);
    }

    public function test_it_falls_back_to_a_stand_in_with_no_invitations(): void
    {
        $this->assertDatabaseCount('invitations', 0);

        $this->post('/admin/templates/preview', $this->payload())
            ->assertOk()
            ->assertSee('أَحْمَد', false);
    }

    public function test_it_can_be_previewed_in_a_chosen_language(): void
    {
        Invitation::factory()->locales(['ar', 'en'], 'ar')->create();

        $this->post('/admin/templates/preview', $this->payload(['locale' => 'en']))
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr"', false);

        $this->post('/admin/templates/preview', $this->payload(['locale' => 'ar']))
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl"', false);
    }

    /**
     * A file chosen but not uploaded arrives as a blob: URL the browser made.
     * Anything else would let a crafted value put arbitrary content in a frame
     * that runs scripts.
     */
    public function test_only_blob_or_storage_media_urls_are_accepted(): void
    {
        foreach (['blob:http://localhost/abc-123', '/storage/templates/videos/x.mp4'] as $allowed) {
            $this->post('/admin/templates/preview', $this->payload(['intro_video' => $allowed]))
                ->assertOk();
        }

        foreach ([
            'javascript:alert(1)',
            'https://evil.example/clip.mp4',
            'data:text/html,<script>alert(1)</script>',
            '//evil.example/clip.mp4',
        ] as $refused) {
            $this->post('/admin/templates/preview', $this->payload(['intro_video' => $refused]))
                ->assertSessionHasErrors('intro_video');
        }
    }

    public function test_it_refuses_a_base_template_that_does_not_exist(): void
    {
        $this->post('/admin/templates/preview', ['base_template' => 'no-such-design'])
            ->assertSessionHasErrors('base_template');
    }

    public function test_it_refuses_a_bad_colour(): void
    {
        $this->post('/admin/templates/preview', $this->payload([
            'palette' => ['paper' => 'url(javascript:alert(1))'],
        ]))->assertSessionHasErrors('palette.paper');
    }

    public function test_the_preview_is_never_cached_or_indexed(): void
    {
        $this->post('/admin/templates/preview', $this->payload())
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_it_requires_authentication(): void
    {
        auth()->logout();

        $this->post('/admin/templates/preview', $this->payload())
            ->assertRedirect('/admin/login');
    }

    public function test_the_literal_segment_is_not_read_as_a_template_id(): void
    {
        $this->assertSame(
            'admin.templates.preview',
            app('router')->getRoutes()->match(
                Request::create('/admin/templates/preview', 'POST')
            )->getName()
        );
    }
}
