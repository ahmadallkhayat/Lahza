@php
    use App\Models\Asset;
    use App\Support\Placeholders;

    $locales = config('templates.locales');

    // On edit the kind is fixed: a video row holds a file and no body, and
    // letting it become text would orphan the upload and leave any template
    // that picked it pointing at nothing. The request enforces this too.
    $kind = old('kind', $asset->kind ?: Asset::KIND_TEXT);
@endphp

{{-- x-data goes on a plain wrapper, not on the card component: Blade compiles
     component attributes into a PHP array, so quotes inside the expression
     break out of the generated string. --}}
<div
    class="space-y-6"
    x-data="assetForm(@js([
        'kind' => $kind,
        'body' => (string) old('body', $asset->body),
        'locale' => (string) old('locale', $asset->locale),
        'aliases' => Placeholders::aliases(),
        'samples' => Placeholders::samples(),
        'rtl' => collect($locales)->map(fn ($m) => (bool) $m['rtl'])->all(),
    ]))"
>

<x-ui.card title="Asset" description="What this is, and which design's library it belongs to.">
    <div class="grid gap-5 sm:grid-cols-2">
        <x-ui.field label="Kind" name="kind" required>
            @if ($asset->exists)
                {{-- Shown, not editable. Posted as a hidden field so the form
                     round-trips, though the request ignores it on update. --}}
                <div class="flex h-[38px] items-center gap-2">
                    <x-ui.badge tone="neutral">{{ $asset->kindLabel() }}</x-ui.badge>
                    <span class="text-xs text-ink-500">Fixed once created.</span>
                </div>
                <input type="hidden" name="kind" value="{{ $asset->kind }}">
            @else
                <x-ui.select name="kind" x-model="kind" required>
                    @foreach (Asset::KINDS as $value => $label)
                        <option value="{{ $value }}" @selected($kind === $value)>{{ $label }}</option>
                    @endforeach
                </x-ui.select>
            @endif
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

        <x-ui.field label="Name" name="name" required hint="What the dropdowns will show.">
            <x-ui.input name="name" :value="old('name', $asset->name)" required placeholder="Ring box opening" />
        </x-ui.field>

        <x-ui.field label="Note" name="description" hint="Optional. Only ever seen here.">
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

{{-- ── Text ─────────────────────────────────────────────────────────
     Placeholders are the point of this half: a hosting line is the same
     sentence every time bar the names, so it is written once with {bride}
     and {groom} and filled in per invitation. --}}
<div x-show="isText" x-cloak class="space-y-6">
    <x-ui.card title="Text" description="Written once, inserted into any invitation with the names filled in.">
        <div class="space-y-5">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field
                    label="Language"
                    name="locale"
                    hint="Sets the writing direction. Leave unset for something language-neutral."
                >
                    <x-ui.select name="locale" x-model="locale">
                        <option value="">Not language specific</option>
                        @foreach ($locales as $code => $meta)
                            <option value="{{ $code }}" @selected(old('locale', $asset->locale) === $code)>
                                {{ $meta['label'] }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field
                    label="Suits"
                    name="field"
                    hint="Which field this is written for. Only a hint — any block can be inserted anywhere."
                >
                    <x-ui.select name="field">
                        <option value="">Anything</option>
                        @foreach (Asset::TEXT_FIELDS as $value => $label)
                            <option value="{{ $value }}" @selected(old('field', $asset->field) === $value)>{{ $label }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
            </div>

            <div>
                <x-ui.label for="body" required>The text</x-ui.label>

                {{-- Placeholder buttons rather than a list to copy from: the
                     exact spelling stops mattering, and the token lands at the
                     cursor instead of at the end. --}}
                <div class="mb-2 flex flex-wrap items-center gap-1.5">
                    <span class="text-xs text-ink-500">Insert:</span>
                    @foreach (Placeholders::all() as $token)
                        <button
                            type="button"
                            x-on:click="insert('{{ $token['token'] }}')"
                            class="rounded-md bg-ink-100 px-2 py-0.5 font-mono text-xs text-ink-700 transition-colors hover:bg-brand-50 hover:text-brand-700"
                            title="{{ $token['label'] }}"
                        >{{ '{'.$token['token'].'}' }}</button>
                    @endforeach
                </div>

                <textarea
                    name="body"
                    id="body"
                    rows="5"
                    x-ref="body"
                    x-model="body"
                    :dir="dir"
                    class="block w-full rounded-lg border-ink-300 bg-white text-sm text-ink-800 shadow-sm placeholder:text-ink-400 focus:border-brand-500 focus:ring-brand-500 @error('body') border-bad-400 @enderror"
                    placeholder="{{ 'تتشرف عائلة {groom} وعائلة {bride} بدعوتكم' }}"
                ></textarea>

                <p class="mt-1.5 text-xs text-ink-500">
                    Any of the tokens above. Spelling is forgiving — {bride}, {bridename} and {bride_name} all work.
                </p>

                <x-ui.error name="body" />

                {{-- A token nothing can fill is worth saying now rather than
                     after it reaches a customer's invitation. Not an error:
                     braces may well be intentional prose. --}}
                <template x-if="unknown.length > 0">
                    <div class="mt-2 flex items-start gap-2 rounded-lg border border-warn-600/20 bg-warn-50 px-3 py-2 text-xs text-warn-700">
                        <x-heroicon-o-exclamation-triangle class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                        <span>
                            Nothing fills
                            <span class="font-mono" x-text="unknownList"></span>
                            — it will appear exactly as written.
                        </span>
                    </div>
                </template>
            </div>
        </div>
    </x-ui.card>

    {{-- Sample values, not a real invitation's: this block is not attached to
         one yet, and the point is the shape of the sentence. --}}
    <x-ui.card title="Preview" description="With sample names in place of the placeholders.">
        <p
            class="whitespace-pre-line text-sm leading-relaxed text-ink-800"
            :dir="dir"
            x-text="preview || 'Nothing written yet.'"
        ></p>
    </x-ui.card>
</div>

</div>
