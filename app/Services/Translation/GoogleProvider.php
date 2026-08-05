<?php

namespace App\Services\Translation;

use Illuminate\Support\Facades\Http;

/**
 * Google Cloud Translation v2.
 *
 * Needs a key and billing, and is the option that can actually do Sorani
 * Kurdish (ckb) — which is why it exists alongside the keyless provider.
 */
class GoogleProvider implements TranslationProvider
{
    /** Locale codes as Google knows them. Sorani is ckb, not ku. */
    private const CODES = ['ar' => 'ar', 'ku' => 'ckb', 'en' => 'en'];

    public function name(): string
    {
        return 'Google Translate';
    }

    public function supports(string $target): bool
    {
        return isset(self::CODES[$target]) && filled(config('translation.google.key'));
    }

    public function translate(array $texts, string $source, string $target): array
    {
        if (! isset(self::CODES[$target])) {
            throw new TranslationFailed("Google Translate has no code mapped for '{$target}'.");
        }

        if (blank(config('translation.google.key'))) {
            throw new TranslationFailed('GOOGLE_TRANSLATE_KEY is not set.');
        }

        // Keys are preserved by position: the API returns translations in the
        // order they were sent.
        $keys = array_keys($texts);

        $response = Http::timeout(30)
            ->asForm()
            ->post(config('translation.google.endpoint'), [
                'key' => config('translation.google.key'),
                'q' => array_values($texts),
                'source' => self::CODES[$source] ?? $source,
                'target' => self::CODES[$target],
                'format' => 'text',
            ]);

        if (! $response->successful()) {
            throw new TranslationFailed(
                'Google Translate returned '.$response->status().': '
                .($response->json('error.message') ?? 'unknown error')
            );
        }

        $translations = $response->json('data.translations', []);

        if (count($translations) !== count($keys)) {
            throw new TranslationFailed('Google Translate returned a different number of strings than were sent.');
        }

        return array_combine(
            $keys,
            array_map(fn ($t) => (string) ($t['translatedText'] ?? ''), $translations)
        );
    }
}
