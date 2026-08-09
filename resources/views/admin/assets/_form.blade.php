@php
    use App\Models\Asset;
    use App\Support\Placeholders;

    $locales = config('templates.locales');

    // On edit the kind is fixed: a video row holds a file and no body, and
    // letting it become text would orphan the upload and leave any template
    // that picked it pointing at nothing. The request enforces this too.
    //
    // On create it starts empty. Choosing what this is comes first, and the
    // rest of the form is written for that answer.
    $kind = old('kind', $asset->kind ?: '');
@endphp

{{-- x-data goes on a plain wrapper, not on the card component: Blade compiles
     component attributes into a PHP array, so quotes inside the expression
     break out of the generated string. --}}
<div
    class="space-y-6"
    x-data="assetForm(@js([
        'kind' => $kind,

        // A text block is written in every language at once, so this is a map
        // rather than a string. old() wins on a failed submit; otherwise the
        // stored map, which Asset::bodies() has already decoded.
        'bodies' => collect($locales)
            ->map(fn ($m, $code) => (string) old("body.$code", $asset->bodies()[$code] ?? ''))
            ->all(),

        // The verse keeps its single box: it is Arabic whoever is reading.
        'verseBody' => (string) old('body', $asset->isVerse() ? $asset->body : ''),

        'locales' => array_keys($locales),
        'aliases' => Placeholders::aliases(),
        'samples' => Placeholders::samples(),
        'rtl' => collect($locales)->map(fn ($m) => (bool) $m['rtl'])->all(),
    ]))"
>

{{-- ── Type ─────────────────────────────────────────────────────────
     The first question, and on create the only one until it is answered:
     everything below is written for one kind or the other. Tiles rather than
     a dropdown because each option needs a sentence to distinguish it, and
     because this is the choice the whole page turns on. --}}
