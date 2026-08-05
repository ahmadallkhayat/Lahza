<?php

namespace Database\Seeders;

use App\Models\Invitation;
use App\Models\Template;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Brings the archived lux wedding page into the system as a real record.
 *
 * Its media is copied out of archive/lux-wedding/ onto the public disk. The
 * colours are the ones sampled from the intro video: it opens on dark
 * chocolate and ends flooded with champagne light, which is why the reveal
 * lands on warm ivory rather than cutting to black.
 */
class LuxuryWeddingV1Seeder extends Seeder
{
    public function run(): void
    {
        $template = Template::updateOrCreate(
            ['slug' => 'luxury-wedding-v1'],
            [
                'name' => 'Luxury Wedding V1',
                'base_template' => 'luxury-wedding-v1',
                'description' => 'Ring box opening onto champagne light. Ivory and gold, Arabic-first.',
                'palette' => [
                    'paper' => '#f7f1e8',
                    'paper_deep' => '#ede1d0',
                    'ink' => '#2e1d16',
                    'ink_soft' => '#6b5648',
                    'gold' => '#a8874f',
                    'gold_deep' => '#856a3c',
                    'curtain' => '#1a0f0d',
                ],
                'settings' => [
                    'keepVideoAudioMs' => 0,
                    'musicFadeMs' => 1600,
                    // The clip runs 10.000s and plateaus at peak brightness
                    // from ~9.25s, so the dissolve starts at 9.2s while it is
                    // still moving and ends on its brightest frame.
                    'fadeOutLeadMs' => 800,
                    'crossfadeMs' => 1400,
                    'duckTo' => 0.42,
                    'fogHz' => 700,
                    'duckMs' => 2600,
                    'revealFallbackMs' => 13000,
                ],
                'is_active' => true,
            ]
        );

        $this->attachMedia($template);
        $this->sampleInvitation($template);
    }

    private function attachMedia(Template $template): void
    {
        $archive = base_path('archive/lux-wedding');

        $media = [
            'intro_video' => ['Intro_video.mp4', 'templates/videos/luxury-wedding-v1-intro.mp4'],
            'music' => ['music.mp3', 'templates/music/luxury-wedding-v1.mp3'],
        ];

        foreach ($media as $column => [$source, $target]) {
            $from = $archive.DIRECTORY_SEPARATOR.$source;

            if (! File::exists($from)) {
                $this->command?->warn("Missing {$from} — skipping {$column}.");

                continue;
            }

            if (! Storage::disk('public')->exists($target)) {
                Storage::disk('public')->put($target, File::get($from));
            }

            $template->update([$column => Storage::url($target)]);
        }
    }

    private function sampleInvitation(Template $template): void
    {
        Invitation::updateOrCreate(
            ['slug' => 'ahmad-sidra'],
            [
                'template_id' => $template->id,

                // Issued in all three, so every language link is there to look
                // at straight after seeding.
                'locales' => ['ar', 'ku', 'en'],
                'default_locale' => 'ar',

                'groom_ar' => 'أَحْمَد',
                'bride_ar' => 'سِدْرَة',
                'groom_latin' => 'Ahmad',
                'bride_latin' => 'Sidra',

                // Built in the event's own zone. `now()` is UTC here, so
                // setting 19:00 on that would land at 22:00 in Baghdad.
                'starts_at' => CarbonImmutable::now('Asia/Baghdad')->addMonths(4)->setTime(19, 0),
                'timezone' => 'Asia/Baghdad',
                'duration_hours' => 5,

                'hosts' => [
                    'ar' => 'تتشرف عائلة السالم وعائلة الحسن بدعوتكم لحضور حفل زفاف ولديهما',
                    'ku' => 'خێزانی ساڵم و خێزانی حەسەن بە خۆشحاڵییەوە بانگهێشتتان دەکەن بۆ ئاهەنگی هاوسەرگیری منداڵەکانیان',
                    'en' => 'The Al-Salem and Al-Hassan families request the honour of your presence at the wedding of their children',
                ],
                'venue_name' => [
                    'ar' => 'قاعة بابل',
                    'ku' => 'هۆڵی بابل',
                    'en' => 'Babylon Hall',
                ],
                'venue_city' => [
                    'ar' => 'بغداد، العراق',
                    'ku' => 'بەغدا، عێراق',
                    'en' => 'Baghdad, Iraq',
                ],
                'programme' => [
                    'ar' => [
                        ['time' => '٧:٠٠', 'title' => 'استقبال الضيوف'],
                        ['time' => '٨:٠٠', 'title' => 'الزفة'],
                        ['time' => '٩:٠٠', 'title' => 'العشاء'],
                        ['time' => '١٠:٣٠', 'title' => 'قطع الكيكة'],
                    ],
                    'ku' => [
                        ['time' => '٧:٠٠', 'title' => 'پێشوازی لە میوانان'],
                        ['time' => '٨:٠٠', 'title' => 'زەفە'],
                        ['time' => '٩:٠٠', 'title' => 'نانی ئێوارە'],
                        ['time' => '١٠:٣٠', 'title' => 'بڕینی کێک'],
                    ],
                    'en' => [
                        ['time' => '7:00', 'title' => 'Guests received'],
                        ['time' => '8:00', 'title' => 'Wedding procession'],
                        ['time' => '9:00', 'title' => 'Dinner served'],
                        ['time' => '10:30', 'title' => 'Cutting of the cake'],
                    ],
                ],

                'map_query' => 'Baghdad',
                'phone' => '+9647700000000',
                'show_verse' => true,
                'published' => true,
            ]
        );
    }
}
