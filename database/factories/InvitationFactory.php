<?php

namespace Database\Factories;

use App\Models\Invitation;
use App\Models\Template;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'template_id' => Template::factory(),
            'slug' => 'inv-'.Str::lower(Str::random(8)),
            'locale' => 'ar',
            'groom_ar' => 'أَحْمَد',
            'bride_ar' => 'سِدْرَة',
            'groom_latin' => 'Ahmad',
            'bride_latin' => 'Sidra',
            'hosts' => 'تتشرف عائلة السالم وعائلة الحسن بدعوتكم',
            // 19:00 in the event's own zone, which the model stores as UTC.
            'starts_at' => CarbonImmutable::parse('2026-12-04 19:00', 'Asia/Amman'),
            'timezone' => 'Asia/Amman',
            'duration_hours' => 5,
            'venue_name' => 'قاعة الفورسيزونز',
            'venue_city' => 'عمّان، الأردن',
            'map_query' => 'Four Seasons Hotel Amman',
            'phone' => '+962790000000',
            'programme' => [
                ['time' => '٧:٠٠', 'title' => 'استقبال الضيوف'],
                ['time' => '٨:٠٠', 'title' => 'العشاء'],
            ],
            'show_verse' => true,
            'published' => true,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['published' => false]);
    }

    public function locale(string $locale): static
    {
        return $this->state(fn () => ['locale' => $locale]);
    }
}
