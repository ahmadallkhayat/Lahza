{{-- `href` turns the whole tile into a link. A number worth showing is
     usually a number worth clicking into, and a tile-sized target beats
     hunting for the matching item in the sidebar. --}}
@props(['label', 'value', 'icon' => null, 'hint' => null, 'href' => null])

@php
    $tag = $href ? 'a' : 'div';

    $classes = implode(' ', array_filter([
        'block rounded-xl border border-ink-200 bg-white p-5 shadow-panel',
        $href ? 'group transition-colors hover:border-brand-300 hover:bg-brand-50/30' : null,
    ]));
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class($classes) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-xs font-medium text-ink-500">{{ $label }}</p>
        @if ($icon)
            <x-dynamic-component
                :component="'heroicon-o-'.$icon"
                class="h-4 w-4 shrink-0 text-ink-400 {{ $href ? 'group-hover:text-brand-500' : '' }}"
            />
        @endif
    </div>

    <p class="mt-2 text-2xl font-semibold tracking-tight text-ink-900 tabular-nums">{{ $value }}</p>

    @if ($hint)
        <p class="mt-1 text-xs text-ink-500">{{ $hint }}</p>
    @endif
</{{ $tag }}>
