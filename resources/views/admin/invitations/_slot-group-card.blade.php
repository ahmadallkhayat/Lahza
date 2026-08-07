{{-- A group of slots that belong together, on a card of their own.

     The verse is the case this exists for: the verse, its translation and its
     reference are one thing with three parts, shown together on the page and
     useless apart. Editing them as three unrelated rows in the Content card
     said the opposite, and filling them meant finding three library blocks and
     trusting they were the same verse.

     One picker fills the whole group from one asset of the kind the group asks
     for. Every field stays freely editable afterwards — the insert is a
     starting point, not a binding. --}}

@php
    $locales = config('templates.locales');
@endphp

@foreach ($slotGroups as $groupKey => $group)
    <x-ui.card :title="$group['label']" :description="$group['description']">
        {{-- Keyed by group, matching how _form built it. Reading it by asset_kind
             worked only while the two names happened to coincide. --}}
        @if (filled($groupAssets[$groupKey] ?? []))
            <x-slot:actions>
                <div class="flex flex-wrap items-center gap-2">
                    <select
                        x-model="groupPicked.{{ $groupKey }}"
                        aria-label="Premade {{ Str::lower($group['label']) }}"
                        class="max-w-xs rounded-lg border-ink-300 bg-white text-xs text-ink-800 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    >
                        <option value="">From the library…</option>
                        @foreach ($groupAssets[$groupKey] as $asset)
                            <option value="{{ $asset['id'] }}">{{ $asset['name'] }}</option>
                        @endforeach
                    </select>

                    <x-ui.button
                        type="button"
                        variant="secondary"
                        size="sm"
                        x-bind:disabled="! groupPicked.{{ $groupKey }}"
                        x-on:click="insertGroup('{{ $groupKey }}')"
                    >Insert</x-ui.button>
                </div>
            </x-slot:actions>
        @endif

        <div class="space-y-5">
            <p
                x-show="groupNotes.{{ $groupKey }}"
                x-cloak
                class="text-xs text-ok-700"
                x-text="groupNotes.{{ $groupKey }}"
            ></p>

            @foreach ($group['fields'] as $field => $meta)
                <div>
                    <x-ui.label>{{ $meta['label'] }}</x-ui.label>
                    @if ($meta['hint'])
                        <p class="mb-2 text-xs text-ink-500">{{ $meta['hint'] }}</p>
                    @endif

                    @if ($meta['arabic'])
                        {{-- One box: Arabic in every language, like the names.
                             Posts under whichever language is default, bound
                             rather than printed so changing the default here
                             does not strand what was typed. --}}
                        <div class="max-w-xl">
                            <textarea
                                :name="`{{ $meta['name'] }}[${def}]`"
                                rows="{{ $meta['rows'] ?: 3 }}"
                                dir="rtl"
                                lang="ar"
                                x-model="text['{{ $field }}'][def]"
                                class="block w-full rounded-lg border-ink-300 bg-white text-base leading-loose text-ink-800 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                            ></textarea>
                        </div>
                    @else
                        <div
                            class="grid gap-3"
                            :style="`grid-template-columns: repeat(${shownIn(@js($meta['except'])).length}, minmax(0, 1fr))`"
                        >
                            @foreach ($locales as $code => $lang)
                                {{-- A verse has no Arabic translation: the
                                     verse is already Arabic. Declared by the
                                     slot, not assumed here. --}}
                                @continue(in_array($code, $meta['except'], true))

                                <div x-show="has('{{ $code }}')" x-cloak>
                                    <div class="mb-1 text-[0.65rem] font-semibold uppercase tracking-wider text-ink-400">
                                        {{ $lang['label'] }}
                                    </div>

                                    @if ($meta['rows'])
                                        <textarea
                                            name="{{ $meta['name'] }}[{{ $code }}]"
                                            rows="{{ $meta['rows'] }}"
                                            @if ($lang['rtl']) dir="rtl" @endif
                                            x-model="text['{{ $field }}']['{{ $code }}']"
                                            class="block w-full rounded-lg border-ink-300 bg-white text-sm text-ink-800 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                        ></textarea>
                                    @else
                                        <input
                                            type="text"
                                            name="{{ $meta['name'] }}[{{ $code }}]"
                                            @if ($lang['rtl']) dir="rtl" @endif
                                            x-model="text['{{ $field }}']['{{ $code }}']"
                                            class="block w-full rounded-lg border-ink-300 bg-white text-sm text-ink-800 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                        >
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <x-ui.error :name="'content.'.$field.'.*'" />
                </div>
            @endforeach

            <p class="text-xs text-ink-500">
                Leave a field empty to use the design’s own wording.
            </p>
        </div>
    </x-ui.card>
@endforeach
