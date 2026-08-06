<?php

namespace Tests\Feature\Admin;

use App\Models\Invitation;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    // ── Dashboard ────────────────────────────────────────────────────────

    /** It reported a hard-coded 0 for everything before, whatever the data. */
    public function test_dashboard_counts_are_real(): void
    {
        Template::factory()->count(2)->create();
        Invitation::factory()->count(3)->create(['published' => true]);
        Invitation::factory()->count(2)->create(['published' => false]);

        $html = $this->get('/admin')->assertOk()->getContent();

        // 5 templates: 2 made directly, plus one per invitation via the factory.
        $this->assertStringContainsString('>7</p>', $html, 'template count');
        $this->assertStringContainsString('>5</p>', $html, 'invitation count');
        $this->assertStringContainsString('3 of 5 links are live', $html);
    }

    public function test_dashboard_lists_upcoming_events_soonest_first(): void
    {
        $later = Invitation::factory()->create([
            'groom_ar' => 'ثَانِي',
            'starts_at' => now()->addMonths(6),
        ]);
        $sooner = Invitation::factory()->create([
            'groom_ar' => 'أَوَّل',
            'starts_at' => now()->addWeek(),
        ]);

        $html = $this->get('/admin')->assertOk()->getContent();

        $this->assertLessThan(
            strpos($html, $later->groom_ar),
            strpos($html, $sooner->groom_ar),
            'The sooner event must be listed first.'
        );
    }

    /** A past event is done with; it should not sit at the top of the list. */
    public function test_dashboard_omits_events_that_have_passed(): void
    {
        Invitation::factory()->create([
            'groom_ar' => 'مَاضِي',
            'starts_at' => now()->subMonth(),
        ]);

        $this->get('/admin')->assertOk()->assertDontSee('مَاضِي', false);
    }

    public function test_dashboard_surfaces_drafts(): void
    {
        Invitation::factory()->create(['slug' => 'not-live-yet', 'published' => false]);

        $this->get('/admin')->assertOk()->assertSee('/i/not-live-yet');
    }

    // ── Row actions ──────────────────────────────────────────────────────

    /**
     * Icon-only controls carry their meaning in an accessible name, not the
     * glyph. If these disappear the buttons become unusable with a screen
     * reader and unlabelled on hover.
     */
    public function test_invitation_row_actions_are_labelled(): void
    {
        // Unpublished, so the publish action reads as the "make it live" case.
        Invitation::factory()->create([
            'groom_ar' => 'يُوسُف',
            'bride_ar' => 'لَيْلَى',
            'published' => false,
        ]);

        $html = $this->get('/admin/invitations')->assertOk()->getContent();

        foreach ([
            'Edit the invitation for',
            'Copy the link for',
            'in a new tab',
            'Publish — make the link live',
            'Delete the invitation for',
        ] as $label) {
            $this->assertStringContainsString($label, $html, "missing label: {$label}");
        }
    }

    public function test_template_row_actions_are_labelled(): void
    {
        Template::factory()->create(['name' => 'Luxury Wedding']);

        $html = $this->get('/admin/templates')->assertOk()->getContent();

        $this->assertStringContainsString('Edit Luxury Wedding', $html);
        $this->assertStringContainsString('Delete Luxury Wedding', $html);
    }

    /** The confirm must name the row, or there is no way to catch a misclick. */
    public function test_delete_confirmation_names_the_thing_being_deleted(): void
    {
        Template::factory()->create(['name' => 'Luxury Wedding']);

        $this->get('/admin/templates')
            ->assertOk()
            ->assertSee('Delete the template “Luxury Wedding”', false);
    }

    public function test_publish_icon_reflects_the_current_state(): void
    {
        Invitation::factory()->create(['published' => true]);

        $this->get('/admin/invitations')
            ->assertOk()
            ->assertSee('Unpublish — the link will stop working');
    }

    // ── Destructive guards ───────────────────────────────────────────────

    /**
     * The controller refuses this delete anyway. Disabling the button states
     * the reason up front instead of after a failed round trip.
     */
    public function test_delete_is_disabled_for_a_template_still_in_use(): void
    {
        $template = Template::factory()->create(['name' => 'In Use']);
        Invitation::factory()->create(['template_id' => $template->id]);

        $this->get('/admin/templates')
            ->assertOk()
            ->assertSee('Cannot delete — 1 invitation still uses this template', false)
            ->assertSee('disabled', false);
    }

    /** Singular vs plural, because "1 invitations still use" reads as a bug. */
    public function test_the_in_use_reason_is_pluralised(): void
    {
        $template = Template::factory()->create();
        Invitation::factory()->count(3)->create(['template_id' => $template->id]);

        $this->get('/admin/templates')
            ->assertOk()
            ->assertSee('3 invitations still use this template', false);
    }

    public function test_refusing_a_delete_flashes_an_error_not_a_success(): void
    {
        $template = Template::factory()->create();
        Invitation::factory()->create(['template_id' => $template->id]);

        $this->delete("/admin/templates/{$template->id}")
            ->assertSessionHas('error')
            ->assertSessionMissing('status');

        $this->assertDatabaseHas('templates', ['id' => $template->id]);
    }

    public function test_an_unused_template_can_still_be_deleted(): void
    {
        $template = Template::factory()->create();

        $this->delete("/admin/templates/{$template->id}")
            ->assertSessionHas('status')
            ->assertSessionMissing('error');

        $this->assertDatabaseMissing('templates', ['id' => $template->id]);
    }
}
