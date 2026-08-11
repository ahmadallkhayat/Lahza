<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\Template;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A design showing itself off, with nobody's wedding in it.
 *
 * The landing page frames this in a phone and "See it in action" opens it
 * whole, so it has to be the real base template — the moment it becomes a
 * mock-up it starts advertising something Lahza does not make.
 */
class DemoTest extends TestCase
{
    use RefreshDatabase;

    private function template(array $overrides = []): Template
    {
        return Template::factory()->create(array_merge([
            'base_template' => 'luxury-wedding-v1',
            'is_active' => true,
            'intro_video' => '/storage/x.mp4',
        ], $overrides));
    }

    public function test_it_is_public(): void
    {
        $this->get(route('templates.demo', $this->template()))->assertOk();
    }

    /** The real design, not a mock-up of one. */
    public function test_it_renders_the_actual_base_template(): void
    {
        $this->get(route('templates.demo', $this->template()))
            ->assertOk()
            ->assertSee('id="curtain"', false)
            ->assertSee('id="backdrop"', false)
            ->assertSee('armSequence', false)
            ->assertSee('const TARGET', false);
    }

    public function test_it_is_filled_in(): void
    {
        $html = $this->get(route('templates.demo', $this->template()))->assertOk()->getContent();

        // A countdown with a real date behind it, a programme with more than
        // one row, and a venue — the things that make a design read as an
        // invitation rather than as a layout.
        $this->assertStringContainsString('id="countdown"', $html);
        $this->assertMatchesRegularExpression('/const TARGET = new Date\(\'20\d\d/', $html);
    }

    /** Nothing is written, however many times it is looked at. */
    public function test_it_persists_nothing(): void
    {
        $template = $this->template();

        $this->get(route('templates.demo', $template))->assertOk();
        $this->get(route('templates.demo', $template))->assertOk();

        $this->assertDatabaseCount('invitations', 0);
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * Different names each visit.
     *
     * A fixed sample quietly becomes the thing everyone assumes Lahza makes,
     * and a visitor who scrolls past the same six designs twice should not
     * read the same couple twice. Ten tries makes a false failure from an
     * unlucky repeat vanishingly unlikely.
     */
    public function test_the_details_are_randomised(): void
    {
        $template = $this->template();
        $seen = [];

        for ($i = 0; $i < 10; $i++) {
            $html = $this->get(route('templates.demo', $template))->getContent();
            preg_match('/<title>(.*?)<\/title>/s', $html, $m);
            $seen[] = $m[1] ?? '';
        }

        $this->assertGreaterThan(1, count(array_unique($seen)));
    }

    public function test_a_deactivated_design_is_not_reachable(): void
    {
        $this->get(route('templates.demo', $this->template(['is_active' => false])))
            ->assertNotFound();
    }

    public function test_it_is_never_cached_or_indexed(): void
    {
        $this->get(route('templates.demo', $this->template()))
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    /** A real invitation is untouched by any of this. */
    public function test_a_real_invitation_still_renders(): void
    {
        $invitation = Invitation::factory()->create(['published' => true]);

        $this->get("/i/{$invitation->slug}")
            ->assertOk()
            ->assertSee($invitation->groom_ar, false);
    }

    /* ── the landing page's side of it ──────────────────────────────── */

    public function test_the_landing_page_frames_each_design_and_links_to_it(): void
    {
        SiteSettings::flush();

        $design = $this->template(['name' => 'Framed Theme']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Framed Theme')
            ->assertSee(route('templates.demo', $design), false)
            ->assertSee('See it in action');
    }

    /** The card holds the invitation now, not a bare clip of its footage. */
    public function test_the_landing_page_no_longer_shows_a_raw_video(): void
    {
        $this->template(['intro_video' => '/storage/unique-clip.mp4']);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('/storage/unique-clip.mp4', $html);
        $this->assertStringContainsString('designPreview(', $html);
    }
}
