@php
    /**
     * Luxury Wedding V1.
     *
     * Ported from archive/lux-wedding/invitation.html. Everything that was
     * hardcoded there now arrives as data: colours and timings from the
     * template record, content from the invitation, labels from lang files.
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
    // always load. Kurdish must not use Cairo ΓÇö Cairo has no glyphs for
    // ┌ò ┌╡ █å █ò █Ä, and a fallback face supplying only those five letters breaks
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

    /* ΓöÇΓöÇ Falling petals ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
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
    // nothing on purpose ΓÇö the variation is the point, not reproducibility.
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
    // where it did not ΓÇö so nothing here has to know which it got.
    $verse = trim($invitation->slot('verse'));
    $verseTranslation = trim($invitation->slot('verse_translation'));
    $verseRef = trim($invitation->slot('verse_ref'));
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{{ $invitation->groom_ar }} &amp; {{ $invitation->bride_ar }}</title>

{{-- WhatsApp is the distribution channel, so the preview card matters. --}}
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $invitation->groom_ar }} & {{ $invitation->bride_ar }}">
<meta property="og:description" content="{{ $invitation->formattedDate() }}{{ $invitation->text('venue_name') ? ' ΓÇö '.$invitation->text('venue_name') : '' }}">
<meta property="og:url" content="{{ $localeUrl($locale) }}">
@if ($invitation->og_image)
    {{-- Absolute: a scraper does not resolve relative URLs. --}}
    <meta property="og:image" content="{{ url($invitation->og_image) }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $invitation->groom_ar }} & {{ $invitation->bride_ar }}">
    <meta name="twitter:card" content="summary_large_image">
@else
    {{-- No card, so no promise of one: summary_large_image without an image
         makes a scraper reserve space it cannot fill. --}}
    <meta name="twitter:card" content="summary">
@endif

<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

@if ($selfHosted)
    @foreach ($fonts as $font)
        <link rel="stylesheet" href="{{ asset("fonts/{$font}.css") }}">
    @endforeach
@else
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family={{ collect($fonts)->map(fn ($f) => $cdnSpec[$f])->implode('&family=') }}&display=swap">
@endif

<style>
:root {
  --paper:      {{ $p['paper'] }};
  --paper-deep: {{ $p['paper_deep'] }};
  --ink:        {{ $p['ink'] }};
  --ink-soft:   {{ $p['ink_soft'] }};
  --gold:       {{ $p['gold'] }};
  --gold-deep:  {{ $p['gold_deep'] }};
  --curtain:    {{ $p['curtain'] }};

  --shell: 30rem;
  --fade: {{ $s['crossfadeMs'] }}ms;

  /* Arrivals decelerate hard and settle ΓÇö a flat `ease` is nearly linear
     through the middle, which is what made every transition feel like it was
     being dragged rather than released. */
  --ease: cubic-bezier(.16, .84, .28, 1);
  --ease-soft: cubic-bezier(.33, 0, .2, 1);

  /* Tracking pulls Arabic and Kurdish letters apart and breaks their
     cursive joins, so it is only switched on for Latin. */
  --tk: {{ $rtl ? 0 : 1 }};

  --f-name:  'Aref Ruqaa', serif;
  --f-serif: 'Amiri', serif;
  --f-ui:    {!! $uiFont !!};
  --f-latin: {!! $latinFont !!};
}

*, *::before, *::after { box-sizing: border-box; }
html, body { margin: 0; padding: 0; }

body {
  background: var(--paper);
  color: var(--ink);
  font-family: var(--f-ui);
  font-weight: 300;
  -webkit-font-smoothing: antialiased;
  overflow-x: hidden;
}

/* Nothing scrolls until the invitation is actually there to read.
   Scrolling behind the curtain showed the whole page through it and gave the
   reveal away. Both elements carry it because iOS Safari honours it on one and
   not the other depending on version, and touch-action stops the rubber band
   that slips through anyway. */
html.held, html.held body {
  overflow: hidden;
  height: 100%;
  touch-action: none;
  overscroll-behavior: none;
}

body::before {
  content: '';
  position: fixed;
  inset: 0;
  pointer-events: none;
  /* Behind the content but above the clip, so the wash tints the video too. */
  z-index: -1;
  background:
    radial-gradient(120% 80% at 50% 0%,   color-mix(in srgb, var(--gold) 28%, transparent), transparent 60%),
    radial-gradient(100% 70% at 50% 100%, color-mix(in srgb, var(--gold) 14%, transparent), transparent 65%);
}

/* ΓöÇΓöÇΓöÇ BACKDROP ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
   The lowest layer in the root stacking context. Everything above it can
   blend against it, which is the whole reason it is not inside the curtain. */
#backdrop {
  position: fixed;
  inset: 0;
  z-index: -2;
  overflow: hidden;
  background: var(--curtain);
}

#backdrop video {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* The intro plays sharp, then hands over. */
#intro { opacity: 0; transition: opacity var(--fade) var(--ease-soft), filter var(--fade) var(--ease-soft); }
#intro.playing { opacity: 1; }

/* Already slowed at encode time, so it plays at rate 1.0 ΓÇö see
   AmbientVideoEncoder. Scaled up so the blur has material to bleed from and
   cannot show a soft edge at the viewport.

   Kept faint on purpose. Any higher and the footage decides the page's colour,
   which wastes the palette derived from it: the point is that the gold and the
   paper still read, with the clip only moving underneath them. */
#ambient {
  opacity: 0;
  transform: scale(1.15);
  filter: blur(30px) saturate(1.1);
  transition: opacity 2s var(--ease);
}
#ambient.on { opacity: .22; }

/* When there is no separate encode the intro clip stands in, blurred in place.
   It has to be slowed in the browser, which judders ΓÇö so it runs nearer real
   time and leans on the blur instead. */
#intro.ambient {
  filter: blur(30px) saturate(1.1);
  transform: scale(1.15);
  opacity: .18;
}

