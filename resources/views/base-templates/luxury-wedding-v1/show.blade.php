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

    $programme = $invitation->programmeRows();
    $verseTranslation = trim(__('invitation.verse_translation'));
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
<meta property="og:description" content="{{ $invitation->formattedDate() }}{{ $invitation->text('venue_name') ? ' — '.$invitation->text('venue_name') : '' }}">
<meta property="og:url" content="{{ $localeUrl($locale) }}">
<meta name="twitter:card" content="summary_large_image">

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

body::before {
  content: '';
  position: fixed;
  inset: 0;
  pointer-events: none;
  z-index: 0;
  background:
    radial-gradient(120% 80% at 50% 0%,   color-mix(in srgb, var(--gold) 28%, transparent), transparent 60%),
    radial-gradient(100% 70% at 50% 100%, color-mix(in srgb, var(--gold) 14%, transparent), transparent 65%);
}

/* ─── INTRO ──────────────────────────────────────────────────── */
#curtain {
  position: fixed;
  inset: 0;
  z-index: 50;
  background: var(--curtain);
  display: grid;
  place-items: center;
  cursor: pointer;
  transition: opacity var(--fade) ease;
}
#curtain.gone { opacity: 0; pointer-events: none; }

#intro {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  /* 16:9 footage on a portrait phone: cover crops the sides. Centred
     subjects survive it; switch to `contain` to letterbox instead. */
  object-fit: cover;
  opacity: 0;
  transition: opacity 600ms ease;
}
#intro.playing { opacity: 1; }

#prompt { position: relative; z-index: 2; text-align: center; transition: opacity 500ms ease; }
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
  font-size: .8rem;
  letter-spacing: calc(.34em * var(--tk));
  font-weight: 300;
}

/* ─── CONTENT ────────────────────────────────────────────────── */
main {
  position: relative;
  z-index: 1;
  max-width: var(--shell);
  margin: 0 auto;
  padding: clamp(3rem, 9vh, 5.5rem) 1.6rem 4.5rem;
  text-align: center;
  opacity: 0;
  transform: translateY(14px);
  transition: opacity var(--fade) ease, transform var(--fade) ease;
}
main.revealed { opacity: 1; transform: none; }

main.revealed > * { animation: rise 1s ease both; }
main.revealed > *:nth-child(1) { animation-delay: .15s; }
main.revealed > *:nth-child(2) { animation-delay: .30s; }
main.revealed > *:nth-child(3) { animation-delay: .45s; }
main.revealed > *:nth-child(4) { animation-delay: .60s; }
main.revealed > *:nth-child(5) { animation-delay: .75s; }
main.revealed > *:nth-child(6) { animation-delay: .90s; }
main.revealed > *:nth-child(7) { animation-delay: 1.05s; }
main.revealed > *:nth-child(8) { animation-delay: 1.20s; }
@keyframes rise {
  from { opacity: 0; transform: translateY(16px); }
  to   { opacity: 1; transform: none; }
}

section { margin-block: 2.9rem; }

.label {
  font-size: .68rem;
  letter-spacing: calc(.3em * var(--tk));
  color: var(--ink-soft);
  margin: 0 0 .55rem;
  font-weight: 400;
}

/* Verse: Arabic always, translation small beneath it when present. */
.verse-ar {
  font-family: var(--f-serif);
  font-size: 1.04rem;
  line-height: 2.15;
  color: var(--gold-deep);
  margin: 0 auto;
  max-width: 25rem;
}
.verse-en {
  font-family: var(--f-latin);
  font-style: italic;
  font-size: .84rem;
  line-height: 1.8;
  color: var(--ink-soft);
  margin: .9rem auto 0;
  max-width: 23rem;
}
.verse cite {
  display: block;
  font-family: var(--f-ui);
  font-style: normal;
  font-size: .66rem;
  letter-spacing: calc(.22em * var(--tk));
  color: var(--ink-soft);
  margin-top: .85rem;
}