@if (! $asset->exists)
    <x-ui.card title="Type" description="What kind of asset this is. Fixed once created.">
        <fieldset>
            <legend class="sr-only">Asset type</legend>

            <div class="grid gap-3 sm:grid-cols-2">
                @foreach (Asset::KINDS as $value => $label)
                    @php($meta = Asset::KIND_META[$value])
                    <label
                        class="flex cursor-pointer flex-col gap-1.5 rounded-lg border p-4 transition-colors"
                        :class="kind === '{{ $value }}'
                            ? 'border-brand-500 bg-brand-50/50 ring-1 ring-brand-500'
                            : 'border-ink-200 hover:border-ink-300 hover:bg-ink-50'"
                    >
                        {{-- The radio itself carries the value and the keyboard
                             behaviour; only its rendering is replaced. --}}
                        <input type="radio" name="kind" value="{{ $value }}" x-model="kind" class="sr-only">

                        <span class="flex items-center gap-2">
                            <x-dynamic-component
                                :component="'heroicon-o-'.$meta['icon']"
                                class="h-4 w-4 shrink-0"
                                ::class="kind === '{{ $value }}' ? 'text-brand-600' : 'text-ink-400'"
                            />
                            <span class="text-sm font-medium text-ink-900">{{ $label }}</span>
                        </span>

                        <span class="text-xs leading-relaxed text-ink-500">{{ $meta['blurb'] }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <x-ui.error name="kind" />
    </x-ui.card>
@endif

{{-- Everything from here is written for the chosen kind, so on create it waits
     for that choice rather than presenting fields that may not apply. --}}
<div x-show="hasKind" x-cloak class="space-y-6">

<x-ui.card title="Asset" description="What this is, and which design's library it belongs to.">
    <div class="grid gap-5 sm:grid-cols-2">
        @if ($asset->exists)
            <x-ui.field label="Kind" name="kind">
                {{-- Shown, not editable. Posted as a hidden field so the form
                     round-trips, though the request ignores it on update. --}}
                <div class="flex h-[38px] items-center gap-2">
                    <x-ui.badge tone="neutral">{{ $asset->kindLabel() }}</x-ui.badge>
                    <span class="text-xs text-ink-500">Fixed once created.</span>
                </div>
                <input type="hidden" name="kind" value="{{ $asset->kind }}">
            </x-ui.field>
        @endif

        <x-ui.field label="Name" name="name" required hint="What the dropdowns will show.">
            <x-ui.input name="name" :value="old('name', $asset->name)" required placeholder="Ring box opening" />
        </x-ui.field>

        <x-ui.field
            label="Base template"
            name="base_template"
            hint="Leave shared for something any design can use, such as a verse."
        >
            <x-ui.select name="base_template">
                <option value="">Shared — all templates</option>
                @foreach ($baseTemplates as $key => $label)
                    <option value="{{ $key }}" @selected(old('base_template', $asset->base_template) === $key)>{{ $label }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>

        <x-ui.field label="Note" name="description" hint="Optional. Only ever seen here." class="sm:col-span-2">
            <x-ui.input name="description" :value="old('description', $asset->description)" />
        </x-ui.field>

        <label class="flex items-center gap-2 text-sm text-ink-700 sm:col-span-2">
            <input
                type="checkbox"
                name="is_active"
                value="1"
                @checked(old('is_active', $asset->exists ? $asset->is_active : true))
                class="rounded border-ink-300 text-brand-600 focus:ring-brand-500"
            >
            Active — offered in the dropdowns
        </label>
    </div>
</x-ui.card>

{{-- ── Media ────────────────────────────────────────────────────────
     One file input for both kinds; `accept` follows the chosen kind so the
     picker filters correctly without two near-identical fields. --}}
<div x-show="isMedia" x-cloak>
    <x-ui.card title="File" description="Stored once here, then picked from a dropdown on any template.">
        @if ($asset->exists && $asset->path)
            <div class="mb-4">
                @if ($asset->kind === Asset::KIND_VIDEO)
                    <video
                        src="{{ $asset->path }}"
                        class="block h-40 w-auto rounded-lg border border-ink-200 bg-ink-900"
                        muted
                        loop
                        playsinline
                        preload="metadata"
                        controls
                    ></video>
                @else
                    <audio src="{{ $asset->path }}" class="w-full max-w-md" controls preload="none"></audio>
                @endif

                <p class="mt-1.5 font-mono text-xs text-ink-400">{{ $asset->path }}</p>
            </div>
        @endif

        <x-ui.field
            label="{{ $asset->exists ? 'Replace file' : 'File' }}"
            name="file"
            :hint="$asset->exists
                ? 'Leave empty to keep the current file. Replacing it moves every template already using this asset onto the new one.'
                : 'MP4, WebM or MOV for video. MP3, WAV, OGG, AAC, M4A or FLAC for music.'"
        >
            <input
                type="file"
                name="file"
                id="file"
                :accept="kind === 'video'
                    ? 'video/mp4,video/webm,video/quicktime'
                    : 'audio/mpeg,audio/mp3,audio/wav,audio/ogg,audio/aac,audio/mp4,audio/x-m4a,audio/flac'"
                class="block w-full text-sm text-ink-600 file:mr-3 file:rounded-md file:border-0 file:bg-ink-100 file:px-3 file:py-2 file:text-xs file:font-medium file:text-ink-700 hover:file:bg-ink-200"
            >
        </x-ui.field>

        <p class="mt-4 text-xs text-ink-500">
            Uploads are
            {{ \App\Support\UploadLimit::describe('templates.max_video_kb') === 'no size limit'
                ? 'unlimited in size'
                : \App\Support\UploadLimit::describe('templates.max_video_kb') }}.
        </p>
    </x-ui.card>
</div>

{{-- ── Verse ────────────────────────────────────────────────────────
     Its own kind, not text filed under a category. A verse, its translation
     and its reference are shown together and are useless apart, so they are
     written together here and inserted into an invitation as one thing.

     The verse itself is Arabic in every language, as it is on the page. The
     translation and the reference are not: an English invitation reads
     "QURAN 30:21" where an Arabic one reads سورة الروم. --}}
<div x-show="isVerse" x-cloak class="space-y-6">
    <x-ui.card title="The verse" description="Arabic, shown as-is in every language.">
        <div class="space-y-5">
            <div>
                <x-ui.label for="verse_body" required>The verse</x-ui.label>
                <textarea
                    name="body"
                    id="verse_body"
                    rows="3"
                    {{-- Both cards hold a body box and only one is on screen.
                         Disabling the other keeps it out of the submission, so
                         the form never posts the field twice. --}}
                    x-bind:disabled="! isVerse"
                    dir="rtl"
                    lang="ar"
                    x-model="verseBody"
                    class="block w-full rounded-lg border-ink-300 bg-white text-base leading-loose text-ink-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 @error('body') border-bad-400 @enderror"
                    placeholder="وَمِنْ آيَاتِهِ أَنْ خَلَقَ لَكُم مِّنْ أَنفُسِكُمْ أَزْوَاجًا"
                ></textarea>
                <p class="mt-1.5 text-xs text-ink-500">
                    Tashkeel is kept exactly as typed. No placeholders here — a verse is quoted, not composed.
                </p>
                <x-ui.error name="body" />
            </div>

            {{-- Directly under the verse, because that is where it is printed.
                 Every language gets one: the surah reads سورة الروم in Arabic
                 and Kurdish but QURAN 30:21 in English. --}}
            <div>
                <x-ui.label>Reference</x-ui.label>
                <p class="mb-2 text-xs text-ink-500">The surah and ayah, as each language prints it.</p>

                <div class="grid gap-3 sm:grid-cols-{{ min(count($locales), 3) }}">
                    @foreach ($locales as $code => $meta)
                        <div>
                            <div class="mb-1 text-[0.65rem] font-semibold uppercase tracking-wider text-ink-400">
                                {{ $meta['label'] }}
                            </div>
                            <input
                                type="text"
                                name="reference[{{ $code }}]"
                                id="reference_{{ $code }}"
                                @if ($meta['rtl']) dir="rtl" @endif
                                value="{{ old("reference.$code", data_get($asset->reference, $code)) }}"
                                placeholder="{{ $meta['rtl'] ? 'سورة الروم — ٢١' : 'QURAN 30:21' }}"
                                class="block w-full rounded-lg border-ink-300 bg-white text-sm text-ink-800 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                            >
                            <x-ui.error :name="'reference.'.$code" />
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </x-ui.card>

    {{-- No Arabic box. The verse is Arabic, so an Arabic translation of it is
         a field that can never hold anything meaningful — which is why the
         language it is written in is named once, here and in the slot that
         receives it, rather than assumed in each form. --}}
    <x-ui.card
        title="Translation"
        description="Shown small beneath the verse. Arabic and Kurdish read the verse as it is, so neither is offered one."
    >
        <div class="space-y-4">
            @foreach ($locales as $code => $meta)
                @continue(in_array($code, Asset::VERSE_UNTRANSLATED, true))

                <div>
                    <x-ui.label :for="'translation_'.$code">{{ $meta['label'] }}</x-ui.label>
                    <textarea
                        name="translation[{{ $code }}]"
                        id="translation_{{ $code }}"
                        rows="2"
                        @if ($meta['rtl']) dir="rtl" @endif
                        class="block w-full rounded-lg border-ink-300 bg-white text-sm text-ink-800 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    >{{ old("translation.$code", data_get($asset->translation, $code)) }}</textarea>
                    <x-ui.error :name="'translation.'.$code" />
                </div>
            @endforeach
        </div>
    </x-ui.card>
</div>

{{-- ── Text ─────────────────────────────────────────────────────────
     Placeholders are the point of this half: a hosting line is the same
     sentence every time bar the names, so it is written once with {bride}
     and {groom} and filled in per invitation. --}}
<div x-show="isText" x-cloak class="space-y-6">
    <x-ui.card title="Text" description="Written once, inserted into any invitation with the names filled in.">
        <div class="space-y-5">
            <x-ui.field
                label="What it is for"
                name="field"
                hint="Sets which section of the library it sits in, and which field offers it first. Any block can still be inserted anywhere."
                class="sm:max-w-sm"
            >
                <x-ui.select name="field">
                    <option value="">Anything</option>
                    @foreach (Asset::textFields() as $value => $label)
                        <option value="{{ $value }}" @selected(old('field', $asset->field) === $value)>{{ $label }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>

            {{-- One box per language, all on the page at once.
                 A hosting line is the same sentence in three languages, and
                 this used to be one box plus a language dropdown — so saying
                 that took three library rows, three things to keep in step and
                 three separate Inserts on an invitation. Every language a block
                 is written in now lives on the row that block is.

                 Languages may be left empty. A block written only in Arabic is
                 a perfectly good block; it just has one language to offer. --}}
            <div class="space-y-5">
                <div>
                    <x-ui.label required>The text</x-ui.label>
                    <p class="mt-1 text-xs text-ink-500">
                        Written in each language you want to offer it in. Leave a language empty to skip it —
                        at least one has to be filled.
                    </p>
                    <x-ui.error name="body" />
                </div>

                @foreach ($locales as $code => $meta)
                    <div class="rounded-lg border border-ink-200 p-4">
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <span class="text-xs font-semibold uppercase tracking-wider text-ink-500">
                                {{ $meta['label'] }}
                            </span>

                            {{-- Placeholder buttons rather than a list to copy
                                 from: the exact spelling stops mattering, and
                                 the token lands at the cursor of this
                                 language's box instead of at the end. --}}
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="text-xs text-ink-500">Insert:</span>
                                @foreach (Placeholders::all() as $token)
                                    <button
                                        type="button"
                                        x-on:click="insert('{{ $code }}', '{{ $token['token'] }}')"
                                        class="rounded-md bg-ink-100 px-2 py-0.5 font-mono text-xs text-ink-700 transition-colors hover:bg-brand-50 hover:text-brand-700"
                                        title="{{ $token['label'] }}"
                                    >{{ '{'.$token['token'].'}' }}</button>
                                @endforeach
                            </div>
                        </div>

                        <textarea
                            name="body[{{ $code }}]"
                            id="body_{{ $code }}"
                            rows="4"
                            {{-- The verse card holds a body box too and only one
                                 card is on screen. Disabling the other keeps it
                                 out of the submission, so the form never posts
                                 `body` as both a string and an array. --}}
                            x-bind:disabled="! isText"
                            x-ref="body_{{ $code }}"
                            x-model="bodies['{{ $code }}']"
                            @if ($meta['rtl']) dir="rtl" @endif
                            lang="{{ $code }}"
                            class="block w-full rounded-lg border-ink-300 bg-white text-sm text-ink-800 shadow-sm placeholder:text-ink-400 focus:border-brand-500 focus:ring-brand-500 @error('body.'.$code) border-bad-400 @enderror"
                            placeholder="{{ $meta['rtl']
                                ? 'تتشرف عائلة {groom} وعائلة {bride} بدعوتكم'
                                : 'The families of {groom} and {bride} request the honour of your presence' }}"
                        ></textarea>

                        <x-ui.error :name="'body.'.$code" />

                        {{-- Sample values, not a real invitation's: this block is
                             not attached to one yet, and the point is the shape
                             of the sentence. --}}
                        <template x-if="String(bodies['{{ $code }}'] ?? '').trim() !== ''">
                            <p
                                class="mt-2 whitespace-pre-line border-t border-ink-100 pt-2 text-xs leading-relaxed text-ink-500"
                                @if ($meta['rtl']) dir="rtl" @endif
                                x-text="preview('{{ $code }}')"
                            ></p>
                        </template>

                        {{-- A token nothing can fill is worth saying now rather
                             than after it reaches a customer's invitation. Not
                             an error: braces may well be intentional prose. --}}
                        <template x-if="unknown('{{ $code }}').length > 0">
                            <div class="mt-2 flex items-start gap-2 rounded-lg border border-warn-600/20 bg-warn-50 px-3 py-2 text-xs text-warn-700">
                                <x-heroicon-o-exclamation-triangle class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                <span>
                                    Nothing fills
                                    <span class="font-mono" x-text="unknownList('{{ $code }}')"></span>
                                    — it will appear exactly as written.
                                </span>
                            </div>
                        </template>
                    </div>
                @endforeach

                <p class="text-xs text-ink-500">
                    Spelling is forgiving — {bride}, {bridename} and {bride_name} all work.
                </p>
            </div>
        </div>
    </x-ui.card>
</div>

{{-- Inside the gate, so there is nothing to submit before a type is chosen —
     and inside the Alpine component, which is why the actions live here rather
     than in the create and edit pages that include this. --}}
<div class="flex items-center gap-3">
    <x-ui.button type="submit">{{ $submitLabel }}</x-ui.button>
    <x-ui.button :href="route('admin.assets.index')" variant="ghost">Cancel</x-ui.button>
</div>

</div>

</div>
