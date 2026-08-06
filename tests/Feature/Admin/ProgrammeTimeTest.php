<?php

namespace Tests\Feature\Admin;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The programme time is a per-language display string, not a machine time —
 * Arabic and Kurdish show ٧:٠٠ where English shows 7:00. A picker was added
 * so it is chosen once per row instead of typed once per language, but the
 * stored shape must not change: the same names, the same localised strings.
 */
class ProgrammeTimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_the_editor_offers_a_time_picker_rather_than_a_text_box(): void
    {
        $invitation = Invitation::factory()->create();

        $html = $this->get("/admin/invitations/{$invitation->slug}/edit")->assertOk()->getContent();

        $this->assertStringContainsString('type="time"', $html);
        $this->assertStringContainsString('setStepTime(', $html);
    }

    /** Five-minute granularity; a seconds spinner would be noise here. */
    public function test_the_picker_steps_in_minutes_not_seconds(): void
    {
        $invitation = Invitation::factory()->create();

        $this->get("/admin/invitations/{$invitation->slug}/edit")
            ->assertOk()
            ->assertSee('step="300"', false);
    }

    /**
     * One picker per row, not one per language — that was the whole point.
     * Counted by the programme picker's own id, since the event start time
     * is a separate native picker further down the form.
     */
    public function test_the_time_is_not_asked_for_once_per_language(): void
    {
        $invitation = Invitation::factory()->locales(['ar', 'ku', 'en'], 'ar')->create();

        $html = $this->get("/admin/invitations/{$invitation->slug}/edit")->assertOk()->getContent();

        // The binding, not the method name — the method's definition also
        // appears in the page, inside the x-data attribute.
        $this->assertSame(
            1,
            substr_count($html, 'x-on:input="setStepTime('),
            'Each row should offer a single picker, whatever the language count.'
        );
    }

    /** Hidden or not, every language still posts under its original name. */
    public function test_each_language_still_submits_its_own_time_value(): void
    {
        $invitation = Invitation::factory()->locales(['ar', 'en'], 'ar')->create();

        $html = $this->get("/admin/invitations/{$invitation->slug}/edit")->assertOk()->getContent();

        foreach (['ar', 'en'] as $code) {
            $this->assertStringContainsString(
                "programme[{$code}][\${i}][time]",
                $html,
                "missing submit name for {$code}"
            );
        }
    }

    /**
     * The picker only changes how a value is entered. What the server accepts
     * and stores has to be untouched, including the Arabic-Indic digits.
     */
    public function test_localised_times_round_trip_through_a_save(): void
    {
        $invitation = Invitation::factory()->locales(['ar', 'en'], 'ar')->create();

        $this->put("/admin/invitations/{$invitation->slug}", $this->payload($invitation, [
            'programme' => [
                'ar' => [['time' => '٧:٠٠', 'title' => 'استقبال الضيوف']],
                'en' => [['time' => '7:00', 'title' => 'Reception']],
            ],
        ]))->assertSessionHasNoErrors();

        $invitation->refresh();

        $this->assertSame('٧:٠٠', $invitation->programme['ar'][0]['time']);
        $this->assertSame('7:00', $invitation->programme['en'][0]['time']);
        $this->assertSame('استقبال الضيوف', $invitation->programme['ar'][0]['title']);
    }

    /** What a guest sees must be the localised string, not a 24h clock. */
    public function test_the_guest_page_shows_the_localised_time(): void
    {
        $invitation = Invitation::factory()->locales(['ar', 'en'], 'ar')->create([
            'published' => true,
        ]);

        $this->put("/admin/invitations/{$invitation->slug}", $this->payload($invitation, [
            // Without this the save unticks the checkbox and the link 404s.
            'published' => 1,
            'programme' => [
                'ar' => [['time' => '٧:٣٠', 'title' => 'استقبال الضيوف']],
                'en' => [['time' => '7:30', 'title' => 'Reception']],
            ],
        ]))->assertSessionHasNoErrors();

        $this->post('/admin/logout');

        $this->get("/i/{$invitation->slug}")->assertOk()->assertSee('٧:٣٠', false);
        $this->get("/i/{$invitation->slug}/en")->assertOk()->assertSee('7:30', false);
    }

    /**
     * A row whose time cannot be parsed back into the picker keeps its value.
     * Wiping it because the picker could not read it would be silent loss.
     */
    public function test_an_unparseable_time_is_preserved_on_save(): void
    {
        $invitation = Invitation::factory()->locales(['ar', 'en'], 'ar')->create();

        $this->put("/admin/invitations/{$invitation->slug}", $this->payload($invitation, [
            'programme' => [
                'ar' => [['time' => 'بعد العشاء', 'title' => 'الرقص']],
                'en' => [['time' => 'After dinner', 'title' => 'Dancing']],
            ],
        ]))->assertSessionHasNoErrors();

        $invitation->refresh();

        $this->assertSame('بعد العشاء', $invitation->programme['ar'][0]['time']);
        $this->assertSame('After dinner', $invitation->programme['en'][0]['time']);
    }

    /** A row can legitimately have no time at all. */
    public function test_a_row_with_no_time_still_saves(): void
    {
        $invitation = Invitation::factory()->locales(['en'], 'en')->create();

        $this->put("/admin/invitations/{$invitation->slug}", $this->payload($invitation, [
            'locales' => ['en'],
            'default_locale' => 'en',
            'programme' => ['en' => [['time' => '', 'title' => 'Open bar']]],
        ]))->assertSessionHasNoErrors();

        $invitation->refresh();

        $this->assertSame('Open bar', $invitation->programme['en'][0]['title']);
        $this->assertSame('', (string) $invitation->programme['en'][0]['time']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Invitation $invitation, array $overrides = []): array
    {
        return array_merge([
            'template_id' => $invitation->template_id,
            'slug' => $invitation->slug,
            'locales' => ['ar', 'en'],
            'default_locale' => 'ar',
            'groom_ar' => $invitation->groom_ar,
            'bride_ar' => $invitation->bride_ar,
            // The form posts the date and the clock time separately, then
            // combines them in the event's own zone.
            'date' => $invitation->startsAtLocal()->format('Y-m-d'),
            'time' => $invitation->startsAtLocal()->format('H:i'),
            'timezone' => $invitation->timezone,
            'duration_hours' => $invitation->duration_hours,
            'hosts' => ['ar' => 'أهلا', 'en' => 'Welcome'],
            'venue_name' => ['ar' => 'قاعة', 'en' => 'Hall'],
            'venue_city' => ['ar' => 'بغداد', 'en' => 'Baghdad'],
        ], $overrides);
    }
}
