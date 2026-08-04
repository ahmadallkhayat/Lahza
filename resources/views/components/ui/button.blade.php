@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'type' => 'button',
])

@php
    $sizes = [
        'sm' => 'text-xs px-3 py-1.5 gap-1.5',
        'md' => 'text-sm px-4 py-2.5 gap-2',
    ];

    $variants = [
        'primary'   => 'bg-brand-600 text-white hover:bg-brand-700',
        'secondary' => 'bg-white text-ink-700 border border-ink-300 hover:bg-ink-50',
        'ghost'     => 'text-ink-600 hover:bg-ink-100',
        'danger'    => 'bg-bad-600 text-white hover:bg-bad-700',
    ];

    $classes = implode(' ', [
        'inline-flex items-center justify-center rounded-lg font-medium transition-colors',
        'disabled:opacity-50 disabled:pointer-events-none',
        $sizes[$size] ?? $sizes['md'],
        $variants[$variant] ?? $variants['primary'],
    ]);

    $iconSize = $size === 'sm' ? 'w-3.5 h-3.5' : 'w-4 h-4';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="{{ $iconSize }}" />
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="{{ $iconSize }}" />
        @endif
        {{ $slot }}
    </button>
@endif
