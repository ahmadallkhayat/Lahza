@props([
    'title' => null,
    'description' => null,
    'padded' => true,
])

<div {{ $attributes->class('rounded-xl border border-ink-200 bg-white shadow-panel') }}>
    @if ($title || isset($actions))
        {{-- flex-wrap so actions drop onto their own line in a narrow card
             rather than squeezing the title into a one-word-per-line column. --}}
        <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-3 border-b border-ink-200 px-5 py-4">
            <div class="min-w-0 flex-1 basis-40">
                @if ($title)
                    <h2 class="text-sm font-semibold text-ink-900">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-xs text-ink-500">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="{{ $padded ? 'p-5' : '' }}">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="border-t border-ink-200 bg-ink-50 px-5 py-3">{{ $footer }}</div>
    @endisset
</div>
