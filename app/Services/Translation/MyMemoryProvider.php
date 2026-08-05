<?php

namespace App\Services\Translation;

use Illuminate\Support\Facades\Http;

/**
 * MyMemory — free, no key.
 *
 * Arabic only, deliberately. Asked for `ku` it answers in Kurmanji, which is
 * Latin script and the wrong Kurdish for these pages; asked for `ckb` it
 * returns unrelated text out of its translation memory. Both would put
 * nonsense on a customer's invitation, so Kurdish is declined here and needs
 * the Google provider instead.
 */
class MyMemoryProvider implements TranslationProvider
{
    private const SUPPORTED = ['ar'];

    public function name(): string
    {
        return 'MyMemory';
    }

    public function supports(string $target): bool
    {
        return in_array($target, self::SUPPORTED, true);
    }

    public function translate(array $texts, string $source, string $target): array
    {
        if (! $this->supports($target)) {
            throw new TranslationFailed("MyMemory cannot translate into '{$target}'.");
        }

        $out = [];

        // One string per request; MyMemory has no batch endpoint.
        foreach ($texts as $key => $text) {
            $response = Http::timeout(20)->get(config('translation.mymemory.endpoint'), array_filter([
                'q' => $text,
                'langpair' => $source.'|'.$target,
                'de' => config('translation.mymemory.email'),
            ]));

            if (! $response->successful()) {
                throw new TranslationFailed('MyMemory returned '.$response->status().'.');
            }

            $translated = $response->json('responseData.translatedText');

            // A non-200 responseStatus means the payload is a message, not a
            // translation — writing it into the field would be worse than
            // leaving the field alone.
            if ((int) $response->json('responseStatus') !== 200 || ! is_string($translated)) {
                throw new TranslationFailed('MyMemory could not translate one of the fields.');
            }

            $out[$key] = $translated;
        }

        return $out;
    }
}
