<?php

namespace App\Support;

/**
 * Turns a configured upload ceiling into validation rules and label text.
 *
 * A null limit means "no limit", which needs care in two places: Laravel's
 * `max:` rule has no unlimited form (a bare `max:` is a malformed rule that
 * throws), so the rule has to be omitted entirely rather than emptied.
 */
class UploadLimit
{
    /**
     * The `max:` rule for a config key, or nothing when it is unlimited.
     *
     * Spread into a rule array: `...UploadLimit::rule('templates.max_video_kb')`.
     *
     * @return array<int, string>
     */
    public static function rule(string $key): array
    {
        $kb = config($key);

        return $kb === null ? [] : ['max:'.(int) $kb];
    }

    /**
     * Human phrasing for a form hint: "up to 98MB", or "no size limit".
     *
     * Uses 1000 rather than 1024 because the number is read by a person next
     * to a file manager, which also reports MB in powers of ten.
     */
    public static function describe(string $key): string
    {
        $kb = config($key);

        return $kb === null
            ? 'no size limit'
            : 'up to '.round(((int) $kb) / 1000).'MB';
    }
}