.hosts {
  font-family: var(--f-serif);
  font-size: .98rem;
  line-height: 2;
  color: var(--ink-soft);
  margin: 0 auto;
  max-width: 23rem;
}
html[lang="en"] .hosts { font-family: var(--f-latin); font-size: 1.06rem; line-height: 1.85; }

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

.when  { font-size: 1.45rem; margin: 0; font-weight: 400; }
.hijri { font-size: .78rem; color: var(--ink-soft); margin: .4rem 0 0; }
.at-time { font-family: var(--f-serif); font-size: 1.7rem; color: var(--gold-deep); margin: .9rem 0 0; }
.venue { font-size: 1.1rem; margin: 0; line-height: 1.75; }

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
  font-size: 1.75rem;
  font-weight: 400;
  color: var(--gold-deep);
  font-variant-numeric: tabular-nums;
  line-height: 1.15;
}
.unit small {
  display: block;
  font-size: .62rem;
  letter-spacing: calc(.16em * var(--tk));
  color: var(--ink-soft);
  margin-top: .3rem;
}
.begun { font-family: var(--f-serif); font-size: 1.3rem; color: var(--gold-deep); margin: 0; }

.programme { list-style: none; margin: 0 auto; padding: 0; max-width: 20rem; text-align: start; }
.programme li {
  display: flex;
  align-items: baseline;
  gap: 1rem;
  padding: .7rem 0;
  border-bottom: 1px solid color-mix(in srgb, var(--gold) 20%, transparent);
}
.programme li:last-child { border-bottom: 0; }
.programme time {
  flex: none;
  width: 3.8rem;
  font-family: var(--f-serif);
  color: var(--gold-deep);
  font-variant-numeric: tabular-nums;
}
.programme span { font-size: .98rem; }

.actions { display: flex; flex-wrap: wrap; justify-content: center; gap: .7rem; }
.btn {
  display: inline-flex;
  align-items: center;
  gap: .5rem;
  padding: .8rem 1.5rem;
  border-radius: 2rem;
  font-family: var(--f-ui);
  font-size: .78rem;
  letter-spacing: calc(.13em * var(--tk));
  text-decoration: none;
  border: 1px solid var(--gold);
  color: var(--gold-deep);
  background: transparent;
  cursor: pointer;
  transition: background 250ms ease;
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
#mute svg { width: 1.1rem; height: 1.1rem; stroke-width: 1.6; }

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
  font-size: .68rem;
  text-align: center;
  text-decoration: none;
  line-height: 1.3;
}
#langs a:hover { background: color-mix(in srgb, var(--gold) 16%, var(--paper)); }

