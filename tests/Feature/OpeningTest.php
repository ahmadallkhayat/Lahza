<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a guest meets before they tap: the clip's own first frame, held
 * blurred, with the envelope drawn over it.
 *
 * The animation itself is CSS and cannot be asserted here. What can — and
 * what actually breaks — is the wiring: that the design's choice reaches the
 * markup as a real string rather than a number cast to 0, that its lengths
 * reach the stylesheet as custom properties, and that choosing the plain veil
 * leaves none of the envelope behind.
 */
class OpeningTest extends TestCase
{
    use RefreshDatabase;

    private function page(array $settings = []): string
    {
        $invitation = Invitation::factory()
            ->for(Template::factory()->create([
                'base_template' => 'luxury-wedding-v1',
                'settings' => $settings,
            ]))
            ->create(['published' => true]);

        return $this->get("/i/{$invitation->slug}")->assertOk()->getContent();
    }

    public function test_the_envelope_is_drawn_over_the_held_frame_by_default(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('data-opening="envelope"', $html);
        $this->assertStringContainsString('env-flap', $html);
        $this->assertStringContainsString('env-pocket', $html);
    }

    /** The lengths the studio set are what the stylesheet animates on. */
    public function test_the_openings_lengths_reach_the_stylesheet(): void
    {
        $html = $this->page([
            'openingMs' => 2400,
            'veilBlurPx' => 40,
            'veilDim' => 0.3,
        ]);

        $this->assertStringContainsString('--open: 2400ms', $html);
        $this->assertStringContainsString('--veil-blur: 40px', $html);
        $this->assertStringContainsString('--veil-dim: 0.3', $html);
    }

    /**
     * A choice is a name, not a number.
     *
     * Every other setting is numeric, and the cast that guarantees that would
     * turn "envelope" into 0 — which reaches the markup as data-opening="0",
     * matches no stylesheet rule, and leaves a guest looking at a bare screen.
     */
    public function test_the_choice_survives_as_a_string(): void
    {
        $template = Template::factory()->create([
            'base_template' => 'luxury-wedding-v1',
            'settings' => ['opening' => 'veil'],
        ]);

        $this->assertSame('veil', $template->settings()['opening']);
    }

    public function test_choosing_the_veil_draws_no_envelope(): void
    {
        $html = $this->page(['opening' => 'veil']);

        $this->assertStringContainsString('data-opening="veil"', $html);
        $this->assertStringNotContainsString('env-flap', $html);
        $this->assertStringNotContainsString('env-pocket', $html);
    }

    /**
     * An option the design does not offer never reaches the page.
     *
     * The stylesheet has a rule per declared opening and none for anything
     * else, so a stale or hand-written value has to fall back to one that
     * exists rather than render an opening with no styling at all.
     */
    public function test_an_unknown_opening_falls_back_to_the_default(): void
    {
        $template = Template::factory()->create([
            'base_template' => 'luxury-wedding-v1',
            'settings' => ['opening' => 'trapdoor'],
        ]);

        $this->assertSame('envelope', $template->settings()['opening']);
    }

    public function test_the_studio_can_choose_the_opening(): void
    {
        $template = Template::factory()->create(['base_template' => 'luxury-wedding-v1']);

        $this->actingAs(User::factory()->create())
            ->put("/admin/templates/{$template->id}", [
                'name' => 'A Theme',
                'base_template' => 'luxury-wedding-v1',
                'is_active' => '1',
                'settings' => ['opening' => 'veil', 'openingMs' => '2000'],
            ])
            ->assertSessionHasNoErrors();

        $template->refresh();

        $this->assertSame('veil', $template->settings()['opening']);
        $this->assertSame(2000, $template->settings()['openingMs']);
    }

    /** And cannot invent one the design never offered. */
    public function test_an_opening_outside_the_declared_set_is_rejected(): void
    {
        $template = Template::factory()->create(['base_template' => 'luxury-wedding-v1']);

        $this->actingAs(User::factory()->create())
            ->put("/admin/templates/{$template->id}", [
                'name' => 'A Theme',
                'base_template' => 'luxury-wedding-v1',
                'is_active' => '1',
                'settings' => ['opening' => 'trapdoor'],
            ])
            ->assertSessionHasErrors('settings.opening');
    }

    /** The opening is offered as a menu, not a number box. */
    public function test_the_form_offers_the_opening_as_a_choice(): void
    {
        $template = Template::factory()->create(['base_template' => 'luxury-wedding-v1']);

        $html = $this->actingAs(User::factory()->create())
            ->get("/admin/templates/{$template->id}/edit")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('settings[opening]', $html);
        $this->assertStringContainsString('value="envelope"', $html);
        $this->assertStringContainsString('value="veil"', $html);
    }
}
