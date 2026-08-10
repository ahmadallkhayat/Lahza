<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Add diacritics (tashkeel/harakat) to Arabic text.
 *
 * Uses the Mishkal API (free tier) to add vowel marks to Arabic text.
 * Falls back gracefully if the API is unavailable.
 */
class ArabicDiacriticizer
{
    private const API_URL = 'https://api.mishkal.ai/diacritize';

    private const TIMEOUT = 5;

    public function diacritize(string $text): ?string
    {
        if (blank($text)) {
            return null;
        }

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->post(self::API_URL, ['text' => $text]);

            if ($response->successful() && $response->has('diacritized')) {
                return $response->json('diacritized');
            }

            return null;
        } catch (\Exception) {
            return null;
        }
    }
}
