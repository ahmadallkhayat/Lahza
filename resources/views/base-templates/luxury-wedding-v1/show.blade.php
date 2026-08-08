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

{{-- The invitation is already in the language the guest chose a link for, so
     Chrome offering to translate it is a banner over the reveal and nothing
     more. Both are needed: the meta stops the offer, the attribute stops the
     translation itself if it is triggered another way. --}}
<meta name="google" content="notranslate">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{{ $invitation->groom_ar }} &amp; {{ $invitation->bride_ar }}</title>

{{-- WhatsApp is the distribution channel, so the preview card matters. --}}
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $invitation->groom_ar }} & {{ $invitation->bride_ar }}">
<meta property="og:description" content="{{ $invitation->formattedDate() }}{{ $invitation->text('venue_name') ? ' — '.$invitation->text('venue_name') : '' }}">
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

  /* Arrivals decelerate hard and settle — a flat `ease` is nearly linear
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

/* ─── BACKDROP ───────────────────────────────────────────────────
   The lowest layer in the root stacking context. Everything above it can
   blend against it, which is the whole reason it is not inside the curtain. */
/* Transparent, so the paper behind it is what the invitation sits on.
   This carried `background: var(--curtain)` and, being fixed and permanent,
   covered body's paper for the whole visit — the page never actually returned
   to light after the reveal. The clip covers the viewport while it plays, so
   nothing needs a fill here, and the dark before the tap is the curtain's job. */
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
/* The softening is quicker than the crossfade on purpose: it has to finish
   inside blurLeadMs, before the names are asked to sit over it. */
#intro {
  opacity: 0;
  transition: opacity 900ms var(--ease-soft), filter 900ms var(--ease-soft);
}
#intro.playing { opacity: 1; }

/* Already slowed at encode time, so it plays at rate 1.0 — see
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
   It has to be slowed in the browser, which judders — so it runs nearer real
   time and leans on the blur instead. */
#intro.ambient {
  filter: blur(30px) saturate(1.1);
  transform: scale(1.15);
  opacity: .18;
}

/* Handed over to the reversed encode. Both are blurred identically by now, so
   crossing between them is invisible. */
#intro.handed { opacity: 0; }

/* ─── INTRO ────────────────────────────────────────────────────
   The curtain is now only a scrim and a prompt — the clip moved to #backdrop.
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

/* ─── LOADING ────────────────────────────────────────────────────
   The mark, in the theme's colours, while the clip buffers. Both this and the
   prompt live in the same grid cell so the swap is a crossfade rather than a
   jump — and the prompt starts hidden, which is the whole point: it must not
   be tappable before there is anything for a tap to start. */
#loading, #prompt { grid-area: 1 / 1; }

#loading {
  position: relative;
  z-index: 2;
  display: grid;
  justify-items: center;
  gap: 1.6rem;
  transition: opacity 500ms var(--ease-soft);
}
#loading.gone { opacity: 0; pointer-events: none; }

#loading .mark {
  width: clamp(3.4rem, 16vw, 4.6rem);
  height: auto;
  color: var(--gold);
  opacity: .9;
  animation: breathe-mark 2.4s var(--ease-soft) infinite;
}

@keyframes breathe-mark {
  0%, 100% { opacity: .45; transform: scale(.97); }
  50%      { opacity: .95; transform: scale(1); }
}

/* A thread rather than a progress bar: there is no percentage to report, only
   the fact that something is happening. */
#loading .pulse {
  width: 5.5rem;
  height: 1px;
  overflow: hidden;
  background: color-mix(in srgb, var(--gold) 22%, transparent);
}
#loading p {
  margin: 0;
  font-size: .82rem;
  letter-spacing: calc(.24em * var(--tk));
  color: var(--gold-deep);
  opacity: .75;
}

#loading .pulse i {
  display: block;
  width: 40%;
  height: 100%;
  background: var(--gold);
  animation: thread 1.5s var(--ease-soft) infinite;
}

@keyframes thread {
  from { transform: translateX(-100%); }
  to   { transform: translateX(250%); }
}

#prompt {
  position: relative;
  z-index: 2;
  text-align: center;
  opacity: 0;
  pointer-events: none;
  transition: opacity 500ms var(--ease);
}
#prompt.ready { opacity: 1; pointer-events: auto; }
#prompt.gone { opacity: 0; pointer-events: none; }

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

