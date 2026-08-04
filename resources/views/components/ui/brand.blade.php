{{-- Wordmark. Flat fill, no gradient. --}}
@props(['href' => null])

<{{ $href ? 'a' : 'div' }} @if($href) href="{{ $href }}" @endif
    {{ $attributes->class('flex items-center gap-2.5') }}>
    <span class="flex h-7 w-7 items-center justify-center rounded-md bg-brand-600 text-sm font-semibold text-white">L</span>
    <span class="text-sm font-semibold tracking-tight text-ink-900">Lahza</span>
</{{ $href ? 'a' : 'div' }}>
