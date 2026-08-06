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
    | null means no limit, which is how production is configured — the server
    | sets post_max_size = 0 and nginx's client_max_body_size = 0 to match.
    |
    | Setting a number here does NOT raise the ceiling on its own: PHP and
    | nginx reject an oversized body before Laravel ever sees it, so any value
    | must stay under those two or the studio gets a bare 413 instead of a
    | proper validation message.
    |
    */

    'max_video_kb' => null,
    'max_music_kb' => null,

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
