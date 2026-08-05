{{-- Per-language content in one card, languages side by side.

     Columns are laid out with an inline grid-template rather than a Tailwind
     class because the count follows the languages ticked, which only Alpine
     knows at runtime.

     The programme is row-aligned across languages: it is the same sequence of
     events described in each language, so a row is one moment of the evening
     with a time and title per language. Times repeat per language because the
     digits differ (٧:٠٠ vs 7:00). --}}

@php
    $locales = config('templates.locales');
@endphp

<x-ui.card
    title="Content"
    description="Written once per language. Each language's guest link shows its own."
>
    <x-slot:actions>
        <div class="flex items-center gap-2">
            <span
                x-show="translating"
                x-cloak
                class="text-xs text-ink-500"
            >Translating…</span>

            <x-ui.button
                type="button"
                variant="secondary"
                size="sm"
                icon="language"
                x-bind:disabled="translating || locales.length < 2 || ! has('en')"
                x-on:click="translateFromEnglish()"
            >
                Translate from English
            </x-ui.button>
        </div>
    </x-slot:actions>

    {{-- Outcome of the last translation attempt. --}}
    <template x-if="translateNote">
        <div
            class="mb-5 flex items-start gap-3 rounded-lg border px-4 py-3 text-sm"
            :class="translateOk
                ? 'bg-ok-50 border-ok-600/20 text-ok-700'
                : 'bg-warn-50 border-warn-600/20 text-warn-700'"
        >
            <x-heroicon-o-information-circle class="mt-0.5 h-4 w-4 shrink-0" />
            <span x-text="translateNote"></span>
        </div>
    </template>

    <p x-show="! has('en')" x-cloak class="mb-5 text-xs text-ink-500">
        Tick English to use the translate button — it works from the English text.
    </p>

    {{-- ── Text fields, one column per language ─────────────────────── --}}
    <div class="space-y-5">
        @foreach (['hosts' => 'Hosting line', 'venue_name' => 'Venue', 'venue_city' => 'City'] as $field => $label)
            <div>
                <x-ui.label>{{ $label }}</x-ui.label>
                @if ($field === 'hosts')
                    <p class="mb-2 text-xs text-ink-500">The families extending the invitation.</p>
                @endif

                <div
                    class="grid gap-3"
                    :style="`grid-template-columns: repeat(${locales.length}, minmax(0, 1fr))`"
                >
                    @foreach ($locales as $code => $meta)
                        <div x-show="has('{{ $code }}')" x-cloak>
                            <div class="mb-1 flex items-center gap-1.5">
                                <span class="text-[0.65rem] font-semibold uppercase tracking-wider text-ink-400">
                                    {{ $meta['label'] }}
                                </span>
                                <span
                                    x-show="reviewed['{{ $code }}']"
                                    x-cloak
                                    class="rounded bg-warn-50 px-1.5 py-0.5 text-[0.6rem] font-medium text-warn-700"
                                    title="Machine translated — read it before publishing"
                                >review</span>
                            </div>

                            @if ($field === 'hosts')
                                <textarea
                                    name="{{ $field }}[{{ $code }}]"
                                    id="{{ $field }}_{{ $code }}"
                                    rows="3"
                                    @if ($meta['rtl']) dir="rtl" @endif
                                    x-model="text.{{ $field }}['{{ $code }}']"
                                    class="block w-full rounded-lg border-ink-300 bg-white text-sm text-ink-800 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                ></textarea>
                            @else
                                <input
                                    type="text"
                                    name="{{ $field }}[{{ $code }}]"
                                    id="{{ $field }}_{{ $code }}"
                                    @if ($meta['rtl']) dir="rtl" @endif
                                    x-model="text.{{ $field }}['{{ $code }}']"
                                    class="block w-full rounded-lg border-ink-300 bg-white text-sm text-ink-800 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                >
                            @endif
                        </div>
                    @endforeach
                </div>

                <x-ui.error :name="$field.'.*'" />
            </div>
        @endforeach
    </div>

    {{-- ── Programme, rows aligned across languages ─────────────────── --}}
    <div class="mt-7 border-t border-ink-200 pt-5">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-medium text-ink-900">Programme</p>
                <p class="text-xs text-ink-500">
                    One row per moment of the evening, described in each language.
                    Rows with no title in any language are discarded on save.
                </p>
            </div>
            <x-ui.button type="button" variant="secondary" size="sm" icon="plus" x-on:click="addStep()">
                Add row
            </x-ui.button>
        </div>

        <div class="space-y-3">
            <template x-for="(step, i) in steps" :key="i">
                <div class="rounded-lg border border-ink-200 bg-ink-50 p-3">
                    <div class="flex items-start gap-3">
                        <div
                            class="grid flex-1 gap-3"
                            :style="`grid-template-columns: repeat(${locales.length}, minmax(0, 1fr))`"
                        >
                            @foreach ($locales as $code => $meta)
                                <div x-show="has('{{ $code }}')" x-cloak>
                                    <p class="mb-1 text-[0.65rem] font-semibold uppercase tracking-wider text-ink-400">
                                        {{ $meta['label'] }}
                                    </p>
                                    <div class="flex gap-2" @if ($meta['rtl']) dir="rtl" @endif>
                                        <input
                                            type="text"
                                            :name="`programme[{{ $code }}][${i}][time]`"
                                            x-model="step['{{ $code }}'].time"
                                            placeholder="{{ $meta['numerals'] === 'arab' ? '٧:٠٠' : '7:00' }}"
                                            class="w-20 shrink-0 rounded-lg border-ink-300 bg-white text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                            aria-label="Time"
                                        >
                                        <input
                                            type="text"
                                            :name="`programme[{{ $code }}][${i}][title]`"
                                            x-model="step['{{ $code }}'].title"
                                            class="w-full rounded-lg border-ink-300 bg-white text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                            aria-label="What happens"
                                        >
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <button
                            type="button"
                            x-on:click="steps.splice(i, 1)"
                            class="mt-5 shrink-0 rounded-md p-2 text-ink-400 hover:bg-bad-50 hover:text-bad-600"
                        >
                            <x-heroicon-o-trash class="h-4 w-4" />
                            <span class="sr-only">Remove row</span>
                        </button>
                    </div>
                </div>
            </template>

            <p x-show="steps.length === 0" class="text-sm text-ink-400">No programme rows.</p>
        </div>
    </div>
</x-ui.card>