/* ΓöÇΓöÇΓöÇ INTRO ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
   The curtain is now only a scrim and a prompt ΓÇö the clip moved to #backdrop.
   So it has two stages rather than one: on tap its fill clears, uncovering the
   video already playing beneath it, and it keeps blocking touches until the
   reveal. Leaving it opaque until then, as it was, meant the clip played behind
   a solid panel and was never seen. */
#curtain {
  position: fixed;
  inset: 0;
  z-index: 50;
  background: var(--curtain);
  display: grid;
  place-items: center;
  cursor: pointer;
  transition: background-color var(--fade) var(--ease-soft), opacity var(--fade) var(--ease-soft);
}
#curtain.opened { background-color: transparent; }
#curtain.gone { opacity: 0; pointer-events: none; }

#prompt { position: relative; z-index: 2; text-align: center; transition: opacity 420ms var(--ease); }
#prompt.gone { opacity: 0; }

.ring {
  width: 4.5rem; height: 4.5rem;
  margin: 0 auto 1.4rem;
  border: 1px solid var(--gold);
  border-radius: 50%;
  display: grid;
  place-items: center;
  position: relative;
}
.ring::after {
  content: '';
  position: absolute;
  inset: -.55rem;
  border: 1px solid var(--gold);
  border-radius: 50%;
  opacity: .35;
  animation: breathe 2.8s ease-in-out infinite;
}
.ring span { width: .4rem; height: .4rem; background: var(--gold); border-radius: 50%; }
@keyframes breathe {
  0%, 100% { transform: scale(1);    opacity: .35; }
  50%      { transform: scale(1.18); opacity: 0;   }
}

#prompt p {
  margin: 0;
  color: color-mix(in srgb, var(--gold) 55%, white);
  font-size: .9rem;
  letter-spacing: calc(.34em * var(--tk));
  font-weight: 300;
}

/* ΓöÇΓöÇΓöÇ CONTENT ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ */
/* No z-index here on purpose. `position: relative` alone creates no stacking
   context, which is what lets the names inside blend against the clip two
   layers down. Adding one would silently kill the effect. */
main {
  position: relative;
  max-width: var(--shell);
  margin: 0 auto;
  padding: clamp(3rem, 9vh, 5.5rem) 1.6rem 4.5rem;
  text-align: center;
}

/* Every section waits, including the names ΓÇö they are simply released first,
   and in place, so nothing ever moves between the two phases. */
main > * {
  opacity: 0;
  transform: translateY(18px);
  transition: opacity 1s var(--ease), transform 1s var(--ease);
}

/* Phase one: the names alone, centred on the clip.
   Lifted to the middle of the viewport rather than left where they will end up,
   then let go ΓÇö so the reveal is the names travelling to their place as the
   content assembles beneath them, instead of everything appearing at once.

   The transform sits on this element and not on `main`. A transformed ancestor
   becomes a stacking context, which would cut the names off from the clip they
   blend against; on the blended element itself it is harmless.

   :not(.revealed) because both classes are set once the reveal runs, and this
   selector is the more specific of the two. */
/* The section is present so the heading inside it can be, but only the heading
   travels. The line above the names and the rule below them belong to the laid
   out page and arrive with it ΓÇö lifting the whole block moved all three onto
   the clip, which is not what was asked for. */
main.names-in:not(.revealed) .couple-section {
  opacity: 1;
  transform: none;
}

main.names-in:not(.revealed) .couple {
  transform: translateY(var(--lift, 0px));
}

.couple-section > .hosts,
.couple-section > .rule {
  opacity: 0;
  transition: opacity 1s var(--ease) 250ms;
}
main.revealed .couple-section > .hosts,
main.revealed .couple-section > .rule { opacity: 1; }

/* Long and soft: this one move is what the whole sequence is built around. */
.couple { transition: color 1.4s var(--ease-soft), transform 1.6s var(--ease); }
main > .couple-section { transition: opacity 1.1s var(--ease); }

/* Phase two: the rest, staggered. The step is set per element from one place
   rather than a rule per position ΓÇö the old nth-child(1..8) list meant a ninth
   section silently got no delay at all, and nobody would notice until it sat
   there motionless among eight that moved. */
main.revealed > * {
  opacity: 1;
  transform: none;
  transition-delay: calc(var(--i, 0) * 110ms);
}

@keyframes rise {
  from { opacity: 0; transform: translateY(16px); }
  to   { opacity: 1; transform: none; }
}

/* The end time, quieter than the start it follows. */
.at-time .until { color: var(--ink-soft); }

/* ΓöÇΓöÇΓöÇ SCROLL CUE ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
   The invitation runs well past one screen and nothing said so, which on a
   phone means the names and the date can read as the whole of it. Fades out on
   the first scroll ΓÇö a cue that stays after being acted on is just decoration. */
#more {
  position: fixed;
  inset-inline: 0;
  bottom: 1.1rem;
  z-index: 30;
  display: grid;
  place-items: center;
  gap: .3rem;
  pointer-events: none;
  opacity: 0;
  transition: opacity 900ms var(--ease);
}
#more.on { opacity: .75; }

#more svg {
  width: 1.15rem;
  height: 1.15rem;
  stroke: var(--gold-deep);
  stroke-width: 1.5;
  fill: none;
  animation: nudge 2.2s ease-in-out infinite;
}

@keyframes nudge {
  0%, 100% { transform: translateY(0); opacity: .55; }
  50%      { transform: translateY(5px); opacity: 1; }
}

/* ΓöÇΓöÇΓöÇ PETALS ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
   Above the invitation, below the curtain, and untouchable. They start with the
   reveal and thin out after it: continuous fall is livelier for the first look,
   but it never stops being motion in the corner of the eye, and someone reading
   a programme should not be doing it in weather. */
#petals {
  position: fixed;
  inset: 0;
  z-index: 40;
  overflow: hidden;
  pointer-events: none;
  opacity: 0;
  transition: opacity 2.4s var(--ease-soft);
}
#petals.on { opacity: 1; }

