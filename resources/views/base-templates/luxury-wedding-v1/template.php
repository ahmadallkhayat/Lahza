<?php

/**
 * What this design is, and what it lets an invitation say.
 *
 * A base template is a directory: show.blade.php is the design, this file
 * declares the content it can carry. Everything downstream reads it — the
 * fields on the invitation form, the categories a text asset can be filed
 * under, and what the guest page renders. Adding a slot here makes it appear
 * in all three, which is the point: a field declared in four hand-written
 * lists is a field that drifts out of them.
 *
 * Slots are the design's own content. The core of a wedding invitation — the
 * names, the date, the venue, the programme — is not here, because every
 * design has it and it lives in columns of its own.
 *
 * Each slot understands:
 *
 *   label    what the form calls it
 *   hint     shown under the label; say what leaving it empty does
 *   rows     textarea of this height, or omit for a single-line input
 *   default  translation key used when the invitation leaves it empty, so a
 *            slot can ship with something sensible already in it
 *   arabic   the value is Arabic in every language, like the names — the form
 *            then offers one box rather than one per language
 */

return [
    'label' => 'Luxury Wedding V1',

    'slots' => [
        'verse' => [
            'label' => 'Verse',
            'hint' => 'Leave empty for the design’s own verse. Shown in Arabic in every language.',
            'rows' => 3,
            'default' => 'invitation.verse',
            'arabic' => true,
        ],

        'verse_translation' => [
            'label' => 'Verse translation',
            'hint' => 'Shown small beneath the verse, in that language. Arabic and Kurdish leave it empty by default, since the verse already reads.',
            'rows' => 3,
            'default' => 'invitation.verse_translation',
        ],

        'verse_ref' => [
            'label' => 'Verse reference',
            'hint' => 'The surah and ayah beneath the verse. Leave empty for the default.',
            'default' => 'invitation.verse_ref',
        ],
    ],
];
