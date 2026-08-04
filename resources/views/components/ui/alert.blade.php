@props(['tone' => 'brand', 'title' => null])

@php
    $tones = [
        'brand' => ['bg-brand-50 border-brand-200 text-brand-800', 'information-circle', 'text-brand-600'],
        'ok'    => ['bg-ok-50 border-ok-600/20 text-ok-700',       'check-circle',       'text-ok-600'],
        'warn'  => ['bg-warn-50 border-warn-600/20 text-warn-700', 'exclamation-triangle', 'text-warn-600'],
        'bad'   => ['bg-bad-50 border-bad-600/20 text-bad-700',    'x-circle',           'text-bad-600'],
    ];

    [$shell, $icon, $iconTone] = $tones[$tone] ?? $tones['brand'];
@endphp

<div {{ $attributes->class(['flex items-start gap-3 rounded-lg border px-4 py-3 text-sm', $shell]) }}>
    <x-dynamic-component :component="'heroicon-o-'.$icon" class="mt-0.5 h-4 w-4 shrink-0 {{ $iconTone }}" />
    <div class="min-w-0">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div class="{{ $title ? 'mt-0.5' : '' }}">{{ $slot }}</div>
    </div>
</div>
