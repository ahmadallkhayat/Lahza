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
     * The Arabic is the same text the invitation pages open with, kept here so
     * it can also be dropped into a field by hand.
     */
    private function verses(): void
    {
        $verses = [
            [
                'name' => 'Ar-Rum 30:21',
                'locale' => 'ar',
                'body' => 'وَمِنْ آيَاتِهِ أَنْ خَلَقَ لَكُم مِّنْ أَنفُسِكُمْ أَزْوَاجًا لِّتَسْكُنُوا إِلَيْهَا وَجَعَلَ بَيْنَكُم مَّوَدَّةً وَرَحْمَةً',
                'description' => 'The verse the invitation pages open with.',
            ],
            [
                'name' => 'Ar-Rum 30:21 — English',
                'locale' => 'en',
                'body' => '“And among His signs is that He created for you mates from among yourselves, '
                    .'that you may find tranquility in them; and He has placed between you affection and mercy.”',
                'description' => 'Translation, for the English link.',
            ],
        ];

        foreach ($verses as $verse) {
            Asset::updateOrCreate(
                ['kind' => Asset::KIND_TEXT, 'name' => $verse['name']],
                [
                    'base_template' => null,
                    'field' => 'verse',
                    'locale' => $verse['locale'],
                    'body' => $verse['body'],
                    'description' => $verse['description'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Hosting lines with placeholders, one per language.
     *
     * These are the case the placeholders exist for: the same sentence every
     * time bar the names, so it is written once and filled in per invitation.
     */
    private function hostingLines(): void
    {
        $lines = [
            [
                'name' => 'Both families — Arabic',
                'locale' => 'ar',
                'body' => 'تتشرف عائلة {groom} وعائلة {bride} بدعوتكم لحضور حفل زفاف ولديهما',
            ],
            [
                'name' => 'Both families — Kurdish',
                'locale' => 'ku',
                'body' => 'خێزانی {groom} و خێزانی {bride} بە خۆشحاڵییەوە بانگهێشتتان دەکەن بۆ ئاهەنگی هاوسەرگیری منداڵەکانیان',
            ],
            [
                'name' => 'Both families — English',
                'locale' => 'en',
                'body' => 'The families of {groom} and {bride} request the honour of your presence '
                    .'at the wedding of their children on {date} at {venue}',
            ],
        ];

        foreach ($lines as $line) {
            Asset::updateOrCreate(
                ['kind' => Asset::KIND_TEXT, 'name' => $line['name']],
                [
                    'base_template' => null,
                    'field' => 'hosts',
                    'locale' => $line['locale'],
                    'body' => $line['body'],
                    'description' => 'Placeholders are filled in when it is inserted.',
                    'is_active' => true,
                ]
            );
        }
    }
}
