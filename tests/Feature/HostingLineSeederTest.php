<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Support\Placeholders;
use Database\Seeders\HostingLineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The seeded hosting lines are content, not code, and content is exactly what
 * nothing else checks. A token typo lands on a customer's invitation as a
 * literal {grom}, and a language left out is a card the studio has to fill by
 * hand for every invitation that uses the block.
 */
class HostingLineSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(HostingLineSeeder::class);
    }

    public function test_every_line_is_written_in_every_language(): void
    {
        $locales = array_keys(config('templates.locales'));

        $blocks = Asset::query()->ofKind(Asset::KIND_TEXT)->get();

        $this->assertGreaterThan(10, $blocks->count());

        foreach ($blocks as $block) {
            $this->assertSame(
                $locales,
                $block->writtenLocales(),
                "“{$block->name}” is missing a language."
            );
        }
    }

    /**
     * A token nothing can fill is left exactly as written — by design, so a
     * typo is visible rather than silently blank. Which means a rendered line
     * still carrying a brace is a line with a typo in it.
     */
    public function test_no_line_uses_a_placeholder_nothing_fills(): void
    {
        foreach (Asset::query()->ofKind(Asset::KIND_TEXT)->get() as $block) {
            foreach (array_keys($block->bodies()) as $locale) {
                $rendered = $block->render(null, $locale);

                $this->assertStringNotContainsString(
                    '{',
                    $rendered,
                    "“{$block->name}” ({$locale}) uses a placeholder nothing fills: {$rendered}"
                );
            }
        }
    }

    /** Both names should appear wherever the sentence names anybody. */
    public function test_placeholders_resolve_to_real_values(): void
    {
        $block = Asset::query()
            ->ofKind(Asset::KIND_TEXT)
            ->where('name', 'Both families — formal')
            ->sole();

        $rendered = $block->render(null, 'en');

        $this->assertStringContainsString(Placeholders::samples()['groom'], $rendered);
        $this->assertStringContainsString(Placeholders::samples()['bride'], $rendered);
        $this->assertStringNotContainsString('{', $rendered);
    }

    /** Wording belongs to no design, so every line is in the shared library. */
    public function test_lines_are_shared_and_filed_under_the_hosting_line(): void
    {
        foreach (Asset::query()->ofKind(Asset::KIND_TEXT)->get() as $block) {
            $this->assertNull($block->base_template, "“{$block->name}” is not shared.");
            $this->assertSame('hosts', $block->field);
            $this->assertTrue($block->is_active);
        }
    }

    /** Re-seeding updates in place rather than making a second copy. */
    public function test_seeding_twice_does_not_duplicate(): void
    {
        $before = Asset::query()->ofKind(Asset::KIND_TEXT)->count();

        $this->seed(HostingLineSeeder::class);

        $this->assertSame($before, Asset::query()->ofKind(Asset::KIND_TEXT)->count());
    }
}
