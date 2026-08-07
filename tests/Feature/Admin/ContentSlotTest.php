<?php

namespace Tests\Feature\Admin;

use App\Models\Asset;
use App\Models\Invitation;
use App\Models\Template;
use App\Models\User;
use App\Support\ContentSlots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Content slots: what a base template declares it can say.
 *
 * The design's template.php is the single declaration. The invitation form,
 * the categories a text asset can be filed under, and the guest page all read
 * it, so a field cannot exist in one of them and be missing from another —
 * which is exactly how the library came to offer "verse" as a place to file a
 * block of text while nothing on the invitation could hold one.
 */
class ContentSlotTest extends TestCase
{
    use RefreshDatabase;

    private function invitation(array $attributes = []): Invitation
    {
        return Invitation::factory()
            ->for(Template::factory()->create(['base_template' => 'luxury-wedding-v1']))
            ->create($attributes);
    }

    /* ── the declaration ──────────────────────────────────────────── */

    public function test_a_design_declares_the_slots_it_carries(): void
    {
        $slots = ContentSlots::for('luxury-wedding-v1');

        $this->assertArrayHasKey('verse', $slots);
        $this->assertArrayHasKey('verse_translation', $slots);
        $this->assertArrayHasKey('verse_ref', $slots);
    }

    public function test_a_design_with_no_manifest_simply_has_no_slots(): void
    {
        // Which is why adding manifests to an installation breaks nothing.
        $this->assertSame([], ContentSlots::for('no-such-design'));
        $this->assertSame([], ContentSlots::for(null));
    }

    /**
     * The list a text asset is filed against is computed, not hand-written.
     * It used to be a constant, and it drifted: it offered "programme step"
     * while the invitation form rendered no such field, so a block filed there
     * could be written and never used.
     */
    public function test_the_text_asset_categories_are_derived_from_what_designs_declare(): void
    {
        $fields = Asset::textFields();

        // Core fields every design has.
        $this->assertArrayHasKey('hosts', $fields);
        $this->assertArrayHasKey('venue_name', $fields);

        // Declared by nothing, so offered by nothing.
        $this->assertArrayNotHasKey('programme', $fields);

        // Grouped, so not a category of plain text: the verse and its parts
        // are one block filled from a verse asset, not three places to file
        // a paragraph.
        $this->assertArrayNotHasKey('verse', $fields);
        $this->assertArrayNotHasKey('verse_ref', $fields);
    }

