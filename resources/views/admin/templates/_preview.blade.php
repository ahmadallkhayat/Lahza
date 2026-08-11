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
            <div class="mb-3 w-full">
                <select
                    x-model="deviceId"
                    x-on:change="chooseDevice()"
                    class="w-full rounded-lg border-ink-300 py-1.5 text-xs text-ink-700 focus:border-brand-500 focus:ring-brand-500"
                    aria-label="Preview device"
                >
                    <template x-for="d in devices" :key="d.id">
                        <option
                            :value="d.id"
                            x-text="`${d.label} — ${d.w}×${d.h}`"
                        ></option>
                    </template>
                </select>
            </div>

            {{-- The stage is what the shell is measured against, so the phone
                 tracks the rail's width rather than a number guessed here. --}}
            <div x-ref="stage" class="flex w-full justify-center">
                {{-- Phone-shaped, because that is what a guest opens it on.

                     The frame is the chosen device's real viewport and is
                     scaled down to fit; the shell is sized from it. Doing it
                     the other way round — fitting the frame to the rail — lays
                     the invitation out at a size no handset has, and the
                     design reads window.innerHeight to place the names and to
                     decide on the scroll cue, so both come out wrong. --}}
                <div
                    class="relative shrink-0 bg-ink-950 shadow-lifted ring-1 ring-ink-950/40"
                    :style="`
                        width: ${viewport.w * scale + 12}px;
                        height: ${viewport.h * scale + 12}px;
                        padding: 6px;
                        border-radius: ${device.radius * scale + 6}px;
                    `"
                >
                    <div
                        class="relative overflow-hidden bg-black"
                        :style="`
                            width: ${viewport.w * scale}px;
                            height: ${viewport.h * scale}px;
                            border-radius: ${device.radius * scale}px;
                        `"
                    >
                        {{-- allow="autoplay" is what lets the music play at
                             all: a frame is not granted the autoplay
                             permission by default, so play() was rejected even
                             though the tap inside it is a real user gesture.

                             Sized to the device and scaled from the top left,
                             so what renders inside is a true viewport. --}}
                        <iframe
                            x-ref="frame"
                            title="Invitation preview"
                            class="block border-0 bg-white"
                            :style="`
                                width: ${viewport.w}px;
                                height: ${viewport.h}px;
                                transform: scale(${scale});
                                transform-origin: top left;
                            `"
                            sandbox="allow-scripts allow-same-origin"
                            allow="autoplay; encrypted-media"
                        ></iframe>

                        {{-- Chrome, not layout. The design sets
                             viewport-fit=cover and takes no env() insets, so
                             on a real handset its content does run under both
                             of these — drawing them is what the guest sees. --}}
                        <div
                            x-show="device.notch && ! landscape"
                            x-cloak
                            aria-hidden="true"
                            class="pointer-events-none absolute left-1/2 top-0 -translate-x-1/2 rounded-b-2xl bg-ink-950"
                            :style="`width: ${120 * scale}px; height: ${30 * scale}px;`"
                        ></div>

                        <div
                            x-show="device.indicator"
                            x-cloak
                            aria-hidden="true"
                            class="pointer-events-none absolute left-1/2 -translate-x-1/2 rounded-full bg-white/70"
                            :style="`
                                bottom: ${8 * scale}px;
                                width: ${130 * scale}px;
                                height: ${4 * scale}px;
                            `"
                        ></div>

                        <div
                            x-show="loading"
                            x-cloak
                            class="absolute inset-0 grid place-items-center bg-ink-900/70 text-xs text-white"
                        >
                            Rendering…
                        </div>
                    </div>
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

                <x-ui.button
                    type="button"
                    variant="secondary"
                    size="sm"
                    icon="arrow-path"
                    class="shrink-0"
                    x-bind:disabled="loading"
                    x-on:click="render()"
                >
                    Replay
                </x-ui.button>

                {{-- Guests do turn their phones, and the reveal is laid out
                     off the viewport's height — so landscape is a different
                     render, not the same one rotated. --}}
                <x-ui.button
                    type="button"
                    variant="secondary"
                    size="sm"
                    class="{{ $multilingual ? 'shrink-0' : 'flex-1 justify-center' }}"
                    x-bind:disabled="loading"
                    x-on:click="rotate()"
                    x-bind:aria-pressed="landscape ? 'true' : 'false'"
                >
                    <span x-text="landscape ? 'Portrait' : 'Landscape'"></span>
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
