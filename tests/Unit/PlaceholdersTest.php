<?php

namespace Tests\Unit;

use App\Support\Placeholders;
use Tests\TestCase;

class PlaceholdersTest extends TestCase
{
    public function test_it_fills_a_known_token(): void
    {
        $this->assertSame(
            'Yusuf and Layla',
            Placeholders::render('{groom} and {bride}', ['groom' => 'Yusuf', 'bride' => 'Layla'])
        );
    }

    /**
     * Someone writing this by hand will type whichever spelling comes to mind,
     * and being strict only produces text with a stray token in it.
     */
    public function test_it_accepts_every_spelling_of_a_token(): void
    {
        foreach (['{bride}', '{bridename}', '{bride_name}'] as $written) {
            $this->assertSame(
                'Layla',
                Placeholders::render($written, ['bride' => 'Layla']),
                "{$written} should resolve to the bride's name."
            );
        }
    }

    public function test_it_ignores_case_and_padding(): void
    {
        $this->assertSame(
            'Layla Layla Layla',
            Placeholders::render('{BRIDE} {Bride} { bride }', ['bride' => 'Layla'])
        );
    }

    /**
     * The rule that matters most: a visible {grom} is a typo someone can see
     * and fix, a silently emptied one ships to a customer's guests.
     */
    public function test_an_unknown_token_is_left_exactly_as_written(): void
    {
        // The known token beside it still resolves — one typo does not stop the
        // rest of the sentence being filled in.
        $this->assertSame(
            'Hello {grom} and Layla',
            Placeholders::render('Hello {grom} and {bride}', ['bride' => 'Layla', 'groom' => 'Yusuf'])
        );
    }

    public function test_a_token_with_no_value_yet_is_left_alone(): void
    {
        $this->assertSame(
            '{venue}',
            Placeholders::render('{venue}', ['venue' => null])
        );

        $this->assertSame(
            '{venue}',
            Placeholders::render('{venue}', ['venue' => ''])
        );
    }

    public function test_it_reports_tokens_nothing_can_fill(): void
    {
        $this->assertSame(
            ['grom', 'colour'],
            Placeholders::unknown('{grom} {bride} {colour} {groom} {grom}')
        );

        $this->assertSame([], Placeholders::unknown('{bride} and {groomname}'));
        $this->assertSame([], Placeholders::unknown(null));
    }

    public function test_arabic_text_around_a_token_survives(): void
    {
        $this->assertSame(
            'تتشرف عائلة يُوسُف بدعوتكم',
            Placeholders::render('تتشرف عائلة {groom} بدعوتكم', ['groom' => 'يُوسُف'])
        );
    }

    /** The browser gets these, and must agree with this class about them. */
    public function test_every_token_publishes_its_aliases_and_a_sample(): void
    {
        $aliases = Placeholders::aliases();
        $samples = Placeholders::samples();

        foreach (Placeholders::all() as $token) {
            $this->assertArrayHasKey($token['token'], $aliases);
            $this->assertContains($token['token'], $aliases[$token['token']],
                'The canonical name must be an accepted spelling of itself.');
            $this->assertNotEmpty($samples[$token['token']] ?? null);
        }
    }

    public function test_it_handles_a_body_with_nothing_in_it(): void
    {
        $this->assertSame('', Placeholders::render(null, []));
        $this->assertSame('', Placeholders::render('', []));
    }
}