.petal {
  position: absolute;
  top: calc(var(--size) * -2);
  left: var(--x);
  width: var(--size);
  height: var(--size);
  animation: fall var(--dur) linear var(--delay) infinite;
  will-change: transform;
}

/* The mask is what makes one file work in every colour: the shape comes from
   the SVG, the colour from `background`. As an <img> it would need a copy per
   tint, and the tints come from a palette that is different per template. */
.petal i {
  display: block;
  width: 100%;
  height: 100%;
  background: var(--tint);
  opacity: .5;
  -webkit-mask: url('{{ $petal }}') center / contain no-repeat;
  mask: url('{{ $petal }}') center / contain no-repeat;
  animation: sway calc(var(--dur) / 3) ease-in-out var(--delay) infinite alternate;
  will-change: transform;
}

@keyframes fall {
  from { transform: translateY(0); }
  to   { transform: translateY(115vh); }
}

@keyframes sway {
  from { transform: translateX(calc(var(--drift) * -1)) rotate(calc(var(--spin) * -1)); }
  to   { transform: translateX(var(--drift)) rotate(var(--spin)); }
}

/* ΓöÇΓöÇΓöÇ GLASS ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
   Only panes get glass, and there are two of them.

   Every section used to be one, which is what made the page read as a stack of
   boxes: a venue is two lines and a hosting line is one, and neither fills a
   card ΓÇö they just sat in the middle of an empty pane looking accidental. So
   the content is grouped into the two panes that have something to hold, and
   the verse, the names and the actions sit on open paper where they belong.

   The fill is `paper` at 62%, and that number is load-bearing. Every colour in
   a generated palette is contrast-checked against solid `paper`, and a moving
   image behind text voids that guarantee ΓÇö whatever frame is passing decides
   the ratio. Keeping the pane mostly paper holds the measured contrast to
   within a few percent of what was promised. Thinner glass looks better in a
   screenshot and fails on a bright frame. */
section { margin-block: 2.6rem; }

.pane {
  position: relative;
  padding: 2.2rem 1.6rem;
  border-radius: 1.75rem;
  background: color-mix(in srgb, var(--paper) 72%, transparent);
  border: 1px solid color-mix(in srgb, var(--gold) 22%, transparent);
  box-shadow:
    0 1px 0 color-mix(in srgb, #fff 45%, transparent) inset,
    0 22px 50px -28px color-mix(in srgb, var(--curtain) 55%, transparent);
}

@supports (backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px)) {
  .pane {
    -webkit-backdrop-filter: blur(20px) saturate(1.3);
    backdrop-filter: blur(20px) saturate(1.3);
    background: color-mix(in srgb, var(--paper) 62%, transparent);
  }
}

/* Separates the two halves of a pane without implying a new card. */
.divide {
  height: 1px;
  margin: 1.9rem auto;
  max-width: 8rem;
  background: linear-gradient(
    to right,
    transparent,
    color-mix(in srgb, var(--gold) 45%, transparent),
    transparent
  );
}

main > .couple-section {
  transition:
    opacity 900ms ease,
    transform 900ms ease,
    background-color 1.2s ease,
    border-color 1.2s ease;
}

.label {
  font-size: .84rem;
  letter-spacing: calc(.26em * var(--tk));
  color: var(--ink-soft);
  margin: 0 0 .7rem;
  font-weight: 400;
}

/* Verse: Arabic always, translation beneath it when present.
   Bold and set larger than anything but the names ΓÇö it opens the invitation and
   was reading as a caption. Amiri ships a 700 weight, so this is the real face
   rather than a browser-synthesised smear. */
.verse-ar {
  font-family: var(--f-serif);
  font-size: clamp(1.2rem, 4.8vw, 1.42rem);
  font-weight: 700;
  line-height: 2.1;
  color: var(--gold-deep);
  margin: 0 auto;
  max-width: 25rem;
}
.verse-en {
  font-family: var(--f-latin);
  font-style: italic;
  font-size: 1rem;
  line-height: 1.85;
  color: var(--ink-soft);
  margin: 1rem auto 0;
  max-width: 23rem;
}
.verse cite {
  display: block;
  font-family: var(--f-ui);
  font-style: normal;
  font-size: .8rem;
  letter-spacing: calc(.2em * var(--tk));
  color: var(--ink-soft);
  margin-top: 1rem;
}

.hosts {
  font-family: var(--f-serif);
  font-size: 1.1rem;
  line-height: 2;
  color: var(--ink-soft);
  margin: 0 auto;
  max-width: 23rem;
}
html[lang="en"] .hosts { font-family: var(--f-latin); font-size: 1.18rem; line-height: 1.85; }

/* Couple names ΓÇö Arabic in every locale. */
.couple {
  margin: 0;
  font-family: var(--f-name);
  font-size: clamp(2.5rem, 13vw, 3.9rem);
  /* Ruqaa throws swashes well above the baseline; leading stops clipping. */
  line-height: 1.7;
  color: var(--ink);
  font-weight: 400;
}

/* ΓöÇΓöÇ Names over the clip ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
   While the video is still running the names read as the inverse of whatever
   is behind them: white on a dark frame, black on a bright one, changing as
   the footage does. `difference` against white gives exactly that, per pixel
   and per frame, with no sampling and nothing to keep in sync.

   This is why the clip sits at the bottom of the root stacking context and
   why `main` carries no z-index ΓÇö blending only reaches what is painted below
   it in the same context.

   The gold ampersand is exempt: inverting it turns it a muddy cyan. It holds
   its own colour and simply fades in with the rest. */
main.blending .couple {
  color: #fff;
  mix-blend-mode: difference;
}
main.blending .couple .amp { mix-blend-mode: normal; }

/* Where blending is unavailable the names just use their own colour. Worth
   stating rather than leaving to chance: the fallback for `difference` is no
   blending at all, which on a dark frame would be dark ink on dark video. */
