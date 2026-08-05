<?php

namespace App\Support;

/**
 * sRGB colour maths for palette extraction.
 *
 * Colours travel as [r, g, b] arrays of 0-255 integers.
 */
final class Color
{
    /** @param array{0:int,1:int,2:int} $rgb */
    public static function toHex(array $rgb): string
    {
        return sprintf('#%02x%02x%02x', ...array_map(
            fn ($c) => max(0, min(255, (int) round($c))),
            $rgb
        ));
    }

    /** @return array{0:int,1:int,2:int}|null */
    public static function fromHex(?string $hex): ?array
    {
        if (blank($hex)) {
            return null;
        }

        $hex = ltrim(trim($hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return null;
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * WCAG relative luminance.
     *
     * @param  array{0:int,1:int,2:int}  $rgb
     */
    public static function luminance(array $rgb): float
    {
        $channels = array_map(function ($c) {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * WCAG contrast ratio, 1.0 to 21.0.
     *
     * @param  array{0:int,1:int,2:int}  $a
     * @param  array{0:int,1:int,2:int}  $b
     */
    public static function contrast(array $a, array $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * Colourfulness, 0.0 to 1.0 — how far the colour sits from grey.
     *
     * Unlike HSL saturation this does not inflate for very dark or very light
     * colours, which makes it the right signal when hunting for an accent: a
     * near-black navy scores ~0.12 here but ~0.58 on saturation, and using
     * saturation makes a dark background beat the actual accent.
     *
     * @param  array{0:int,1:int,2:int}  $rgb
     */
    public static function chroma(array $rgb): float
    {
        return (max($rgb) - min($rgb)) / 255;
    }

    /**
     * HSL lightness, 0.0 to 1.0.
     *
     * @param  array{0:int,1:int,2:int}  $rgb
     */
    public static function lightness(array $rgb): float
    {
        return (max($rgb) + min($rgb)) / 2 / 255;
    }

    /**
     * Euclidean distance, 0.0 to ~441.7.
     *
     * @param  array{0:int,1:int,2:int}  $a
     * @param  array{0:int,1:int,2:int}  $b
     */
    public static function distance(array $a, array $b): float
    {
        return sqrt(
            ($a[0] - $b[0]) ** 2
            + ($a[1] - $b[1]) ** 2
            + ($a[2] - $b[2]) ** 2
        );
    }

    /**
     * @param  array{0:int,1:int,2:int}  $rgb
     * @return array{0:int,1:int,2:int}
     */
    public static function darken(array $rgb, float $amount): array
    {
        return array_map(fn ($c) => (int) round($c * (1 - $amount)), $rgb);
    }

    /**
     * @param  array{0:int,1:int,2:int}  $rgb
     * @return array{0:int,1:int,2:int}
     */
    public static function lighten(array $rgb, float $amount): array
    {
        return array_map(fn ($c) => (int) round($c + (255 - $c) * $amount), $rgb);
    }

    /**
     * Push $rgb until it reads clearly against $against, keeping its hue.
     *
     * Moves away from $against — darker when $against is light, lighter when
     * it is dark. Falls back to black or white when the hue simply cannot get
     * there.
     *
     * @param  array{0:int,1:int,2:int}  $rgb
     * @param  array{0:int,1:int,2:int}  $against
     * @return array{0:int,1:int,2:int}
     */
    public static function ensureContrast(array $rgb, array $against, float $target): array
    {
        if (self::contrast($rgb, $against) >= $target) {
            return $rgb;
        }

        $goLighter = self::luminance($against) < 0.18;
        $candidate = $rgb;

        for ($i = 0; $i < 30; $i++) {
            $candidate = $goLighter
                ? self::lighten($candidate, 0.06)
                : self::darken($candidate, 0.06);

            if (self::contrast($candidate, $against) >= $target) {
                return $candidate;
            }
        }

        return $goLighter ? [255, 255, 255] : [0, 0, 0];
    }

    /**
     * @param  array{0:int,1:int,2:int}  $rgb
     * @return array{0:float,1:float,2:float} [hue 0-360, saturation 0-1, lightness 0-1]
     */
    public static function toHsl(array $rgb): array
    {
        // (float) matters: PHP's `/` returns an int when it divides evenly, so
        // 0/255 is int 0 and a strict `=== 0.0` grey check never matched —
        // which sent pure black, white and any flat grey into a division by
        // zero below.
        [$r, $g, $b] = array_map(fn ($c) => (float) $c / 255, $rgb);

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $delta = $max - $min;
        $l = ($max + $min) / 2;

        if ($delta <= 0.0) {
            return [0.0, 0.0, $l];
        }

        $s = $l > 0.5 ? $delta / (2 - $max - $min) : $delta / ($max + $min);

        $h = match (true) {
            $max === $r => fmod(($g - $b) / $delta + ($g < $b ? 6 : 0), 6),
            $max === $g => ($b - $r) / $delta + 2,
            default => ($r - $g) / $delta + 4,
        };

        return [$h * 60, $s, $l];
    }

    /** @return array{0:int,1:int,2:int} */
    public static function fromHsl(float $h, float $s, float $l): array
    {
        $h = fmod(fmod($h, 360) + 360, 360) / 360;
        $s = max(0.0, min(1.0, $s));
        $l = max(0.0, min(1.0, $l));

        if ($s <= 0.0) {
            $v = (int) round($l * 255);

            return [$v, $v, $v];
        }

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;

        $channel = function (float $t) use ($p, $q): int {
            $t = fmod(fmod($t, 1) + 1, 1);

            $value = match (true) {
                $t < 1 / 6 => $p + ($q - $p) * 6 * $t,
                $t < 1 / 2 => $q,
                $t < 2 / 3 => $p + ($q - $p) * (2 / 3 - $t) * 6,
                default => $p,
            };

            return (int) round($value * 255);
        };

        return [$channel($h + 1 / 3), $channel($h), $channel($h - 1 / 3)];
    }

    /**
     * Rotate the hue, keeping saturation and lightness.
     *
     * Used to offer a complementary accent alongside the one actually in the
     * footage: still anchored to the clip's own colour, just the opposite side
     * of the wheel, which is a harmony rather than an unrelated choice.
     *
     * @param  array{0:int,1:int,2:int}  $rgb
     * @return array{0:int,1:int,2:int}
     */
    public static function rotateHue(array $rgb, float $degrees): array
    {
        [$h, $s, $l] = self::toHsl($rgb);

        return self::fromHsl($h + $degrees, $s, $l);
    }

    /**
     * Move a colour to a given lightness, keeping its hue and saturation.
     *
     * This is what `lighten()` cannot do. lighten() is a relative nudge with no
     * target, so reaching a page-light tint means iterating — and the iteration
     * sails past the lightness you wanted, crowding every channel up near 255
     * where little visible colour is left. That overshoot is what turned an
     * extracted "warm ivory" into flat grey.
     *
     * @param  array{0:int,1:int,2:int}  $rgb
     * @return array{0:int,1:int,2:int}
     */
    public static function withLightness(array $rgb, float $lightness): array
    {
        [$h, $s] = self::toHsl($rgb);

        return self::fromHsl($h, $s, $lightness);
    }
}
