@php
    use App\Models\Asset;

    $palette = old('palette', $template->palette());
    $settings = old('settings', $template->settings());

    // Library media this design can pick from. `$library` comes from the
    // controller, keyed by kind.
    $videoAssets = $library[Asset::KIND_VIDEO];
    $musicAssets = $library[Asset::KIND_MUSIC];

    // Which library asset the stored file is, if it is one. Matched by path
    // because a template holds the media URL rather than a foreign key.
    $currentVideoAsset = filled($template->intro_video)
        ? $videoAssets->firstWhere('path', $template->intro_video)
        : null;

    $currentMusicAsset = filled($template->music)
        ? $musicAssets->firstWhere('path', $template->music)
        : null;

    /* The design this theme is built on, and what it declares.
       Timings and colour roles both come from its manifest rather than from
       lists kept here — a design with no petals should not be offered a petal
       duration, and one with a colour role of its own should be.

       Read from the submitted value first so a failed save redisplays the
       fields of the design that was being chosen, not the one stored, and
       falls back to the first design on the create page where none is chosen
       yet. Switching design shows the new one's fields after saving, since
       only the server knows what a design declares — the same arrangement the
       invitation form uses for the slots that follow its theme. */
    $selectedBase = old('base_template', $template->base_template);

    // Only for deciding which fields to draw — never for the select itself,
    // which keeps its "Select a base template" placeholder so choosing one
    // stays a deliberate act rather than a default nobody noticed.
    $chosenBase = $selectedBase ?: array_key_first($baseTemplates);

    $settingFields = \App\Support\BaseTemplates::editableSettings($chosenBase);

    // Colour roles are rendered in _colours.blade.php, alongside the picker.
    $paletteRoles = \App\Support\BaseTemplates::palette($chosenBase);
@endphp

