<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\Template;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_invitation_is_public(): void
    {
        $invitation = Invitation::factory()->create();

        $this->get("/i/{$invitation->slug}")
            ->assertOk()
            ->assertSee($invitation->groom_ar, false)
            ->assertSee($invitation->bride_ar, false);
    }

    public function test_draft_invitation_is_hidden_from_guests_but_previewable_by_studio(): void
    {
        $invitation = Invitation::factory()->draft()->create();

        // 404 rather than 403: a 403 would confirm the slug exists.
        $this->get("/i/{$invitation->slug}")->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->get("/i/{$invitation->slug}")
            ->assertOk();
    }

    public function test_unknown_slug_is_not_found(): void
    {
        $this->get('/i/no-such-invitation')->assertNotFound();
    }

    public function test_page_direction_and_tracking_follow_the_locale(): void
    {
        $arabic = Invitation::factory()->create(['slug' => 'ar-inv']);
        $english = Invitation::factory()->locale('en')->create(['slug' => 'en-inv']);

        $this->get('/i/ar-inv')
            ->assertSee('<html lang="ar" dir="rtl"', false)
            // Tracking breaks Arabic letter joins, so it must stay off.
            ->assertSee('--tk: 0', false);

        $this->get('/i/en-inv')
            ->assertSee('<html lang="en" dir="ltr"', false)
            ->assertSee('--tk: 1', false);

        $this->assertTrue($arabic->isRtl());
        $this->assertFalse($english->isRtl());
    }

    public function test_latin_names_show_only_on_english_invitations(): void
    {
        Invitation::factory()->create(['slug' => 'ar-names']);
        Invitation::factory()->locale('en')->create(['slug' => 'en-names']);

        $this->get('/i/ar-names')->assertDontSee('class="latin"', false);
        $this->get('/i/en-names')->assertSee('class="latin"', false);
    }

    public function test_music_never_autoplays(): void
    {
        $invitation = Invitation::factory()->create();

        // Playback is started by the tap handler. An autoplay attribute would
        // both break the reveal and be blocked by the browser anyway.
        $this->get("/i/{$invitation->slug}")->assertDontSee('autoplay', false);
    }

    public function test_open_graph_tags_are_present(): void
    {
        // WhatsApp is the distribution channel; a link with no preview card
        // looks broken.
        $invitation = Invitation::factory()->create();

        $this->get("/i/{$invitation->slug}")
            ->assertSee('property="og:title"', false)
            ->assertSee('property="og:description"', false)
            ->assertSee('property="og:url"', false);
    }

    public function test_calendar_file_is_utc_and_rfc_compliant(): void
    {
        $invitation = Invitation::factory()->create([
            'slug' => 'cal-test',
            'starts_at' => CarbonImmutable::parse('2026-12-04 19:00', 'Asia/Amman'),
            'timezone' => 'Asia/Amman',
            'duration_hours' => 5,
        ]);

        $response = $this->get("/i/{$invitation->slug}/calendar.ics")
            ->assertOk()
            ->assertHeader('content-type', 'text/calendar; charset=utf-8');

        $body = $response->getContent();

        // 19:00 in Amman (+03) is 16:00 UTC.
        $this->assertStringContainsString('DTSTART:20261204T160000Z', $body);
        $this->assertStringContainsString('DTEND:20261204T210000Z', $body);
        // RFC 5545 requires CRLF line endings; Apple rejects bare newlines.
        $this->assertStringContainsString("\r\n", $body);
    }

    public function test_draft_calendar_file_is_hidden(): void
    {
        $invitation = Invitation::factory()->draft()->create();

        $this->get("/i/{$invitation->slug}/calendar.ics")->assertNotFound();
    }

    public function test_missing_template_view_fails_loudly(): void
    {
        $template = Template::factory()->create(['base_template' => 'no-such-design']);
        $invitation = Invitation::factory()->for($template)->create();

        $this->assertFalse($template->isRenderable());
        $this->get("/i/{$invitation->slug}")->assertStatus(500);
    }

    public function test_template_palette_and_settings_fall_back_to_defaults(): void
    {
        $template = Template::factory()->create([
            'palette' => ['gold' => '#ffffff'],
            'settings' => ['fogHz' => 400],
        ]);

        // Overrides win, everything else still has a value.
        $this->assertSame('#ffffff', $template->palette()['gold']);
        $this->assertSame(Template::DEFAULT_PALETTE['paper'], $template->palette()['paper']);
        $this->assertSame(400, $template->settings()['fogHz']);
        $this->assertSame(Template::DEFAULT_SETTINGS['crossfadeMs'], $template->settings()['crossfadeMs']);
    }
}
