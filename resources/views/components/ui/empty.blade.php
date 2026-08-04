@props(['icon' => 'inbox', 'title', 'description' => null])

<div {{ $attributes->class('flex flex-col items-center justify-center px-6 py-14 text-center') }}>
    <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-lg bg-ink-100">
        <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5 text-ink-400" />
    </div>

    <p class="text-sm font-medium text-ink-800">{{ $title }}</p>

    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-ink-500">{{ $description }}</p>
    @endif

    @isset($actions)
        <div class="mt-5 flex items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
