<?php

namespace Tests\Feature\Admin;

use App\Models\Invitation;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JavaScript belongs in resources/js, registered with Alpine.data() and
 * referenced by name — never written inline in an x-data="..." attribute.
 *
 * That attribute is delimited by a double quote, so one literal double quote
 * inside it, even in a comment, closes it early and dumps the rest of the
 * script onto the page as visible text. There is no error and no failing
 * request; it is only noticed by looking at the page.
 *
 * These tests enforce the arrangement that makes it impossible, rather than
 * checking for the symptom after the fact.
 */
class InlineScriptIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /** Anything that only appears in a function body, not in a component call. */
    private const LOGIC_TOKENS = ['=>', 'function', 'const ', 'let ', 'return ', 'await ', '/*', '//'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    /**
     * Every x-data value on the page, read to the quote a browser would stop
     * at — so an attribute broken by a stray quote yields the truncated value
     * the browser actually sees.
     *
     * @return array<int, string>
     */
    private function xDataValues(string $html): array
    {
        $values = [];
        $offset = 0;

        while (($start = strpos($html, 'x-data="', $offset)) !== false) {
            $start += strlen('x-data="');
            $end = strpos($html, '"', $start);

            $values[] = substr($html, $start, $end - $start);
            $offset = $end;
        }

        return $values;
    }

    /** @return array<int, string> */
    private function adminPages(): array
    {
        $invitation = Invitation::factory()->create();
        $template = Template::factory()->create();

        return [
            '/admin',
            '/admin/invitations',
            '/admin/templates',
            '/admin/invitations/create',
            "/admin/invitations/{$invitation->slug}/edit",
            '/admin/templates/create',
            "/admin/templates/{$template->id}/edit",
            '/admin/account',
        ];
    }

    public function test_no_page_defines_a_javascript_body_inside_an_attribute(): void
    {
        foreach ($this->adminPages() as $page) {
            $html = $this->get($page)->assertOk()->getContent();

            foreach ($this->xDataValues($html) as $value) {
                foreach (self::LOGIC_TOKENS as $token) {
                    $this->assertStringNotContainsString(
                        $token,
                        $value,
                        "{$page} defines JavaScript inside an x-data attribute (found \"{$token}\"). "
                        .'Move it to resources/js and register it with Alpine.data() — a literal '
                        .'double quote in there breaks the attribute and spills the script onto the page.'
                    );
                }
            }
        }
    }

    /** The components are still wired up, not merely absent. */
    public function test_the_form_components_are_referenced_by_name(): void
    {
        $invitation = Invitation::factory()->create();
        $template = Template::factory()->create();

        $this->get("/admin/invitations/{$invitation->slug}/edit")
            ->assertOk()
            ->assertSee('invitationForm(', false);

        $this->get('/admin/invitations/create')
            ->assertOk()
            ->assertSee('invitationForm(', false);

        $this->get("/admin/templates/{$template->id}/edit")
            ->assertOk()
            ->assertSee('templatePreview(', false);

        $this->get('/admin/templates/create')
            ->assertOk()
            ->assertSee('templatePreview(', false);
    }

    /**
     * The server data each component needs must survive the trip through the
     * attribute. Blade escapes it, so quotes inside it are safe — but a
     * missing key would leave the component silently inert.
     */
    public function test_the_components_receive_their_server_data(): void
    {
        $invitation = Invitation::factory()->locales(['ar', 'en'], 'ar')->create();

        $html = $this->get("/admin/invitations/{$invitation->slug}/edit")->assertOk()->getContent();

        $component = collect($this->xDataValues($html))
            ->first(fn ($value) => str_contains($value, 'invitationForm('));

        $this->assertNotNull($component, 'The invitation form component was not found.');

        foreach (['locales', 'labels', 'numerals', 'steps', 'translateUrl'] as $key) {
            $this->assertStringContainsString(
                $key,
                $component,
                "The invitation form was not passed \"{$key}\"."
            );
        }
    }

    /**
     * The symptom as a user meets it: script rendered as page text. Kept
     * alongside the structural checks because it is what actually got noticed.
     */
    public function test_no_script_is_rendered_as_visible_text(): void
    {
        foreach ($this->adminPages() as $page) {
            $text = strip_tags($this->get($page)->assertOk()->getContent());

            foreach (['applyTranslations', 'paintColours', 'setStepTime', 'const ', '=> {'] as $token) {
                $this->assertStringNotContainsString(
                    $token,
                    $text,
                    "Script leaked into the visible text of {$page} (found: {$token})."
                );
            }
        }
    }
}
