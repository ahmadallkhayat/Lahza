<?php

namespace Tests\Feature\Admin;

use App\Models\Asset;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\QuranVerseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The library of marriage verses.
 *
 * These assertions are about shape, not scripture: that every row is a usable
 * verse asset, that re-running the seeder does not duplicate anything, and
 * that a studio edit is recoverable. Whether the Arabic is correct is a
 * question for someone who can read it, and no test here claims otherwise.
 */
class QuranVerseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_verse_is_usable_as_it_stands(): void
    {
        $this->seed(QuranVerseSeeder::class);

        $verses = Asset::where('kind', Asset::KIND_VERSE)->get();

        $this->assertGreaterThan(10, $verses->count());

        foreach ($verses as $verse) {
            $this->assertNotEmpty($verse->body, $verse->name.' has no verse');

            // A reference in every language, since each writes it differently.
            foreach (['ar', 'ku', 'en'] as $locale) {
                $this->assertNotEmpty(
                    $verse->reference[$locale] ?? null,
                    $verse->name.' has no '.$locale.' reference'
                );
            }

            // Shared and filed under nothing: being a verse is the whole of
            // what it is for, and it belongs to no one design.
            $this->assertNull($verse->base_template);
            $this->assertNull($verse->field);
            $this->assertTrue($verse->is_active);
        }
    }

    public function test_the_arabic_carries_its_vowel_marks(): void
    {
        // Unvocalised text would be the wrong thing on the page, and is the
        // most likely way a bad copy would arrive without anyone noticing.
        $this->seed(QuranVerseSeeder::class);

        foreach (Asset::where('kind', Asset::KIND_VERSE)->get() as $verse) {
            $this->assertMatchesRegularExpression(
                '/\p{Mn}/u',
                $verse->body,
                $verse->name.' has no diacritics — the text is not vocalised'
            );

            // And nothing but Arabic script, spaces and marks.
            $this->assertDoesNotMatchRegularExpression(
                '/[A-Za-z0-9]/',
                $verse->body,
                $verse->name.' has Latin characters in the verse'
            );
        }
    }

    public function test_running_it_twice_does_not_duplicate(): void
    {
        $this->seed(QuranVerseSeeder::class);
        $first = Asset::where('kind', Asset::KIND_VERSE)->count();

        $this->seed(QuranVerseSeeder::class);

        $this->assertSame($first, Asset::where('kind', Asset::KIND_VERSE)->count());
    }

    public function test_re_running_restores_a_verse_someone_edited(): void
    {
        $this->seed(QuranVerseSeeder::class);

        $verse = Asset::where('kind', Asset::KIND_VERSE)->firstOrFail();
        $original = $verse->body;

        $verse->update(['body' => 'typed over by hand']);

        $this->seed(QuranVerseSeeder::class);

        $this->assertSame($original, $verse->fresh()->body);
    }

    public function test_the_verses_reach_the_invitation_editor(): void
    {
        $this->seed(QuranVerseSeeder::class);

        Template::factory()->create(['base_template' => 'luxury-wedding-v1']);
        $this->actingAs(User::factory()->create());

        $this->get('/admin/invitations/create')
            ->assertOk()
            ->assertSee('Ar-Rum 30:21')
            ->assertSee('Al-Furqan 25:74');
    }
}
