<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    private function studio(): User
    {
        return User::factory()->create([
            'name' => 'Lahza Studio',
            'username' => 'admin',
            'email' => 'admin@lahza.test',
            'password' => Hash::make('password'),
        ]);
    }

    public function test_guests_cannot_reach_account_settings(): void
    {
        $this->get('/admin/account')->assertRedirect('/admin/login');
        $this->put('/admin/account')->assertRedirect('/admin/login');
        $this->put('/admin/account/password')->assertRedirect('/admin/login');
    }

    public function test_page_renders_with_the_current_details(): void
    {
        $this->actingAs($this->studio())
            ->get('/admin/account')
            ->assertOk()
            ->assertSee('Account')
            ->assertSee('admin@lahza.test')
            ->assertSee('Current password');
    }

    public function test_profile_can_be_updated(): void
    {
        $user = $this->studio();

        $this->actingAs($user)->put('/admin/account', [
            'name' => 'Ahmad',
            'username' => 'ahmad',
            'email' => 'ahmad@lahza.test',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('Ahmad', $user->name);
        $this->assertSame('ahmad', $user->username);
        $this->assertSame('ahmad@lahza.test', $user->email);
    }

    public function test_new_username_actually_signs_in(): void
    {
        $user = $this->studio();

        $this->actingAs($user)->put('/admin/account', [
            'name' => 'Ahmad',
            'username' => 'ahmad',
            'email' => 'admin@lahza.test',
        ]);

        $this->post('/admin/logout');

        $this->post('/admin/login', ['username' => 'ahmad', 'password' => 'password'])
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_email_is_lowercased_and_trimmed(): void
    {
        $user = $this->studio();

        $this->actingAs($user)->put('/admin/account', [
            'name' => 'Lahza Studio',
            'username' => 'admin',
            'email' => '  STUDIO@Lahza.Test  ',
        ])->assertSessionHasNoErrors();

        $this->assertSame('studio@lahza.test', $user->fresh()->email);
    }

    public function test_username_and_email_must_be_unique_across_accounts(): void
    {
        $this->studio();
        $other = User::factory()->create(['username' => 'someone', 'email' => 'someone@lahza.test']);

        $this->actingAs($other)->put('/admin/account', [
            'name' => 'Someone',
            'username' => 'admin',
            'email' => 'someone@lahza.test',
        ])->assertSessionHasErrors('username');

        $this->actingAs($other)->put('/admin/account', [
            'name' => 'Someone',
            'username' => 'someone',
            'email' => 'admin@lahza.test',
        ])->assertSessionHasErrors('email');
    }

    public function test_keeping_your_own_username_is_not_a_duplicate(): void
    {
        $user = $this->studio();

        $this->actingAs($user)->put('/admin/account', [
            'name' => 'Renamed',
            'username' => 'admin',
            'email' => 'admin@lahza.test',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $user->fresh()->name);
    }

    /** The username reaches URLs and the shell, so its shape is constrained. */
    public function test_username_rejects_characters_outside_alpha_dash(): void
    {
        $user = $this->studio();

        foreach (['ahmad khayat', 'ahmad@studio', 'ahmad/../etc', 'ah'] as $bad) {
            $this->actingAs($user)->put('/admin/account', [
                'name' => 'Ahmad',
                'username' => $bad,
                'email' => 'admin@lahza.test',
            ])->assertSessionHasErrors('username');
        }

        $this->assertSame('admin', $user->fresh()->username);
    }

    public function test_password_can_be_changed(): void
    {
        $user = $this->studio();

        $this->actingAs($user)->put('/admin/account/password', [
            'current_password' => 'password',
            'password' => 'a-much-better-secret',
            'password_confirmation' => 'a-much-better-secret',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('a-much-better-secret', $user->fresh()->password));
    }

    public function test_new_password_is_what_signs_you_in_afterwards(): void
    {
        $user = $this->studio();

        $this->actingAs($user)->put('/admin/account/password', [
            'current_password' => 'password',
            'password' => 'a-much-better-secret',
            'password_confirmation' => 'a-much-better-secret',
        ]);

        $this->post('/admin/logout');

        $this->post('/admin/login', ['username' => 'admin', 'password' => 'password'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();

        $this->post('/admin/login', ['username' => 'admin', 'password' => 'a-much-better-secret'])
            ->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = $this->studio();

        $this->actingAs($user)->put('/admin/account/password', [
            'current_password' => 'not-my-password',
            'password' => 'a-much-better-secret',
            'password_confirmation' => 'a-much-better-secret',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_confirmation_must_match(): void
    {
        $user = $this->studio();

        $this->actingAs($user)->put('/admin/account/password', [
            'current_password' => 'password',
            'password' => 'a-much-better-secret',
            'password_confirmation' => 'something-else',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_short_password_is_rejected(): void
    {
        $user = $this->studio();

        $this->actingAs($user)->put('/admin/account/password', [
            'current_password' => 'password',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reusing_the_current_password_is_rejected(): void
    {
        $user = $this->studio();

        $this->actingAs($user)->put('/admin/account/password', [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');
    }

    /**
     * Changing a password should end sessions opened with the old one, or a
     * change made *because* someone else got in achieves nothing.
     */
    public function test_changing_the_password_evicts_other_sessions(): void
    {
        if (config('session.driver') !== 'database') {
            $this->markTestSkipped('Other-session eviction only applies to the database driver.');
        }

        $user = $this->studio();

        \DB::table('sessions')->insert([
            'id' => 'someone-elses-session',
            'user_id' => $user->id,
            'ip_address' => '203.0.113.9',
            'user_agent' => 'other device',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($user)->put('/admin/account/password', [
            'current_password' => 'password',
            'password' => 'a-much-better-secret',
            'password_confirmation' => 'a-much-better-secret',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'someone-elses-session']);
    }

    /** One account, edited via the session — never a user chosen by id. */
    public function test_there_is_no_route_for_editing_another_account(): void
    {
        $user = $this->studio();
        $other = User::factory()->create();

        $this->actingAs($user)->get("/admin/account/{$other->id}")->assertNotFound();
        $this->actingAs($user)->put("/admin/account/{$other->id}")->assertNotFound();
    }
}
