<?php

use App\Models\Asset;

/**
 * What this design is, what it paints with, how it moves, and what it lets an
 * invitation say.
 *
 * A base template is a directory: show.blade.php is the design, this file
 * declares everything about it that anything outside the directory needs to
 * know. Everything downstream reads it — the studio's catalogue, the colours
 * card, the timings card, the invitation form, and what the guest page
 * renders. Declaring something here makes it appear in all of them, which is
 * the point: a field declared in four hand-written lists is a field that
 * drifts out of them.
 *
 * Four sections, and a design may omit any of them:
 *
 *   label      what the studio calls this design
 *   category   the occasion it is for; groups the catalogue
 *   palette    the colour roles it paints with
 *   settings   the timings it animates on
 *   groups     slots edited together as one thing
 *   slots      the design's own content
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

    'category' => 'Wedding',

    /**
     * The colour roles this design paints with, and what the colours card
     * calls each one.
     *
     * These particular seven are also what VideoPaletteExtractor produces, so
     * "Generate from video" fills all of them. A design free to name roles of
     * its own should expect only the shared ones to be filled that way.
     */
    'palette' => [
        'paper' => ['label' => 'Page background', 'default' => '#f7f1e8'],
        'paper_deep' => ['label' => 'Panel fill', 'default' => '#ede1d0'],
        'ink' => ['label' => 'Primary text', 'default' => '#2e1d16'],
        'ink_soft' => ['label' => 'Secondary text', 'default' => '#6b5648'],
        'gold' => ['label' => 'Accent', 'default' => '#a8874f'],
        'gold_deep' => ['label' => 'Accent (text safe)', 'default' => '#856a3c'],
        'curtain' => ['label' => 'Intro backdrop', 'default' => '#1a0f0d'],
    ],

    /**
     * How the intro hands over to the invitation.
     *
     * A timing with a `label` is one the studio can change, and it appears on
     * the timings card with these bounds enforced. One without a label is
     * this design's own business: it still reaches the page as CONFIG, but
     * nothing offers it and no request can set it. Declaring `step` makes it
     * a decimal; everything else is a whole number of milliseconds.
     */
    'settings' => [
        'keepVideoAudioMs' => [
            'label' => 'Keep video audio',
            'hint' => 'ms before muting the clip. 0 keeps it silent throughout.',
            'default' => 0,
            'min' => 0,
            'max' => 60000,
        ],

        'musicFadeMs' => [
            'label' => 'Music fade in',
            'hint' => 'ms for the music to reach full volume.',
            'default' => 1600,
            'min' => 0,
            'max' => 60000,
        ],

        'fadeOutLeadMs' => [
            'label' => 'Dissolve lead',
            'hint' => 'ms before the clip ends that the crossfade begins.',
            'default' => 800,
            'min' => 0,
            'max' => 60000,
        ],

        // Larger than fadeOutLeadMs on purpose: the difference is how long the
        // names have the video to themselves before it starts dissolving.
        'namesLeadMs' => [
            'label' => 'Names lead',
            'hint' => 'ms before the clip ends that the names appear over it.',
            'default' => 2600,
            'min' => 0,
            'max' => 60000,
        ],

        // How long the softened backdrop has the screen to itself before the
        // rest of the invitation arrives. This is the gap that makes the blur
        // its own beat: the names sit over the clip, the clip softens, and only
        // then does the content assemble. Set it to 0 and the blur happens
        // behind arriving content, which is what it used to do.
        'blurLeadMs' => [
            'label' => 'Blur hold',
            'hint' => 'ms the softened clip holds alone before the content arrives.',
            'default' => 1200,
            'min' => 0,
            'max' => 60000,
        ],

        'crossfadeMs' => [
            'label' => 'Dissolve length',
            'hint' => 'ms for the video to dissolve into the invitation.',
            'default' => 1400,
            'min' => 100,
            'max' => 60000,
        ],

        'duckTo' => [
            'label' => 'Music duck level',
            'hint' => '0–1. Volume once the invitation is showing.',
            'default' => 0.42,
            'min' => 0,
            'max' => 1,
            'step' => 0.01,
        ],

        'fogHz' => [
            'label' => 'Muffle cutoff',
            'hint' => 'Hz low-pass after the reveal. Lower sounds more distant.',
            'default' => 700,
            'min' => 100,
            'max' => 20000,
        ],

        'duckMs' => [
            'label' => 'Settle duration',
            'hint' => 'ms for the music to pull back and blur.',
            'default' => 2600,
            'min' => 0,
            'max' => 60000,
        ],

        'revealFallbackMs' => [
            'label' => 'Safety fallback',
            'hint' => 'ms after which the invitation shows even if the clip never plays.',
            'default' => 13000,
            'min' => 1000,
            'max' => 120000,
        ],

        // Unlabelled, so the studio never sees them — both are this design's
        // own safety margins rather than anything worth tuning per theme.

        // How long petals keep falling after the reveal before thinning out.
        'petalsMs' => ['default' => 22000],

        // Longest the mark holds before the prompt appears regardless. A clip
        // that cannot be reached must not leave a guest waiting on a spinner.
        'loaderTimeoutMs' => ['default' => 8000],
    ],

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
            'hint' => 'Shown small beneath the verse, in that language.',
            'rows' => 3,
            'default' => 'invitation.verse_translation',
            'group' => 'verse',

            // Read off the model rather than repeated here, so the asset
            // editor and this card cannot come to disagree about which
            // languages print a translation. Arabic and Kurdish read the verse
            // as it is; only English prints one beneath.
            'except' => Asset::VERSE_UNTRANSLATED,
        ],

        'verse_ref' => [
            'label' => 'Reference',
            'hint' => 'The surah and ayah beneath the verse. Leave empty for the default.',
            'default' => 'invitation.verse_ref',
            'group' => 'verse',
        ],
    ],
];