@supports not (mix-blend-mode: difference) {
  main.blending .couple { color: var(--paper); mix-blend-mode: normal; }
}
.couple .amp {
  display: block;
  font-family: var(--f-serif);
  font-size: .36em;
  color: var(--gold);
  margin-block: .1em;
  line-height: 1;
}
.couple .latin {
  display: block;
  font-family: var(--f-latin);
  font-size: .26em;
  font-style: normal;
  letter-spacing: .22em;
  color: var(--ink-soft);
  margin-top: -.12em;
  line-height: 1.6;
}

/* Gold hairline with a diamond. Symmetric, so direction-agnostic. */
.rule {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .8rem;
  margin: 2.2rem auto 0;
  max-width: 13rem;
}
.rule::before, .rule::after { content: ''; flex: 1; height: 1px; }
.rule::before { background: linear-gradient(to right, transparent, var(--gold)); }
.rule::after  { background: linear-gradient(to right, var(--gold), transparent); }
.rule i { width: .42rem; height: .42rem; background: var(--gold); transform: rotate(45deg); flex: none; }

.when  { font-size: 1.62rem; margin: 0; font-weight: 400; }
.hijri { font-size: .92rem; color: var(--ink-soft); margin: .4rem 0 0; }
.at-time { font-family: var(--f-serif); font-size: 1.9rem; color: var(--gold-deep); margin: .9rem 0 0; }
.venue { font-size: 1.22rem; margin: 0; line-height: 1.75; }

.countdown { display: flex; justify-content: center; gap: clamp(.5rem, 3.5vw, 1.3rem); }
.unit {
  flex: 1 1 0;
  max-width: 5.4rem;
  padding: .9rem .3rem .75rem;
  background: var(--paper-deep);
  border: 1px solid color-mix(in srgb, var(--gold) 28%, transparent);
  border-radius: .55rem;
}
.unit b {
  display: block;
  font-family: var(--f-serif);
  font-size: 1.95rem;
  font-weight: 400;
  color: var(--gold-deep);
  font-variant-numeric: tabular-nums;
  line-height: 1.15;
}
.unit small {
  display: block;
  font-size: .76rem;
  letter-spacing: calc(.16em * var(--tk));
  color: var(--ink-soft);
  margin-top: .3rem;
}
.begun { font-family: var(--f-serif); font-size: 1.45rem; color: var(--gold-deep); margin: 0; }

/* An evening runs in order, so it is drawn as one: a spine down the times with
   a mark at each moment. Rules between rows made it a table of two columns that
   happened to be sorted. */
.programme { list-style: none; margin: 0 auto; padding: 0; max-width: 20rem; text-align: start; }
.programme li {
  position: relative;
  display: flex;
  align-items: baseline;
  gap: 1rem;
  padding: .75rem 0 .75rem 0;
}

/* The spine, drawn between the marks rather than through them ΓÇö it stops short
   on the last row so the line ends at the final moment instead of trailing off
   into nothing. */
.programme li::before {
  content: '';
  position: absolute;
  inset-inline-start: 4.35rem;
  top: 1.5rem;
  bottom: -.35rem;
  width: 1px;
  background: color-mix(in srgb, var(--gold) 28%, transparent);
}
.programme li:last-child::before { display: none; }

.programme li::after {
  content: '';
  position: absolute;
  inset-inline-start: 4.1rem;
  top: .95rem;
  width: .45rem;
  height: .45rem;
  border-radius: 50%;
  background: var(--gold);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--paper) 80%, transparent);
}

.programme span { padding-inline-start: 1.15rem; }
.programme time {
  flex: none;
  width: 3.8rem;
  font-family: var(--f-serif);
  color: var(--gold-deep);
  font-variant-numeric: tabular-nums;
}
.programme span { font-size: 1.06rem; }

.actions { display: flex; flex-wrap: wrap; justify-content: center; gap: .7rem; }
.btn {
  display: inline-flex;
  align-items: center;
  gap: .5rem;
  padding: .8rem 1.5rem;
  border-radius: 2rem;
  font-family: var(--f-ui);
  font-size: .92rem;
  letter-spacing: calc(.13em * var(--tk));
  text-decoration: none;
  border: 1px solid var(--gold);
  color: var(--gold-deep);
  background: transparent;
  cursor: pointer;
  transition: background 240ms var(--ease), transform 240ms var(--ease);
}
.btn:hover { background: color-mix(in srgb, var(--gold) 12%, transparent); }
.btn.solid { background: var(--gold); color: var(--paper); }
.btn.solid:hover { background: var(--gold-deep); }
.btn svg { width: .95rem; height: .95rem; stroke-width: 1.6; flex: none; }

#mute {
  position: fixed;
  inset-block-end: 1.15rem;
  inset-inline-start: 1.15rem;
  z-index: 40;
  width: 2.75rem; height: 2.75rem;
  display: none;
  place-items: center;
  border-radius: 50%;
  border: 1px solid color-mix(in srgb, var(--gold) 50%, transparent);
  background: color-mix(in srgb, var(--paper) 85%, transparent);
  -webkit-backdrop-filter: blur(8px);   /* Safari / iOS needs the prefix */
  backdrop-filter: blur(8px);
  color: var(--gold-deep);
  cursor: pointer;
}
#mute.on { display: grid; }

/* Both icons occupy the same cell and cross-fade.
   They were grid siblings relying on the `hidden` attribute, which auto-placed
   them into two rows ΓÇö so any moment both were visible showed one squashed
   above the other inside a 2.75rem circle. Stacking them removes the
   possibility rather than trying to time it, and swapping by opacity is
   smoother than swapping by display, which cannot transition at all. */
#mute svg {
  grid-area: 1 / 1;
  width: 1.1rem;
  height: 1.1rem;
  stroke-width: 1.6;
  transition: opacity 260ms var(--ease);
}
#mute #ic-off { opacity: 0; }
#mute.muted #ic-on { opacity: 0; }
#mute.muted #ic-off { opacity: 1; }

