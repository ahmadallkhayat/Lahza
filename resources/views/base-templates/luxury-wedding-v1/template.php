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

    /**
     * Slots that belong together and are edited as one thing.
     *
     * A verse, its translation and its reference are shown together and are
     * useless apart, so they get their own card on the invitation form and
     * their own kind of library asset — one that fills all three at once
     * rather than being found and inserted three times.
     */
    'groups' => [
        'verse' => [
            'label' => 'Verse',
            'description' => 'The religious verse the invitation opens with.',
            'asset_kind' => 'verse',

            // Which part of a verse asset lands in which slot. Declared rather
            // than assumed from the names, so another design can lay a verse
            // out differently without the insert logic knowing about it.
            'fills' => [
                'verse' => 'body',
                'verse_translation' => 'translation',
                'verse_ref' => 'reference',
            ],
        ],
    ],

    'slots' => [
        'verse' => [
            'label' => 'The verse',
            'hint' => 'Leave empty for the design’s own verse. Shown in Arabic in every language.',
            'rows' => 3,
            'default' => 'invitation.verse',
            'arabic' => true,
            'group' => 'verse',
        ],

        'verse_translation' => [
            'label' => 'Translation',
            'hint' => 'Shown small beneath the verse, in that language. Arabic and Kurdish leave it empty by default, since the verse already reads.',
            'rows' => 3,
            'default' => 'invitation.verse_translation',
            'group' => 'verse',
        ],

        'verse_ref' => [
            'label' => 'Reference',
            'hint' => 'The surah and ayah beneath the verse. Leave empty for the default.',
            'default' => 'invitation.verse_ref',
            'group' => 'verse',
        ],
    ],
];
