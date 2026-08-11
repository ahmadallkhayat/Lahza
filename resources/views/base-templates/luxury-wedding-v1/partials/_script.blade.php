<script>
const CONFIG = @json($s);
const TARGET = new Date('{{ $invitation->startsAtIso() }}').getTime();
const ARABIC_NUMERALS = @json(config("templates.locales.{$locale}.numerals") === 'arab');
const IS_BOOMERANG = @json($template->boomerang_intro);

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
   Three beats, and each one gets the screen to itself:

     1. the clip plays sharp; near its end the names appear over it,
        reading as its inverse
     2. the clip softens to the backdrop it will stay as, with the names
        still the only thing on it — this is a beat, not a transition
     3. the rest of the invitation assembles and the page unlocks

   Beat two used to happen underneath beat three, which is why the blur never
   registered as anything: by the time the eye reached it there was content on
   top. blurLeadMs is the gap that separates them.

   Nothing moves between beats. The names are released in their final position
   and stay there — what changes is what else is visible around them. */
let opened = false, named = false, revealed = false;
let fadeTimer = null, namesTimer = null;

/* Set when the invitation is entered from a language switch. The beats below
   are all played out over time, and there is nothing to play out when the
   guest has already watched the intro once — so it marks them as instant
   rather than giving the skip path its own copy of the same steps. */
let skipping = false;

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

/* ── the held first frame ───────────────────────────────────────────
   Show the clip's own opening frame, blurred, as what the guest waits on.

   Tied to readyState rather than to the prompt, because the two answer
   different questions: armPrompt also fires on error and on the loader
   timeout, and veiling then would hold up an empty black rectangle where
   the curtain's own colour belongs. HAVE_CURRENT_DATA is the level that
   means there is a frame decoded to show — anything less and this would
   be asking the compositor to blur nothing. */
/* ── buffering while they look ──────────────────────────────────────
   The clip is marked preload="metadata" so the page becomes interactive
   without waiting for a several-megabyte download. That is right for the
   first paint and wrong for the tap: a guest who taps promptly does it
   against a clip with almost nothing buffered, and the envelope opens onto
   a frame that has not started moving yet.

   The wait is the answer to it. Once metadata is in, there is a held frame
   on screen and a prompt to read, and that time can be spent fetching
   rather than idle — so preload is raised to auto and the clip keeps
   loading behind the veil.

   The property alone, never load(): load() restarts the fetch from nothing
   and throws away the very frame the veil is showing. */
function deepen() {
    if (!intro || intro.preload === 'auto') return;

    intro.preload = 'auto';
}

if (intro) {
    if (intro.readyState >= 1) deepen();
    else intro.addEventListener('loadedmetadata', deepen, { once: true });
}

let veiled = false;

function veil() {
    if (veiled || !intro) return;

    /* Never after the tap.

       This is the bug that left the whole clip blurred for its entire run.
       The frame only has to arrive before the guest taps for the ordinary
       path to work, and on a slow connection it does not: armPrompt also
       fires on the loader timeout, so the tap can land first, and then
       loadeddata turns up afterwards and veils a clip that is already
       playing. `.veiled` carries the blur and sits below `.playing` in the
       stylesheet, so it wins — and nothing takes it off again, because the
       only thing that does is the tap that already happened. */
    if (opened) return;

    veiled = true;

    intro.classList.add('veiled');
}

