{{-- Live preview.

     The frame renders the real base template through a preview route, so it
     cannot drift from what a guest sees — same markup, same reveal sequence,
     same JavaScript.

     Colours are patched straight onto the frame's CSS custom properties, which
     is instant and does not interrupt playback. Anything structural — media,
     timings, the base template itself — needs a re-render, so those debounce
     into a reload rather than thrashing the frame on every keystroke. --}}

<div
    class="@4xl:sticky @4xl:top-6"
    x-data="templatePreview(@js([
        'locale' => $invitationLocales[0] ?? 'ar',
        'previewUrl' => route('admin.templates.preview'),
        'stored' => ['intro_video' => $template->intro_video, 'music' => $template->music],
    ]))"
>
    <x-ui.card title="Live preview">
        <div class="flex flex-col items-center">
            {{-- Phone-shaped, because that is what a guest opens it on.

                 The frame renders at 375px — a real phone viewport — then
                 scales down to fit the rail. Narrowing the iframe itself
                 would lay the invitation out at a width no phone has, so
                 the preview would stop being representative.

                 The numbers have to agree or the right edge gets clipped.
                 The shell is border-box, so its 6px border leaves 252×532
                 of content; 252/375 = 0.672, and 532/0.672 = 792. --}}
            <div
                class="relative shrink-0 overflow-hidden rounded-[1.5rem] border-[6px] border-ink-800 bg-ink-900 shadow-lifted"
                style="width: 264px; height: 544px;"
            >
                {{-- allow="autoplay" is what lets the music play at all: a
                     frame is not granted the autoplay permission by
                     default, so play() was rejected even though the tap
                     inside it is a real user gesture. --}}
                <iframe
                    x-ref="frame"
                    title="Invitation preview"
                    class="block border-0 bg-white"
                    style="width: 375px; height: 792px; transform: scale(0.672); transform-origin: top left;"
                    sandbox="allow-scripts allow-same-origin"
                    allow="autoplay; encrypted-media"
                ></iframe>

                <div
                    x-show="loading"
                    x-cloak
                    class="absolute inset-0 grid place-items-center bg-ink-900/70 text-xs text-white"
                >
                    Rendering…
                </div>
            </div>

            {{-- Controls sit under the frame: in a rail this narrow they
                 would crush the card title into one word per line. --}}
            @php($multilingual = count($invitationLocales) > 1)

            <div class="mt-4 flex w-full items-center gap-2">
                @if ($multilingual)
                    <select
                        x-model="locale"
                        x-on:change="render()"
                        class="min-w-0 flex-1 rounded-lg border-ink-300 py-1.5 text-xs text-ink-700 focus:border-brand-500 focus:ring-brand-500"
                        aria-label="Preview language"
                    >
                        @foreach ($invitationLocales as $code)
                            <option value="{{ $code }}">{{ config("templates.locales.{$code}.label") }}</option>
                        @endforeach
                    </select>
                @endif

                {{-- With no language picker beside it the button would sit
                     alone against the left edge, so it takes the row. --}}
                <x-ui.button
                    type="button"
                    variant="secondary"
                    size="sm"
                    icon="arrow-path"
                    class="{{ $multilingual ? 'shrink-0' : 'w-full justify-center' }}"
                    x-bind:disabled="loading"
                    x-on:click="render()"
                >
                    Replay
                </x-ui.button>
            </div>

            {{-- One fixed-height row for both states, so the helper text below
                 does not jump every time a render starts or finishes. --}}
            <div class="mt-2 flex h-4 w-full items-center">
                <p x-show="stale && ! loading" x-cloak class="text-xs text-warn-700">Updating…</p>
                <p x-show="error" x-cloak class="truncate text-xs text-bad-600" x-text="error"></p>
            </div>

            <p class="mt-1 w-full text-xs leading-relaxed text-ink-500">
                Tap to play the intro, as a guest would. Colours update live; media and timings re-render.
            </p>
    </div>
    </x-ui.card>
</div>
