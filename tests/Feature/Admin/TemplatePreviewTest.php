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

    /**
     * Regression: validation used to fail with a 302 back to the form, which
     * fetch() follows. The preview then received the admin page at status 200
     * and rendered it into its own frame — the studio saw a copy of the page
     * it was standing on. A failure must be a status the caller can detect.
     */
    public function test_a_missing_base_template_is_a_422_not_a_redirect(): void
    {
        $response = $this->post('/admin/templates/preview', ['locale' => 'ar']);

        $response->assertStatus(422);

        $this->assertFalse(
            $response->isRedirect(),
            'A failed preview must not redirect — fetch follows it and renders the destination.'
        );
    }

    /** Even without the JSON headers a browser sends on a normal form post. */
    public function test_it_does_not_redirect_even_when_json_is_not_requested(): void
    {
        $this->post('/admin/templates/preview', [], ['Accept' => 'text/html'])
            ->assertStatus(422);
    }

    public function test_the_failure_says_what_to_do(): void
    {
        $this->post('/admin/templates/preview', [])
            ->assertStatus(422)
            ->assertSee('Choose a base template to preview.', false);
    }

    /** The frame must never be handed an admin page to render. */
    public function test_a_failed_preview_returns_no_admin_markup(): void
    {
        $body = $this->post('/admin/templates/preview', [])->getContent();

        $this->assertStringNotContainsString('<html', $body);
        $this->assertStringNotContainsString('New template', $body);
        $this->assertStringNotContainsString('csrf-token', $body);
    }

    public function test_it_renders_the_real_base_template(): void
    {
        // Not a mock-up: the same markup and JavaScript a guest gets, so the
        // preview cannot drift from the invitation.
        $this->post('/admin/templates/preview', $this->payload())
            ->assertOk()
            ->assertSee('armSequence', false)     // the reveal sequence
            ->assertSee('showNames', false)       // and both of its beats
            ->assertSee('const TARGET', false)    // the countdown
            ->assertSee('id="curtain"', false)
            ->assertSee('id="backdrop"', false);  // the layer the names blend on
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

    /**
     * Regression: settings.* was validated as `numeric`, so the moment a
     * design declared one that is a name rather than a number — the opening
     * style — every preview of every theme on that design answered 422 and
     * the frame went blank. A setting's type belongs to the design that
     * declared it, and the preview cannot assume they are all numbers.
     */
    public function test_a_setting_that_is_a_name_does_not_break_the_preview(): void
    {
        $html = $this->post('/admin/templates/preview', $this->payload([
            'settings' => ['opening' => 'veil', 'crossfadeMs' => '1400'],
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('data-opening="veil"', $html);
    }

    /**
     * Being loose about the value is safe because the model is not.
     *
     * These arrive mid-keystroke, so the endpoint must not 422 on a
     * half-typed number — but nothing a form can put in this array may reach
     * the stylesheet. A number gets cast, and a choice the design does not
     * offer falls back to the one it does.
     */
    public function test_junk_settings_cannot_reach_the_page(): void
    {
        $html = $this->post('/admin/templates/preview', $this->payload([
            'settings' => [
                'crossfadeMs' => 'red; background:url(javascript:alert(1))',
                'opening' => 'trapdoor',
            ],
        ]))->assertOk()->getContent();

        $this->assertStringNotContainsString('javascript:alert(1)', $html);
        $this->assertStringNotContainsString('trapdoor', $html);

        // Cast to a number and fallen back to a declared opening.
        $this->assertMatchesRegularExpression('/--fade:\s*0ms/', $html);
        $this->assertStringContainsString('data-opening="envelope"', $html);
    }

    /** A setting the chosen design never declared is simply not rendered. */
    public function test_a_setting_the_design_does_not_declare_is_ignored(): void
    {
        $html = $this->post('/admin/templates/preview', $this->payload([
            'settings' => ['somethingElseMs' => '4000'],
        ]))->assertOk()->getContent();

        $this->assertStringNotContainsString('somethingElseMs', $html);
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
            ->assertSee('يُوسُف', false);
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
            // 422 rather than session errors: the endpoint answers fetch(), so
            // a refusal is a status. Asserting the value never reaches the body
            // is the part that actually matters here.
            $response = $this->post('/admin/templates/preview', $this->payload(['intro_video' => $refused]));

            $response->assertStatus(422);
            $this->assertStringNotContainsString($refused, $response->getContent());
        }
    }

    public function test_it_refuses_a_base_template_that_does_not_exist(): void
    {
        $this->post('/admin/templates/preview', ['base_template' => 'no-such-design'])
            ->assertStatus(422);
    }

    public function test_it_refuses_a_bad_colour(): void
    {
        $response = $this->post('/admin/templates/preview', $this->payload([
            'palette' => ['paper' => 'url(javascript:alert(1))'],
        ]));

        $response->assertStatus(422);
        $this->assertStringNotContainsString('javascript:alert(1)', $response->getContent());
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
