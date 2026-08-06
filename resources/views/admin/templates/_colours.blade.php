{{-- Colours, generated from the intro video and chosen from.

     Each option is a complete palette with the same contrast floors enforced,
     so whichever is picked stays legible. They differ by page direction (light
     or dark) and by which accent the clip suggests — including the complement of
     it, which is the opposite side of the wheel rather than an unrelated colour.

     Both inputs per role share one x-model, which is also what keeps the colour
     picker and the hex box in step. --}}

@php
    $roles = [
        'paper' => 'Page background',
        'paper_deep' => 'Panel fill',
        'ink' => 'Primary text',
        'ink_soft' => 'Secondary text',
        'gold' => 'Accent',
        'gold_deep' => 'Accent (text safe)',
        'curtain' => 'Intro backdrop',
    ];
@endphp

{{-- x-data goes on a plain wrapper, not on the card component: Blade compiles
     component attributes into a PHP array, so quotes inside the expression break
     out of the generated string. --}}
<div
    x-data="palettePicker(@js([
        'palette' => $palette,
        'palettesUrl' => route('admin.templates.palettes'),
        // Null on the create page: there is no saved template to read a
        // stored video from, so only a freshly picked file can be sent.
        'templateId' => $template->exists ? $template->id : null,
    ]))"
>
<x-ui.card
    title="Colours"
    description="Generate options from the intro video, or set them by hand."
>
    <x-slot:actions>
        <div class="flex items-center gap-2">
            <span x-show="loading" x-cloak class="text-xs text-ink-500">Reading the video…</span>

            <x-ui.button
                type="button"
                variant="secondary"
                size="sm"
                icon="sparkles"
                x-bind:disabled="loading"
                x-on:click="generate()"
            >
                Generate from video
            </x-ui.button>
        </div>
    </x-slot:actions>

    <template x-if="note">
        <div
            class="mb-5 flex items-start gap-3 rounded-lg border px-4 py-3 text-sm"
            :class="ok ? 'bg-brand-50 border-brand-200 text-brand-800'
                       : 'bg-warn-50 border-warn-600/20 text-warn-700'"
        >
            <x-heroicon-o-information-circle class="mt-0.5 h-4 w-4 shrink-0" />
            <span x-text="note"></span>
        </div>
    </template>

    {{-- Options. The preview is a miniature of the invitation itself — a page
         in `paper`, names in `ink`, the date in `gold_deep`, a rule in `gold` —
         which says far more than seven bare squares. --}}
    <div x-show="variants.length" x-cloak class="mb-6">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <template x-for="variant in variants" :key="variant.key">
                <button
                    type="button"
                    x-on:click="apply(variant)"
                    class="rounded-xl border-2 p-2 text-left transition-colors"
                    :class="chosen === variant.key
                        ? 'border-brand-500'
                        : 'border-ink-200 hover:border-ink-300'"
                >
                    <div
                        class="rounded-lg px-3 py-4 text-center"
                        :style="`background-color: ${variant.palette.paper}`"
                    >
                        <p class="text-[0.6rem] tracking-widest" :style="`color: ${variant.palette.ink_soft}`">
                            LAHZA
                        </p>
                        <p class="mt-1 text-sm font-semibold" :style="`color: ${variant.palette.ink}`">
                            يوسف &amp; ليلى
                        </p>
                        <p class="text-[0.65rem]" :style="`color: ${variant.palette.gold_deep}`">
                            4 December 2026
                        </p>
                        <div class="mx-auto mt-2 h-0.5 w-8 rounded" :style="`background-color: ${variant.palette.gold}`"></div>
                        <div
                            class="mx-auto mt-2 h-4 w-10 rounded"
                            :style="`background-color: ${variant.palette.curtain}`"
                            title="Intro backdrop"
                        ></div>
                    </div>

                    <p class="mt-2 px-1 text-xs font-medium text-ink-700" x-text="variant.label"></p>

                    <div class="mt-1.5 flex gap-1 px-1">
                        <template x-for="hex in Object.values(variant.palette)" :key="hex">
                            <span
                                class="h-3 w-3 rounded-sm border border-black/10"
                                :style="`background-color: ${hex}`"
                            ></span>
                        </template>
                    </div>
                </button>
            </template>
        </div>

        <p class="mt-3 text-xs text-ink-500">
            Every option keeps text legible against its own background. Adjust anything below after choosing.
        </p>
    </div>

    {{-- The values that actually save --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($roles as $key => $label)
            <x-ui.field :label="$label" :name="'palette.'.$key" :for="'palette_'.$key">
                <div class="flex items-center gap-2">
                    <input
                        type="color"
                        id="palette_{{ $key }}"
                        name="palette[{{ $key }}]"
                        x-model="palette.{{ $key }}"
                        class="h-9 w-12 shrink-0 cursor-pointer rounded border border-ink-300 bg-white p-0.5"
                    >
                    <input
                        type="text"
                        x-model="palette.{{ $key }}"
                        class="block w-full rounded-lg border-ink-300 font-mono text-xs text-ink-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                        aria-label="{{ $label }} hex value"
                    >
                </div>
            </x-ui.field>
        @endforeach
    </div>
</x-ui.card>
</div>
