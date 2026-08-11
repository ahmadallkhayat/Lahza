{{-- Brand lockup.

     The mark carries its own letterforms, but only legibly at size. So pass
     `wordmark` anywhere it runs small — the sidebar and the mobile bar — and
     leave it off where the mark is large enough to stand alone, as on sign-in
     and the landing page's hero. --}}
@props(['href' => null, 'wordmark' => false, 'size' => 'h-9'])

<{{ $href ? 'a' : 'div' }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class('inline-flex items-center gap-2.5') }}
>
    <x-ui.logo :class="$size" />

    @if ($wordmark)
        <span class="text-sm font-semibold tracking-tight text-ink-900">Lahza</span>
    @endif
</{{ $href ? 'a' : 'div' }}>
