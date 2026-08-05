<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | Languages an invitation can be issued in. One invitation may use several,
    | each getting its own guest link.
    |
    | `rtl` drives the page direction. `numerals` picks Arabic-Indic (٠١٢) or
    | Latin (012) digits. `label` is what the studio sees; `short` is what a
    | guest sees on the language switcher, in that language's own script.
    |
    | ku is Sorani Kurdish — Arabic script, right to left. Kurmanji is Latin
    | and left to right, and would be a separate entry.
    |
    | Adding a locale here also needs a lang/<code>/invitation.php file and the
    | code adding to the route constraint in routes/web.php.
    |
    */

    'locales' => [
        'ar' => ['label' => 'Arabic', 'short' => 'ع', 'rtl' => true, 'numerals' => 'arab'],
        'ku' => ['label' => 'Kurdish (Sorani)', 'short' => 'کو', 'rtl' => true, 'numerals' => 'arab'],
        'en' => ['label' => 'English', 'short' => 'EN', 'rtl' => false, 'numerals' => 'latn'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Timezones
    |--------------------------------------------------------------------------
    |
    | Shortlist offered in the studio, Baghdad first as the default. Any zone
    | PHP recognises still validates.
    |
    */

    'default_timezone' => 'Asia/Baghdad',

    'timezones' => [
        'Asia/Baghdad',
        'Asia/Amman',
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
