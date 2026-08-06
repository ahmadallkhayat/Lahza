<?php

namespace Tests\Feature\Admin;

use App\Models\Asset;
use App\Models\Invitation;
use App\Models\User;
use App\Support\Placeholders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ready-written text offered in the invitation editor.
 *
 * Placeholders are resolved in the browser as a block is inserted, so what
 * lands in the field is ordinary editable text and the stored shape of an
 * invitation does not change. These tests cover what the server owes that:
 * the right blocks in the picker, and the data the substitution needs.
 */
class InvitationTextAssetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    private function editUrl(): string
    {
        return '/admin/invitations/'.Invitation::factory()->locales(['ar', 'en'], 'ar')->create()->slug.'/edit';
    }

    public function test_text_assets_are_offered_in_the_editor(): void
    {
        Asset::factory()->create(['name' => 'Both families', 'field' => 'hosts']);

        $this->get($this->editUrl())
            ->assertOk()
            ->assertSee('Both families')
            ->assertSee('Insert premade text');
    }

    /** Media has no business in a text picker. */
    public function test_media_assets_are_not_offered(): void
    {
        Asset::factory()->video()->create(['name' => 'Ring box opening']);

        $this->get($this->editUrl())
            ->assertOk()
            ->assertDontSee('Ring box opening');
    }

    public function test_an_inactive_block_is_not_offered(): void
    {
        Asset::factory()->inactive()->create(['name' => 'Retired wording']);

        $this->get($this->editUrl())
            ->assertOk()
            ->assertDontSee('Retired wording');
    }

    /**
     * The picker groups by whether a block suits the field it sits under, so a
     * verse is still reachable from the hosting line without being the first
     * thing offered there.
     */
    public function test_blocks_are_grouped_by_what_they_suit(): void
    {
        Asset::factory()->create(['name' => 'A hosting line', 'field' => 'hosts']);
        Asset::factory()->create(['name' => 'A verse', 'field' => 'verse']);

        $this->get($this->editUrl())
            ->assertOk()
            ->assertSee('Suits this field')
            ->assertSee('Other text')
            ->assertSee('A hosting line')
            ->assertSee('A verse');
    }

    /**
     * The browser resolves {date} and {time} itself, and needs the meridiem
     * words to do it — ICU's own marker is a terse "م", so they come from the
     * lang files rather than from Intl.
     */
    public function test_the_editor_carries_what_the_browser_needs_to_substitute(): void
    {
        Asset::factory()->create();

        $html = $this->get($this->editUrl())->assertOk()->getContent();

        foreach (['placeholders', 'meridiem', 'assets'] as $key) {
            $this->assertStringContainsString($key, $html,
                "The invitation form was not passed \"{$key}\".");
        }

        // Every accepted spelling has to reach the browser, or {bridename}
        // resolves on the server and not in the form.
        foreach (Placeholders::aliases()['bride'] as $alias) {
            $this->assertStringContainsString($alias, $html);
        }
    }

    /** The editor still saves normally with a library in place. */
    public function test_an_invitation_still_saves_with_text_assets_present(): void
    {
        Asset::factory()->create();

        $invitation = Invitation::factory()->create();

        $this->put("/admin/invitations/{$invitation->slug}", [
            'template_id' => $invitation->template_id,
            'locales' => ['ar'],
            'default_locale' => 'ar',
            'groom_ar' => 'يُوسُف',
            'bride_ar' => 'لَيْلَى',
            'hosts' => ['ar' => 'تتشرف عائلة يُوسُف بدعوتكم'],
            'date' => '2026-12-04',
            'time' => '19:00',
            'timezone' => 'Asia/Baghdad',
            'duration_hours' => 5,
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            'تتشرف عائلة يُوسُف بدعوتكم',
            $invitation->fresh()->text('hosts', 'ar')
        );
    }

    /**
     * The server-side renderer is what a future feature would use to resolve
     * these at render time. It has to agree with the browser about an
     * invitation's values.
     */
    public function test_an_invitations_values_fill_a_block(): void
    {
        $invitation = Invitation::factory()->locales(['ar', 'en'], 'ar')->create();

        $arabic = Placeholders::render(
            'تتشرف عائلة {groom} وعائلة {bride}',
            Placeholders::valuesFor($invitation, 'ar')
        );

        $this->assertStringContainsString('يُوسُف', $arabic);
        $this->assertStringContainsString('لَيْلَى', $arabic);

        // English is the one place the Latin form reads better.
        $english = Placeholders::render(
            'The families of {groom} and {bride}',
            Placeholders::valuesFor($invitation, 'en')
        );

        $this->assertSame('The families of Yusuf and Layla', $english);
    }
}
