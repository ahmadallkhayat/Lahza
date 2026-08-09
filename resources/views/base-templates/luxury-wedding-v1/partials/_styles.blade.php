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

/* The intro plays sharp, then hands over. Tied to --fade (crossfadeMs) so the
   clip's dissolve and the curtain's are the same length by construction. */
#intro {
  opacity: 0;
  transition: opacity var(--fade) var(--ease-soft), filter var(--fade) var(--ease-soft);
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
  transition: opacity 420ms var(--ease);
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
main.names-in:not(.revealed) .couple-section {
  opacity: 1;
  transform: none;
}

main.names-in:not(.revealed) .couple {
  /* Lifted to the center of the viewport so the names sit over the clip. */
  transform: translateY(var(--lift, 0px));
}

/* ── Names over the clip ─────────────────────────────────────────
   While the video is still running the names read as the inverse of whatever
   is behind them: white on a dark frame, black on a bright one, changing as
   the footage does. `difference` against white gives exactly that, per pixel
   and per frame, with no sampling and nothing to keep in sync.

   This is why the clip sits at the bottom of the root stacking context and
   why `main` carries no z-index — blending only reaches what is painted below
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
  margin: 32px 0 0;
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
