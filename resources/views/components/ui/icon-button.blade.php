{{-- A square, icon-only action for table rows and toolbars.

     `label` is required and not optional by accident: an icon on its own has
     no accessible name, so it is rendered as both the tooltip and screen
     reader text. Without it the button is a mystery to anyone not using a
     mouse, and to anyone who does not recognise the glyph.

     `disabled` renders a real disabled button rather than hiding the action,
     so the reason can still be read from the tooltip. --}}
@props([
    'icon',
    'label',
    'href' => null,
    'type' => 'button',
    'tone' => 'default',
    'disabled' => false,
])

@php
    $tones = [
        'default' => 'text-ink-500 hover:bg-ink-100 hover:text-ink-900',
        'danger' => 'text-ink-500 hover:bg-bad-50 hover:text-bad-700',
        'ok' => 'text-ok-600 hover:bg-ok-50 hover:text-ok-700',
        'brand' => 'text-brand-600 hover:bg-brand-50 hover:text-brand-700',
    ];

    $classes = implode(' ', [
        'inline-flex h-8 w-8 items-center justify-center rounded-lg transition-colors',
        'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500',
        $disabled
            ? 'cursor-not-allowed text-ink-300'
            : ($tones[$tone] ?? $tones['default']),
    ]);
@endphp

@if ($href && ! $disabled)
    <a href="{{ $href }}" title="{{ $label }}" {{ $attributes->class($classes) }}>
        <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-[1.05rem] w-[1.05rem]" />
        <span class="sr-only">{{ $label }}</span>
    </a>
@else
    <button
        type="{{ $type }}"
        title="{{ $label }}"
        @disabled($disabled)
        {{ $attributes->class($classes) }}
    >
        <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-[1.05rem] w-[1.05rem]" />
        <span class="sr-only">{{ $label }}</span>
    </button>
@endif
