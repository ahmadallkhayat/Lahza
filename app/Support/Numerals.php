<?php

namespace App\Support;

final class Numerals
{
    private const ARABIC_INDIC = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    /**
     * Render digits in the system this locale uses.
     *
     * ICU is inconsistent here — it returns Latin digits for `ar` but
     * Arabic-Indic for `ckb` — so dates are always formatted with
     * `@numbers=latn` and converted through here instead. One rule, one place.
     */
    public static function localise(string $text, string $locale): string
    {
        $system = config("templates.locales.{$locale}.numerals", 'latn');

        if ($system !== 'arab') {
            return $text;
        }

        return str_replace(range(0, 9), self::ARABIC_INDIC, $text);
    }
}