/* Belt and braces: an SVG carrying `hidden` should not paint, and relying on
   the UA rule alone is what let this through in the first place. */
[hidden] { display: none !important; }

/* ΓöÇΓöÇΓöÇ Language switcher ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
   Only rendered when the invitation is issued in more than one
   language, and only after the reveal, so it never sits over the
   intro. Deliberately quiet ΓÇö a guest needs it once. */
#langs {
  position: fixed;
  inset-block-start: 1.15rem;
  inset-inline-end: 1.15rem;
  z-index: 40;
  display: none;
  gap: .3rem;
}
#langs.on { display: flex; }
#langs a {
  min-width: 2.1rem;
  padding: .35rem .5rem;
  border-radius: 1rem;
  border: 1px solid color-mix(in srgb, var(--gold) 40%, transparent);
  background: color-mix(in srgb, var(--paper) 80%, transparent);
  -webkit-backdrop-filter: blur(8px);
  backdrop-filter: blur(8px);
  color: var(--gold-deep);
  font-family: var(--f-ui);
  font-size: .8rem;
  text-align: center;
  text-decoration: none;
  line-height: 1.3;
}
#langs a:hover { background: color-mix(in srgb, var(--gold) 16%, var(--paper)); }

footer {
  margin-top: 3.5rem;
  font-size: .82rem;
  letter-spacing: calc(.3em * var(--tk));
  color: var(--ink-soft);
  opacity: .75;
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; }
}
</style>
</head>
<body>

{{-- Marks an unpublished preview. Every base template should include this,
     or a draft looks identical to a live invitation. --}}
@include('public._draft-notice')

{{-- ΓöÇΓöÇ Backdrop ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
     The clip lives here rather than inside the curtain, at the very bottom of
     the root stacking context, because two things depend on it being there.

     The names blend against it with mix-blend-mode, and blending only reaches
     what is painted below in the *same* stacking context ΓÇö inside the curtain
     (z-index 50) it was unreachable from the content, and `main` must not
     carry a z-index of its own for the same reason.

     And it outlives the intro: once the reveal is done the same element keeps
     running, reversed and blurred, as the surface the glass panels refract. --}}
<div id="backdrop" aria-hidden="true">
    @if ($template->intro_video)
        <video
            id="intro"
            playsinline
            preload="auto"
            muted
            src="{{ $template->intro_video }}"
        ></video>
    @endif

    {{-- The ambient loop: the same clip reversed and slowed, encoded small
         because it is only ever seen through a heavy blur. Absent until the
         template has been re-saved, and the intro clip stands in for it. --}}
    @if ($template->ambient_video ?? null)
        <video
            id="ambient"
            playsinline
            preload="none"
            muted
            loop
            src="{{ $template->ambient_video }}"
        ></video>
    @endif
</div>

<div id="curtain">
    <div id="prompt">
        <div class="ring"><span></span></div>
        <p>{{ __('invitation.tap') }}</p>
    </div>
</div>

{{-- ΓöÇΓöÇ Petals ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
     Over the invitation rather than behind it: behind the glass they would only
     show in the gaps between panes, and with a pane on every section there are
     barely any.

     Two nested elements per petal because a leaf's fall is two motions ΓÇö down,
     and swinging across ΓÇö and one element has only one `transform` for them to
     fight over. The outer drops, the inner swings and turns. --}}
@if ($petal)
    <div id="petals" aria-hidden="true">
        @foreach ($petals as $p2)
            <div
                class="petal"
                style="
                    --x: {{ $p2['x'] }}%;
                    --size: {{ $p2['size'] }}px;
                    --dur: {{ $p2['dur'] }}s;
                    --delay: {{ $p2['delay'] }}s;
                    --drift: {{ $p2['drift'] }}rem;
                    --spin: {{ $p2['spin'] }}deg;
                    --tint: {{ $p[$p2['tint']] }};
                "
            ><i></i></div>
        @endforeach
    </div>
@endif

{{-- Never autoplays: playback starts in the tap handler, both to respect
     browser autoplay policy and because the reveal is the moment the
     sound belongs to. --}}
@if ($template->music)
    <audio id="music" src="{{ $template->music }}" loop preload="auto"></audio>

    <button id="mute" type="button" aria-label="{{ __('invitation.mute') }}">
        <svg id="ic-on" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M11 5 6 9H2v6h4l5 4V5z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 5.5a9 9 0 0 1 0 13"/></svg>
        <svg id="ic-off" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M11 5 6 9H2v6h4l5 4V5z"/><path d="m22 9-6 6M16 9l6 6"/></svg>
    </button>
@endif

@if ($otherLocales->isNotEmpty())
    <nav id="langs" aria-label="{{ __('invitation.language') }}">
        @foreach ($otherLocales as $other)
            <a
                href="{{ $localeUrl($other) }}"
                hreflang="{{ $other }}"
                lang="{{ $other }}"
                data-switch
            >{{ config("templates.locales.{$other}.short", strtoupper($other)) }}</a>
        @endforeach
    </nav>
@endif

{{-- Only drawn when there is something below the fold to scroll to, which the
     script decides once the content is laid out. --}}
<div id="more" aria-hidden="true">
    <svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
</div>