    public function test_a_category_no_design_declares_is_refused(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/admin/assets', [
            'kind' => Asset::KIND_TEXT,
            'name' => 'A blessing',
            'body' => 'مبارك',
            'field' => 'programme',
        ])->assertSessionHasErrors('field');
    }

    /* ── slot groups ──────────────────────────────────────────────── */

    /**
     * The mapping from asset part to slot has to survive normalisation.
     *
     * It did not: groups() listed the keys it wanted and dropped the rest,
     * which discarded `fills` silently. The manifest declared the mapping, the
     * browser got an empty object, and the Insert button filled nothing with no
     * error anywhere. The button rendered, so every test still passed.
     */
    public function test_a_group_keeps_the_mapping_that_fills_it(): void
    {
        $group = ContentSlots::groups('luxury-wedding-v1')['verse'];

        $this->assertSame([
            'verse' => 'body',
            'verse_translation' => 'translation',
            'verse_ref' => 'reference',
        ], $group['fills']);
    }

    public function test_a_mapping_cannot_name_a_slot_outside_its_group(): void
    {
        // A stale key left in a manifest would otherwise write into a field the
        // card does not even show.
        foreach (ContentSlots::groups('luxury-wedding-v1') as $group) {
            foreach (array_keys($group['fills']) as $slot) {
                $this->assertArrayHasKey($slot, $group['slots']);
            }
        }
    }

    /**
     * Every part a mapping names has to be something a library asset actually
     * carries, or Insert would read undefined and write nothing.
     */
    public function test_every_mapped_part_exists_on_the_asset(): void
    {
        $verse = Asset::factory()->verse()->create();

        foreach (ContentSlots::groups('luxury-wedding-v1') as $group) {
            foreach ($group['fills'] as $slot => $part) {
                $this->assertNotNull(
                    $verse->{$part},
                    "Slot {$slot} is filled from '{$part}', which a verse asset does not carry"
                );
            }
        }
    }

    /**
     * End to end: the mapping reaches the browser. This is the assertion that
     * was missing — the button was tested, the data behind it was not.
     */
    public function test_the_mapping_reaches_the_page(): void
    {
        $this->actingAs(User::factory()->create());

        Template::factory()->create(['base_template' => 'luxury-wedding-v1']);
        Asset::factory()->verse()->create(['name' => 'Ar-Rum 30:21']);

        $html = $this->get('/admin/invitations/create')->assertOk()->getContent();

        // Serialised the same way Blade does, so this compares what the browser
        // is handed rather than a guess at how it was escaped.
        $fills = ContentSlots::groups('luxury-wedding-v1')['verse']['fills'];
        $encoded = Str::between((string) Js::from($fills), "JSON.parse('", "')");

        $this->assertStringContainsString($encoded, $html);
    }

    /**
     * A Kurdish invitation shows the verse and its reference, and no
     * translation line beneath them.
     *
     * The verse is read in Arabic on a Kurdish invitation, so only English
     * carries a rendering. Asserted rather than left to lang/ku happening to
     * hold an empty string: this is a decision about what the page says, and
     * the next person to fill that key in should have to change a test.
     */
    public function test_a_kurdish_invitation_shows_the_verse_untranslated(): void
    {
        $invitation = $this->invitation([
            'published' => true,
            'show_verse' => true,
            'locales' => ['ku'],
            'default_locale' => 'ku',
            'content' => null,
        ]);

        $this->assertSame('', $invitation->renderIn('ku')->slot('verse_translation'));

        $this->get('/i/'.$invitation->slug)
            ->assertOk()
            // The verse itself, in Arabic, and the reference in Kurdish.
            ->assertSee(trim(__('invitation.verse', [], 'ku')), false)
            ->assertSee(trim(__('invitation.verse_ref', [], 'ku')), false)
            // No element for a translation that is not there.
            ->assertDontSee('class="verse-en"', false);
    }

    /** English is the one language that prints a rendering beneath the verse. */
    public function test_only_english_carries_a_verse_translation(): void
    {
        foreach (['ar', 'ku'] as $locale) {
            $this->assertSame(
                '',
                trim(__('invitation.verse_translation', [], $locale)),
                $locale.' should print no verse translation'
            );
        }

        $this->assertNotSame('', trim(__('invitation.verse_translation', [], 'en')));
    }

    /* ── reading a slot ───────────────────────────────────────────── */

    public function test_an_empty_slot_falls_back_to_the_designs_own_wording(): void
    {
        // Empty means "use the design's verse", not "show nothing" — so every
        // invitation that says nothing about it still renders one.
        $invitation = $this->invitation(['content' => null]);

        $this->assertSame(
            trim(__('invitation.verse', [], 'ar')),
            $invitation->renderIn('ar')->slot('verse')
        );
    }

    public function test_a_filled_slot_overrides_the_default(): void
    {
        $invitation = $this->invitation([
            'content' => ['verse' => ['ar' => 'وَتَعَاوَنُوا عَلَى الْبِرِّ']],
        ]);

        $this->assertSame('وَتَعَاوَنُوا عَلَى الْبِرِّ', $invitation->renderIn('ar')->slot('verse'));
    }

    public function test_an_arabic_slot_is_the_same_value_in_every_language(): void
    {
        // Stored once, like the names. Read back from whichever language it
        // was stored under, so English shows the Arabic verse too.
        $invitation = $this->invitation([
            'locales' => ['ar', 'en'],
            'default_locale' => 'ar',
            'content' => ['verse' => ['ar' => 'آية مخصصة']],
        ]);

        $this->assertSame('آية مخصصة', $invitation->renderIn('en')->slot('verse'));
    }

    /**
     * text() lends a missing language another's value, which is right for a
     * venue name. It is wrong here: the reference reads "QURAN 30:21" in
     * English and سورة الروم in Arabic, so borrowing puts Latin text under an
     * Arabic verse. A slot always has a default to fall back on instead.
     */
    public function test_a_per_language_slot_never_borrows_another_languages_value(): void
    {
        $invitation = $this->invitation([
            'locales' => ['ar', 'en'],
            'default_locale' => 'ar',
            'content' => ['verse_ref' => ['en' => 'QURAN 30:21']],
        ]);

        $arabic = $invitation->renderIn('ar')->slot('verse_ref');

        $this->assertNotSame('QURAN 30:21', $arabic);
        $this->assertSame(trim(__('invitation.verse_ref', [], 'ar')), $arabic);
    }

    /* ── the guest page ───────────────────────────────────────────── */

    public function test_the_guest_page_shows_the_invitations_own_verse(): void
    {
        $invitation = $this->invitation([
            'published' => true,
            'show_verse' => true,
            'locales' => ['ar'],
            'default_locale' => 'ar',
            'content' => ['verse' => ['ar' => 'آية مخصصة تمامًا']],
        ]);

        $this->get('/i/'.$invitation->slug)
            ->assertOk()
            ->assertSee('آية مخصصة تمامًا', false)
            ->assertDontSee(trim(__('invitation.verse', [], 'ar')), false);
    }

    public function test_the_guest_page_falls_back_to_the_designs_verse(): void
    {
        $invitation = $this->invitation([
            'published' => true,
            'show_verse' => true,
            'locales' => ['ar'],
            'default_locale' => 'ar',
            'content' => null,
        ]);

        $this->get('/i/'.$invitation->slug)
            ->assertOk()
            ->assertSee(trim(__('invitation.verse', [], 'ar')), false);
    }

    /* ── saving ───────────────────────────────────────────────────── */

    public function test_slot_content_is_saved_and_undeclared_keys_are_dropped(): void
    {
        $this->actingAs(User::factory()->create());

        $invitation = $this->invitation(['locales' => ['ar'], 'default_locale' => 'ar']);

        $this->put('/admin/invitations/'.$invitation->slug, [
            'template_id' => $invitation->template_id,
            'locales' => ['ar'],
            'default_locale' => 'ar',
            'groom_ar' => 'أَحْمَد',
            'bride_ar' => 'سِدْرَة',
            'date' => '2027-01-01',
            'time' => '19:00',
            'timezone' => 'Asia/Baghdad',
            'duration_hours' => 4,
            'content' => [
                'verse' => ['ar' => 'آية مخصصة'],
                // Nothing declares this, and the form cannot render it.
                'smuggled' => ['ar' => 'should not be stored'],
            ],
        ])->assertSessionHasNoErrors();

        $content = $invitation->fresh()->content;

        $this->assertSame('آية مخصصة', $content['verse']['ar']);
        $this->assertArrayNotHasKey('smuggled', $content);
    }

    public function test_clearing_a_slot_returns_it_to_the_designs_wording(): void
    {
        // Stored blank would mean "show nothing"; dropped means "use the
        // default", which is what an emptied box should do.
        $invitation = $this->invitation([
            'locales' => ['ar'],
            'default_locale' => 'ar',
            'content' => ['verse' => ['ar' => 'آية مخصصة']],
        ]);

        $this->actingAs(User::factory()->create())
            ->put('/admin/invitations/'.$invitation->slug, [
                'template_id' => $invitation->template_id,
                'locales' => ['ar'],
                'default_locale' => 'ar',
                'groom_ar' => 'أَحْمَد',
                'bride_ar' => 'سِدْرَة',
                'date' => '2027-01-01',
                'time' => '19:00',
                'timezone' => 'Asia/Baghdad',
                'duration_hours' => 4,
                'content' => ['verse' => ['ar' => '   ']],
            ])->assertSessionHasNoErrors();

        $invitation = $invitation->fresh();

        $this->assertNull($invitation->content);
        $this->assertSame(
            trim(__('invitation.verse', [], 'ar')),
            $invitation->renderIn('ar')->slot('verse')
        );
    }

    /* ── the form ─────────────────────────────────────────────────── */

    public function test_the_invitation_form_renders_the_declared_slots(): void
    {
        $this->actingAs(User::factory()->create());

        Template::factory()->create(['base_template' => 'luxury-wedding-v1']);

        $this->get('/admin/invitations/create')
            ->assertOk()
            ->assertSee('Translation')
            ->assertSee('content[verse]', false);
    }
}
