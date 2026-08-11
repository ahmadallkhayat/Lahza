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
        // What it says now comes from SiteSettings, so this asserts that the
        // page renders for a guest and offers both doors — not any particular
        // wording, which the studio can change without touching code.
        $this->get('/')
            ->assertOk()
            ->assertSee(route('register'), false)
            ->assertSee(route('login'), false);
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
     * Sign-up is open, and it only ever makes customers.
     *
     * This used to assert the opposite — that /register did not exist at all.
     * It exists now, which makes the thing worth guarding the *other* half of
     * that promise: that no route out here can mint an account which reaches
     * the studio. A role smuggled into the request must be ignored.
     */
    public function test_public_registration_cannot_create_an_admin(): void
    {
        $this->get('/register')->assertOk();

        $this->post('/register', [
            'name' => 'Sara',
            'username' => 'sara',
            'email' => 'sara@example.test',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
            'role' => User::ROLE_ADMIN,
        ])->assertRedirect(route('account.dashboard'));

        $user = User::where('username', 'sara')->sole();

        $this->assertSame(User::ROLE_CUSTOMER, $user->role);
        $this->assertFalse($user->isAdmin());
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