<main id="content">

    @if ($invitation->show_verse && $verse !== '')
        <section class="verse pane">
            <p class="verse-ar" lang="ar" dir="rtl">{{ $verse }}</p>
            @if ($verseTranslation !== '')
                <p class="verse-en">{{ $verseTranslation }}</p>
            @endif
            @if ($verseRef !== '')
                <cite>{{ $verseRef }}</cite>
            @endif
        </section>
    @endif

    {{-- Released first, while the clip is still running, and lifted to the
         middle of it. The line that introduces the couple travels with them ΓÇö
         it is part of the same sentence, not a separate announcement. --}}
    <section class="couple-section">
        @if ($hosts = $invitation->text('hosts'))
            <p class="hosts">{{ $hosts }}</p>
        @endif

        <h1 class="couple" lang="ar" dir="rtl">
            <span class="name">{{ $invitation->groom_ar }}@if ($invitation->showsLatinNames())<em class="latin">{{ $invitation->groom_latin }}</em>@endif</span>
            <span class="amp">&amp;</span>
            <span class="name">{{ $invitation->bride_ar }}@if ($invitation->showsLatinNames())<em class="latin">{{ $invitation->bride_latin }}</em>@endif</span>
        </h1>
        <div class="rule"><i></i></div>
    </section>

    {{-- When, as one pane. The date, the reading of it, the time and the count
         down to it are one fact told four ways ΓÇö split across two panes they
         read as two unrelated boxes, and each was too thin to be a card. --}}
    <section class="pane">
        <p class="label">{{ __('invitation.date') }}</p>
        <p class="when">{{ $invitation->formattedDate() }}</p>
        @if ($hijri = $invitation->hijriDate())
            <p class="hijri">{{ $hijri }}</p>
        @endif
        <p class="at-time">
            {{ $invitation->formattedTime() }}
            @if ($endTime = $invitation->formattedEndTime())
                <span class="until">&ndash; {{ $endTime }}</span>
            @endif
        </p>

        <div class="countdown" id="countdown">
            <div class="unit"><b data-u="d">00</b><small>{{ __('invitation.days') }}</small></div>
            <div class="unit"><b data-u="h">00</b><small>{{ __('invitation.hours') }}</small></div>
            <div class="unit"><b data-u="m">00</b><small>{{ __('invitation.minutes') }}</small></div>
            <div class="unit"><b data-u="s">00</b><small>{{ __('invitation.seconds') }}</small></div>
        </div>
        <p class="begun" id="begun" hidden>{{ __('invitation.begun') }}</p>
    </section>

    @php
        $venueName = $invitation->text('venue_name');
        $venueCity = $invitation->text('venue_city');
    @endphp

    {{-- Where and what happens there, likewise one pane: a venue is two lines
         and a programme is the evening that runs in it. --}}
    @if ($venueName || $venueCity || $programme)
        <section class="pane">
            @if ($venueName || $venueCity)
                <p class="label">{{ __('invitation.venue') }}</p>
                <p class="venue">
                    {{ $venueName }}@if ($venueName && $venueCity)<br>@endif{{ $venueCity }}
                </p>
            @endif

            @if ($programme)
                @if ($venueName || $venueCity)
                    <div class="divide"></div>
                @endif

                <p class="label">{{ __('invitation.programme') }}</p>
                <ul class="programme">
                    @foreach ($programme as $row)
                        <li>
                            <time>{{ $row['time'] ?? '' }}</time>
                            <span>{{ $row['title'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    <section class="actions">
        @if ($mapUrl = $invitation->mapUrl())
            <a class="btn solid" href="{{ $mapUrl }}" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                {{ __('invitation.map') }}
            </a>
        @endif

        <a class="btn" href="{{ $calendarUrl }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/></svg>
            {{ __('invitation.calendar') }}
        </a>

        @if ($invitation->phone)
            <a class="btn" href="tel:{{ $invitation->phone }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 3h3l2 5-2.5 1.5a12 12 0 0 0 6 6L16 13l5 2v3a2 2 0 0 1-2.2 2A17 17 0 0 1 4 5.2A2 2 0 0 1 6 3z"/></svg>
                {{ __('invitation.call') }}
            </a>
        @endif
    </section>

    <footer>{{ __('invitation.brand') }}</footer>
</main>

<script>
const CONFIG = @json($s);
const TARGET = new Date('{{ $invitation->startsAtIso() }}').getTime();
const ARABIC_NUMERALS = @json(config("templates.locales.{$locale}.numerals") === 'arab');

const curtain  = document.getElementById('curtain');
const intro    = document.getElementById('intro');
const ambient  = document.getElementById('ambient');
const petals   = document.getElementById('petals');
const promptEl = document.getElementById('prompt');
const music    = document.getElementById('music');
const content  = document.getElementById('content');
const muteBtn  = document.getElementById('mute');
const langs    = document.getElementById('langs');
const cd       = document.getElementById('countdown');
const begun    = document.getElementById('begun');

const LABELS = { mute: @json(__('invitation.mute')), unmute: @json(__('invitation.unmute')) };

/* ΓöÇΓöÇ audio ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
   The muffled "next room" effect is a low-pass filter, which only the
   Web Audio API can do.

   Only file:// is excluded. There, createMediaElementSource reads the
   media as an opaque origin and yields silence, and the call cannot be
   undone. Everything else is fine ΓÇö including about:srcdoc, which is
   what the studio's preview frame runs as. Testing for http(s)
   instead, as this once did, silently dropped the filter in that frame
   and the preview lost the muffle the real page has. */
const CAN_FILTER = location.protocol !== 'file:';
let actx = null, fogNode = null, duckNode = null, muteNode = null, muted = false;

function buildAudioGraph() {
    try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx || !music) return false;

        actx = new Ctx();
        fogNode = actx.createBiquadFilter();
        fogNode.type = 'lowpass';
        fogNode.frequency.value = 20000;

        duckNode = actx.createGain(); duckNode.gain.value = 0;
        muteNode = actx.createGain(); muteNode.gain.value = 1;

        actx.createMediaElementSource(music)
            .connect(fogNode).connect(duckNode).connect(muteNode)
            .connect(actx.destination);

        return true;
    } catch {
        actx = fogNode = duckNode = muteNode = null;
        return false;
    }
}

/* Frequency sweeps sound natural exponentially, gain linearly. */
function ramp(param, to, ms, exponential = false) {
    const t = actx.currentTime;
    param.cancelScheduledValues(t);
    param.setValueAtTime(Math.max(param.value, 0.0001), t);
    if (exponential) param.exponentialRampToValueAtTime(Math.max(to, 0.0001), t + ms / 1000);
    else             param.linearRampToValueAtTime(to, t + ms / 1000);
}

function rampVolume(to, ms) {
    const from = music.volume, t0 = performance.now();
    (function step() {
        const p = ms > 0 ? Math.min(1, (performance.now() - t0) / ms) : 1;
        music.volume = from + (to - from) * p;
        if (p < 1) requestAnimationFrame(step);
    })();
}

function startMusic() {
    if (!music) return;

    if (CAN_FILTER && buildAudioGraph()) {
        actx.resume();
        music.volume = 1;
        ramp(duckNode.gain, 1, CONFIG.musicFadeMs);
    } else {
        music.volume = 0;
        rampVolume(1, CONFIG.musicFadeMs);
    }

    music.play().then(() => {
        musicStarted = true;
        muteBtn && muteBtn.classList.add('on');
    }).catch(() => {});
}

/* Pull back and blur once the invitation is on screen. */
function settleMusic() {
    if (!music) return;
    if (actx) {
        ramp(duckNode.gain, CONFIG.duckTo, CONFIG.duckMs);
        ramp(fogNode.frequency, CONFIG.fogHz, CONFIG.duckMs, true);
    } else {
        rampVolume(CONFIG.duckTo, CONFIG.duckMs);   // quieter, but not muffled
    }
}

let musicStarted = false;

if (muteBtn) {
    muteBtn.addEventListener('click', () => {
        // Arriving via the language switcher skips the intro, so the music was
        // never started and there has been no gesture to allow it. This click
        // is that gesture.
        if (!musicStarted) {
            startMusic();

            return;
        }

        muted = !muted;
        if (muteNode) muteNode.gain.value = muted ? 0 : 1;
        else music.muted = muted;

        muteBtn.classList.toggle('muted', muted);
        muteBtn.setAttribute('aria-label', muted ? LABELS.unmute : LABELS.mute);
    });
}

/* ΓöÇΓöÇ the sequence ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
   Three beats, not one:

     1. the names alone, over the running clip, reading as its inverse
     2. the clip dissolves and the names settle into their own colour
     3. the rest of the invitation arrives and the page unlocks

   Nothing moves between beats. The names are released in their final position
   and stay there ΓÇö what changes is what else is visible around them. */
let opened = false, named = false, revealed = false;
let fadeTimer = null, namesTimer = null;

/* Held from the first paint. Every path out of the intro has to release it,
   which is why unlock() is called from reveal() and nowhere else. */
function hold() { document.documentElement.classList.add('held'); }

function release() {
    document.documentElement.classList.remove('held');
    // In case a stray gesture moved the page before the lock applied.
    window.scrollTo(0, 0);
}

hold();

curtain.addEventListener('click', () => {
    if (opened) return;
    opened = true;

    promptEl.classList.add('gone');
    // Clears the scrim so the clip beneath shows; the curtain itself stays
    // until the reveal so nothing below it can be touched yet.
    curtain.classList.add('opened');

    if (intro) {
        intro.classList.add('playing');
        intro.muted = CONFIG.keepVideoAudioMs === 0;
        intro.currentTime = 0;
        intro.play().then(armSequence).catch(reveal);
    } else {
        // No clip: the names still lead, just on a timer rather than a duration.
        showNames();
        setTimeout(reveal, 900);
    }

    if (CONFIG.keepVideoAudioMs > 0 && intro) {
        setTimeout(() => { intro.muted = true; startMusic(); }, CONFIG.keepVideoAudioMs);
    } else {
        startMusic();
    }

    setTimeout(reveal, CONFIG.revealFallbackMs);
});

/**
 * The names, over the clip, inverted against it.
 *
 * Separate from reveal() so a failed or stalled video cannot leave the page
 * showing nothing but a fading curtain: whichever path fires first, the names
 * are already there.
 */
function showNames() {
    if (named) return;
    named = true;

    // How far to lift the names so they sit in the middle of the clip.
    //
    // Measured now, before the class lands, because this reads the element
    // where it will finally sit ΓÇö the page is unscrolled and nothing is
    // transformed yet, so its own box is the honest answer. Measuring after
    // would be measuring the lift that was just applied.
    const names = content.querySelector('.couple');

    if (names) {
        const box = names.getBoundingClientRect();
        const lift = (window.innerHeight / 2) - (box.top + box.height / 2);

        content.style.setProperty('--lift', Math.round(lift) + 'px');
    }

    content.classList.add('blending', 'names-in');
}

/* Start the dissolve before the clip runs out so it fades while still in
   motion. setTimeout for precision; timeupdate backs it up if playback
   stalls or seeks. */
/* ΓöÇΓöÇ the scroll cue ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
   Shown only when there is more page than screen, and taken away the moment it
   has been acted on. Measured after a frame so the reveal's own layout has
   settled ΓÇö asking before that reports the collapsed height and concludes there
   is nothing to scroll to. */
function armScrollCue() {
    const cue = document.getElementById('more');
    if (!cue) return;

    requestAnimationFrame(() => {
        if (document.documentElement.scrollHeight - window.innerHeight < 80) return;

        cue.classList.add('on');

        const dismiss = () => {
            cue.classList.remove('on');
            setTimeout(() => cue.remove(), 1000);
        };

        window.addEventListener('scroll', dismiss, { once: true, passive: true });
    });
}

/* ΓöÇΓöÇ petals ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
   Started with the reveal and taken away again once the first look is over.
   Leaving them falling forever means anyone reading the programme does it with
   movement in the corner of their eye, which stops being charming quickly. */
function startPetals() {
    if (!petals) return;

    // Motion is the whole of what this is, so the preference means none of it.
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    petals.classList.add('on');

    setTimeout(() => {
        petals.classList.remove('on');
        // Taken out of the document once faded, so nothing keeps animating
        // off-screen for the rest of the visit.
        setTimeout(() => petals.remove(), 2600);
    }, CONFIG.petalsMs);
}

/* ΓöÇΓöÇ the ambient backdrop ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
   The clip again, reversed and slowed to almost nothing, blurred past
   recognition. It is not there to be watched ΓÇö it is there so the glass panels
   have something moving to refract, which is what stops them looking like flat
   translucent rectangles.

   Reversed because forward playback loops with a visible jump back to the first
   frame; reversed footage of a slow motion reads as continuous drift. */
function startAmbient() {
    // Motion is the entire point of this layer, so honouring the preference
    // means not running it at all rather than running it faster.
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const layer = ambient ?? intro;
    if (!layer) return;

    // The encoded loop is already slow, so it plays at its own speed ΓÇö asking
    // the browser to slow it further is what made it stutter. Only the
    // stand-in has to be slowed here, and gently.
    layer.playbackRate = layer === ambient ? 1 : CONFIG.ambientRate;
    layer.loop = true;
    layer.muted = true;

    // Without a reversed encode the intro clip stands in, blurred in place.
    layer.classList.add(layer === intro ? 'ambient' : 'on');

    layer.play().catch(() => {
        // Refused, which costs nothing but the effect. Take the blur off so it
        // does not sit there as a frozen smear.
        layer.classList.remove('ambient', 'on');
    });

    // A video decoding behind a hidden tab is pure battery for no one's
    // benefit, and phones background these aggressively anyway.
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) layer.pause();
        else layer.play().catch(() => {});
    });
}

