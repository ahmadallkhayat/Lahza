<?php

namespace App\Support;

use App\Models\Invitation;
use App\Models\Template;
use Carbon\CarbonImmutable;

/**
 * A believable invitation that belongs to nobody.
 *
 * The landing page shows each design working rather than a still of its clip,
 * and a design only reads as an invitation when it is full of the kind of text
 * that really sits in it — two names of plausible length, a hosting line long
 * enough to wrap, an evening with more than one thing in it.
 *
 * Never saved and never owned. It exists for the length of one render, which
 * is why the id and slug below are set by hand: the design asks for its own
 * URL while rendering, and route() needs a key to build one from.
 *
 * Randomised per request on purpose. A visitor who scrolls past the same six
 * designs twice should not read the same couple twice — and a fixed sample
 * quietly becomes the thing everyone assumes Lahza makes.
 */
final class DemoInvitation
{
    /** @var array<string, array<int, string>> */
    private const GROOMS = [
        'ar' => ['يُوسُف', 'كَرَم', 'زَيْد', 'أَحْمَد', 'عُمَر', 'سَعِيد', 'حَسَن', 'مُراد'],
        'latin' => ['Yusuf', 'Karam', 'Zaid', 'Ahmad', 'Omar', 'Saeed', 'Hassan', 'Murad'],
    ];

    /** @var array<string, array<int, string>> */
    private const BRIDES = [
        'ar' => ['لَيْلَى', 'زَيْنَة', 'مَرْيَم', 'سَارَة', 'نُور', 'رِيم', 'هُدَى', 'دِينَا'],
        'latin' => ['Layla', 'Zeina', 'Maryam', 'Sara', 'Noor', 'Reem', 'Huda', 'Dina'],
    ];

    /** @var array<int, array<string, string>> */
    private const VENUES = [
        ['ar' => 'قاعة بابل', 'ku' => 'هۆڵی بابل', 'en' => 'Babylon Hall'],
        ['ar' => 'قاعة النخيل', 'ku' => 'هۆڵی دارخورما', 'en' => 'Al Nakheel Hall'],
        ['ar' => 'قصر الياسمين', 'ku' => 'کۆشکی یاسەمین', 'en' => 'Jasmine Palace'],
        ['ar' => 'قاعة اللؤلؤة', 'ku' => 'هۆڵی مرواری', 'en' => 'The Pearl Hall'],
    ];

    /** @var array<int, array<string, string>> */
    private const CITIES = [
        ['ar' => 'بغداد، العراق', 'ku' => 'بەغدا، عێراق', 'en' => 'Baghdad, Iraq'],
        ['ar' => 'أربيل، العراق', 'ku' => 'هەولێر، عێراق', 'en' => 'Erbil, Iraq'],
        ['ar' => 'عمّان، الأردن', 'ku' => 'عەممان، ئوردن', 'en' => 'Amman, Jordan'],
    ];

    /** @var array<int, array<string, string>> */
    private const FAMILIES = [
        ['ar' => 'السالم', 'ku' => 'ساڵم', 'en' => 'Al-Salem'],
        ['ar' => 'الحسن', 'ku' => 'حەسەن', 'en' => 'Al-Hassan'],
        ['ar' => 'الرشيد', 'ku' => 'ڕەشید', 'en' => 'Al-Rashid'],
        ['ar' => 'العبيدي', 'ku' => 'عوبەیدی', 'en' => 'Al-Obaidi'],
    ];

    /**
     * Build one, pointed at the given design.
     *
     * @param  array<int, string>|null  $locales
     */
    public static function for(Template $template, ?array $locales = null): Invitation
    {
        $locales = $locales ?: ['ar', 'en'];
        $default = $locales[0];

        $groom = mt_rand(0, count(self::GROOMS['ar']) - 1);
        $bride = mt_rand(0, count(self::BRIDES['ar']) - 1);

        $venue = self::VENUES[array_rand(self::VENUES)];
        $city = self::CITIES[array_rand(self::CITIES)];

        $hostA = self::FAMILIES[array_rand(self::FAMILIES)];
        $hostB = self::FAMILIES[array_rand(self::FAMILIES)];

        // Far enough out that the countdown has real numbers in every unit,
        // which is most of what makes it look alive.
        $startsAt = CarbonImmutable::now(config('templates.default_timezone'))
            ->addDays(mt_rand(40, 260))
            ->setTime(mt_rand(18, 20), [0, 30][mt_rand(0, 1)]);

        $invitation = new Invitation([
            'slug' => 'demo',
            'locales' => $locales,
            'default_locale' => $default,

            'groom_ar' => self::GROOMS['ar'][$groom],
            'bride_ar' => self::BRIDES['ar'][$bride],
            'groom_latin' => self::GROOMS['latin'][$groom],
            'bride_latin' => self::BRIDES['latin'][$bride],

            'hosts' => self::perLocale($locales, fn ($l) => match ($l) {
                'ar' => 'تتشرف عائلة '.$hostA['ar'].' وعائلة '.$hostB['ar'].' بدعوتكم',
                'ku' => 'خێزانی '.$hostA['ku'].' و خێزانی '.$hostB['ku'].' بانگهێشتتان دەکەن',
                default => 'The '.$hostA['en'].' and '.$hostB['en'].' families request the honour of your presence',
            }),

            'venue_name' => self::perLocale($locales, fn ($l) => $venue[$l] ?? $venue['en']),
            'venue_city' => self::perLocale($locales, fn ($l) => $city[$l] ?? $city['en']),
            'map_query' => $venue['en'].', '.$city['en'],

            'starts_at' => $startsAt,
            'timezone' => config('templates.default_timezone'),
            'duration_hours' => 5,

            'programme' => self::perLocale($locales, fn ($l) => self::programme($l)),

            'phone' => '+964 770 000 0000',
            'show_verse' => true,
            'published' => true,
        ]);

        // Never saved. The design builds its own links while rendering and
        // route() will not do that without a key.
        $invitation->id = 0;
        $invitation->setRelation('template', $template);

        return $invitation->renderIn($default);
    }

    /**
     * @param  array<int, string>  $locales
     * @return array<string, mixed>
     */
    private static function perLocale(array $locales, callable $value): array
    {
        $out = [];

        foreach ($locales as $locale) {
            $out[$locale] = $value($locale);
        }

        return $out;
    }

    /** @return array<int, array<string, string>> */
    private static function programme(string $locale): array
    {
        $rows = match ($locale) {
            'ar' => [['٧:٠٠', 'استقبال الضيوف'], ['٨:٠٠', 'الزفة'], ['٩:٣٠', 'العشاء'], ['١١:٠٠', 'السهرة']],
            'ku' => [['٧:٠٠', 'پێشوازی میوانان'], ['٨:٠٠', 'زەفە'], ['٩:٣٠', 'نانی ئێوارە'], ['١١:٠٠', 'ئاهەنگ']],
            default => [['7:00', 'Guests arrive'], ['8:00', 'The entrance'], ['9:30', 'Dinner'], ['11:00', 'Dancing']],
        };

        return array_map(fn ($row) => ['time' => $row[0], 'title' => $row[1]], $rows);
    }
}
