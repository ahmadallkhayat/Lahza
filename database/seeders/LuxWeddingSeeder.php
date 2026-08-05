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
class LuxWeddingSeeder extends Seeder
{
    public function run(): void
    {
        $template = Template::updateOrCreate(
            ['slug' => 'lux-wedding'],
            [
                'name' => 'Lux Wedding',
                'category' => 'lux-wedding',
                'view' => 'lux-wedding',
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
            'intro_video' => ['Intro_video.mp4', 'templates/videos/lux-wedding-intro.mp4'],
            'music' => ['music.mp3', 'templates/music/lux-wedding.mp3'],
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
                'locale' => 'ar',
                'groom_ar' => 'أَحْمَد',
                'bride_ar' => 'سِدْرَة',
                'groom_latin' => 'Ahmad',
                'bride_latin' => 'Sidra',
                'hosts' => 'تتشرف عائلة السالم وعائلة الحسن بدعوتكم لحضور حفل زفاف ولديهما',
                // Built in the event's own zone. `now()` is UTC here, so
                // setting 19:00 on that would land at 22:00 in Amman.
                'starts_at' => CarbonImmutable::now('Asia/Amman')->addMonths(4)->setTime(19, 0),
                'timezone' => 'Asia/Amman',
                'duration_hours' => 5,
                'venue_name' => 'قاعة الفورسيزونز',
                'venue_city' => 'عمّان، الأردن',
                'map_query' => 'Four Seasons Hotel Amman',
                'phone' => '+962790000000',
                'programme' => [
                    ['time' => '٧:٠٠', 'title' => 'استقبال الضيوف'],
                    ['time' => '٨:٠٠', 'title' => 'الزفة'],
                    ['time' => '٩:٠٠', 'title' => 'العشاء'],
                    ['time' => '١٠:٣٠', 'title' => 'قطع الكيكة'],
                ],
                'show_verse' => true,
                'published' => true,
            ]
        );
    }
}