/**
 * Schedule both beats off the clip's own duration.
 *
 * The names come in `namesLeadMs` before the end, the dissolve starts at
 * `fadeOutLeadMs` before it ΓÇö so the names have the clip to themselves for the
 * difference between the two. setTimeout for precision; timeupdate below backs
 * it up if playback stalls or the guest seeks.
 */
function armSequence() {
    if (!intro) return;
    const d = intro.duration;
    if (!isFinite(d) || d <= 0) {
        // Duration unknown, so lead times mean nothing. Show the names now
        // rather than leave the clip playing over an empty page.
        showNames();

        return;
    }

    const left = at => Math.max(0, (d - at / 1000 - intro.currentTime) * 1000);

    clearTimeout(namesTimer);
    clearTimeout(fadeTimer);
    namesTimer = setTimeout(showNames, left(CONFIG.namesLeadMs));
    fadeTimer = setTimeout(reveal, left(CONFIG.fadeOutLeadMs));
}

if (intro) {
    intro.addEventListener('loadedmetadata', () => { if (opened) armSequence(); });
    intro.addEventListener('timeupdate', () => {
        const d = intro.duration;
        if (!isFinite(d)) return;
        const left = d - intro.currentTime;
        if (left <= CONFIG.namesLeadMs / 1000) showNames();
        if (left <= CONFIG.fadeOutLeadMs / 1000) reveal();
    });
    intro.addEventListener('ended', reveal);
    intro.addEventListener('error', reveal);
}

