<?php

namespace Tests\Feature\Admin;

use App\Models\Invitation;
use App\Models\Template;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SiteSettings::flush();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /* ── the studio is the studio's ─────────────────────────────────── */

    /**
     * The reason EnsureUserIsAdmin exists.
     *
     * Signing in is open to anyone now, so `auth` alone stopped meaning "one
     * of us". Without the role check, a stranger who signed up on the landing
     * page could read the whole template library and every customer's
     * invitations by typing the URL.
     */
    public function test_a_customer_cannot_reach_the_studio(): void
    {
        $customer = User::factory()->customer()->create();

        foreach ([
            '/admin',
            '/admin/templates',
            '/admin/invitations',
            '/admin/assets',
            '/admin/customers',
            '/admin/settings',
        ] as $url) {
            $this->actingAs($customer)->get($url)->assertNotFound();
        }
    }

    public function test_an_admin_can(): void
    {
        $this->actingAs($this->admin())->get('/admin/customers')->assertOk();
        $this->actingAs($this->admin())->get('/admin/settings')->assertOk();
    }

    /* ── customers ──────────────────────────────────────────────────── */

    public function test_the_list_counts_each_customers_invitations(): void
    {
        $customer = User::factory()->customer()->create(['name' => 'Sara Ahmed']);
        Invitation::factory()->count(3)->create(['user_id' => $customer->id]);

        // Somebody else's, to prove the count is per account.
        Invitation::factory()->create(['user_id' => User::factory()->customer()->create()->id]);

        $this->actingAs($this->admin())
            ->get('/admin/customers')
            ->assertOk()
            ->assertSee('Sara Ahmed')
            ->assertSeeInOrder(['Sara Ahmed', '3'], false);
    }

    /**
     * Asserted through the empty state rather than by looking for the admin's
     * name: the sidebar prints whoever is signed in, so their name is on the
     * page whatever the table holds. "No customers yet" can only render when
     * the query found none — which is the actual claim.
     */
    public function test_the_studios_own_accounts_are_not_in_the_customer_list(): void
    {
        $admin = $this->admin();
        $this->admin();

        $this->actingAs($admin)
            ->get('/admin/customers')
            ->assertOk()
            ->assertSee('No customers yet');
    }

    public function test_an_admin_can_create_an_account_for_someone(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/customers', [
                'name' => 'Layla',
                'username' => 'layla',
                'email' => 'layla@example.test',
                'phone' => '+9647700000000',
                'password' => 'correct-horse-battery',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $created = User::where('username', 'layla')->sole();

        // A customer, never an admin — this page makes customers.
        $this->assertSame(User::ROLE_CUSTOMER, $created->role);
    }

    /** Even if the request says otherwise. */
    public function test_creating_a_customer_cannot_mint_an_admin(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/customers', [
                'name' => 'Sneaky',
                'username' => 'sneaky',
                'email' => 'sneaky@example.test',
                'password' => 'correct-horse-battery',
                'role' => User::ROLE_ADMIN,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(User::ROLE_CUSTOMER, User::where('username', 'sneaky')->sole()->role);
    }

    public function test_another_admin_is_not_editable_from_the_customer_pages(): void
    {
        $other = $this->admin();

        $this->actingAs($this->admin())->get("/admin/customers/{$other->id}")->assertNotFound();
        $this->actingAs($this->admin())->get("/admin/customers/{$other->id}/edit")->assertNotFound();
        $this->actingAs($this->admin())->delete("/admin/customers/{$other->id}")->assertNotFound();
    }

    /**
     * Deleting the account must not take the wedding down with it.
     *
     * The foreign key is nullOnDelete for exactly this: an invitation whose
     * owner is gone reverts to being the studio's, which is where every
     * invitation started.
     */
    public function test_deleting_a_customer_keeps_their_invitations(): void
    {
        $customer = User::factory()->customer()->create();
        $invitation = Invitation::factory()->create(['user_id' => $customer->id]);

        $this->actingAs($this->admin())
            ->delete("/admin/customers/{$customer->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $customer->id]);
        $this->assertDatabaseHas('invitations', ['id' => $invitation->id, 'user_id' => null]);
    }

    /* ── site settings ──────────────────────────────────────────────── */

    public function test_settings_start_at_their_defaults(): void
    {
        $this->assertSame('Lahza', SiteSettings::text('brand_name'));
        $this->assertNotEmpty(SiteSettings::list('offers'));
    }

    public function test_saving_settings_changes_the_landing_page(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/settings', [
                'brand_name' => 'Lahza Studio',
                'hero_heading' => 'A different heading',
                'contact_email' => 'hello@example.test',
                'offers' => [
                    ['title' => 'One thing', 'body' => 'About it'],
                    // Empty rows are dropped rather than rendered blank.
                    ['title' => '', 'body' => ''],
                ],
            ])
            ->assertSessionHasNoErrors();

        SiteSettings::flush();

        $this->assertCount(1, SiteSettings::list('offers'));

        $this->get('/')
            ->assertOk()
            ->assertSee('A different heading')
            ->assertSee('One thing')
            ->assertSee('hello@example.test');
    }

    /** Clearing a field puts its default back rather than blanking the page. */
    public function test_an_emptied_setting_returns_to_its_default(): void
    {
        SiteSettings::put(['hero_heading' => 'Temporary']);
        SiteSettings::flush();
        $this->assertSame('Temporary', SiteSettings::text('hero_heading'));

        SiteSettings::put(['hero_heading' => '']);
        SiteSettings::flush();

        $this->assertSame(
            SiteSettings::defaults()['hero_heading'],
            SiteSettings::text('hero_heading'),
        );
    }

    public function test_the_landing_page_shows_featured_designs_only(): void
    {
        $shown = Template::factory()->create([
            'name' => 'Shown Theme',
            'is_active' => true,
            'intro_video' => '/storage/x.mp4',
        ]);

        $hidden = Template::factory()->create([
            'name' => 'Hidden Theme',
            'is_active' => true,
            'intro_video' => '/storage/y.mp4',
        ]);

        SiteSettings::put(['featured_templates' => [$shown->id]]);
        SiteSettings::flush();

        $this->get('/')
            ->assertOk()
            ->assertSee('Shown Theme')
            ->assertDontSee('Hidden Theme');
    }

    /** A theme with no clip has nothing to show, so it is not shown. */
    public function test_a_design_without_a_video_is_not_advertised(): void
    {
        Template::factory()->create([
            'name' => 'Clipless Theme',
            'is_active' => true,
            'intro_video' => null,
        ]);

        $this->get('/')->assertOk()->assertDontSee('Clipless Theme');
    }

    public function test_settings_are_not_editable_by_a_customer(): void
    {
        $this->actingAs(User::factory()->customer()->create())
            ->put('/admin/settings', ['brand_name' => 'Hijacked'])
            ->assertNotFound();

        SiteSettings::flush();

        $this->assertSame('Lahza', SiteSettings::text('brand_name'));
    }
}
