<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * What the public site says, and who to contact about it.
 *
 * Every value has a default here, so the landing page is a finished page from
 * the first deploy rather than a set of empty boxes waiting for someone to
 * fill them in. Saving a setting overrides its default; clearing one puts the
 * default back, which is why nothing here is ever stored as an empty string.
 *
 * Read once per request and held. The landing page asks for a dozen of these
 * while it renders and none of them can change mid-request.
 */
final class SiteSettings
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * Everything the site can be told about itself, with what it says when
     * nobody has told it anything.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'brand_name' => 'Lahza',
            'tagline' => 'Digital invitations, built one moment at a time.',

            'hero_heading' => 'Invitations worth opening',
            'hero_body' => 'Every invitation is built around your own film — its colours, '
                .'its music, its opening. Share one link and your guests hold the evening '
                .'in their hand.',
            'hero_cta' => 'Start yours',

            'offer_heading' => 'What we make',
            'offers' => [
                [
                    'title' => 'A film, not a card',
                    'body' => 'Your clip opens the invitation and stays as the backdrop behind it.',
                ],
                [
                    'title' => 'Arabic, Kurdish and English',
                    'body' => 'One invitation, a link per language, each set properly in its own script.',
                ],
                [
                    'title' => 'Everything the evening needs',
                    'body' => 'The programme, the venue on a map, a countdown, and a calendar entry in one tap.',
                ],
            ],

            'designs_heading' => 'Designs',
            'designs_body' => 'Each one is built around your own colours and film.',

            // Which themes the landing page shows. Empty means every active
            // one, which is the sensible thing for a studio with a handful —
            // and the wrong thing later, hence the setting.
            'featured_templates' => [],
            'featured_limit' => 6,

            'contact_heading' => 'Talk to us',
            'contact_body' => 'Tell us the date and we will take it from there.',
            'contact_phone' => '',
            'contact_whatsapp' => '',
            'contact_email' => '',
            'contact_instagram' => '',
            'contact_address' => '',

            'footer_note' => '',
        ];
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $defaults = self::defaults();

        return self::$cache = array_merge($defaults, self::stored($defaults));
    }

    public static function get(string $key, mixed $fallback = null): mixed
    {
        return self::all()[$key] ?? $fallback;
    }

    /** A single line of text, trimmed. */
    public static function text(string $key): string
    {
        $value = self::get($key);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** @return array<int, mixed> */
    public static function list(string $key): array
    {
        $value = self::get($key);

        return is_array($value) ? array_values($value) : [];
    }

    /**
     * Write a batch, dropping anything the defaults do not know about and
     * anything left empty — an empty value is how a field is handed back to
     * its default rather than a value in its own right.
     *
     * @param  array<string, mixed>  $values
     */
    public static function put(array $values): void
    {
        $known = array_intersect_key($values, self::defaults());

        foreach ($known as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                Setting::whereKey($key)->delete();

                continue;
            }

            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        self::flush();
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    /**
     * What is actually in the table, narrowed to keys that still mean
     * something.
     *
     * Survives the table not being there. Console commands run against a
     * fresh checkout before `migrate` — `route:cache` and friends boot the
     * app, and a view composer asking for settings would take them down with
     * a missing-table error before the migration that creates it can run.
     *
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private static function stored(array $defaults): array
    {
        try {
            return Setting::query()
                ->whereIn('key', array_keys($defaults))
                ->pluck('value', 'key')
                ->filter(fn ($value) => $value !== null && $value !== '' && $value !== [])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /** Whether the store is reachable at all — for the admin to warn on. */
    public static function available(): bool
    {
        try {
            DB::table('settings')->limit(1)->exists();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
