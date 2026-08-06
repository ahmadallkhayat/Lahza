<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\Template;
use App\Models\User;
use App\Support\BaseTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A draft is visible to the signed-in studio so it can be checked before the
 * link goes out. That is deliberate, but it means the preview looks exactly
 * like the live page — so it has to say that it is not one.
 */
class DraftPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_still_cannot_see_a_draft(): void
    {
        $invitation = Invitation::factory()->draft()->create();

        $this->get("/i/{$invitation->slug}")->assertNotFound();
    }

    public function test_the_studio_preview_says_it_is_a_draft(): void
    {
        $invitation = Invitation::factory()->draft()->create();

        $this->actingAs(User::factory()->create())
            ->get("/i/{$invitation->slug}")
            ->assertOk()
            ->assertSee('Draft — not published.', false)
            ->assertSee('Guests get a “page not found”', false);
    }

    /** The notice must never reach a real guest's invitation. */
    public function test_a_published_invitation_shows_no_notice(): void
    {
        $invitation = Invitation::factory()->create(['published' => true]);

        $this->actingAs(User::factory()->create())
            ->get("/i/{$invitation->slug}")
            ->assertOk()
            ->assertDontSee('Draft — not published.', false);
    }

    public function test_the_notice_links_to_the_invitation_so_it_can_be_published(): void
    {
        $invitation = Invitation::factory()->draft()->create();

        $this->actingAs(User::factory()->create())
            ->get("/i/{$invitation->slug}")
            ->assertOk()
            ->assertSee(route('admin.invitations.edit', $invitation), false);
    }

    /**
     * Enforces the "every base template should include this" comment. A new
     * design that forgets the include would otherwise ship a draft preview
     * indistinguishable from a live invitation, and nothing would catch it.
     */
    public function test_every_base_template_renders_the_draft_notice(): void
    {
        $bases = array_keys(BaseTemplates::available());

        $this->assertNotEmpty($bases, 'No base templates found to check.');

        $this->actingAs(User::factory()->create());

        foreach ($bases as $base) {
            $template = Template::factory()->create(['base_template' => $base]);
            $invitation = Invitation::factory()->draft()->create(['template_id' => $template->id]);

            $this->get("/i/{$invitation->slug}")
                ->assertOk()
                ->assertSee('Draft — not published.', false);
        }
    }
}
