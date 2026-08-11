<?php

namespace Tests\Feature;

use App\Models\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What someone who has asked not to be moved actually gets.
 *
 * The design carried the usual blanket — every transition and animation on
 * the page forced to .01ms with !important. On a page that is almost entirely
 * a timed reveal that does not make it calmer, it makes it a jump cut: the
 * envelope snapped open, the names appeared already placed, and the whole
 * sequence arrived at once. It reached far more than anyone intended, too,
 * because a `*` rule with !important also overrode the careful reduced-motion
 * handling written for the envelope higher up the same stylesheet.
 *
 * It was only ever visible to someone whose machine asks for it — Windows has
 * the switch under Accessibility, and DevTools device emulation does not
 * change it — which is exactly the kind of thing nothing notices until a
 * person says the animation is not playing on their computer.
 */
class ReducedMotionTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        $invitation = Invitation::factory()->create(['published' => true]);

        return $this->get("/i/{$invitation->slug}")->assertOk()->getContent();
    }

    /** The blanket is gone, and must not come back. */
    public function test_transitions_are_not_globally_flattened(): void
    {
        $html = $this->page();

        $this->assertStringNotContainsString('transition-duration: .01ms', $html);
        $this->assertStringNotContainsString('animation-duration: .01ms', $html);
    }

    /**
     * The preference is still honoured — this is not a licence to ignore it.
     *
     * Movement goes: the content stops travelling, the names stop being
     * lifted onto the clip, and everything that loops for as long as the page
     * is open stops looping.
     */
    public function test_movement_is_still_refused(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $html);

        // The travel, and the loops.
        $this->assertMatchesRegularExpression(
            '/prefers-reduced-motion.*main\s*>\s*\*/s',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/prefers-reduced-motion.*\.petal/s',
            $html,
        );
    }

    /**
     * And the fades stay, which is what lets the sequence still read as one.
     *
     * These are the transitions the reveal is built on. If a future blanket
     * flattens them the page stops being an invitation that opens and becomes
     * one that is simply already open.
     */
    public function test_the_reveal_still_has_its_fades(): void
    {
        $html = $this->page();

        $this->assertMatchesRegularExpression('/main\s*>\s*\*\s*\{[^}]*transition:\s*opacity/s', $html);
        $this->assertStringContainsString('transition: opacity', $html);
    }

    /**
     * The envelope's own reduced-motion handling can now actually run.
     *
     * It dissolves rather than folding — a full-screen 3D rotation is the
     * kind of thing the preference is for — but a dissolve is still a
     * transition, and under the blanket it was being flattened to nothing
     * along with everything else.
     */
    public function test_the_envelope_dissolves_rather_than_snapping(): void
    {
        $html = $this->page();

        $this->assertMatchesRegularExpression(
            '/prefers-reduced-motion.*\.env-flap.*transition:\s*opacity/s',
            $html,
        );
    }
}
