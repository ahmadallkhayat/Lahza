{{-- `collapsible` folds the body behind its header. Useful for sections whose
     defaults are usually right — they stay reachable without taking up the
     screen on every visit. `open` decides the starting state.

     A collapsible card always renders its header, since the header is the
     control. Non-collapsible cards behave exactly as before. --}}
@props([
    'title' => null,
    'description' => null,
    'padded' => true,
    'collapsible' => false,
    'open' => false,
])

<div
    @if ($collapsible) x-data="{ open: @js((bool) $open) }" @endif
    {{ $attributes->class('rounded-xl border border-ink-200 bg-white shadow-panel') }}
>
    @if ($title || isset($actions) || $collapsible)
        {{-- flex-wrap so actions drop onto their own line in a narrow card
             rather than squeezing the title into a one-word-per-line column. --}}
        <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-3 border-b border-ink-200 px-5 py-4">
            @if ($collapsible)
                <button
                    type="button"
                    @click="open = ! open"
                    :aria-expanded="open ? 'true' : 'false'"
                    class="-m-1 flex min-w-0 flex-1 basis-40 items-start gap-2 rounded-lg p-1 text-left transition-colors hover:bg-ink-50"
                >
                    <x-heroicon-o-chevron-right
                        class="mt-0.5 h-4 w-4 shrink-0 text-ink-400 transition-transform"
                        ::class="open && 'rotate-90'"
                    />
                    <span class="min-w-0">
                        @if ($title)
                            <span class="block text-sm font-semibold text-ink-900">{{ $title }}</span>
                        @endif
                        @if ($description)
                            <span class="mt-0.5 block text-xs text-ink-500">{{ $description }}</span>
                        @endif
                    </span>
                </button>
            @else
                <div class="min-w-0 flex-1 basis-40">
                    @if ($title)
                        <h2 class="text-sm font-semibold text-ink-900">{{ $title }}</h2>
                    @endif
                    @if ($description)
                        <p class="mt-0.5 text-xs text-ink-500">{{ $description }}</p>
                    @endif
                </div>
            @endif

            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    {{-- x-transition, not the x-collapse plugin: core Alpine already ships
         this and a height animation is not worth a new dependency. --}}
    <div
        class="{{ $padded ? 'p-5' : '' }}"
        @if ($collapsible)
            x-show="open"
            x-transition.opacity.duration.150ms
            x-cloak
        @endif
    >
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="border-t border-ink-200 bg-ink-50 px-5 py-3" @if ($collapsible) x-show="open" x-cloak @endif>
            {{ $footer }}
        </div>
    @endisset
</div>
