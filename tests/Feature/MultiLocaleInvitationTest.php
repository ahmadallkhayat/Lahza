<?php

namespace Tests\Feature;

use App\Models\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiLocaleInvitationTest extends TestCase
{
    use RefreshDatabase;

    private function trilingual(): Invitation
    {
        return Invitation::factory()
            ->locales(['ar', 'ku', 'en'], 'ar')
            ->create(['slug' => 'ahmad-sidra']);
    }

    public function test_default_language_is_served_from_the_bare_slug(): void
    {
        $this->trilingual();

        $this->get('/i/ahmad-sidra')
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl"', false)
            ->assertSee('قاعة بابل', false);
    }

    public function test_each_language_has_its_own_link(): void
    {
        $this->trilingual();

        $this->get('/i/ahmad-sidra/ku')
            ->assertOk()
            ->assertSee('<html lang="ku" dir="rtl"', false)
            ->assertSee('هۆڵی بابل', false);

        $this->get('/i/ahmad-sidra/en')
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr"', false)
            ->assertSee('Babylon Hall', false);
    }

    /**
     * The whole reason content is stored per language: a language's own venue
     * name has to appear on its own link, not the default's.
     */
    public function test_content_does_not_leak_between_languages(): void
    {
        $this->trilingual();

        $this->get('/i/ahmad-sidra/en')
            ->assertSee('Babylon Hall', false)
            ->assertDontSee('قاعة بابل', false)
            ->assertDontSee('هۆڵی بابل', false);
    }

    public function test_a_language_not_enabled_falls_back_to_the_default(): void
    {
        // Arabic and English only.
        Invitation::factory()->locales(['ar', 'en'], 'ar')->create(['slug' => 'two-langs']);

        // A mistyped or stale link should still show the invitation rather than
        // 404 on the language segment.
        $this->get('/i/two-langs/ku')
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl"', false);
    }

    public function test_an_unknown_language_segment_is_not_a_route(): void
    {
        Invitation::factory()->locales(['ar', 'en'])->create(['slug' => 'constrained']);

        // The segment is constrained, so junk does not reach the controller.
        $this->get('/i/constrained/zz')->assertNotFound();
    }

    public function test_the_switcher_appears_only_when_there_is_a_choice(): void
    {
        $this->trilingual();
        Invitation::factory()->locale('ar')->create(['slug' => 'single']);

        $this->get('/i/ahmad-sidra')->assertSee('id="langs"', false);
        $this->get('/i/single')->assertDontSee('id="langs"', false);
    }

    public function test_the_switcher_links_to_the_other_languages_only(): void
    {
        $this->trilingual();

        $html = $this->get('/i/ahmad-sidra/en')->getContent();

        // Scoped to the switcher: the page's own URL legitimately appears
        // elsewhere, in og:url and the calendar link.
        preg_match('#<nav id="langs".*?</nav>#s', $html, $nav);
        $this->assertNotEmpty($nav, 'The switcher was not rendered.');

        // On the English page it offers Arabic and Kurdish, not English again.
        $this->assertStringContainsString('/i/ahmad-sidra"', $nav[0]);
        $this->assertStringContainsString('/i/ahmad-sidra/ku"', $nav[0]);
        $this->assertStringNotContainsString('/i/ahmad-sidra/en', $nav[0]);
    }

    public function test_the_page_declares_its_own_language_as_canonical(): void
    {
        $this->trilingual();

        $this->get('/i/ahmad-sidra/en')
            ->assertSee('<meta property="og:url" content="'.route('invitations.show.locale', ['ahmad-sidra', 'en']).'">', false);

        $this->get('/i/ahmad-sidra')
            ->assertSee('<meta property="og:url" content="'.route('invitations.show', 'ahmad-sidra').'">', false);
    }

    public function test_calendar_file_follows_the_language_of_the_link(): void
    {
        $this->trilingual();

        $arabic = $this->get('/i/ahmad-sidra/calendar.ics')->getContent();
        $english = $this->get('/i/ahmad-sidra/en/calendar.ics')->getContent();

        $this->assertStringContainsString('قاعة بابل', $arabic);
        $this->assertStringContainsString('Babylon Hall', $english);
    }

    public function test_missing_translation_falls_back_rather_than_leaving_a_gap(): void
    {
        // Kurdish enabled but its venue never filled in.
        $invitation = Invitation::factory()->locales(['ar', 'ku'], 'ar')->create([
            'slug' => 'partial',
            'venue_name' => ['ar' => 'قاعة بابل'],
        ]);

        $invitation->renderIn('ku');

        $this->assertSame('قاعة بابل', $invitation->text('venue_name'));
        $this->get('/i/partial/ku')->assertOk()->assertSee('قاعة بابل', false);
    }

    public function test_timezone_defaults_to_baghdad(): void
    {
        $this->assertSame('Asia/Baghdad', config('templates.default_timezone'));

        // The column default backs it up for anything inserted without one.
        $id = Invitation::factory()->create()->id;
        $this->assertSame('Asia/Baghdad', Invitation::find($id)->timezone);
    }

    public function test_a_pasted_maps_link_wins_over_the_search_query(): void
    {
        $invitation = Invitation::factory()->create([
            'map_url' => 'https://maps.app.goo.gl/abc123',
            'map_query' => 'Somewhere else',
        ]);

        $this->assertSame('https://maps.app.goo.gl/abc123', $invitation->mapUrl());
    }

    public function test_locale_helpers_report_the_right_shape(): void
    {
        $one = Invitation::factory()->locale('ar')->create();
        $many = $this->trilingual();

        $this->assertFalse($one->isMultilingual());
        $this->assertTrue($many->isMultilingual());

        $this->assertSame(['ar', 'ku', 'en'], $many->localeList());
        $this->assertTrue($many->hasLocale('ku'));
        $this->assertFalse($many->hasLocale('fr'));

        // renderIn ignores a language the invitation does not have.
        $this->assertSame('ar', $many->renderIn('fr')->locale());
        $this->assertSame('en', $many->renderIn('en')->locale());
    }
}
