<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Icon Sets
    |--------------------------------------------------------------------------
    |
    | Registers resources/svg as an icon set so the brand mark stays a real
    | .svg file rather than markup pasted into a Blade view. The file carries
    | fill="currentColor", so wherever it is placed it takes the surrounding
    | text colour — one source, every context.
    |
    | resources/svg/lahza-logo.svg  ->  <x-icon-lahza-logo />
    |
    | Heroicons registers its own set through its service provider and is
    | unaffected by anything here.
    |
    */

    'sets' => [
        'default' => [
            'path' => 'resources/svg',
            'prefix' => 'icon',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Attributes
    |--------------------------------------------------------------------------
    |
    | Applied to every icon in every set. Left empty: sizing belongs to the
    | component that uses an icon, not to all icons everywhere.
    |
    */

    'attributes' => [
        //
    ],

];