footer {
  margin-top: 3.5rem;
  font-size: .7rem;
  letter-spacing: calc(.34em * var(--tk));
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

<div id="curtain">
    @if ($template->intro_video)
        <video id="intro" playsinline preload="auto" muted src="{{ $template->intro_video }}"></video>
    @endif
    <div id="prompt">
        <div class="ring"><span></span></div>
        <p>{{ __('invitation.tap') }}</p>
    </div>
</div>

{{-- Never autoplays: playback starts in the tap handler, both to respect
     browser autoplay policy and because the reveal is the moment the
     sound belongs to. --}}
@if ($template->music)
    <audio id="music" src="{{ $template->music }}" loop preload="auto"></audio>

    <button id="mute" type="button" aria-label="{{ __('invitation.mute') }}">
        <svg id="ic-on" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M11 5 6 9H2v6h4l5 4V5z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 5.5a9 9 0 0 1 0 13"/></svg>
        <svg id="ic-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" hidden><path d="M11 5 6 9H2v6h4l5 4V5z"/><path d="m22 9-6 6M16 9l6 6"/></svg>
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

<main id="content">

    @if ($invitation->show_verse)
        <section class="verse">
            <p class="verse-ar" lang="ar" dir="rtl">{{ __('invitation.verse') }}</p>
            @if ($verseTranslation !== '')
                <p class="verse-en">{{ $verseTranslation }}</p>
            @endif
            <cite>{{ __('invitation.verse_ref') }}</cite>
        </section>
    @endif

    @if ($hosts = $invitation->text('hosts'))
        <section>
            <p class="hosts">{{ $hosts }}</p>
        </section>
    @endif

    <section>
        <h1 class="couple" lang="ar" dir="rtl">
            <span class="name">{{ $invitation->groom_ar }}@if ($invitation->showsLatinNames())<em class="latin">{{ $invitation->groom_latin }}</em>@endif</span>
            <span class="amp">&amp;</span>
            <span class="name">{{ $invitation->bride_ar }}@if ($invitation->showsLatinNames())<em class="latin">{{ $invitation->bride_latin }}</em>@endif</span>
        </h1>
        <div class="rule"><i></i></div>
    </section>

    <section>
        <p class="label">{{ __('invitation.date') }}</p>
        <p class="when">{{ $invitation->formattedDate() }}</p>
        @if ($hijri = $invitation->hijriDate())
            <p class="hijri">{{ $hijri }}</p>
        @endif
        <p class="at-time">{{ $invitation->formattedTime() }}</p>
    </section>

    <section>
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

    @if ($venueName || $venueCity)
        <section>
            <p class="label">{{ __('invitation.venue') }}</p>
            <p class="venue">
                {{ $venueName }}@if ($venueName && $venueCity)<br>@endif{{ $venueCity }}
            </p>
        </section>
    @endif

    @if ($programme)
        <section>
            <p class="label">{{ __('invitation.programme') }}</p>
            <ul class="programme">
                @foreach ($programme as $row)
                    <li>
                        <time>{{ $row['time'] ?? '' }}</time>
                        <span>{{ $row['title'] }}</span>
                    </li>
                @endforeach
            </ul>
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
const promptEl = document.getElementById('prompt');
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

        document.getElementById('ic-on').hidden  = muted;
        document.getElementById('ic-off').hidden = !muted;
        muteBtn.setAttribute('aria-label', muted ? LABELS.unmute : LABELS.mute);
    });
}

/* ── the sequence ───────────────────────────────────────────────── */
let opened = false, revealed = false, fadeTimer = null;

curtain.addEventListener('click', () => {
    if (opened) return;
    opened = true;

    promptEl.classList.add('gone');

    if (intro) {
        intro.classList.add('playing');
        intro.muted = CONFIG.keepVideoAudioMs === 0;
        intro.currentTime = 0;
        intro.play().then(armFadeOut).catch(reveal);
    } else {
        setTimeout(reveal, 400);
    }

    if (CONFIG.keepVideoAudioMs > 0 && intro) {
        setTimeout(() => { intro.muted = true; startMusic(); }, CONFIG.keepVideoAudioMs);
    } else {
        startMusic();
    }

    setTimeout(reveal, CONFIG.revealFallbackMs);
});

/* Start the dissolve before the clip runs out so it fades while still in
   motion. setTimeout for precision; timeupdate backs it up if playback
   stalls or seeks. */
function armFadeOut() {
    if (!intro) return;
    const d = intro.duration;
    if (!isFinite(d) || d <= 0) return;
    clearTimeout(fadeTimer);
    fadeTimer = setTimeout(reveal, Math.max(0, (d - CONFIG.fadeOutLeadMs / 1000 - intro.currentTime) * 1000));
}

if (intro) {
    intro.addEventListener('loadedmetadata', () => { if (opened) armFadeOut(); });
    intro.addEventListener('timeupdate', () => {
        const d = intro.duration;
        if (isFinite(d) && d - intro.currentTime <= CONFIG.fadeOutLeadMs / 1000) reveal();
    });
    intro.addEventListener('ended', reveal);
    intro.addEventListener('error', reveal);
}

function reveal() {
    if (revealed) return;
    revealed = true;
    clearTimeout(fadeTimer);

    curtain.classList.add('gone');
    content.classList.add('revealed');
    if (muteBtn) muteBtn.classList.add('on');
    if (langs) langs.classList.add('on');
    settleMusic();

    // Let the clip run through the whole dissolve — pausing here is what
    // made it look like it stopped and then faded.
    setTimeout(() => {
        if (intro) intro.pause();
        curtain.remove();
    }, CONFIG.crossfadeMs + 200);
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
