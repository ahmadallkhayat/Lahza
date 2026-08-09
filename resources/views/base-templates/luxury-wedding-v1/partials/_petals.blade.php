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
