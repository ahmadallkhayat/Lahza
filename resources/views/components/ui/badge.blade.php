@props(['tone' => 'neutral'])

@php
    $tones = [
        'neutral' => 'bg-ink-100 text-ink-700 ring-ink-200',
        'brand'   => 'bg-brand-50 text-brand-700 ring-brand-200',
        'ok'      => 'bg-ok-50 text-ok-700 ring-ok-600/20',
        'warn'    => 'bg-warn-50 text-warn-700 ring-warn-600/20',
        'bad'     => 'bg-bad-50 text-bad-700 ring-bad-600/20',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
    $tones[$tone] ?? $tones['neutral'],
]) }}>{{ $slot }}</span>
