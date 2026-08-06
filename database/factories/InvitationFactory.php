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
            'locales' => ['ar'],
            'default_locale' => 'ar',
            'groom_ar' => 'يُوسُف',
            'bride_ar' => 'لَيْلَى',
            'groom_latin' => 'Yusuf',
            'bride_latin' => 'Layla',
            // 19:00 in the event's own zone, which the model stores as UTC.
            'starts_at' => CarbonImmutable::parse('2026-12-04 19:00', 'Asia/Baghdad'),
            'timezone' => 'Asia/Baghdad',
            'duration_hours' => 5,
            'hosts' => ['ar' => 'تتشرف عائلة السالم وعائلة الحسن بدعوتكم'],
            'venue_name' => ['ar' => 'قاعة بابل'],
            'venue_city' => ['ar' => 'بغداد، العراق'],
            'programme' => [
                'ar' => [
                    ['time' => '٧:٠٠', 'title' => 'استقبال الضيوف'],
                    ['time' => '٨:٠٠', 'title' => 'العشاء'],
                ],
            ],
            'map_query' => 'Baghdad',
            'phone' => '+9647700000000',
            'show_verse' => true,
            'published' => true,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['published' => false]);
    }

    /** Single language, with the translated content moved under that code. */
    public function locale(string $locale): static
    {
        return $this->state(fn (array $attributes) => [
            'locales' => [$locale],
            'default_locale' => $locale,
            'hosts' => [$locale => 'Hosting line'],
            'venue_name' => [$locale => 'Babylon Hall'],
            'venue_city' => [$locale => 'Baghdad'],
            'programme' => [$locale => [['time' => '7:00', 'title' => 'Guests received']]],
        ]);
    }

    /**
     * Several languages, each with its own content, so per-language rendering
     * and the switcher have something real to work with.
     *
     * @param  array<int, string>  $locales
     */
    public function locales(array $locales, ?string $default = null): static
    {
        $text = [
            'ar' => ['host' => 'عائلة السالم', 'venue' => 'قاعة بابل', 'city' => 'بغداد'],
            'ku' => ['host' => 'خێزانی ساڵم', 'venue' => 'هۆڵی بابل', 'city' => 'بەغدا'],
            'en' => ['host' => 'The Al-Salem family', 'venue' => 'Babylon Hall', 'city' => 'Baghdad'],
        ];

        return $this->state(fn () => [
            'locales' => $locales,
            'default_locale' => $default ?? $locales[0],
            'hosts' => collect($locales)->mapWithKeys(fn ($l) => [$l => $text[$l]['host']])->all(),
            'venue_name' => collect($locales)->mapWithKeys(fn ($l) => [$l => $text[$l]['venue']])->all(),
            'venue_city' => collect($locales)->mapWithKeys(fn ($l) => [$l => $text[$l]['city']])->all(),
            'programme' => collect($locales)
                ->mapWithKeys(fn ($l) => [$l => [['time' => '7:00', 'title' => $text[$l]['venue']]]])
                ->all(),
        ]);
    }
}
