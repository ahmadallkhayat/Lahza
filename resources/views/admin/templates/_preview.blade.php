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
    x-data="{
        stale: false,
        loading: false,
        error: '',
        locale: @js($invitationLocales[0] ?? 'ar'),

        // Object URLs for files chosen but not yet uploaded, so the preview
        // shows the new clip rather than the stored one.
        blobs: { intro_video: null, music: null },

        init() {
            this.render();

            // Tidy each render once the document inside exists.
            this.$refs.frame.addEventListener('load', () => this.dressFrame());

            const form = this.$root.closest('form') ?? document;

            form.addEventListener('input', (e) => {
                const name = e.target.name ?? '';

                // Colour changes are cheap — apply them without re-rendering.
                if (name.startsWith('palette[')) {
                    this.paintColours();
                    return;
                }

                if (name.startsWith('settings[') || name === 'base_template') {
                    this.markStale();
                }
            });

            form.addEventListener('change', (e) => {
                if (e.target.type !== 'file') return;

                const key = e.target.name;
                if (! (key in this.blobs)) return;

                if (this.blobs[key]) URL.revokeObjectURL(this.blobs[key]);
                this.blobs[key] = e.target.files?.[0] ? URL.createObjectURL(e.target.files[0]) : null;

                this.markStale();
            });
        },

        markStale() {
            this.stale = true;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.render(), 700);
        },

        /** Read the form's current values rather than tracking them twice. */
        formValues() {
            const form = this.$root.closest('form');
            const body = new FormData();

            body.append('_token', @js(csrf_token()));
            body.append('locale', this.locale);
            body.append('base_template', form?.elements?.base_template?.value ?? '');

            // form.elements rather than a CSS attribute selector. Such a
            // selector needs quoting, and a double quote anywhere in this
            // expression closes the attribute and dumps the rest of the script
            // onto the page as text. Nothing here may contain one.
            for (const el of Array.from(form?.elements ?? [])) {
                if (el.name?.startsWith('palette[') || el.name?.startsWith('settings[')) {
                    body.append(el.name, el.value);
                }
            }

            // A freshly chosen file wins; otherwise fall back to what is stored.
            const stored = @js(['intro_video' => $template->intro_video, 'music' => $template->music]);
            for (const key of ['intro_video', 'music']) {
                const value = this.blobs[key] ?? stored[key];
                if (value) body.append(key, value);
            }

            return body;
        },

        async render() {
            if (this.loading) return;

            this.loading = true;
            this.error = '';

            try {
                const response = await fetch(@js(route('admin.templates.preview')), {
                    method: 'POST',
                    body: this.formValues(),
                });

                if (! response.ok) {
                    this.error = response.status === 422
                        ? 'Choose a base template to preview.'
                        : 'The preview could not be rendered.';
                    return;
                }

                // srcdoc keeps the frame same-origin, which is what lets the
                // blob: URLs made out here resolve inside it.
                this.$refs.frame.srcdoc = await response.text();
                this.stale = false;
            } catch (e) {
                this.error = 'The preview could not be rendered.';
            } finally {
                this.loading = false;
            }
        },

        /**
         * Preview-only polish, injected from out here rather than baked into
         * the base template — a guest's phone shows no scrollbar over the
         * page, so one in the frame is an artefact of the preview, not of the
         * invitation. The frame still scrolls; the bar is just not drawn.
         */
        dressFrame() {
            const doc = this.$refs.frame?.contentDocument;
            if (! doc?.head) return;

            const style = doc.createElement('style');
            style.textContent =
                'html { scrollbar-width: none; -ms-overflow-style: none; }' +
                'html::-webkit-scrollbar { width: 0; height: 0; display: none; }';
            doc.head.appendChild(style);

            // Colours may have been changed while the render was in flight.
            this.paintColours();
        },

        /** Push the form's colours onto the frame without re-rendering it. */
        paintColours() {
            const doc = this.$refs.frame?.contentDocument;
            if (! doc) return;

            const form = this.$root.closest('form');

            for (const el of Array.from(form?.elements ?? [])) {
                if (! el.name?.startsWith('palette[')) continue;

                // palette[gold_deep] -> --gold-deep
                const role = el.name.slice(8, -1).replaceAll('_', '-');
                doc.documentElement.style.setProperty('--' + role, el.value);
            }
        },
    }"
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