<x-ui.card title="Theme" description="A custom theme built on one of the base templates.">
    <div class="grid gap-5 sm:grid-cols-2">
        <x-ui.field label="Name" name="name" required>
            <x-ui.input name="name" :value="old('name', $template->name)" required />
        </x-ui.field>

        <x-ui.field
            label="Base template"
            name="base_template"
            required
            hint="The design this theme is built on. Each is a directory under resources/views/base-templates."
        >
            {{-- Grouped by occasion. With one design the optgroup is barely
                 visible; with a dozen across weddings, graduations and baby
                 showers it is the difference between a catalogue and a list. --}}
            <x-ui.select name="base_template" required>
                <option value="">Select a base template</option>
                @foreach (\App\Support\BaseTemplates::grouped() as $category => $designs)
                    <optgroup label="{{ $category }}">
                        @foreach ($designs as $key => $label)
                            <option value="{{ $key }}" @selected($selectedBase === $key)>{{ $label }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </x-ui.select>
        </x-ui.field>

        <x-ui.field label="Slug" name="slug" hint="Left blank, it is generated from the name.">
            <x-ui.input name="slug" :value="old('slug', $template->slug)" />
        </x-ui.field>

        <x-ui.field label="Description" name="description" class="sm:col-span-2">
            <x-ui.textarea name="description" rows="2">{{ old('description', $template->description) }}</x-ui.textarea>
        </x-ui.field>

        <label class="flex items-center gap-2 text-sm text-ink-700 sm:col-span-2">
            <input
                type="checkbox"
                name="is_active"
                value="1"
                @checked(old('is_active', $template->exists ? $template->is_active : true))
                class="rounded border-ink-300 text-brand-600 focus:ring-brand-500"
            >
            Active — available when creating invitations
        </label>
    </div>
</x-ui.card>

<x-ui.card title="Media" description="The intro clip and its music — picked from the library, or uploaded here.">
    <x-slot:actions>
        <x-ui.button :href="route('admin.assets.index')" variant="ghost" size="sm" icon="rectangle-stack">
            Library
        </x-ui.button>
    </x-slot:actions>

    <div class="space-y-5">
        <x-ui.field
            label="Intro video"
            name="intro_video"
            :hint="'MP4, WebM or MOV, '.\App\Support\UploadLimit::describe('templates.max_video_kb').'. Colours are generated from it below.'"
        >
            @if ($template->intro_video)
                {{-- No `controls`: the native player chrome — scrub bar, volume,
                     fullscreen, download menu — is noise on a thumbnail. Click
                     the frame to play or pause it. --}}
                <div
                    class="mb-2 w-fit"
                    x-data="{ playing: false }"
                >
                    <button
                        type="button"
                        class="relative block overflow-hidden rounded-lg border border-ink-200 bg-ink-900"
                        x-on:click="playing = ! playing; playing ? $refs.preview.play() : $refs.preview.pause()"
                        :aria-label="playing ? 'Pause preview' : 'Play preview'"
                    >
                        <video
                            x-ref="preview"
                            src="{{ $template->intro_video }}"
                            class="block h-36 w-auto"
                            muted
                            loop
                            playsinline
                            preload="metadata"
                            x-on:ended="playing = false"
                        ></video>

                        <span
                            x-show="! playing"
                            class="pointer-events-none absolute inset-0 grid place-items-center bg-ink-900/25"
                        >
                            <span class="grid h-9 w-9 place-items-center rounded-full bg-white/85">
                                <x-heroicon-s-play class="h-4 w-4 translate-x-px text-ink-800" />
                            </span>
                        </span>
                    </button>

                    <p class="mt-1 text-xs text-ink-400">
                        Silent preview — click to play.
                        @if ($currentVideoAsset)
                            From the library: <span class="font-medium text-ink-600">{{ $currentVideoAsset->name }}</span>.
                        @endif
                    </p>
                </div>

                <label class="mb-3 flex items-center gap-2 text-xs text-ink-600">
                    <input type="checkbox" name="remove_video" value="1" class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                    Remove current video
                </label>
            @endif

            {{-- Library pick. Blank on every render rather than preselected: it
                 is an action ("use this one"), not a stored setting. That is
                 what keeps a later save from re-deriving the colours, and what
                 makes the current asset something to state in prose above
                 rather than a selection that looks re-submittable. --}}
            @if ($videoAssets->isNotEmpty())
                <div class="mb-3">
                    <x-ui.select name="intro_video_asset">
                        <option value="">{{ $template->intro_video ? 'Keep the current video' : 'Choose from the library…' }}</option>
                        @foreach ($videoAssets as $asset)
                            <option value="{{ $asset->id }}">
                                {{-- Naming the design matters on create, where the
                                     list spans all of them because none has been
                                     chosen yet. --}}
                                {{ $asset->name }} — {{ $asset->base_template ? $asset->baseTemplateLabel() : 'shared' }}
                            </option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.error name="intro_video_asset" />
                </div>
            @endif

            <input
                type="file"
                name="intro_video"
                id="intro_video"
                accept="video/mp4,video/webm,video/quicktime"
                class="block w-full text-sm text-ink-600 file:mr-3 file:rounded-md file:border-0 file:bg-ink-100 file:px-3 file:py-2 file:text-xs file:font-medium file:text-ink-700 hover:file:bg-ink-200"
            >

            <p class="mt-1.5 text-xs text-ink-400">
                @if ($videoAssets->isEmpty())
                    Nothing in the library for this design yet —
                    <a href="{{ route('admin.assets.create', ['kind' => 'video']) }}" class="underline">add a clip</a>
                    to reuse it across templates.
                @else
                    An upload here wins over the library choice above.
                @endif
            </p>
        </x-ui.field>

        <x-ui.field
            label="Music"
            name="music"
            :hint="'MP3, WAV, OGG, AAC, M4A or FLAC, '.\App\Support\UploadLimit::describe('templates.max_music_kb').'. Prefer MP3 — guests download this on their phones. Plays only after the guest taps.'"
        >
            @if ($template->music)
                <audio src="{{ $template->music }}" class="mb-2 w-full max-w-md" controls preload="none"></audio>

                @if ($currentMusicAsset)
                    <p class="mb-2 text-xs text-ink-400">
                        From the library: <span class="font-medium text-ink-600">{{ $currentMusicAsset->name }}</span>.
                    </p>
                @endif

                <label class="mb-3 flex items-center gap-2 text-xs text-ink-600">
                    <input type="checkbox" name="remove_music" value="1" class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                    Remove current track
                </label>
            @endif

            @if ($musicAssets->isNotEmpty())
                <div class="mb-3">
                    <x-ui.select name="music_asset">
                        <option value="">{{ $template->music ? 'Keep the current track' : 'Choose from the library…' }}</option>
                        @foreach ($musicAssets as $asset)
                            <option value="{{ $asset->id }}">
                                {{-- Naming the design matters on create, where the
                                     list spans all of them because none has been
                                     chosen yet. --}}
                                {{ $asset->name }} — {{ $asset->base_template ? $asset->baseTemplateLabel() : 'shared' }}
                            </option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.error name="music_asset" />
                </div>
            @endif

            <input
                type="file"
                name="music"
                id="music"
                accept="audio/mpeg,audio/mp3,audio/wav,audio/ogg,audio/aac,audio/mp4,audio/x-m4a,audio/flac"
                class="block w-full text-sm text-ink-600 file:mr-3 file:rounded-md file:border-0 file:bg-ink-100 file:px-3 file:py-2 file:text-xs file:font-medium file:text-ink-700 hover:file:bg-ink-200"
            >

            <p class="mt-1.5 text-xs text-ink-400">
                @if ($musicAssets->isEmpty())
                    Nothing in the library for this design yet —
                    <a href="{{ route('admin.assets.create', ['kind' => 'music']) }}" class="underline">add a track</a>
                    to reuse it across templates.
                @else
                    An upload here wins over the library choice above.
                @endif
            </p>
        </x-ui.field>

        <label class="flex items-center gap-2 text-sm text-ink-700">
            <input
                type="checkbox"
                name="boomerang_intro"
                value="1"
                @checked(old('boomerang_intro', $template->boomerang_intro))
                class="rounded border-ink-300 text-brand-600 focus:ring-brand-500"
            >
            Boomerang intro — plays forward then reverses, seamlessly looped
        </label>
    </div>
</x-ui.card>

@include('admin.templates._colours')

{{-- Collapsed by default: the defaults are usually right, and eight number
     fields between the media and the save button is a lot to scroll past on
     every edit. --}}
@if ($settingFields)
    <x-ui.card
        title="Reveal timings"
        description="How the intro hands over to the invitation. The defaults suit most templates."
        collapsible
        :open="$errors->hasAny(array_map(fn ($k) => 'settings.'.$k, array_keys($settingFields)))"
    >
        <div class="grid gap-5 sm:grid-cols-2">
            @foreach ($settingFields as $key => $field)
                <x-ui.field
                    :label="$field['label']"
                    :name="'settings.'.$key"
                    :for="'settings_'.$key"
                    :hint="$field['hint'] ?? null"
                >
                    @if (isset($field['options']))
                        {{-- A named choice rather than a number: the design
                             offers a fixed set and has a rule for each. --}}
                        <x-ui.select :name="'settings['.$key.']'" :id="'settings_'.$key">
                            @foreach ($field['options'] as $option)
                                <option
                                    value="{{ $option }}"
                                    @selected(($settings[$key] ?? $field['default']) === $option)
                                >{{ $field['labels'][$option] ?? Str::headline($option) }}</option>
                            @endforeach
                        </x-ui.select>
                    @else
                        <x-ui.input
                            type="number"
                            :name="'settings['.$key.']'"
                            :id="'settings_'.$key"
                            :value="$settings[$key] ?? $field['default']"
                            :step="$field['step'] ?? 1"
                            :min="$field['min'] ?? null"
                            :max="$field['max'] ?? null"
                        />
                    @endif
                </x-ui.field>
            @endforeach
        </div>
    </x-ui.card>
@endif
