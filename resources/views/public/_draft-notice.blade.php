{{-- Shown only to a signed-in studio user looking at an unpublished
     invitation. Guests never reach this page at all — the controller 404s
     before rendering — but without a marker the preview is indistinguishable
     from the live page, which reads as "the link works" when it does not.

     Fixed to the bottom so it does not sit over the intro reveal, and outside
     the invitation's own styling so a template cannot accidentally hide it.
     `print:hidden` keeps it off a saved PDF. --}}
@if (! $invitation->published && auth()->check())
    <div
        dir="ltr"
        class="print:hidden"
        style="position:fixed; inset-inline:0; bottom:0; z-index:2147483647;
               display:flex; flex-wrap:wrap; align-items:center; justify-content:center;
               gap:.75rem; padding:.6rem 1rem;
               background:#1c1917; color:#fafaf9;
               font-family:ui-sans-serif,system-ui,sans-serif; font-size:.8125rem; line-height:1.4;
               box-shadow:0 -1px 12px rgb(0 0 0 / .35);"
    >
        <span style="display:inline-flex; align-items:center; gap:.45rem;">
            <span style="display:inline-block; width:.5rem; height:.5rem; border-radius:9999px; background:#f59e0b;"></span>
            <strong style="font-weight:600;">Draft — not published.</strong>
            <span style="color:#d6d3d1;">Only you can see this. Guests get a “page not found”.</span>
        </span>

        <a
            href="{{ route('admin.invitations.edit', $invitation) }}"
            style="color:#fafaf9; text-decoration:underline; text-underline-offset:2px; white-space:nowrap;"
        >Publish it</a>
    </div>
@endif
