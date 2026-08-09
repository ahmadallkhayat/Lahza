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
     * And promises nothing when there is not.
     *
     * The page used to declare summary_large_image unconditionally while
     * carrying no og:image at all, so a scraper reserved room for a picture
     * that never arrived and rendered a broken-looking card.
     */
    public function test_the_page_promises_no_image_when_it_has_none(): void
    {
        $invitation = $this->invitation(['og_image' => null]);

        $html = $this->get('/i/'.$invitation->slug)->assertOk()->getContent();

        $this->assertStringNotContainsString('og:image', $html);
        $this->assertStringNotContainsString('summary_large_image', $html);
        $this->assertStringContainsString('twitter:card', $html);
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
