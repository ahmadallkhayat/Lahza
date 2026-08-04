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
        $this->get('/admin/login')->assertOk()->assertSee('Sign in');
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
            'email' => 'admin@lahza.test',
            'password' => 'password',
        ])->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->studio();

        $this->post('/admin/login', [
            'email' => 'admin@lahza.test',
            'password' => 'wrong',
        ])->assertSessionHasErrors('email');

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
        RateLimiter::clear('admin@lahza.test|127.0.0.1');
        $this->studio();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', [
                'email' => 'admin@lahza.test',
                'password' => 'wrong',
            ]);
        }

        $this->post('/admin/login', [
            'email' => 'admin@lahza.test',
            'password' => 'wrong',
        ])->assertSessionHasErrorsIn('default', ['email']);

        $this->assertStringContainsString(
            'Too many attempts',
            session('errors')->first('email')
        );
    }
}
