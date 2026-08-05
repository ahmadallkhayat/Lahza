{{-- Brand lockup.

     The mark already contains "lahza" as letterforms, so no text wordmark sits
     beside it — that would say the name twice. Pass :wordmark="true" if the
     mark is ever used at a size where its letterforms stop being legible. --}}
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