/* ─── CONTENT ────────────────────────────────────────────────── */
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

/* Every section waits, including the names — they are simply released first,
   and in place, so nothing ever moves between the two phases. */
main > * {
  opacity: 0;
  transform: translateY(18px);
  transition: opacity 1s var(--ease), transform 1s var(--ease);
}

/* Phase one: the names alone, centred on the clip.
   Lifted to the middle of the viewport rather than left where they will end up,
   then let go — so the reveal is the names travelling to their place as the
   content assembles beneath them, instead of everything appearing at once.

   The transform sits on this element and not on `main`. A transformed ancestor
   becomes a stacking context, which would cut the names off from the clip they
   blend against; on the blended element itself it is harmless.

   :not(.revealed) because both classes are set once the reveal runs, and this
   selector is the more specific of the two. */
/* The section is present so the heading inside it can be, but only the heading
   travels. The line above the names and the rule below them belong to the laid
   out page and arrive with it — lifting the whole block moved all three onto
   the clip, which is not what was asked for. */
/* ── Names over the clip ─────────────────────────────────────────
   One colour, all the way through.

   These used to invert against the footage with mix-blend-mode: difference,
   which did exactly what it promises — recomputed per frame — so on real
   footage the names strobed black to white to black as the clip moved. The
   effect only existed because the backdrop was an opaque dark sheet and the
   names had to fight it.

   The backdrop is now transparent and softened to almost nothing before the
   names appear, so what they sit on is paper with a faint blur drifting under
   it. Legible, unchanging, and the same colour they end on — nothing to
   transition and nothing to flicker. */

main.names-in:not(.revealed) .couple-section {
  opacity: 1;
  transform: none;
}

main.names-in:not(.revealed) .couple {
  /* Lifted to the center of the viewport so the names sit over the clip. */
  transform: translateY(var(--lift, 0px));
  /* Clean white with a soft shadow — legible over any footage without the
     strobing that mix-blend-mode: difference caused on moving video. */
  color: #fff;
  text-shadow: 0 2px 24px rgba(0, 0, 0, 0.5),
               0 0 80px rgba(0, 0, 0, 0.25);
}

.couple-section > .hosts,
.couple-section > .rule {
  opacity: 0;
  transition: opacity 1s var(--ease) 250ms;
}
main.revealed .couple-section > .hosts,
main.revealed .couple-section > .rule { opacity: 1; }

/* The one move: names glide from the centre of the clip to their home, color
   fades from white to the page ink, shadow dissolves. Long and soft. */
.couple {
  transition: color 1.4s var(--ease-soft),
              transform 1.6s var(--ease),
              text-shadow 1.4s var(--ease-soft);
}
main > .couple-section { transition: opacity 1.1s var(--ease); }

/* Phase two: the rest, staggered. The step is set per element from one place
   rather than a rule per position — the old nth-child(1..8) list meant a ninth
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

/* ─── SCROLL CUE ─────────────────────────────────────────────────
   The invitation runs well past one screen and nothing said so, which on a
   phone means the names and the date can read as the whole of it. Fades out on
   the first scroll — a cue that stays after being acted on is just decoration. */
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

/* ─── PETALS ─────────────────────────────────────────────────────
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

/* ─── GLASS ──────────────────────────────────────────────────────
   Only panes get glass, and there are two of them.

   Every section used to be one, which is what made the page read as a stack of
   boxes: a venue is two lines and a hosting line is one, and neither fills a
   card — they just sat in the middle of an empty pane looking accidental. So
   the content is grouped into the two panes that have something to hold, and
   the verse, the names and the actions sit on open paper where they belong.

   The fill is `paper` at 62%, and that number is load-bearing. Every colour in
   a generated palette is contrast-checked against solid `paper`, and a moving
   image behind text voids that guarantee — whatever frame is passing decides
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
   Bold and set larger than anything but the names — it opens the invitation and
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

/* The line that introduces the couple, and it reads as a headline now.
   It has been nudged twice from .98 and neither move was perceptible; a tenth
   of a rem is not a size change. Set above the verse deliberately: the verse is
   a quotation and this is the invitation speaking. */
.hosts {
  font-family: var(--f-serif);
  font-size: clamp(1.3rem, 5.2vw, 1.62rem);
  font-weight: 400;
  line-height: 1.95;
  /* Full ink, not the soft grey it had. At headline size the muted colour was
     the other half of why it receded. */
  color: var(--ink);
  margin: 0 auto;
  max-width: 23rem;
}
html[lang="en"] .hosts { font-family: var(--f-latin); font-size: clamp(1.35rem, 5.4vw, 1.7rem); line-height: 1.8; }

