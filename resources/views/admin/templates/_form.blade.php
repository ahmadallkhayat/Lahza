@php
    $palette = old('palette', $template->palette());
    $settings = old('settings', $template->settings());

    // Colour roles live in _colours.blade.php, alongside the picker.
    $settingLabels = [
        'keepVideoAudioMs' => ['Keep video audio', 'ms before muting the clip. 0 keeps it silent throughout.'],
        'musicFadeMs' => ['Music fade in', 'ms for the music to reach full volume.'],
        'fadeOutLeadMs' => ['Dissolve lead', 'ms before the clip ends that the crossfade begins.'],
        'crossfadeMs' => ['Dissolve length', 'ms for the video to dissolve into the invitation.'],
        'duckTo' => ['Music duck level', '0–1. Volume once the invitation is showing.'],
        'fogHz' => ['Muffle cutoff', 'Hz low-pass after the reveal. Lower sounds more distant.'],
        'duckMs' => ['Settle duration', 'ms for the music to pull back and blur.'],
        'revealFallbackMs' => ['Safety fallback', 'ms after which the invitation shows even if the clip never plays.'],
    ];
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
            <x-ui.select name="base_template" required>
                <option value="">Select a base template</option>
                @foreach ($baseTemplates as $key => $label)
                    <option value="{{ $key }}" @selected(old('base_template', $template->base_template) === $key)>{{ $label }}</option>
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

<x-ui.card title="Media" description="The intro clip and its music.">
    <div class="space-y-5">
        <x-ui.field
            label="Intro video"
            name="intro_video"
            :hint="'MP4, WebM or MOV, up to '.round(config('templates.max_video_kb') / 1000).'MB. Colours are generated from it below.'"
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

                    <p class="mt-1 text-xs text-ink-400">Silent preview — click to play.</p>
                </div>

                <label class="mb-2 flex items-center gap-2 text-xs text-ink-600">
                    <input type="checkbox" name="remove_video" value="1" class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                    Remove current video
                </label>
            @endif
            <input
                type="file"
                name="intro_video"
                id="intro_video"
                accept="video/mp4,video/webm,video/quicktime"
                class="block w-full text-sm text-ink-600 file:mr-3 file:rounded-md file:border-0 file:bg-ink-100 file:px-3 file:py-2 file:text-xs file:font-medium file:text-ink-700 hover:file:bg-ink-200"
            >
        </x-ui.field>

        <x-ui.field
            label="Music"
            name="music"
            :hint="'MP3, WAV, OGG, AAC, M4A or FLAC, up to '.round(config('templates.max_music_kb') / 1024).'MB. Prefer MP3 — guests download this on their phones. Plays only after the guest taps.'"
        >
            @if ($template->music)
                <audio src="{{ $template->music }}" class="mb-2 w-full max-w-md" controls preload="none"></audio>
                <label class="mb-2 flex items-center gap-2 text-xs text-ink-600">
                    <input type="checkbox" name="remove_music" value="1" class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                    Remove current track
                </label>
            @endif
            <input
                type="file"
                name="music"
                id="music"
                accept="audio/mpeg,audio/mp3,audio/wav,audio/ogg,audio/aac,audio/mp4,audio/x-m4a,audio/flac"
                class="block w-full text-sm text-ink-600 file:mr-3 file:rounded-md file:border-0 file:bg-ink-100 file:px-3 file:py-2 file:text-xs file:font-medium file:text-ink-700 hover:file:bg-ink-200"
            >
        </x-ui.field>
    </div>
</x-ui.card>

@include('admin.templates._colours')

<x-ui.card title="Reveal timings" description="How the intro hands over to the invitation.">
    <div class="grid gap-5 sm:grid-cols-2">
        @foreach ($settingLabels as $key => [$label, $hint])
            <x-ui.field :label="$label" :name="'settings.'.$key" :for="'settings_'.$key" :hint="$hint">
                <x-ui.input
                    type="number"
                    :name="'settings['.$key.']'"
                    :id="'settings_'.$key"
                    :value="$settings[$key] ?? \App\Models\Template::DEFAULT_SETTINGS[$key]"
                    step="{{ $key === 'duckTo' ? '0.01' : '1' }}"
                />
            </x-ui.field>
        @endforeach
    </div>
</x-ui.card>
