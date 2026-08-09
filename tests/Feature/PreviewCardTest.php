<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The card a shared link expands into.
 *
 * Drawing it needs ffmpeg and a downloaded font face, so these assert the
 * contract around it rather than the pixels: a card is optional, and the page
 * must be honest about whether it has one.
 */
class PreviewCardTest extends TestCase
{
    use RefreshDatabase;

    private function invitation(array $attributes = []): Invitation
    {
        return Invitation::factory()
            ->for(Template::factory()->create(['base_template' => 'luxury-wedding-v1']))
            ->create($attributes + ['published' => true]);
    }

    public function test_the_page_advertises_the_card_when_there_is_one(): void
    {
        $invitation = $this->invitation(['og_image' => '/storage/invitations/og/x.jpg']);

        $this->get('/i/'.$invitation->slug)
            ->assertOk()
            ->assertSee('og:image', false)
            ->assertSee(url('/storage/invitations/og/x.jpg'), false)
            ->assertSee('summary_large_image', false);
    }

    /**
     * And falls back to a default image when there is not a custom one.
     *
     * This satisfies scrapers (like WhatsApp/Instagram) that require an image
     * to render a preview card.
     */
    public function test_the_page_falls_back_to_default_image_when_none_provided(): void
    {
        $invitation = $this->invitation(['og_image' => null]);

        $html = $this->get('/i/'.$invitation->slug)->assertOk()->getContent();

        $this->assertStringContainsString('og:image', $html);
        $this->assertStringContainsString('og-image.png', $html);
        $this->assertStringContainsString('summary_large_image', $html);
    }

    public function test_the_card_url_is_absolute(): void
    {
        // A scraper is not a browser and will not resolve a relative path.
        $invitation = $this->invitation(['og_image' => '/storage/invitations/og/x.jpg']);

        $html = $this->get('/i/'.$invitation->slug)->assertOk()->getContent();

        preg_match('/property="og:image" content="([^"]+)"/', $html, $m);

        $this->assertNotEmpty($m[1] ?? null);
        $this->assertStringStartsWith('http', $m[1]);
    }

    public function test_the_couple_are_named_in_the_preview_text(): void
    {
        $invitation = $this->invitation(['groom_ar' => 'كَرَم', 'bride_ar' => 'لَيْلَى']);

        $this->get('/i/'.$invitation->slug)
            ->assertOk()
            ->assertSee('og:title', false)
            ->assertSee('كَرَم', false)
            ->assertSee('لَيْلَى', false);
    }
}
