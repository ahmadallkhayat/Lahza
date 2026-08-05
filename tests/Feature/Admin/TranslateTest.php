<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Translation\GoogleProvider;
use App\Services\Translation\MyMemoryProvider;
use App\Services\Translation\TranslationProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The endpoint's contract, and MyMemory's behaviour specifically — it is the
 * keyless fallback and the one that has to decline Kurdish. The default
 * provider is covered by GoogleFreeTranslationTest.
 */
class TranslateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['translation.provider' => 'mymemory']);
        $this->actingAs(User::factory()->create());
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'source' => 'en',
            'targets' => ['ar'],
            'texts' => ['text:venue_name' => 'Babylon Hall'],
        ], $overrides);
    }

    public function test_it_requires_authentication(): void
    {
        auth()->logout();

        $this->postJson('/admin/translate', $this->payload())->assertUnauthorized();
    }

    public function test_it_translates_into_arabic(): void
    {
        Http::fake([
            'api.mymemory.translated.net/*' => Http::response([
                'responseStatus' => 200,
                'responseData' => ['translatedText' => 'قاعة بابل'],
            ]),
        ]);

        $this->postJson('/admin/translate', $this->payload())
            ->assertOk()
            ->assertJsonPath('translations.ar.text:venue_name', 'قاعة بابل')
            ->assertJsonPath('declined', []);
    }

    /**
     * The important one. MyMemory answers Kurdish requests with Kurmanji in
     * Latin script, or with unrelated text out of its translation memory.
     * Either would land nonsense on a customer's invitation, so it must decline
     * rather than return something.
     */
    public function test_kurdish_is_declined_rather_than_filled_wrongly(): void
    {
        Http::fake(); // any call at all would be a failure here

        $response = $this->postJson('/admin/translate', $this->payload([
            'targets' => ['ar', 'ku'],
        ]));

        $response->assertOk()
            ->assertJsonMissingPath('translations.ku')
            ->assertJsonPath('declined.ku', fn ($reason) => str_contains($reason, 'Kurdish')
                && str_contains($reason, 'translation key'));
    }

    public function test_the_provider_itself_refuses_kurdish(): void
    {
        $provider = new MyMemoryProvider;

        $this->assertTrue($provider->supports('ar'));
        $this->assertFalse($provider->supports('ku'));
        $this->assertFalse($provider->supports('ckb'));
    }

    public function test_a_doubtful_response_is_not_written_into_the_field(): void
    {
        // MyMemory signals trouble with a non-200 responseStatus while still
        // returning a body — that body is a message, not a translation.
        Http::fake([
            'api.mymemory.translated.net/*' => Http::response([
                'responseStatus' => 403,
                'responseData' => ['translatedText' => 'INVALID LANGUAGE PAIR'],
            ]),
        ]);

        $this->postJson('/admin/translate', $this->payload())
            ->assertOk()
            ->assertJsonMissingPath('translations.ar')
            ->assertJsonPath('declined.ar', fn ($reason) => filled($reason));
    }

    public function test_an_unreachable_provider_is_reported_not_fatal(): void
    {
        Http::fake(fn () => throw new \RuntimeException('network down'));

        $this->postJson('/admin/translate', $this->payload())
            ->assertOk()
            ->assertJsonMissingPath('translations.ar')
            ->assertJsonPath('declined.ar', 'Could not reach MyMemory.');
    }

    public function test_the_source_language_is_never_translated_into_itself(): void
    {
        Http::fake();

        $this->postJson('/admin/translate', $this->payload(['targets' => ['en']]))
            ->assertOk()
            ->assertJsonPath('translations', [])
            ->assertJsonPath('declined', []);
    }

    public function test_it_validates_its_input(): void
    {
        $this->postJson('/admin/translate', $this->payload(['targets' => ['zz']]))
            ->assertJsonValidationErrors('targets.0');

        $this->postJson('/admin/translate', $this->payload(['texts' => []]))
            ->assertJsonValidationErrors('texts');

        $this->postJson('/admin/translate', $this->payload([
            'texts' => ['a' => str_repeat('x', 1001)],
        ]))->assertJsonValidationErrors('texts.a');
    }

    public function test_google_declares_kurdish_support_only_with_a_key(): void
    {
        config(['translation.google.key' => null]);
        $google = new GoogleProvider;
        $this->assertFalse($google->supports('ku'));

        config(['translation.google.key' => 'test-key']);
        $this->assertTrue($google->supports('ku'));
        $this->assertTrue($google->supports('ar'));
    }

    public function test_the_configured_provider_is_what_gets_resolved(): void
    {
        config(['translation.provider' => 'google']);
        $this->assertInstanceOf(
            GoogleProvider::class,
            $this->app->make(TranslationProvider::class)
        );

        config(['translation.provider' => 'mymemory']);
        $this->assertInstanceOf(
            MyMemoryProvider::class,
            $this->app->make(TranslationProvider::class)
        );
    }
}
