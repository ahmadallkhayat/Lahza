<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    |
    | Occasions a template can belong to. Keys are stored on the record; values
    | are what the studio sees. Adding one is a single line here.
    |
    */

    'categories' => [
        'lux-wedding' => 'Lux Wedding',
        'wedding' => 'Wedding',
        'engagement' => 'Engagement',
        'graduation' => 'Graduation',
        'birthday' => 'Birthday',
    ],

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | Languages an invitation can be authored in. `rtl` drives the page
    | direction; `numerals` picks Arabic-Indic (٠١٢) or Latin (012) digits.
    |
    | ku is Sorani Kurdish — Arabic script, right to left. Kurmanji is Latin
    | and left to right, and would be a separate entry.
    |
    */

    'locales' => [
        'ar' => ['label' => 'Arabic', 'rtl' => true, 'numerals' => 'arab'],
        'ku' => ['label' => 'Kurdish (Sorani)', 'rtl' => true, 'numerals' => 'arab'],
        'en' => ['label' => 'English', 'rtl' => false, 'numerals' => 'latn'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Timezones
    |--------------------------------------------------------------------------
    |
    | Shortlist offered in the studio. Any zone PHP recognises still validates.
    |
    */

    'timezones' => [
        'Asia/Amman',
        'Asia/Baghdad',
        'Asia/Riyadh',
        'Asia/Dubai',
        'Asia/Kuwait',
        'Asia/Qatar',
        'Asia/Bahrain',
        'Asia/Beirut',
        'Asia/Damascus',
        'Asia/Hebron',
        'Africa/Cairo',
        'Europe/Istanbul',
        'UTC',
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload limits (kilobytes)
    |--------------------------------------------------------------------------
    |
    | Both together must stay under php.ini's post_max_size.
    |
    */

    'max_video_kb' => 100000,
    'max_music_kb' => 51200,

    /*
    |--------------------------------------------------------------------------
    | Palette extraction
    |--------------------------------------------------------------------------
    |
    | Colours can be derived from a template's intro video. Frames are sampled
    | with ffmpeg and read with GD. If the binaries are not on the machine the
    | extraction is skipped and the colours entered in the form are kept.
    |
    */

    'ffmpeg' => env('FFMPEG_PATH', 'ffmpeg'),
    'ffprobe' => env('FFPROBE_PATH', 'ffprobe'),
    'palette_frames' => 6,

];
