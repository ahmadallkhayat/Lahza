<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Provider
    |--------------------------------------------------------------------------
    |
    | Backs the "translate from English" button in the studio.
    |
    | mymemory — no key needed, good for Arabic. It cannot do Sorani Kurdish:
    |            asking for `ku` returns Kurmanji in Latin script, and `ckb`
    |            returns unrelated text from its translation memory. It
    |            therefore declares Arabic only, and Kurdish is refused rather
    |            than filled with something wrong.
    |
    | google   — Cloud Translation v2. Needs a key and billing, and does
    |            support Sorani (ckb). Set TRANSLATION_PROVIDER=google and
    |            GOOGLE_TRANSLATE_KEY to enable Kurdish.
    |
    | Whatever the provider, machine output is a starting point. Every
    | translated field is flagged for review in the form.
    |
    */

    'provider' => env('TRANSLATION_PROVIDER', 'mymemory'),

    'google' => [
        'key' => env('GOOGLE_TRANSLATE_KEY'),
        'endpoint' => 'https://translation.googleapis.com/language/translate/v2',
    ],

    'mymemory' => [
        'endpoint' => 'https://api.mymemory.translated.net/get',
        // Supplying an address raises MyMemory's anonymous rate limit.
        'email' => env('MYMEMORY_EMAIL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */

    // Longest single string sent for translation.
    'max_chars' => 1000,

    // Most strings in one request.
    'max_strings' => 60,

];
