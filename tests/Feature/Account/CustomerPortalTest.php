<?php

namespace Tests\Feature\Account;

use App\Models\Invitation;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A customer's own side of the app.
 *
 * The interesting half of this is not that the pages render — it is where
 * they stop. Sign-up is open to anyone, so every route here is reachable by a
 * stranger, and the only things standing between one couple's invitation and
 * another's are the query scope and the ownership check.
 */
class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::factory()->customer()->create();
    }

    private function template(): Template
    {
        return Template::factory()->create(['is_active' => true]);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'template_id' => $this->template()->id,
            'locales' => ['ar'],
            'default_locale' => 'ar',
            'groom_ar' => 'يوسف',
            'bride_ar' => 'ليلى',
            'date' => '2027-03-01',
            'time' => '19:00',
            'timezone' => 'Asia/Baghdad',
            'duration_hours' => 5,
        ], $overrides);
    }

    public function test_a_guest_is_sent_to_the_customer_sign_in(): void
    {
        $this->get('/account')->assertRedirect(route('login'));
    }

    public function test_a_customer_sees_their_own_dashboard(): void
    {
        $this->actingAs($this->customer())
            ->get('/account')
            ->assertOk()
            ->assertSee('My invitations');
    }

    public function test_a_customer_can_build_an_invitation(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->post('/account/invitations', $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invitation = Invitation::sole();

        $this->assertSame($customer->id, $invitation->user_id);

        // Never live straight off the form — they have not seen it yet.
        $this->assertFalse($invitation->published);
    }

    /** Ownership is set by the server, never taken from the form. */
    public function test_a_customer_cannot_create_an_invitation_for_someone_else(): void
    {
        $customer = $this->customer();
        $victim = $this->customer();

        $this->actingAs($customer)
            ->post('/account/invitations', $this->payload(['user_id' => $victim->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($customer->id, Invitation::sole()->user_id);
    }

    public function test_a_customer_can_publish_and_unpublish_their_own(): void
    {
        $customer = $this->customer();
        $invitation = Invitation::factory()->create(['user_id' => $customer->id, 'published' => false]);

        $this->actingAs($customer)->patch("/account/invitations/{$invitation->slug}/publish");
        $this->assertTrue($invitation->fresh()->published);

        $this->actingAs($customer)->patch("/account/invitations/{$invitation->slug}/publish");
        $this->assertFalse($invitation->fresh()->published);
    }

    /**
     * The whole point of the scope.
     *
     * Route-model binding resolves by slug without knowing who is asking, so
     * without the ownership check a customer could read, edit, publish or
     * delete anyone else's by typing their link.
     */
    public function test_a_customer_cannot_touch_another_customers_invitation(): void
    {
        $mine = $this->customer();
        $theirs = Invitation::factory()->create(['user_id' => $this->customer()->id]);

        $this->actingAs($mine)->get("/account/invitations/{$theirs->slug}/edit")->assertNotFound();
        $this->actingAs($mine)->put("/account/invitations/{$theirs->slug}", $this->payload())->assertNotFound();
        $this->actingAs($mine)->patch("/account/invitations/{$theirs->slug}/publish")->assertNotFound();
        $this->actingAs($mine)->delete("/account/invitations/{$theirs->slug}")->assertNotFound();

        $this->assertDatabaseHas('invitations', ['id' => $theirs->id]);
    }

    /** And an invitation the studio holds is nobody's to claim. */
    public function test_a_customer_cannot_touch_an_unowned_invitation(): void
    {
        $studioOwned = Invitation::factory()->create(['user_id' => null]);

        $this->actingAs($this->customer())
            ->get("/account/invitations/{$studioOwned->slug}/edit")
            ->assertNotFound();
    }

    public function test_the_list_shows_only_their_own(): void
    {
        $customer = $this->customer();

        $mine = Invitation::factory()->create(['user_id' => $customer->id, 'groom_ar' => 'زيد']);
        $theirs = Invitation::factory()->create(['user_id' => $this->customer()->id, 'groom_ar' => 'عمر']);

        $this->actingAs($customer)
            ->get('/account/invitations')
            ->assertOk()
            ->assertSee($mine->slug, false)
            ->assertDontSee($theirs->slug, false);
    }

    /** An inactive theme is not on offer, however the request asks for it. */
    public function test_a_customer_cannot_use_an_inactive_theme(): void
    {
        $retired = Template::factory()->create(['is_active' => false]);

        $this->actingAs($this->customer())
            ->post('/account/invitations', $this->payload(['template_id' => $retired->id]))
            ->assertNotFound();

        $this->assertDatabaseCount('invitations', 0);
    }

    /** The translate button costs the studio money and is not a customer's. */
    public function test_the_translate_button_is_not_offered_to_a_customer(): void
    {
        $customer = $this->customer();
        $this->template();

        $html = $this->actingAs($customer)->get('/account/invitations/create')->assertOk()->getContent();

        $this->assertStringNotContainsString('Translate from English', $html);
    }

    public function test_a_customer_can_change_their_own_details(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->put('/account/profile', [
                'name' => 'New Name',
                'username' => $customer->username,
                'email' => $customer->email,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('New Name', $customer->fresh()->name);
    }
}
