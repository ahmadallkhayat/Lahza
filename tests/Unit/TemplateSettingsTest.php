<?php

namespace Tests\Unit;

use App\Models\Template;
use App\Models\User;
use App\Support\BaseTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateSettingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The template's JavaScript compares one of these strictly. A form sends
     * "0", and `"0" === 0` is false — which left the intro clip unmuted and its
     * own soundtrack playing under the music. Numbers are guaranteed on read.
     */
    public function test_settings_are_numbers_even_when_the_row_holds_strings(): void
    {
        $template = Template::factory()->create([
            'settings' => [
                'keepVideoAudioMs' => '0',
                'crossfadeMs' => '1400',
                'duckTo' => '0.42',
            ],
        ]);

        $settings = $template->settings();

        $this->assertSame(0, $settings['keepVideoAudioMs']);
        $this->assertSame(1400, $settings['crossfadeMs']);
        $this->assertSame(0.42, $settings['duckTo']);
    }

    public function test_the_json_handed_to_the_page_has_no_quoted_numbers(): void
    {
        $template = Template::factory()->create([
            'settings' => ['keepVideoAudioMs' => '0', 'fogHz' => '700'],
        ]);

        $json = json_encode($template->settings());

        $this->assertStringContainsString('"keepVideoAudioMs":0', $json);
        $this->assertStringNotContainsString('"0"', $json);
    }

    public function test_defaults_fill_anything_absent(): void
    {
        $template = Template::factory()->create(['settings' => ['fogHz' => 500]]);

        $settings = $template->settings();
        $declared = BaseTemplates::settingDefaults($template->base_template);

        $this->assertSame(500, $settings['fogHz']);
        $this->assertSame($declared['crossfadeMs'], $settings['crossfadeMs']);

        // Every timing the design declares reaches the page, including the
        // unlabelled ones the studio never sees — those are what the design's
        // own JavaScript reads off CONFIG.
        $this->assertCount(count($declared), $settings);
    }

    /**
     * A timing belongs to the design that declares it, not to every theme.
     *
     * This is the property that lets a second base template exist: its own
     * template.php declares its own timings, and a theme built on it gets
     * those rather than this design's petals and blur holds.
     */
    public function test_settings_are_the_chosen_designs_own(): void
    {
        $template = Template::factory()->create(['base_template' => 'luxury-wedding-v1']);

        $settings = $template->settings();

        $this->assertArrayHasKey('petalsMs', $settings);
        $this->assertSame(
            array_keys(BaseTemplates::settingDefaults('luxury-wedding-v1')),
            array_keys($settings),
        );
    }

    /** A value for a timing the design never declared is not stored or served. */
    public function test_a_timing_the_design_does_not_declare_is_dropped(): void
    {
        $template = Template::factory()->create([
            'settings' => ['fogHz' => 500, 'somethingElseMs' => 900],
        ]);

        $this->assertArrayNotHasKey('somethingElseMs', $template->settings());
    }

    public function test_the_form_stores_numbers_not_strings(): void
    {
        $user = User::factory()->create();
        $template = Template::factory()->create();

        $this->actingAs($user)->put("/admin/templates/{$template->id}", [
            'name' => 'A Theme',
            'base_template' => 'luxury-wedding-v1',
            'is_active' => '1',
            'settings' => ['keepVideoAudioMs' => '0', 'duckTo' => '0.5'],
        ]);

        $raw = $template->refresh()->getAttributes()['settings'];

        $this->assertStringContainsString('"keepVideoAudioMs":0', $raw);
        $this->assertStringNotContainsString('"keepVideoAudioMs":"0"', $raw);
    }
}
