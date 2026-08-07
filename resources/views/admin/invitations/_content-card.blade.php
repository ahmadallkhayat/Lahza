{{-- Per-language content in one card, languages side by side.

     Columns are laid out with an inline grid-template rather than a Tailwind
     class because the count follows the languages ticked, which only Alpine
     knows at runtime.

     The programme is row-aligned across languages: it is the same sequence of
     events described in each language, so a row is one moment of the evening
     with a time and title per language. Times are still stored per language
     because the digits differ (٧:٠٠ vs 7:00), but they are no longer typed
     per language — one picker per row writes all of them. --}}

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
        @foreach ($plainFields as $field => $meta)
            <div>
                <x-ui.label>{{ $meta['label'] }}</x-ui.label>
                @if ($meta['hint'])
                    <p class="mb-2 text-xs text-ink-500">{{ $meta['hint'] }}</p>
                @endif

                {{-- Ready-written text from the library. Placeholders are filled
                     in as it is inserted, so what lands in the box is ordinary
                     editable text rather than tokens waiting to be resolved.

                     A block written in one language goes into that language's
                     column; one with no language goes into the default. Which
                     column it landed in is said out loud — with three columns
                     side by side, guessing is not good enough. --}}
                @if ($textAssets->isNotEmpty())
                    @php
                        $suits = $textAssets->filter(fn ($a) => $a->field === $field || blank($a->field));
                        $others = $textAssets->reject(fn ($a) => $a->field === $field || blank($a->field));
                    @endphp

                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <select
                            x-model="picked.{{ $field }}"
                            aria-label="Premade text for {{ $meta['label'] }}"
                            class="max-w-xs rounded-lg border-ink-300 bg-white text-xs text-ink-800 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                        >
                            <option value="">Insert premade text…</option>

                            @if ($suits->isNotEmpty())
                                <optgroup label="Suits this field">
                                    @foreach ($suits as $asset)
                                        <option value="{{ $asset->id }}">
                                            {{ $asset->name }}{{ $asset->localeLabel() ? ' — '.$asset->localeLabel() : '' }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endif

                            @if ($others->isNotEmpty())
                                <optgroup label="Other text">
                                    @foreach ($others as $asset)
                                        <option value="{{ $asset->id }}">
                                            {{ $asset->name }}{{ $asset->localeLabel() ? ' — '.$asset->localeLabel() : '' }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endif
                        </select>

                        <x-ui.button
                            type="button"
                            variant="secondary"
                            size="sm"
                            x-bind:disabled="! picked.{{ $field }}"
                            x-on:click="insertAsset('{{ $field }}')"
                        >Insert</x-ui.button>

                        <span
                            x-show="assetNote.{{ $field }}"
                            x-cloak
                            class="text-xs text-ok-700"
                            x-text="assetNote.{{ $field }}"
                        ></span>
                    </div>
                @endif

                @if ($meta['arabic'])
                    {{-- One box, not one per language: the value is Arabic in
                         every language, like the names. It posts under whichever
                         language is default, which is bound rather than printed
                         so changing the default here does not strand it. --}}
                    <div class="max-w-xl">
                        @if ($meta['rows'])
                            <textarea
                                :name="`{{ $meta['name'] }}[${def}]`"
                                rows="{{ $meta['rows'] }}"
                                dir="rtl"
                                x-model="text['{{ $field }}'][def]"
                                class="block w-full rounded-lg border-ink-300 bg-white text-sm text-ink-800 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                            ></textarea>
                        @else
                            <input
                                type="text"
                                :name="`{{ $meta['name'] }}[${def}]`"
                                dir="rtl"
                                x-model="text['{{ $field }}'][def]"
                                class="block w-full rounded-lg border-ink-300 bg-white text-sm text-ink-800 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                            >
                        @endif
                    </div>
                @else
                    <div
                        class="grid gap-3"
                        :style="`grid-template-columns: repeat(${shownIn(@js($meta['except'])).length}, minmax(0, 1fr))`"
                    >
                        @foreach ($locales as $code => $lang)
                            @continue(in_array($code, $meta['except'], true))
                            <div x-show="has('{{ $code }}')" x-cloak>
                                <div class="mb-1 flex items-center gap-1.5">
                                    <span class="text-[0.65rem] font-semibold uppercase tracking-wider text-ink-400">
                                        {{ $lang['label'] }}
                                    </span>
                                    <span
                                        x-show="reviewed['{{ $code }}']"
                                        x-cloak
                                        class="rounded bg-warn-50 px-1.5 py-0.5 text-[0.6rem] font-medium text-warn-700"
                                        title="Machine translated — read it before publishing"
                                    >review</span>
                                </div>

                                @if ($meta['rows'])
                                    <textarea
                                        name="{{ $meta['name'] }}[{{ $code }}]"
                                        id="{{ $field }}_{{ $code }}"
                                        rows="{{ $meta['rows'] }}"
                                        @if ($lang['rtl']) dir="rtl" @endif
                                        x-model="text['{{ $field }}']['{{ $code }}']"
                                        class="block w-full rounded-lg border-ink-300 bg-white text-sm text-ink-800 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                    ></textarea>
                                @else
                                    <input
                                        type="text"
                                        name="{{ $meta['name'] }}[{{ $code }}]"
                                        id="{{ $field }}_{{ $code }}"
                                        @if ($lang['rtl']) dir="rtl" @endif
                                        x-model="text['{{ $field }}']['{{ $code }}']"
                                        class="block w-full rounded-lg border-ink-300 bg-white text-sm text-ink-800 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                    >
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                <x-ui.error :name="$field.'.*'" />
                <x-ui.error :name="'content.'.$field.'.*'" />
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
                        <div class="min-w-0 flex-1">

                            {{-- One picker for the row. The same moment in every
                                 language, so it is chosen once and written into
                                 each — rather than typed three times, in two
                                 different numeral systems. --}}
                            <div class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-2">
                                <div class="flex items-center gap-2">
                                    <label
                                        class="text-[0.65rem] font-semibold uppercase tracking-wider text-ink-400"
                                        :for="`step-time-${i}`"
                                    >Time</label>
                                    <input
                                        type="time"
                                        :id="`step-time-${i}`"
                                        step="300"
                                        x-init="$el.value = pickerValue(step)"
                                        x-on:input="setStepTime(i, $event.target.value)"
                                        class="rounded-lg border-ink-300 bg-white text-sm tabular-nums shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                    >
                                </div>

                                {{-- What each language will actually show. Without
                                     this the Arabic-Indic result is invisible until
                                     the invitation is opened. --}}
                                <div class="flex flex-wrap items-center gap-2">
                                    @foreach ($locales as $code => $meta)
                                        <span
                                            x-show="has('{{ $code }}') && step['{{ $code }}'].time"
                                            x-cloak
                                            class="inline-flex items-center gap-1 rounded-md bg-white px-2 py-0.5 text-xs text-ink-600 ring-1 ring-inset ring-ink-200"
                                        >
                                            <span class="text-[0.6rem] uppercase tracking-wider text-ink-400">{{ $code }}</span>
                                            <span @if ($meta['rtl']) dir="rtl" @endif x-text="step['{{ $code }}'].time"></span>
                                        </span>
                                    @endforeach

                                    <span
                                        x-show="! step[def]?.time"
                                        x-cloak
                                        class="text-xs text-ink-400"
                                    >No time set</span>
                                </div>
                            </div>

                            {{-- The value each language submits. Hidden because the
                                 picker owns it — but still posted under the same
                                 names, so nothing about the stored shape changes.
                                 An existing value the picker cannot parse is left
                                 exactly as it was rather than being wiped. --}}
                            @foreach ($locales as $code => $meta)
                                <template x-if="has('{{ $code }}')">
                                    <input
                                        type="hidden"
                                        :name="`programme[{{ $code }}][${i}][time]`"
                                        :value="step['{{ $code }}'].time"
                                    >
                                </template>
                            @endforeach

                            {{-- Every language, no exclusions: a programme step
                                 is described in each one. --}}
                            <div
                                class="grid gap-3"
                                :style="`grid-template-columns: repeat(${locales.length}, minmax(0, 1fr))`"
                            >
                                @foreach ($locales as $code => $meta)
                                    <div x-show="has('{{ $code }}')" x-cloak>
                                        <p class="mb-1 text-[0.65rem] font-semibold uppercase tracking-wider text-ink-400">
                                            {{ $meta['label'] }}
                                        </p>
                                        <input
                                            type="text"
                                            :name="`programme[{{ $code }}][${i}][title]`"
                                            x-model="step['{{ $code }}'].title"
                                            @if ($meta['rtl']) dir="rtl" @endif
                                            class="w-full rounded-lg border-ink-300 bg-white text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                            aria-label="What happens"
                                        >
                                    </div>
                                @endforeach
                            </div>
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
