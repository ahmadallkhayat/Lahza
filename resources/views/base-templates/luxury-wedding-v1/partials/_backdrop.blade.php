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
