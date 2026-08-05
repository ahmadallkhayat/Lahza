<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Translation\GoogleFreeProvider;
use App\Services\Translation\TranslationFailed;
use App\Services\Translation\TranslationProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleFreeTranslationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['translation.provider' => 'google-free']);
        $this->actingAs(User::factory()->create());
    }

    /** The endpoint's shape: [[[chunk, original, ...], ...], ...] */
    private function reply(string ...$chunks): array
    {
        return [array_map(fn ($c) => [$c, 'source', null, null, 1], $chunks)];
    }

    public function test_it_is_the_default_provider(): void
    {
        config(['translation.provider' => null]);

        $this->assertInstanceOf(
            GoogleFreeProvider::class,
            $this->app->make(TranslationProvider::class)
        );
    }

    public function test_it_supports_kurdish_without_a_key(): void
    {
        $provider = new GoogleFreeProvider;

        // The whole reason it is the default.
        $this->assertTrue($provider->supports('ku'));
        $this->assertTrue($provider->supports('ar'));
        $this->assertFalse($provider->supports('zz'));
    }

    public function test_it_maps_kurdish_to_ckb_not_ku(): void
    {
        Http::fake(['translate.googleapis.com/*' => Http::response($this->reply('هۆڵی بابل'))]);

        (new GoogleFreeProvider)->translate(['v' => 'Babylon Hall'], 'en', 'ku');

        // Sorani is ckb to Google; asking for `ku` would return Kurmanji.
        Http::assertSent(fn ($request) => $request['tl'] === 'ckb');
    }

    public function test_it_fills_kurdish_and_arabic_through_the_endpoint(): void
    {
        Http::fake([
            'translate.googleapis.com/*' => Http::response($this->reply('هۆڵی بابل')),
        ]);

        $this->postJson('/admin/translate', [
            'source' => 'en',
            'targets' => ['ar', 'ku'],
            'texts' => ['text:venue_name' => 'Babylon Hall'],
        ])
            ->assertOk()
            ->assertJsonPath('translations.ar.text:venue_name', 'هۆڵی بابل')
            ->assertJsonPath('translations.ku.text:venue_name', 'هۆڵی بابل')
            ->assertJsonPath('declined', []);
    }

    public function test_long_text_split_across_chunks_is_rejoined(): void
    {
        Http::fake([
            'translate.googleapis.com/*' => Http::response(
                $this->reply('بنەماڵەی ئەلسالم ', 'داوای شەرەفی ئامادەبوونتان دەکەن')
            ),
        ]);

        $result = (new GoogleFreeProvider)->translate(['h' => 'long line'], 'en', 'ku');

        $this->assertSame('بنەماڵەی ئەلسالم داوای شەرەفی ئامادەبوونتان دەکەن', $result['h']);
    }

    /**
     * The guard that matters. Kurmanji drift — Kurdish requested, Latin script
     * returned — is exactly what made the other keyless provider unusable, so a
     * right-to-left language with no Arabic characters must never reach a field.
     */
    public function test_latin_script_for_a_kurdish_request_is_rejected(): void
    {
        Http::fake([
            'translate.googleapis.com/*' => Http::response(
                $this->reply('Malbata El-Salem daxwaza rûmetê dike')
            ),
        ]);

        $this->expectException(TranslationFailed::class);
        $this->expectExceptionMessage('wrong script');

        (new GoogleFreeProvider)->translate(['h' => 'The Al-Salem family'], 'en', 'ku');
    }

    public function test_latin_output_is_fine_for_english(): void
    {
        Http::fake(['translate.googleapis.com/*' => Http::response($this->reply('Babylon Hall'))]);

        $result = (new GoogleFreeProvider)->translate(['v' => 'قاعة بابل'], 'ar', 'en');

        $this->assertSame('Babylon Hall', $result['v']);
    }

    public function test_an_empty_or_malformed_response_is_rejected(): void
    {
        foreach ([[], [[]], ['not-an-array'], [[['']]]] as $body) {
            Http::fake(['translate.googleapis.com/*' => Http::response($body)]);

            try {
                (new GoogleFreeProvider)->translate(['v' => 'Babylon Hall'], 'en', 'ar');
                $this->fail('A malformed response was accepted.');
            } catch (TranslationFailed) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_being_blocked_is_reported_rather_than_fatal(): void
    {
        // The endpoint is unofficial; a 429 or 403 is a realistic outcome.
        Http::fake(['translate.googleapis.com/*' => Http::response('', 429)]);

        $this->postJson('/admin/translate', [
            'source' => 'en',
            'targets' => ['ku'],
            'texts' => ['text:venue_name' => 'Babylon Hall'],
        ])
            ->assertOk()
            ->assertJsonMissingPath('translations.ku')
            ->assertJsonPath('declined.ku', fn ($reason) => str_contains($reason, '429'));
    }

    public function test_it_sends_a_browser_user_agent(): void
    {
        // A default PHP user agent gets a 403 from this endpoint.
        Http::fake(['translate.googleapis.com/*' => Http::response($this->reply('قاعة بابل'))]);

        (new GoogleFreeProvider)->translate(['v' => 'Babylon Hall'], 'en', 'ar');

        Http::assertSent(fn ($request) => str_contains($request->header('User-Agent')[0] ?? '', 'Mozilla/5.0'));
    }
}
