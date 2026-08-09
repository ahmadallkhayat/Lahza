<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Template;
use Illuminate\Database\Seeder;

/**
 * Starts the library off with something in it.
 *
 * Two parts. The media the luxury wedding template already uses is registered
 * as an asset, so the clip and track it was seeded with are reusable by the
 * next theme rather than needing to be uploaded again — the paths are matched
 * to what LuxuryWeddingV1Seeder put on the public disk, which is also what
 * makes the template's "from the library" line appear.
 *
 * Then the text: the verse the pages open with, and hosting lines written with
 * placeholders so they read correctly for any couple.
 */
class AssetLibrarySeeder extends Seeder
{
    public function run(): void
    {
        $this->media();
        $this->verses();
        $this->hostingLines();
    }

    /**
     * Register whatever media the seeded template is already using.
     *
     * Read off the template rather than hardcoded: if the archive files were
     * missing when it ran, those columns are null and there is nothing to
     * register, which is the correct outcome rather than an asset pointing at
     * a file that was never written.
     */
    private function media(): void
    {
        $template = Template::where('slug', 'luxury-wedding-v1')->first();

        if ($template === null) {
            return;
        }

        $media = [
            [$template->intro_video, Asset::KIND_VIDEO, 'Ring box opening', 'Dark chocolate opening, flooding to champagne light.'],
            [$template->music, Asset::KIND_MUSIC, 'Luxury wedding score', 'Plays from the reveal onwards, ducked and muffled.'],
        ];

        foreach ($media as [$path, $kind, $name, $description]) {
            if (blank($path)) {
                continue;
            }

            Asset::updateOrCreate(
                ['kind' => $kind, 'path' => $path],
                [
                    // Scoped to the design it was cut for, rather than shared.
                    'base_template' => 'luxury-wedding-v1',
                    'name' => $name,
                    'description' => $description,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Shared, not scoped to a design: a verse is not specific to one layout.
     *
     * One asset, not three. The verse, its translation and its reference are
     * shown together and are useless apart, so they are stored together and
     * inserted into an invitation in one go — this used to be an Arabic row
     * and an English row with nothing recording that they were the same verse.
     */
    private function verses(): void
    {
        // Delegated, so verses have one owner.
        //
        // This method used to carry its own copy of Ar-Rum 30:21 while
        // QuranVerseSeeder carried another, which meant two seeders writing the
        // same row and whichever ran last deciding what the verse said.
        $this->call(QuranVerseSeeder::class);

        // The separate English row the verse kind replaced, if an earlier seed
        // left it. Not filtered by kind: the migration that introduced the
        // verse kind moved everything filed under "verse" onto it, this row
        // included, so looking for a text row would no longer find it.
        Asset::where('name', 'Ar-Rum 30:21 — English')->delete();
    }

    /**
     * Hosting lines with placeholders, one per language.
     *
     * These are the case the placeholders exist for: the same sentence every
     * time bar the names, so it is written once and filled in per invitation.
     */
    private function hostingLines(): void
    {
        /* One row carrying all three languages, not three rows carrying one
           each. The sentence is the same thing in every language, so inserting
           it should be one action — which is what the per-language body makes
           possible. The three old rows are retired below. */
        Asset::updateOrCreate(
            ['kind' => Asset::KIND_TEXT, 'name' => 'Both families'],
            [
                'base_template' => null,
                'field' => 'hosts',
                'body' => json_encode([
                    'ar' => 'تتشرف عائلة {groom} وعائلة {bride} بدعوتكم لحضور حفل زفاف ولديهما',
                    'ku' => 'خێزانی {groom} و خێزانی {bride} بە خۆشحاڵییەوە بانگهێشتتان دەکەن بۆ ئاهەنگی هاوسەرگیری منداڵەکانیان',
                    'en' => 'The families of {groom} and {bride} request the honour of your presence '
                        .'at the wedding of their children on {date} at {venue}',
                ], JSON_UNESCAPED_UNICODE),
                'description' => 'Placeholders are filled in when it is inserted.',
                'is_active' => true,
            ]
        );

        // The per-language rows this replaces. Deleted rather than left
        // inactive: they say exactly what the row above says, and a library
        // offering the same line four times is the problem, not the fix.
        Asset::query()
            ->where('kind', Asset::KIND_TEXT)
            ->whereIn('name', [
                'Both families — Arabic',
                'Both families — Kurdish',
                'Both families — English',
            ])
            ->delete();
    }
}
