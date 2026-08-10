<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Add diacritics (tashkeel/harakat) to Arabic text.
 *
 * Uses the Tarjim.com free API to add vowel marks to Arabic text.
 * Falls back gracefully if the API is unavailable.
 */
class ArabicDiacriticizer
{
    private const TIMEOUT = 8;

    public function diacritize(string $text): ?string
    {
        if (blank($text)) {
            return null;
        }

        // Try the free tarjim.com diacritization API
        $diacritized = $this->tryTarjimApi($text);
        if ($diacritized !== null) {
            return $diacritized;
        }

        // Fallback: try mishkal.ai with different format
        $diacritized = $this->tryMishkalApi($text);
        if ($diacritized !== null) {
            return $diacritized;
        }

        return null;
    }

    private function tryTarjimApi(string $text): ?string
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->post('https://api.tarjim.com/v1/diacritic', [
                    'text' => $text,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['diacritized']) && ! blank($data['diacritized'])) {
                    return $data['diacritized'];
                }
                if (isset($data['result']) && ! blank($data['result'])) {
                    return $data['result'];
                }
            }
        } catch (\Exception $e) {
            Log::debug('Tarjim API diacritization failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    private function tryMishkalApi(string $text): ?string
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->asForm()
                ->post('https://mishkal.ai/api/v2/diacritize', [
                    'text' => $text,
                    'use_case' => 'default',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['diacritized']) && ! blank($data['diacritized'])) {
                    return $data['diacritized'];
                }
            }
        } catch (\Exception $e) {
            Log::debug('Mishkal API diacritization failed', ['error' => $e->getMessage()]);
        }

        return null;
    }
}
