<?php

namespace App\Services\Translation;

use Illuminate\Support\Facades\Http;

/**
 * Google's public web-widget translation endpoint. No key, and it does handle
 * Sorani Kurdish, which is why it is the default.
 *
 * Caveats, deliberately stated rather than buried:
 *   - It is the endpoint Google's own translate widget calls, not a published
 *     API. There is no SLA, and it can be rate-limited or blocked by IP
 *     without notice.
 *   - Nothing here is licensed for commercial volume. Before this is a
 *     business, move to TRANSLATION_PROVIDER=google with a real key — the
 *     GoogleProvider next door speaks the supported API.
 *
 * Quality was checked against invitation copy: "Babylon Hall" comes back as
 * هۆڵی بابل and "Baghdad, Iraq" as بەغدا، عێراق, both matching Kurdish written
 * by hand for the seeder.
 */
class GoogleFreeProvider implements TranslationProvider
{
    private const ENDPOINT = 'https://translate.googleapis.com/translate_a/single';

    /** Sorani is ckb to Google, not ku. */
    private const CODES = ['ar' => 'ar', 'ku' => 'ckb', 'en' => 'en'];

    /** The endpoint answers a default PHP user agent with a 403. */
    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
        .'(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    public function name(): string
    {
        return 'Google';
    }

    public function supports(string $target): bool
    {
        return isset(self::CODES[$target]);
    }

    public function translate(array $texts, string $source, string $target): array
    {
        if (! $this->supports($target)) {
            throw new TranslationFailed("No language code mapped for '{$target}'.");
        }

        $out = [];

        // One string per request. Batching is possible with repeated q
        // parameters but the response shape gets ambiguous, and being sure
        // which translation belongs to which field matters more than speed.
        foreach ($texts as $key => $text) {
            $out[$key] = $this->one($text, $source, $target);
        }

        return $out;
    }

    private function one(string $text, string $source, string $target): string
    {
        $response = Http::withHeaders(['User-Agent' => self::UA])
            ->timeout(20)
            ->get(self::ENDPOINT, [
                'client' => 'gtx',
                'sl' => self::CODES[$source] ?? $source,
                'tl' => self::CODES[$target],
                'dt' => 't',
                'q' => $text,
            ]);

        if (! $response->successful()) {
            throw new TranslationFailed('Google returned '.$response->status().'.');
        }

        // Shape is [[[chunk, original, ...], ...], ...] — a long string comes
        // back split across several chunks that have to be rejoined.
        $segments = $response->json('0');

        if (! is_array($segments) || $segments === []) {
            throw new TranslationFailed('Google returned an unexpected response.');
        }

        $translated = '';

        foreach ($segments as $segment) {
            if (! is_array($segment) || ! isset($segment[0]) || ! is_string($segment[0])) {
                throw new TranslationFailed('Google returned an unexpected response.');
            }

            $translated .= $segment[0];
        }

        $translated = trim($translated);

        if ($translated === '') {
            throw new TranslationFailed('Google returned nothing for one of the fields.');
        }

        $this->assertScript($translated, $target);

        return $translated;
    }

    /**
     * Guards the failure mode that made the keyless alternative unusable:
     * asking for Kurdish and getting Kurmanji back in Latin script. If a
     * right-to-left language comes back without a single Arabic-range
     * character, something is wrong and it must not reach a field.
     */
    private function assertScript(string $translated, string $target): void
    {
        if (! config("templates.locales.{$target}.rtl", false)) {
            return;
        }

        if (! preg_match('/[\x{0600}-\x{06FF}]/u', $translated)) {
            throw new TranslationFailed(
                'Google answered in the wrong script for '
                .config("templates.locales.{$target}.label", $target).'.'
            );
        }
    }
}
