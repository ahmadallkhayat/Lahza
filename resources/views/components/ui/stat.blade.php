@props(['label', 'value', 'icon' => null, 'hint' => null])

<div {{ $attributes->class('rounded-xl border border-ink-200 bg-white p-5 shadow-panel') }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-xs font-medium text-ink-500">{{ $label }}</p>
        @if ($icon)
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-4 w-4 shrink-0 text-ink-400" />
        @endif
    </div>

    <p class="mt-2 text-2xl font-semibold tracking-tight text-ink-900 tabular-nums">{{ $value }}</p>

    @if ($hint)
        <p class="mt-1 text-xs text-ink-500">{{ $hint }}</p>
    @endif
</div>