if (!intro) {
    // No clip to wait for.
    armPrompt();
} else if (intro.readyState >= 3) {
    // Already buffered — a cached clip is ready before this script runs.
    veil();
    armPrompt();
} else {
    intro.addEventListener('canplay', armPrompt, { once: true });
    intro.addEventListener('loadeddata', armPrompt, { once: true });

    // The first frame usually lands well before the clip can play through,
    // so the wait is spent looking at the wedding rather than at a colour.
    intro.addEventListener('loadeddata', veil, { once: true });

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

    /* Music waits for the clip to actually be on screen, not for play() to
       have been called.

       play()'s own promise resolves once the browser has granted playback,
       which is not the same moment as the first frame painting — on a cold
       decoder, a slow connection, or just an unlucky device, there is a real
       gap between the two. Starting music on the promise (or, before that,
       on nothing at all) meant the guest sometimes heard the track begin
       against a still-frozen curtain, because nothing here had actually
       confirmed the video was moving yet.

       'playing' is the event for that: it fires specifically when rendering
       resumes after being paused or stalled for data, which is exactly what
       "the clip is now visibly running" means. musicArmed guards against
       double-starting if the fallback below ever fires alongside it. */
    let musicArmed = false;

    function beginMusic() {
        if (musicArmed) return;
        musicArmed = true;

        if (CONFIG.keepVideoAudioMs > 0 && intro) {
            setTimeout(() => { intro.muted = true; startMusic(); }, CONFIG.keepVideoAudioMs);
        } else {
            startMusic();
        }
    }

    if (intro) {
        // Off with the blur as the envelope opens: .playing carries no filter,
        // so the held frame resolves into the moving clip over the same
        // --open that the flap folds through. One gesture, not two.
        intro.classList.remove('veiled');
        intro.classList.add('playing');

        // Never leave the guest in silence if 'playing' happens not to fire —
        // a stall right after a successful play() is rare but not impossible,
        // and total silence for the rest of the visit is worse than music
        // starting a little late.
        intro.addEventListener('playing', beginMusic, { once: true });
        setTimeout(beginMusic, CONFIG.revealFallbackMs);

        /* Muted, and played synchronously inside the click.

           Both halves of that matter, and getting either wrong is what made the
           clip sometimes not play at all. A silent video is the only kind a
           browser will always start; this used to unmute first whenever
           keepVideoAudioMs was set, so the call was at the browser's discretion.
           And the call has to happen in the click's own tick — it was wrapped in
           a 50ms setTimeout for an iOS frozen-frame bug, which spent the user
           gesture, so by the time play() ran there was no gesture left to
           authorise it. It rejected, and .catch(reveal) then threw away the
           whole intro. Hence: mute, play now, and deal with audio once it runs. */
        intro.muted = true;
        if (intro.currentTime > 0.1) {
            intro.currentTime = 0;
        }

        intro.play().then(() => {
            // Safe here in a way it is not before play(): flipping `muted` in
            // the same tick as the call is what froze the frame on iOS Safari,
            // but playback is already running by the time this resolves.
            if (CONFIG.keepVideoAudioMs > 0) {
                intro.muted = false;
            }

            armSequence();
        }).catch(() => {
            // Even muted playback was refused, so there is no duration to hang
            // the sequence off. The names still lead, just on a timer. Nothing
            // is playing, so there is nothing for music to wait on.
            showNames();
            setTimeout(reveal, 900);
            beginMusic();
        });
    } else {
        // No clip: the names still lead, just on a timer rather than a
        // duration, and music has nothing to sync to either.
        showNames();
        setTimeout(reveal, 900);
        beginMusic();
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

    content.classList.add('blending', 'names-in');
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

/* ── the peek ───────────────────────────────────────────────────────
   Once everything has arrived, the page lifts a little and settles back.

   An invitation opens on a full screen of names and verse with the fold
   well below it, and nothing about a still page says there is more under
   it — the cue says so in words, and words are read after movement is
   noticed, not before. So the page shows rather than tells: it moves just
   far enough that the eye registers travel, and returns, which leaves the
   names exactly where they were rather than scrolling the guest off them.

   Then the cue, and not before. Arming it first would have the peek's own
   scroll event dismiss it a frame later — the hint would take the cue away
   with it, and the one guest who missed the movement would be left with
   neither.

   Real intent cancels it outright: wheel and touchstart, not scroll, since
   the peek itself is a scroll and cannot be told from one. */
function peekThenCue() {
    const room = document.documentElement.scrollHeight - window.innerHeight;
    const distance = Math.min(CONFIG.scrollHintPx, room);

    if (
        distance < 8
        || matchMedia('(prefers-reduced-motion: reduce)').matches
    ) {
        armScrollCue();

        return;
    }

    let cancelled = false;

    const cancel = () => {
        if (cancelled) return;
        cancelled = true;

        // Whatever they are doing now is better than a hint, so stop moving
        // and let the cue stand for the rest.
        armScrollCue();
    };

    window.addEventListener('wheel', cancel, { once: true, passive: true });
    window.addEventListener('touchstart', cancel, { once: true, passive: true });

    window.scrollTo({ top: distance, behavior: 'smooth' });

    setTimeout(() => {
        if (cancelled) return;

        window.scrollTo({ top: 0, behavior: 'smooth' });

        setTimeout(() => {
            if (cancelled) return;

            armScrollCue();
        }, 700);
    }, 900);
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
    // Boomerang video loops on its own, no ambient needed
    if (IS_BOOMERANG) return;

    // Motion is the entire point of this layer, so honouring the preference
    // means not running it at all rather than running it faster.
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const layer = ambient ?? intro;
    if (!layer) return;

    // The encoded loop is already slow, so it plays at its own speed.
    // We don't artificially slow the intro fallback anymore because it stutters.
    if (layer === ambient) {
        layer.playbackRate = 1;

        /* Hand the backdrop over rather than leaving both stacked.

           #intro.handed exists for this and nothing was ever adding it, so the
           intro sat at .18 underneath the loop's .22 for the whole visit —
           two decodes running, and the intro's frozen last frame showing
           through. Both are blurred identically by now, so the cross is
           invisible; once it is done the intro has nothing left to show. */
        if (intro) {
            intro.classList.add('handed');
            setTimeout(() => intro.pause(), CONFIG.crossfadeMs + 200);
        }
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
    let d = intro.duration;
    if (!isFinite(d) || d <= 0) {
        // Duration unknown, so lead times mean nothing. Show the names now
        // rather than leave the clip playing over an empty page.
        showNames();

        return;
    }

    // For boomerang videos (forward + reverse), use half the duration for timing
    if (IS_BOOMERANG) d = d / 2;

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
        let d = intro.duration;
        if (!isFinite(d)) return;
        // For boomerang videos (forward + reverse), use half the duration for timing
        if (IS_BOOMERANG) d = d / 2;
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

    // The clip keeps running right through the blur and is only handed over
    // once the backdrop loop is ready to take it. Reaching its last frame would
    // freeze it visibly, and it is on screen for all of this.
    if (intro) intro.loop = true;

    /* ── beat two: the clip softens, alone.
       The names are the only thing on screen and stay that way while this
       happens. That is the whole point of the pause before beat three — a blur
       that resolves behind arriving content is a blur nobody sees. */
    if (intro) intro.classList.add('ambient');
    curtain.classList.add('gone');

    // Nothing to play out when the intro was skipped: there was no sharp clip
    // to soften and no blend to undo, so every beat lands at once.
    const settle = skipping ? 0 : CONFIG.crossfadeMs;
    const blurLead = skipping ? 0 : CONFIG.blurLeadMs;

    // Once the footage is soft there is nothing sharp left to invert against,
    // so the names come off blend mode and settle into their own ink.
    setTimeout(() => content.classList.remove('blending'), settle);

    // ── beat three: the rest of the invitation, once the blur has landed.
    setTimeout(showContent, blurLead);

    // The backdrop loop takes over for the rest of the visit.
    setTimeout(() => {
        curtain.remove();
        startAmbient();
    }, blurLead + settle);
}

/**
 * Beat three on its own.
 *
 * Split out of reveal() so the blur can hold the screen before it, and so the
 * skip path reaches exactly these steps rather than a second list of them.
 */
function showContent() {
    // The stagger step comes from the element's own position, counted here
    // rather than written into the stylesheet — sections are conditional, so a
    // verse-less invitation would otherwise leave a hole in the sequence.
    Array.from(content.children).forEach((el, i) => el.style.setProperty('--i', i));

    content.classList.add('revealed');
    if (muteBtn) muteBtn.classList.add('on');
    if (langs) langs.classList.add('on');
    settleMusic();
    release();
    startPetals();

    // After the content has had a moment to finish arriving — the sections
    // stagger in, and peeking through that would read as the page lurching
    // rather than as an invitation to scroll.
    setTimeout(peekThenCue, CONFIG.scrollHintDelayMs);
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

    // Collapses reveal()'s waits to nothing. Without it the guest would watch
    // blurLeadMs of empty softened backdrop, scroll-locked, before the
    // invitation they have already seen once turned up again.
    skipping = true;

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