/* Couple names — Arabic in every locale. */
.couple {
  margin: 0;
  font-family: var(--f-name);
  font-size: clamp(2.5rem, 13vw, 3.9rem);
  /* Ruqaa throws swashes well above the baseline; leading stops clipping. */
  line-height: 1.7;
  color: var(--ink);
  font-weight: 400;
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

/* The spine, drawn between the marks rather than through them — it stops short
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
   them into two rows — so any moment both were visible showed one squashed
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

/* ─── Language switcher ──────────────────────────────────────────
   Only rendered when the invitation is issued in more than one
   language, and only after the reveal, so it never sits over the
   intro. Deliberately quiet — a guest needs it once. */
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

{{-- ── Backdrop ─────────────────────────────────────────────────────
     The clip lives here rather than inside the curtain, at the very bottom of
     the root stacking context, because two things depend on it being there.

     The names blend against it with mix-blend-mode, and blending only reaches
     what is painted below in the *same* stacking context — inside the curtain
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
    {{-- Shown until the clip can actually play.
         The prompt used to appear the moment the markup did, so a guest on a
         slow connection tapped a button that could not do anything yet —
         play() was called against an unbuffered video and the tap was simply
         lost. The mark holds the moment instead, in the theme's own colours. --}}
    <div id="loading">
        <svg class="mark" fill="currentColor" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 710 703">
<path d="M354.21 11.77C354.89 11.57 355.16 13.71 355.33 14.24C356.26 17.05 356.48 20.03 357.32 22.86C359.44 30.06 362.47 37.16 366.49 43.52C370.45 49.79 376.47 55.06 382.92 58.58C384.15 59.25 391.5 62.14 391.66 62.85C391.96 64.19 384.09 66.43 382.54 67.18C377.2 69.75 372.25 73.89 368.29 78.25C363.32 83.72 359.51 91.09 357 97.99C356.63 99.03 354.74 105.3 354.41 105.34C353.54 105.44 353.19 103.86 353.07 103.31C352.48 100.72 351.7 98.22 350.8 95.71C348.8 90.14 345.34 83.93 341.47 79.44C337.03 74.28 331.52 69.98 325.32 67.16C324.17 66.63 316.59 63.99 316.41 63.51C316.01 62.39 317.48 62.03 318.21 61.79C320.64 61 323.14 59.78 325.38 58.54C331.32 55.27 336.39 50.9 340.43 45.42C345.68 38.31 348.92 29.34 351.44 20.96C351.8 19.76 353.39 12.02 354.21 11.77ZM326.29 125.17C327.75 124.8 327.05 131.63 326.88 132.65C325.54 140.49 323.64 148.13 320.87 155.59C317.82 163.83 313.2 171.49 308.43 178.84C298.22 194.56 286.2 208.25 273.19 221.71C251.61 244 228.16 264.45 205.67 285.78C192.37 298.4 178.5 310.67 167.24 325.22C148.39 349.55 137.5 377.1 137.65 408.13C137.82 443.32 153.95 475.95 176.43 502.21C187.54 515.19 200.53 526.43 212.96 538.11C224.99 549.41 237.4 560.39 249.62 571.49C258.78 579.8 268.31 587.85 277.07 596.58C296.35 615.79 321.42 636.02 333.41 660.7C336.28 666.61 335.31 676.6 335.3 683.14C335.3 684.09 335.43 689.83 334.49 690.06C333.73 690.25 333.03 688.81 332.76 688.34C331.54 686.27 330.21 684.26 328.93 682.24C323.55 673.75 316.76 666.41 309.74 659.29C298.1 647.49 285.18 636.94 272.94 625.8C262.85 616.62 252.45 607.8 242.27 598.73C234.69 591.98 227.4 584.84 219.67 578.26C211.81 571.57 204.01 564.71 196.38 557.77C194.07 555.67 191.97 553.3 189.59 551.3C174.34 538.48 159.65 524.51 147.42 508.75C125.24 480.16 111.69 444.7 111.22 408.37C111.04 394.73 112.4 381.8 115.66 368.58C119.46 353.19 125.81 338.25 134.13 324.77C146.52 304.71 163.28 288.52 180.4 272.58C195.96 258.09 211.79 243.86 227.32 229.33C232.66 224.34 238.32 219.67 243.54 214.54C264.47 193.97 287.9 175.44 306.58 152.63C311.91 146.13 316.94 139.6 321.53 132.55C322.23 131.47 325.41 125.39 326.29 125.17ZM382.38 125.15C383.32 124.68 384.65 127.56 384.98 128.22C387.02 132.28 389.62 136.18 392.35 139.8C399.68 149.52 407.69 158.83 416.16 167.58C430.74 182.63 446.38 197 462.07 210.89C473.4 220.92 484.14 231.6 495.4 241.69C504.89 250.19 514.05 259.05 523.52 267.54C542.23 284.3 559.86 301.73 573.93 322.67C582.86 335.96 589.12 351.15 593.23 366.55C596.89 380.24 598.46 393.93 598.02 408.13C597.54 423.4 595.46 438.44 590.86 453.03C576.67 497.99 547.87 527.1 513.3 557.21C496.75 571.62 480.64 586.57 464.23 601.14C450.73 613.13 436.97 624.84 423.58 636.94C413.2 646.32 402.42 655.1 392.82 665.34C387.19 671.34 382.02 677.43 377.35 684.21C376.63 685.25 373.59 691.05 372.62 691.04C371.47 691.03 371.88 684.9 371.9 683.85C372.03 676.63 370.95 667.83 374.18 661.14C380.25 648.54 389.88 638.4 399.48 628.45C409.72 617.85 420.29 607.6 431.02 597.51C450.53 579.14 470.72 561.48 490.31 543.2C506.49 528.11 523.51 513.68 537.5 496.45C557.25 472.14 569.66 442.83 571.4 411.45C573.15 379.68 561.15 349.27 541.65 324.66C533.65 314.57 524.24 305.8 515.04 296.84C493.03 275.38 469.73 255.26 448.08 233.4C438.03 223.26 427.38 213.67 418.27 202.63C408.93 191.31 399.57 179.3 393.06 166.06C389.94 159.69 387.82 153.18 385.27 146.62C383.27 141.5 382.88 135.94 382.26 130.52C382.17 129.72 381.69 125.49 382.38 125.15ZM354.22 208.3C355.4 208.04 356.02 211.02 356.32 211.8C357.89 215.85 360.03 219.84 362.36 223.5C368.88 233.72 376.24 243.6 384.32 252.67C403.59 274.26 423.94 296.21 431.86 324.86C438.11 347.5 435.92 373.84 435.91 397.25C435.91 403.4 435.87 409.55 435.82 415.7C435.79 418.61 436.33 422.17 435.17 424.92C434.31 426.96 432.04 428.8 430.57 430.45C426.51 435.03 422.06 439.38 418.37 444.26C415.81 447.64 413.22 450.97 410.73 454.39C410.05 455.32 408.85 457.67 407.56 457.82C406.42 457.95 406.63 455.33 406.63 454.76C406.63 451.13 406.62 447.49 406.67 443.87C406.86 428.65 406.68 413.41 406.68 398.19C406.68 379.82 408.54 354.96 403.64 337.47C398.34 318.51 386.74 302.56 373.86 287.97C369.05 282.52 363.98 277 359.78 271.05C359.07 270.05 355.36 265 354.41 264.95C353.16 264.89 349.9 269.68 349.01 270.8C344.17 276.89 339.04 282.74 333.99 288.67C320.99 303.93 309.51 320.07 304.43 339.76C302.34 347.9 302.39 356.71 302.27 365.06C302.09 376.49 302.08 387.94 302.19 399.38C302.3 412.23 302.16 425.09 302.18 437.95C302.18 443 302.21 448.05 302.1 453.1C302.07 454.22 302.36 458.18 300.29 457.16C298.75 456.4 297.64 454.07 296.66 452.74C294.46 449.73 292 446.79 289.63 443.91C286.12 439.65 282.64 435.29 278.81 431.31C276.9 429.33 274.02 427.46 273.04 424.76C271.88 421.58 272.69 417.15 272.66 413.81C272.6 405.61 272.58 397.4 272.58 389.2C272.58 367.47 271.31 345.59 276.98 324.4C284.68 295.65 307.03 271.09 326.68 249.65C334.62 240.99 342.26 230.95 347.78 220.58C349.41 217.52 350.84 214.49 352.27 211.33C352.65 210.5 353.17 208.53 354.22 208.3ZM179.8 402.41C181.79 401.69 188.43 404.9 190.63 405.66C201.39 409.37 211.89 413.73 222.18 418.6C227.35 421.04 232.66 423.24 237.63 426.11C277.97 449.38 305.75 479.43 327.89 520.18C335.2 533.64 342.7 549.94 347.96 564.3C349.32 568.01 351.07 571.65 352.22 575.43C352.48 576.3 352.86 578.93 353.94 578.97C354.97 579.01 354.98 577.6 355.23 576.87C356.08 574.45 357.08 572.09 357.98 569.69C361.75 559.66 365.98 549.82 370.11 539.92C374.34 529.78 379.83 519.81 385.63 510.48C393.47 497.86 401.21 485.34 411.05 474.12C434.77 447.12 464.26 427.68 497.12 413.46C504.77 410.15 512.7 407.6 520.46 404.6C522.17 403.94 527.01 401.53 528.64 402.76C529.93 403.74 529.46 409.8 529.44 411.45C529.4 417.12 529.31 422.81 529.36 428.49C529.4 431.42 529.81 434.57 529.48 437.48C529.12 440.52 520.2 440.49 517.46 441.39C513.32 442.75 509.1 443.74 505.01 445.27C497.23 448.19 489.45 452.17 482.26 456.35C453.31 473.17 428.77 496.55 411.2 525.15C391.33 557.51 378.48 593.62 366.41 629.42C363.54 637.93 359.95 646.2 357.17 654.75C356.79 655.93 354.7 662.63 353.49 662.4C352.74 662.27 352.26 660.6 352.07 660.01C351.26 657.48 350.61 654.85 349.48 652.44C347.97 649.19 346.96 645.6 345.74 642.21C343.02 634.67 340.33 627.14 337.6 619.6C328.05 593.22 318.93 566.22 305.81 541.35C298.13 526.81 288.99 513.28 278.84 500.37C261.98 478.93 236.95 461.06 212.57 449.29C205.62 445.94 198.26 443.01 190.73 441.27C188.04 440.65 181.44 440.61 179.52 439.02C178.02 437.78 178.82 433.35 178.83 431.56C178.84 424.54 178.88 417.52 178.8 410.5C178.78 408.76 177.67 403.18 179.8 402.41Z" fill="currentColor" fill-rule="evenodd" stroke="currentColor" stroke-width="0.25" stroke-linejoin="round"/>
</svg>
        <div class="pulse"><i></i></div>
        <p>{{ __('invitation.loading') }}</p>
    </div>

    <div id="prompt">
        <div class="ring"><span></span></div>
        <p>{{ __('invitation.tap') }}</p>
    </div>
</div>

{{-- ── Petals ───────────────────────────────────────────────────────
     Over the invitation rather than behind it: behind the glass they would only
     show in the gaps between panes, and with a pane on every section there are
     barely any.

     Two nested elements per petal because a leaf's fall is two motions — down,
     and swinging across — and one element has only one `transform` for them to
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
        @foreach ($otherLocales as $otherLocale)
            @php $other = (string) $otherLocale; @endphp
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
         middle of it. The line that introduces the couple travels with them —
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
         down to it are one fact told four ways — split across two panes they
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
const loading  = document.getElementById('loading');
const music    = document.getElementById('music');
const content  = document.getElementById('content');
const muteBtn  = document.getElementById('mute');
const langs    = document.getElementById('langs');
const cd       = document.getElementById('countdown');
const begun    = document.getElementById('begun');

const LABELS = { mute: @json(__('invitation.mute')), unmute: @json(__('invitation.unmute')) };

/* ── audio ──────────────────────────────────────────────────────
   The muffled "next room" effect is a low-pass filter, which only the
   Web Audio API can do.

   Only file:// is excluded. There, createMediaElementSource reads the
   media as an opaque origin and yields silence, and the call cannot be
   undone. Everything else is fine — including about:srcdoc, which is
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

/* ── the sequence ───────────────────────────────────────────────────
   Three beats, not one:

     1. the names alone, over the running clip, reading as its inverse
     2. the clip dissolves and the names settle into their own colour
     3. the rest of the invitation arrives and the page unlocks

   Nothing moves between beats. The names are released in their final position
   and stay there — what changes is what else is visible around them. */
let opened = false, named = false, revealed = false;
let fadeTimer = null, namesTimer = null;

/* Where each beat falls, once the clip's duration is known. */
let marks = null;

/* Held from the first paint. Every path out of the intro has to release it,
   which is why unlock() is called from reveal() and nowhere else. */
function hold() { document.documentElement.classList.add('held'); }

function release() {
    document.documentElement.classList.remove('held');
    // In case a stray gesture moved the page before the lock applied.
    window.scrollTo(0, 0);
}

hold();

/* ── waiting for the clip ───────────────────────────────────────────
   The prompt appears when there is something a tap can start, not when the
   markup arrives. Before this, a guest on a slow connection tapped and nothing
   happened: play() was called against an unbuffered video, the promise
   rejected, and the tap was spent for nothing.

   `canplay` rather than `canplaythrough` — through means the browser predicts
   the whole clip will play without stalling, which on a phone connection it
   often never commits to. Enough to start is enough, since the sequence is
   driven by timeupdate and copes with a stall anyway. */
let armed = false;

function armPrompt() {
    if (armed) return;
    armed = true;

    if (loading) loading.classList.add('gone');
    promptEl.classList.add('ready');
}

if (!intro) {
    // No clip to wait for.
    armPrompt();
} else if (intro.readyState >= 3) {
    // Already buffered — a cached clip is ready before this script runs.
    armPrompt();
} else {
    intro.addEventListener('canplay', armPrompt, { once: true });
    intro.addEventListener('loadeddata', armPrompt, { once: true });

    // Never leave someone looking at the mark. If the clip cannot be reached
    // the tap still has to work; the sequence already handles a failed play().
    intro.addEventListener('error', armPrompt, { once: true });
    setTimeout(armPrompt, CONFIG.loaderTimeoutMs);
}

curtain.addEventListener('click', () => {
    // Nothing to open until the clip can start.
    if (!armed) return;
    if (opened) return;
    opened = true;

    promptEl.classList.remove('ready');
    promptEl.classList.add('gone');
    // Clears the scrim so the clip beneath shows; the curtain itself stays
    // until the reveal so nothing below it can be touched yet.
    curtain.classList.add('opened');

    if (intro) {
        intro.classList.add('playing');
        intro.muted = CONFIG.keepVideoAudioMs === 0;
        if (intro.currentTime > 0.1) {
            intro.currentTime = 0;
        }
        
        // Give iOS Safari a tick to process the muted property change before playing,
        // which prevents the bug where audio plays but the video frame is frozen.
        setTimeout(() => {
            intro.play().then(armSequence).catch(reveal);
        }, 50);
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
    // where it will finally sit — the page is unscrolled and nothing is
    // transformed yet, so its own box is the honest answer.
    const names = content.querySelector('.couple');

    if (names) {
        const box = names.getBoundingClientRect();
        const lift = (window.innerHeight / 2) - (box.top + box.height / 2);

        content.style.setProperty('--lift', Math.round(lift) + 'px');
    }

    content.classList.add('names-in');
}

/* Start the dissolve before the clip runs out so it fades while still in
   motion. setTimeout for precision; timeupdate backs it up if playback
   stalls or seeks. */
/* ── the scroll cue ─────────────────────────────────────────────────
   Shown only when there is more page than screen, and taken away the moment it
   has been acted on. Measured after a frame so the reveal's own layout has
   settled — asking before that reports the collapsed height and concludes there
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

/* ── petals ─────────────────────────────────────────────────────────
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

/* ── the ambient backdrop ───────────────────────────────────────────
   The clip again, reversed and slowed to almost nothing, blurred past
   recognition. It is not there to be watched — it is there so the glass panels
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

    // The encoded loop is already slow, so it plays at its own speed.
    // We don't artificially slow the intro fallback anymore because it stutters.
    if (layer === ambient) {
        layer.playbackRate = 1;
    }
    
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
 * `fadeOutLeadMs` before it — so the names have the clip to themselves for the
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

    marks = cues(d);

    const wait = at => Math.max(0, (at - intro.currentTime) * 1000);

    clearTimeout(namesTimer);
    clearTimeout(fadeTimer);

    namesTimer = setTimeout(showNames, wait(marks.names));
    fadeTimer = setTimeout(reveal, wait(marks.reveal));
}

/**
 * When each beat happens, in seconds into the clip.
 *
 * The lead times are measured back from the end, which is the right way round
 * for a clip long enough to hold them — and nonsense for one that is not. A
 * four-second intro with a 2.6s names lead put the names 1.4s in and the reveal
 * at 3.2s, so the clip was over before it had shown anything: it read as being
 * cut off, because it was.
 *
 * So each beat also has a floor as a fraction of the clip. Whichever is later
 * wins, which leaves long clips exactly as they were and stops a short one from
 * being consumed by its own lead times.
 */
function cues(d) {
    /* The leads are measured back from the end, which is right whenever the
       clip is long enough to hold them. When it is not, they are scaled down
       together rather than each being given a floor of its own.

       Floors were the first attempt and they were worse: pinning the soften to
       42% of the clip meant a five-second intro went blurred barely two seconds
       in, so the footage looked like it had finished early and the names landed
       at the very end. Scaling keeps the shape of the sequence — sharp, then
       names, then the rest — and only compresses it.

       The cap is the important number. Whatever the duration, the leads never
       take more than this share, so at least the rest of the clip plays sharp. */
    const want = CONFIG.namesLeadMs / 1000;
    const scale = want > 0 ? Math.min(1, (d * 0.32) / want) : 1;

    const back = ms => d - (ms / 1000) * scale;

    const names = back(CONFIG.namesLeadMs);

    // Just enough for the names to register before everything else fires —
    // the glide, the curtain dissolve, and the content stagger all start
    // together as one swift motion.
    const reveal = Math.max(names + 0.1, back(CONFIG.fadeOutLeadMs));

    return { names, reveal };
}

if (intro) {
    intro.addEventListener('loadedmetadata', () => { if (opened) armSequence(); });
    intro.addEventListener('timeupdate', () => {
        const d = intro.duration;
        if (!isFinite(d)) return;
        // The same marks the timers use, so a stall cannot fire a beat at a
        // moment the schedule would not have.
        const at = marks ?? cues(d);

        if (intro.currentTime >= at.names) showNames();
        if (intro.currentTime >= at.reveal) reveal();
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

    // The clip must not stop while the crossfade is still running — if it
    // reaches its last frame it freezes visibly for the remainder. Looping
    // it makes the wrap invisible under the dissolve.
    if (intro) intro.loop = true;

    // Let the browser paint the white-centred names FIRST. Without this frame
    // break, 'names-in' and 'revealed' land in the same style recalc — the
    // :not(.revealed) rule that sets color:#fff never visually applies, so the
    // transition has no "from" value and the colour snaps instead of fading.
    requestAnimationFrame(() => {
        // All three fire on the same frame: video blurs, curtain dissolves,
        // content appears. Nothing else is ever visible over a sharp video.
        if (intro) intro.classList.add('ambient');
        curtain.classList.add('gone');

        Array.from(content.children).forEach((el, i) => el.style.setProperty('--i', i));

        content.classList.add('revealed');
        if (muteBtn) muteBtn.classList.add('on');
        if (langs) langs.classList.add('on');
        settleMusic();
        release();
        startPetals();
        armScrollCue();

        setTimeout(() => {
            if (intro) intro.pause();
            curtain.remove();
            startAmbient();
        }, CONFIG.crossfadeMs + 200);
    });
}

/* ── language switching ─────────────────────────────────────────
   Each language is its own URL so a single language can be sent to the
   guests who read it. Switching therefore reloads — and replaying a
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

/**
 * Arrive with the invitation already open.
 *
 * This used to be its own copy of what reveal() does, and it fell behind it —
 * it never released the scroll lock, so switching language left a page that
 * could not be scrolled at all, and it missed the petals, the scroll cue and
 * the backdrop besides. Two lists of the same steps only stay in step until
 * one of them is edited.
 *
 * So it marks the beats that need no playing-out as already done and hands over
 * to the real thing, which is the only place that knows what revealing means.
 */
function skipIntro() {
    opened = true;

    // No clip to centre on and none to blur out of, so neither beat has
    // anything to do — but they must be marked done or reveal() will run them.
    named = true;

    curtain.classList.add('gone');

    reveal();
}

try {
    if (sessionStorage.getItem(SKIP_KEY)) {
        sessionStorage.removeItem(SKIP_KEY);
        skipIntro();
    }
} catch { /* private mode: the intro just plays again */ }

/* ── countdown ──────────────────────────────────────────────────── */
const AR_DIGITS = '٠١٢٣٤٥٦٧٨٩';
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
