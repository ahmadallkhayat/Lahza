@php
    $palette = old('palette', $template->palette());
    $settings = old('settings', $template->settings());

    $paletteLabels = [
        'paper' => 'Page background',
        'paper_deep' => 'Panel fill',
        'ink' => 'Primary text',
        'ink_soft' => 'Secondary text',
        'gold' => 'Accent',
        'gold_deep' => 'Accent (text safe)',
        'curtain' => 'Intro backdrop',
    ];

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

<x-ui.card title="Design" description="What this template is and which code renders it.">
    <div class="grid gap-5 sm:grid-cols-2">
        <x-ui.field label="Name" name="name" required>
            <x-ui.input name="name" :value="old('name', $template->name)" required />
        </x-ui.field>

        <x-ui.field label="Category" name="category" required>
            <x-ui.select name="category" required>
                @foreach (config('templates.categories') as $key => $label)
                    <option value="{{ $key }}" @selected(old('category', $template->category) === $key)>{{ $label }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>

        <x-ui.field
            label="Design view"
            name="view"
            required
            hint="Blade directory under resources/views/templates."
        >
            <x-ui.select name="view" required>
                <option value="">Select a design</option>
                @foreach ($views as $key => $label)
                    <option value="{{ $key }}" @selected(old('view', $template->view) === $key)>{{ $label }} ({{ $key }})</option>
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
            :hint="'MP4, WebM or MOV, up to '.round(config('templates.max_video_kb') / 1000).'MB.'"
        >
            @if ($template->intro_video)
                <video src="{{ $template->intro_video }}" class="mb-2 h-36 rounded-lg border border-ink-200" controls muted></video>
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

<x-ui.card title="Colours" description="Emitted as CSS custom properties the design reads.">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($paletteLabels as $key => $label)
            <x-ui.field :label="$label" :name="'palette.'.$key" :for="'palette_'.$key">
                <div class="flex items-center gap-2">
                    <input
                        type="color"
                        id="palette_{{ $key }}"
                        name="palette[{{ $key }}]"
                        value="{{ $palette[$key] ?? \App\Models\Template::DEFAULT_PALETTE[$key] }}"
                        class="h-9 w-12 shrink-0 cursor-pointer rounded border border-ink-300 bg-white p-0.5"
                        oninput="this.nextElementSibling.value = this.value"
                    >
                    <input
                        type="text"
                        value="{{ $palette[$key] ?? \App\Models\Template::DEFAULT_PALETTE[$key] }}"
                        class="block w-full rounded-lg border-ink-300 font-mono text-xs text-ink-700 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                        oninput="this.previousElementSibling.value = this.value"
                        aria-label="{{ $label }} hex value"
                    >
                </div>
            </x-ui.field>
        @endforeach
    </div>
</x-ui.card>

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
