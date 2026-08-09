@php
    /**
     * Luxury Wedding V1.
     *
     * Ported from archive/lux-wedding/invitation.html. Everything that was
     * hardcoded there now arrives as data: colours and timings from the
     * template record, content from the invitation, labels from lang files.
     *
     * Split into partials/ because the design's markup, styling and behaviour
     * are each substantial on their own — this file only sets up the data
     * they all read and stitches them together in document order.
     */
    $p = $template->palette();
    $s = $template->settings();

    $rtl = $invitation->isRtl();
    $locale = $invitation->locale();

    // One link per language. The switcher only appears when there is a choice.
    $otherLocales = collect($invitation->localeList())->reject(fn ($l) => $l === $locale);

    $localeUrl = fn (string $l) => $l === $invitation->defaultLocale()
        ? route('invitations.show', $invitation)
        : route('invitations.show.locale', [$invitation, $l]);

    $calendarUrl = $locale === $invitation->defaultLocale()
        ? route('invitations.calendar', $invitation)
        : route('invitations.calendar.locale', [$invitation, $locale]);

    // Names and the verse are Arabic in every locale, so Aref Ruqaa and Amiri
    // always load. Kurdish must not use Cairo — Cairo has no glyphs for
    // ڕ ڵ ۆ ە ێ, and a fallback face supplying only those five letters breaks
    // the cursive joins.
    $fonts = ['amiri', 'aref-ruqaa'];
    $fonts[] = match ($locale) {
        'en' => 'cormorant-garamond',
        'ku' => 'noto-sans-arabic',
        default => 'cairo',
    };

    // Self-hosted by `php artisan fonts:fetch`. Falls back to the CDN so a
    // checkout that has not run it still renders correctly.
    $selfHosted = collect($fonts)->every(fn ($f) => file_exists(public_path("fonts/{$f}.css")));

    $cdnSpec = [
        'amiri' => 'amiri:400,700',
        'aref-ruqaa' => 'aref-ruqaa:400,700',
        'cairo' => 'cairo:300,400,600',
        'noto-sans-arabic' => 'noto-sans-arabic:300,400,600',
        'cormorant-garamond' => 'cormorant-garamond:300,400,400i',
    ];

    $latinFont = "'Cormorant Garamond', Georgia, serif";
    $uiFont = match ($locale) {
        'en' => $latinFont,
        'ku' => "'Noto Sans Arabic', system-ui, sans-serif",
        default => "'Cairo', system-ui, sans-serif",
    };

    /* ── Falling petals ───────────────────────────────────────────────
       The shape is a mask, not an image, so its colour comes from CSS and one
       file serves every palette. Inlined as a data URI to keep it a single
       request and to let the swap be nothing more than replacing the file.

       Tints are drawn from the palette rather than picked freely: these fall
       over a theme whose colours came from the couple's own video, and anything
       chosen outside that clashes with it. */
    $petalSvg = resource_path('svg/petal.svg');
    $petal = file_exists($petalSvg)
        ? 'data:image/svg+xml;base64,'.base64_encode(file_get_contents($petalSvg))
        : null;

    $petalTints = ['gold', 'gold_deep', 'paper_deep', 'ink_soft'];

    // Randomised per render, so no two openings drift the same way. Seeded by
    // nothing on purpose — the variation is the point, not reproducibility.
    $petals = collect(range(0, 25))->map(fn ($i) => [
        'x' => mt_rand(-4, 104),
        'size' => mt_rand(9, 20),
        'dur' => mt_rand(11, 24),
        // Negative delays start the drift mid-fall, so the first frame already
        // has petals in the air rather than a row of them waiting at the top.
        'delay' => -mt_rand(0, 22),
        'tint' => $petalTints[$i % count($petalTints)],
        'drift' => mt_rand(8, 22) / 10,
        'spin' => mt_rand(14, 40),
    ]);

    $programme = $invitation->programmeRows();
    // Slots this design declares in its template.php. Each is the
    // invitation's own wording where it gave one, and the design's default
    // where it did not — so nothing here has to know which it got.
    $verse = trim($invitation->slot('verse'));
    $verseTranslation = trim($invitation->slot('verse_translation'));
    $verseRef = trim($invitation->slot('verse_ref'));
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}" translate="no">
<head>
<meta charset="utf-8">

@include('base-templates.luxury-wedding-v1.partials._meta')

@include('base-templates.luxury-wedding-v1.partials._styles')
</head>
<body>

{{-- Marks an unpublished preview. Every base template should include this,
     or a draft looks identical to a live invitation. --}}
@include('public._draft-notice')

@include('base-templates.luxury-wedding-v1.partials._backdrop')

@include('base-templates.luxury-wedding-v1.partials._curtain')

@include('base-templates.luxury-wedding-v1.partials._petals')

@include('base-templates.luxury-wedding-v1.partials._music')

@include('base-templates.luxury-wedding-v1.partials._language-switcher')

@include('base-templates.luxury-wedding-v1.partials._scroll-cue')

@include('base-templates.luxury-wedding-v1.partials._content')

@include('base-templates.luxury-wedding-v1.partials._script')
</body>
</html>
