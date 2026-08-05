<?php

namespace Tests\Unit;

use App\Support\Color;
use PHPUnit\Framework\TestCase;

class ColorTest extends TestCase
{
    public function test_hex_round_trip(): void
    {
        $this->assertSame([168, 135, 79], Color::fromHex('#a8874f'));
        $this->assertSame('#a8874f', Color::toHex([168, 135, 79]));
        $this->assertSame([255, 255, 255], Color::fromHex('#fff'));
        $this->assertNull(Color::fromHex('not-a-colour'));
        $this->assertNull(Color::fromHex(null));
    }

    public function test_contrast_matches_known_wcag_values(): void
    {
        $this->assertSame(21.0, round(Color::contrast([0, 0, 0], [255, 255, 255]), 2));
        $this->assertSame(1.0, round(Color::contrast([120, 120, 120], [120, 120, 120]), 2));
    }

    /**
     * Chroma, not HSL saturation, is what finds an accent. Saturation rates a
     * near-black navy about as colourful as gold, which lets a dark background
     * beat the actual accent.
     */
    public function test_chroma_does_not_inflate_for_near_black(): void
    {
        $navy = [11, 25, 41];
        $gold = [210, 174, 54];

        $this->assertLessThan(Color::chroma($gold), Color::chroma($navy));
        $this->assertLessThan(0.2, Color::chroma($navy));
    }

    public function test_hsl_round_trip_preserves_the_colour(): void
    {
        foreach ([[168, 135, 79], [11, 25, 41], [247, 241, 232], [0, 0, 0], [255, 255, 255]] as $rgb) {
            [$h, $s, $l] = Color::toHsl($rgb);
            $back = Color::fromHsl($h, $s, $l);

            foreach ([0, 1, 2] as $i) {
                $this->assertLessThanOrEqual(1, abs($rgb[$i] - $back[$i]),
                    'HSL round trip drifted on '.Color::toHex($rgb));
            }
        }
    }

    /**
     * withLightness() hits a requested lightness exactly, keeping hue and
     * saturation. lighten() cannot: it is a relative nudge with no target, so
     * reaching a page-light tint means iterating, and the iteration overshoots
     * toward white. That overshoot is what made an extracted "warm ivory" come
     * out as flat grey.
     */
    public function test_with_lightness_hits_its_target_where_lighten_overshoots(): void
    {
        $champagne = [197, 171, 133];
        [$hue, $saturation] = Color::toHsl($champagne);

        $viaHsl = Color::withLightness($champagne, 0.93);
        [$h2, $s2, $l2] = Color::toHsl($viaHsl);

        // Lands exactly where asked, hue and saturation intact.
        $this->assertEqualsWithDelta(0.93, $l2, 0.01, 'withLightness missed its target lightness');
        $this->assertEqualsWithDelta($hue, $h2, 4.0, 'hue drifted');
        $this->assertEqualsWithDelta($saturation, $s2, 0.05, 'saturation drifted');

        // Nudging toward white sails past that lightness instead.
        $viaLighten = $champagne;
        for ($i = 0; $i < 12; $i++) {
            $viaLighten = Color::lighten($viaLighten, 0.2);
        }

        $this->assertGreaterThan(0.96, Color::toHsl($viaLighten)[2], 'lighten() should overshoot toward white');

        // And with the channels crowded up near 255 there is visibly less
        // colour left than at the lightness we actually wanted.
        $this->assertLessThan(Color::chroma($viaHsl), Color::chroma($viaLighten));
    }

    public function test_ensure_contrast_reaches_the_target_in_both_directions(): void
    {
        $paper = [247, 241, 232];
        $curtain = [26, 15, 13];
        $midGrey = [128, 128, 128];

        // Against a light background it darkens.
        $onPaper = Color::ensureContrast($midGrey, $paper, 4.5);
        $this->assertGreaterThanOrEqual(4.5, Color::contrast($onPaper, $paper));

        // Against a dark background it lightens.
        $onCurtain = Color::ensureContrast($midGrey, $curtain, 4.5);
        $this->assertGreaterThanOrEqual(4.5, Color::contrast($onCurtain, $curtain));
    }

    public function test_ensure_contrast_is_a_no_op_when_already_readable(): void
    {
        $this->assertSame([0, 0, 0], Color::ensureContrast([0, 0, 0], [255, 255, 255], 7.0));
    }

    public function test_distance_separates_and_matches(): void
    {
        $this->assertSame(0.0, Color::distance([10, 20, 30], [10, 20, 30]));
        $this->assertGreaterThan(60, Color::distance([26, 15, 13], [197, 171, 133]));
    }
}
