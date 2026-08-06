<?php

namespace Tests\Feature\Admin;

use App\Models\Invitation;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alpine components on these pages live inside an x-data="..." attribute.
 * The attribute is delimited by a double quote, so a single literal double
 * quote anywhere inside it — including in a comment — closes the attribute
 * early and dumps the remaining JavaScript onto the page as visible text.
 *
 * It is silent: no error, no failing request, just script showing in the UI.
 * These tests read the attribute back out of the rendered page and check it
 * still reaches its final method, which it cannot do if it was cut short.
 */
class InlineScriptIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    /**
     * Reads the value of the first x-data attribute appearing after $anchor,
     * stopping at the quote the browser would stop at.
     */
    private function xDataAfter(string $html, string $anchor): string
    {
        $from = strpos($html, $anchor);
        $this->assertNotFalse($from, "Anchor not found in page: {$anchor}");

        $start = strpos($html, 'x-data="', $from);
        $this->assertNotFalse($start, 'No x-data attribute after the anchor.');

        $start += strlen('x-data="');
        $end = strpos($html, '"', $start);

        return substr($html, $start, $end - $start);
    }

    public function test_the_invitation_form_script_stays_inside_its_attribute(): void
    {
        $invitation = Invitation::factory()->create();

        $html = $this->get("/admin/invitations/{$invitation->slug}/edit")->assertOk()->getContent();

        $script = $this->xDataAfter($html, 'class="space-y-6"');

        $this->assertStringContainsString(
            'applyTranslations',
            $script,
            'The x-data attribute closed early. A literal double quote inside it — often in a '
            .'comment — breaks out and dumps the rest of the script onto the page as text.'
        );
    }

    /** The same trap, on the page that already carries a warning about it. */
    public function test_the_template_preview_script_stays_inside_its_attribute(): void
    {
        $html = $this->get('/admin/templates/create')->assertOk()->getContent();

        // Anchored on the class attribute that precedes x-data on the preview
        // wrapper — anchoring on something inside the attribute would search
        // past the very thing being read.
        $script = $this->xDataAfter($html, '@4xl:sticky');

        $this->assertStringContainsString(
            'paintColours',
            $script,
            'The preview x-data attribute closed early — see the note in _preview.blade.php.'
        );
    }

    public function test_the_template_edit_page_script_stays_inside_its_attribute(): void
    {
        $template = Template::factory()->create();

        $html = $this->get("/admin/templates/{$template->id}/edit")->assertOk()->getContent();

        // Anchored on the class attribute that precedes x-data on the preview
        // wrapper — anchoring on something inside the attribute would search
        // past the very thing being read.
        $script = $this->xDataAfter($html, '@4xl:sticky');

        $this->assertStringContainsString('paintColours', $script);
    }

    /**
     * The symptom as a user meets it: JavaScript rendered as page text.
     * Checks the tail of each component, which is what surfaces first.
     */
    public function test_no_script_is_rendered_as_visible_text(): void
    {
        $invitation = Invitation::factory()->create();
        $template = Template::factory()->create();

        $pages = [
            "/admin/invitations/{$invitation->slug}/edit",
            '/admin/invitations/create',
            "/admin/templates/{$template->id}/edit",
            '/admin/templates/create',
        ];

        foreach ($pages as $page) {
            $html = $this->get($page)->assertOk()->getContent();

            // Text nodes only: everything inside a tag is stripped first, so
            // a legitimate attribute containing script does not trip this.
            $text = strip_tags($html);

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