function reveal() {
    if (revealed) return;
    revealed = true;
    clearTimeout(fadeTimer);
    clearTimeout(namesTimer);

    // If the clip failed early the names may never have had their moment; they
    // still lead, just by a shorter margin than intended.
    showNames();

    curtain.classList.add('gone');

    // The stagger step comes from the element's own position, counted here
    // rather than written into the stylesheet ΓÇö sections are conditional, so a
    // verse-less invitation would otherwise leave a hole in the sequence.
    Array.from(content.children).forEach((el, i) => el.style.setProperty('--i', i));

    content.classList.add('revealed');
    if (muteBtn) muteBtn.classList.add('on');
    if (langs) langs.classList.add('on');
    settleMusic();
    release();
    startPetals();
    armScrollCue();

    // Once the clip is out of sight the names have nothing left to invert
    // against, so they come off blend mode and take their own colour.
    setTimeout(() => content.classList.remove('blending'), CONFIG.crossfadeMs);

    // Let the clip run through the whole dissolve ΓÇö pausing here is what
    // made it look like it stopped and then faded.
    setTimeout(() => {
        if (intro) intro.pause();
        curtain.remove();
        startAmbient();
    }, CONFIG.crossfadeMs + 200);
}

/* ΓöÇΓöÇ language switching ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
   Each language is its own URL so a single language can be sent to the
   guests who read it. Switching therefore reloads ΓÇö and replaying a
   ten-second intro to read the same invitation again would be tedious,
   so the destination is told to skip straight to the content.

   Music cannot resume by itself after a navigation: there has been no
   gesture on the new page. The music button handles that. */
const SKIP_KEY = 'lahza:skip-intro';

if (langs) {
    langs.querySelectorAll('[data-switch]').forEach(link => {
        link.addEventListener('click', () => {
            try { sessionStorage.setItem(SKIP_KEY, '1'); } catch {}
        });
    });
}

function skipIntro() {
    revealed = true;
    opened = true;

    curtain.remove();
    content.classList.add('revealed');
    if (muteBtn) muteBtn.classList.add('on');
    if (langs) langs.classList.add('on');
}

try {
    if (sessionStorage.getItem(SKIP_KEY)) {
        sessionStorage.removeItem(SKIP_KEY);
        skipIntro();
    }
} catch { /* private mode: the intro just plays again */ }

/* ΓöÇΓöÇ countdown ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ */
const AR_DIGITS = '┘á┘í┘ó┘ú┘ñ┘Ñ┘ª┘º┘¿┘⌐';
const digits = n => {
    const s = String(n).padStart(2, '0');
    return ARABIC_NUMERALS ? s.replace(/\d/g, d => AR_DIGITS[d]) : s;
};

const cell = u => cd.querySelector(`[data-u="${u}"]`);

function tick() {
    const diff = TARGET - Date.now();
    if (diff <= 0) { cd.hidden = true; begun.hidden = false; return; }
    cell('d').textContent = digits(Math.floor(diff / 864e5));
    cell('h').textContent = digits(Math.floor(diff % 864e5 / 36e5));
    cell('m').textContent = digits(Math.floor(diff % 36e5 / 6e4));
    cell('s').textContent = digits(Math.floor(diff % 6e4 / 1e3));
}
tick();
setInterval(tick, 1000);
</script>
</body>
</html>
