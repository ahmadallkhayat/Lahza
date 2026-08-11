<?php

namespace Tests\Feature\Admin;

use App\Models\Template;
use App\Models\User;
use App\Support\BaseTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The claim BaseTemplates makes about itself: adding a design means adding a
 * directory and nothing else.
 *
 * That claim was false until the manifest grew a palette and settings section.
 * One design's timings and colour roles were written into Template's
 * constants, TemplateRequest's rules, the timings card and the colours card —
 * so a second design could not have declared a timing of its own without
 * editing four files that had no business knowing about it, and would have
 * inherited a petal duration whether or not it had petals.
 *
 * So this builds a whole second design at runtime — its own occasion, its own
 * colour role, its own timing, none of them shared with luxury-wedding-v1 —
 * and checks that everything downstream picks it up on its own. It is the
 * regression test for the next design, written before there is one.
 */
class SecondBaseTemplateTest extends TestCase
{
    use RefreshDatabase;

    private const NAME = 'zz-test-graduation';

    protected function setUp(): void
    {
        parent::setUp();

        $dir = resource_path(BaseTemplates::ROOT.'/'.self::NAME);
        File::ensureDirectoryExists($dir);

        File::put($dir.'/show.blade.php', '<h1>{{ $invitation->groom_ar }}</h1>');

        File::put($dir.'/template.php', <<<'PHP'
            <?php

            return [
                'label' => 'Test Graduation',
                'category' => 'Graduation',

                'palette' => [
                    'paper' => ['label' => 'Page background', 'default' => '#ffffff'],
                    'tassel' => ['label' => 'Tassel', 'default' => '#123456'],
                ],

                'settings' => [
                    'capThrowMs' => [
                        'label' => 'Cap throw',
                        'hint' => 'ms before the caps go up.',
                        'default' => 4200,
                        'min' => 0,
                        'max' => 9000,
                    ],
                    'internalMs' => ['default' => 77],
                ],

                'slots' => [
                    'motto' => ['label' => 'Motto'],
                ],
            ];
            PHP);

        BaseTemplates::flush();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(resource_path(BaseTemplates::ROOT.'/'.self::NAME));
        BaseTemplates::flush();

        parent::tearDown();
    }

    public function test_the_design_is_discovered_and_named_by_its_manifest(): void
    {
        $this->assertTrue(BaseTemplates::exists(self::NAME));
        $this->assertSame('Test Graduation', BaseTemplates::available()[self::NAME]);
    }

    public function test_it_is_filed_under_its_own_occasion(): void
    {
        $grouped = BaseTemplates::grouped();

        $this->assertArrayHasKey('Graduation', $grouped);
        $this->assertArrayHasKey(self::NAME, $grouped['Graduation']);

        // And has not disturbed the design that was already here.
        $this->assertArrayHasKey('luxury-wedding-v1', $grouped['Wedding']);
    }

    /** Its colours and timings are its own, sharing nothing with the wedding. */
    public function test_it_declares_its_own_palette_and_timings(): void
    {
        $palette = BaseTemplates::paletteDefaults(self::NAME);
        $settings = BaseTemplates::settingDefaults(self::NAME);

        $this->assertSame(['paper', 'tassel'], array_keys($palette));
        $this->assertSame('#123456', $palette['tassel']);

        $this->assertSame(['capThrowMs', 'internalMs'], array_keys($settings));
        $this->assertSame(4200, $settings['capThrowMs']);

        // None of the wedding's vocabulary leaks in.
        $this->assertArrayNotHasKey('curtain', $palette);
        $this->assertArrayNotHasKey('petalsMs', $settings);
    }

    /** A timing with no label is the design's own business, not the studio's. */
    public function test_unlabelled_timings_are_not_offered_to_the_studio(): void
    {
        $editable = BaseTemplates::editableSettings(self::NAME);

        $this->assertArrayHasKey('capThrowMs', $editable);
        $this->assertArrayNotHasKey('internalMs', $editable);
    }

    /** A theme built on it renders with its defaults, not the wedding's. */
    public function test_a_theme_on_it_gets_its_defaults(): void
    {
        $template = Template::factory()->create(['base_template' => self::NAME]);

        $this->assertSame('#123456', $template->palette()['tassel']);
        $this->assertSame(4200, $template->settings()['capThrowMs']);

        // Including the unlabelled one — the design's JavaScript reads it.
        $this->assertSame(77, $template->settings()['internalMs']);

        $this->assertArrayNotHasKey('petalsMs', $template->settings());
    }

    /** The studio's form draws this design's fields, and only those. */
    public function test_the_form_renders_the_designs_own_fields(): void
    {
        $template = Template::factory()->create(['base_template' => self::NAME]);

        $html = $this->actingAs(User::factory()->create())
            ->get("/admin/templates/{$template->id}/edit")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('settings[capThrowMs]', $html);
        $this->assertStringContainsString('palette[tassel]', $html);
        $this->assertStringContainsString('Graduation', $html);

        // The wedding's fields are not drawn for a design that has none.
        $this->assertStringNotContainsString('settings[blurLeadMs]', $html);
        $this->assertStringNotContainsString('palette[curtain]', $html);

        // Nor is the one it declared but gave no label.
        $this->assertStringNotContainsString('settings[internalMs]', $html);
    }

    /** Saving validates against this design's bounds and keeps its keys. */
    public function test_saving_accepts_its_timings_and_rejects_out_of_range(): void
    {
        $template = Template::factory()->create(['base_template' => self::NAME]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put("/admin/templates/{$template->id}", [
                'name' => 'A Graduation Theme',
                'base_template' => self::NAME,
                'is_active' => '1',
                'settings' => ['capThrowMs' => '5000'],
                'palette' => ['tassel' => '#abcdef'],
            ])
            ->assertSessionHasNoErrors();

        $template->refresh();

        $this->assertSame(5000, $template->settings()['capThrowMs']);
        $this->assertSame('#abcdef', $template->palette()['tassel']);

        // Its own max, declared in its own manifest.
        $this->actingAs($user)
            ->put("/admin/templates/{$template->id}", [
                'name' => 'A Graduation Theme',
                'base_template' => self::NAME,
                'is_active' => '1',
                'settings' => ['capThrowMs' => '99999'],
            ])
            ->assertSessionHasErrors('settings.capThrowMs');
    }

    /**
     * A timing belonging to another design cannot be smuggled in.
     *
     * cleaned() filters against what this design declares, so a hand-crafted
     * post naming the wedding's petals writes nothing.
     */
    public function test_another_designs_timing_is_not_stored(): void
    {
        $template = Template::factory()->create(['base_template' => self::NAME]);

        $this->actingAs(User::factory()->create())
            ->put("/admin/templates/{$template->id}", [
                'name' => 'A Graduation Theme',
                'base_template' => self::NAME,
                'is_active' => '1',
                'settings' => ['capThrowMs' => '5000', 'petalsMs' => '1'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertArrayNotHasKey('petalsMs', $template->refresh()->settings());
    }
}
