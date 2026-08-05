<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function studio(): User
    {
        return User::factory()->create([
            'username' => 'admin',
            'email' => 'admin@lahza.test',
            'password' => Hash::make('password'),
        ]);
    }

    public function test_landing_page_is_public(): void
    {
        $this->get('/')->assertOk()->assertSee('Coming soon');
    }

    public function test_login_screen_renders(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('Sign in')->assertSee('Username');
    }

    public function test_guests_are_redirected_from_every_studio_route(): void
    {
        foreach (['/admin', '/admin/templates', '/admin/invitations'] as $url) {
            $this->get($url)->assertRedirect('/admin/login');
        }
    }

    public function test_studio_account_can_sign_in(): void
    {
        $user = $this->studio();

        $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'password',
        ])->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user);
    }

    /**
     * The email is still on the record, but it is not a credential. Without
     * this, someone could "helpfully" re-add email sign-in later and widen the
     * way in without anyone noticing.
     */
    public function test_email_is_not_accepted_in_place_of_a_username(): void
    {
        $this->studio();

        $this->post('/admin/login', [
            'username' => 'admin@lahza.test',
            'password' => 'password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_email_is_still_stored_on_the_account(): void
    {
        $this->assertSame('admin@lahza.test', $this->studio()->fresh()->email);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->studio();

        $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'wrong',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_authenticated_user_can_reach_studio_pages(): void
    {
        $this->actingAs($this->studio());

        $this->get('/admin')->assertOk()->assertSee('Dashboard');
        $this->get('/admin/templates')->assertOk()->assertSee('No templates yet');
        $this->get('/admin/invitations')->assertOk()->assertSee('No invitations yet');
    }

    public function test_signed_in_user_is_bounced_off_the_login_screen(): void
    {
        $this->actingAs($this->studio())
            ->get('/admin/login')
            ->assertRedirect('/admin');
    }

    public function test_user_can_sign_out(): void
    {
        $this->actingAs($this->studio())
            ->post('/admin/logout')
            ->assertRedirect('/admin/login');

        $this->assertGuest();
    }

    /**
     * Accounts are created by seeder or console only. If a registration
     * route ever gets added by accident, this fails.
     */
    public function test_there_is_no_public_registration(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
        $this->get('/login')->assertNotFound();
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        RateLimiter::clear('admin|127.0.0.1');
        $this->studio();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', [
                'username' => 'admin',
                'password' => 'wrong',
            ]);
        }

        $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'wrong',
        ])->assertSessionHasErrorsIn('default', ['username']);

        $this->assertStringContainsString(
            'Too many attempts',
            session('errors')->first('username')
        );
    }
}
