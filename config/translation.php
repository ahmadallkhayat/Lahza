<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Provider
    |--------------------------------------------------------------------------
    |
    | Backs the "translate from English" button in the studio.
    |
    | google-free — the default. No key, and it does handle Sorani Kurdish.
    |               It is the endpoint Google's own translate widget calls, so
    |               there is no SLA and it can be rate-limited or blocked by IP
    |               without notice, and it is not licensed for commercial
    |               volume. Fine for filling drafts in your own studio; move to
    |               `google` before this is a business.
    |
    | google      — Cloud Translation v2, the supported API. Needs a key and
    |               billing. Same coverage, dependable.
    |
    | mymemory    — no key, Arabic only. Kept as a fallback if the free Google
    |               endpoint starts refusing requests. It cannot do Kurdish:
    |               asked for `ku` it answers in Kurmanji, which is Latin
    |               script and the wrong Kurdish; asked for `ckb` it returns
    |               unrelated text out of its translation memory. It therefore
    |               declares Arabic only rather than producing something wrong.
    |
    | Whatever the provider, machine output is a starting point. Every
    | translated field is flagged for review in the form, and a right-to-left
    | language that comes back in Latin script is rejected rather than written
    | into a field.
    |
    */

    'provider' => env('TRANSLATION_PROVIDER', 'google-free'),

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
