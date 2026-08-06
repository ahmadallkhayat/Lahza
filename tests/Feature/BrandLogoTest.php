<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The mark is inlined as SVG rather than linked, so a broken or wrongly
 * coloured file shows up as a blank space or a black blob on every page.
 */
class BrandLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_mark_file_is_well_formed_svg(): void
    {
        $svg = file_get_contents(resource_path('svg/lahza-logo.svg'));

        $document = new \DOMDocument;
        $this->assertTrue(
            $document->loadXML($svg),
            'resources/svg/lahza-logo.svg is not parseable XML.'
        );
        $this->assertSame('svg', $document->documentElement->nodeName);
    }

    /**
     * The mark takes its colour from the surrounding text, which is how it
     * goes teal in the sidebar. A hardcoded fill would ignore that and render
     * black wherever it appears.
     */
    public function test_the_mark_inherits_its_colour(): void
    {
        $svg = file_get_contents(resource_path('svg/lahza-logo.svg'));

        $this->assertStringContainsString('currentColor', $svg);
        $this->assertStringNotContainsString('#000000', $svg, 'A hardcoded black would not follow the brand colour.');
    }

    /** A stroke left black while the fill inherits gives a black outline. */
    public function test_the_mark_has_no_hardcoded_stroke_colour(): void
    {
        $svg = file_get_contents(resource_path('svg/lahza-logo.svg'));

        if (str_contains($svg, 'stroke="')) {
            $this->assertStringNotContainsString('stroke="#', $svg, 'Stroke must inherit too, or it stays black.');
        }

        $this->addToAssertionCount(1);
    }

    /** Favicons cannot inherit anything, so the brand colour is baked in. */
    public function test_the_favicon_carries_the_brand_colour(): void
    {
        $svg = file_get_contents(public_path('favicon.svg'));

        $document = new \DOMDocument;
        $this->assertTrue($document->loadXML($svg), 'public/favicon.svg is not parseable XML.');

        $this->assertStringContainsString('#0d9488', $svg);
        $this->assertStringNotContainsString('currentColor', $svg, 'A favicon has no text context to inherit from.');
    }

    public function test_the_mark_is_inlined_on_admin_pages_with_an_accessible_name(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="Lahza"', $html);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('viewBox="0 0 710 703"', $html, 'The new mark should be the one rendered.');
    }

    public function test_the_mark_appears_on_the_sign_in_screen(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('viewBox="0 0 710 703"', false);
    }

    public function test_every_page_that_links_a_favicon_gets_the_new_one(): void
    {
        foreach (['/', '/admin/login'] as $url) {
            $this->get($url)->assertOk()->assertSee('favicon.svg', false);
        }
    }
}
