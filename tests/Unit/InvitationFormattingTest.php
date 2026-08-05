<?php

namespace Tests\Unit;

use App\Models\Invitation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationFormattingTest extends TestCase
{
    use RefreshDatabase;

    private function at(string $local, string $tz = 'Asia/Baghdad', string $locale = 'ar'): Invitation
    {
        return Invitation::factory()->create([
            'starts_at' => CarbonImmutable::parse($local, $tz),
            'timezone' => $tz,
            'locales' => [$locale], 'default_locale' => $locale,
        ]);
    }

    /**
     * Eloquent stores whatever wall clock a Carbon instance carries, so a
     * zoned instance used to be written as local time and read back as UTC —
     * 19:00 in Baghdad came out as 22:00. The model converts on the way in.
     */
    public function test_start_time_is_stored_utc_and_read_back_in_the_event_zone(): void
    {
        $invitation = $this->at('2026-12-04 19:00');

        $this->assertDatabaseHas('invitations', [
            'id' => $invitation->id,
            'starts_at' => '2026-12-04 16:00:00',
        ]);

        $fresh = $invitation->fresh();
        $this->assertSame('19:00', $fresh->startsAtLocal()->format('H:i'));
        $this->assertSame('2026-12-04T19:00:00+03:00', $fresh->startsAtIso());
    }

    public function test_time_survives_a_round_trip_in_a_different_zone(): void
    {
        $invitation = $this->at('2027-03-14 18:30', 'Asia/Baghdad')->fresh();

        $this->assertDatabaseHas('invitations', ['starts_at' => '2027-03-14 15:30:00']);
        $this->assertSame('18:30', $invitation->startsAtLocal()->format('H:i'));
    }

    public function test_end_time_uses_the_duration(): void
    {
        $invitation = $this->at('2026-12-04 19:00');
        $invitation->update(['duration_hours' => 4]);

        $this->assertSame('23:00', $invitation->fresh()->endsAtLocal()->format('H:i'));
    }

    public function test_arabic_dates_use_arabic_digits_and_comma(): void
    {
        $invitation = $this->at('2026-12-04 19:00', locale: 'ar');

        $date = $invitation->formattedDate();

        $this->assertStringContainsString('،', $date, 'Arabic should use the Arabic comma.');
        $this->assertStringContainsString('٤', $date, 'Arabic should use Arabic-Indic digits.');
        $this->assertStringNotContainsString('4', $date);
    }

    public function test_english_dates_use_latin_digits_and_comma(): void
    {
        $invitation = $this->at('2026-12-04 19:00', locale: 'en');

        $this->assertSame('Friday, 4 December 2026', $invitation->formattedDate());
        $this->assertSame('7:00 in the evening', $invitation->formattedTime());
    }

    public function test_kurdish_dates_use_sorani_month_names(): void
    {
        $invitation = $this->at('2026-12-04 19:00', locale: 'ku');

        // ICU knows Sorani as ckb, not ku.
        $this->assertStringContainsString('کانونی یەکەم', $invitation->formattedDate());
        $this->assertStringContainsString('٤', $invitation->formattedDate());
    }

    /**
     * A `calendar` locale keyword is silently ignored by IntlDateFormatter, so
     * this guards the explicit IntlCalendar the model builds instead.
     */
    public function test_hijri_date_is_a_real_hijri_date(): void
    {
        $invitation = $this->at('2026-12-04 19:00', locale: 'ar');

        $hijri = $invitation->hijriDate();

        // 4 Dec 2026 falls in Jumada al-Akhirah 1448 (Umm al-Qura).
        $this->assertStringContainsString('جمادى الآخرة', $hijri);
        $this->assertStringContainsString('١٤٤٨', $hijri);
        $this->assertStringNotContainsString('ديسمبر', $hijri, 'Gregorian month leaked into the Hijri line.');
    }

    public function test_kurdish_hijri_falls_back_to_arabic_month_names(): void
    {
        // ICU has no Kurdish Hijri months and would emit English ("Jumada II"),
        // which reads worse on an Arabic-script page than the Arabic does.
        $invitation = $this->at('2026-12-04 19:00', locale: 'ku');

        $this->assertStringContainsString('جمادى الآخرة', $invitation->hijriDate());
        $this->assertStringNotContainsString('Jumada', $invitation->hijriDate());
    }

    public function test_english_invitations_have_no_hijri_line(): void
    {
        $this->assertNull($this->at('2026-12-04 19:00', locale: 'en')->hijriDate());
    }

    public function test_blank_programme_rows_are_dropped(): void
    {
        $invitation = Invitation::factory()->create([
            'programme' => ['ar' => [
                ['time' => '19:00', 'title' => 'Reception'],
                ['time' => '20:00', 'title' => ''],
                ['time' => '', 'title' => null],
            ]],
        ]);

        $this->assertCount(1, $invitation->programmeRows());
        $this->assertSame('Reception', $invitation->programmeRows()[0]['title']);
    }

    public function test_map_url_falls_back_to_venue_when_no_query_is_set(): void
    {
        $invitation = Invitation::factory()->create([
            'map_query' => null,
            'map_url' => null,
            'venue_name' => ['ar' => 'Babylon Hall'],
            'venue_city' => ['ar' => 'Baghdad'],
        ]);

        $this->assertStringContainsString('Babylon+Hall+Baghdad', $invitation->mapUrl());
    }

    public function test_map_url_is_null_without_a_venue(): void
    {
        $invitation = Invitation::factory()->create([
            'map_query' => null,
            'map_url' => null,
            'venue_name' => null,
            'venue_city' => null,
        ]);

        $this->assertNull($invitation->mapUrl());
    }
}
