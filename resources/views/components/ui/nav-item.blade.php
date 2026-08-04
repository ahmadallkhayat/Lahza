@props(['label', 'href', 'icon' => 'square-3-stack-3d', 'active' => false])

<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->class([
        'group flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
        'bg-brand-50 text-brand-700' => $active,
        'text-ink-600 hover:bg-ink-100 hover:text-ink-900' => ! $active,
    ]) }}
>
    <x-dynamic-component
        :component="'heroicon-o-'.$icon"
        class="h-[1.05rem] w-[1.05rem] shrink-0 {{ $active ? 'text-brand-600' : 'text-ink-400 group-hover:text-ink-600' }}"
    />
    <span class="truncate">{{ $label }}</span>
</a>
