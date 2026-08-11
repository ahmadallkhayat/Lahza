/**
 * The template editor's live preview.
 *
 * Kept out of an x-data="..." attribute for the same reason as the invitation
 * form: one literal double quote inside such an attribute closes it early and
 * spills the remaining script onto the page as text.
 *
 * The frame renders the real base template through a preview route, so it
 * cannot drift from what a guest sees — same markup, same reveal sequence,
 * same JavaScript.
 */

/**
 * Real phone viewports, in CSS pixels — the numbers a browser reports for
 * innerWidth/innerHeight on each device, not the hardware pixel counts.
 *
 * The size is not decoration. The invitation reads window.innerHeight twice:
 * once to lift the names to the middle of the screen, and once to decide
 * whether there is enough page to be worth a scroll cue. A frame sized to fit
 * the rail rather than to match a device therefore lays the invitation out at
 * a height no phone has, and both of those come out wrong — which is exactly
 * the drift the preview exists to prevent. So the device is chosen first and
 * the shell is sized from it, never the other way round.
 *
 * `notch` and `indicator` are chrome drawn over the frame. The design sets
 * viewport-fit=cover and takes no env() insets, so on a real handset its
 * content genuinely does run under both — drawing them is the honest
 * representation, not an embellishment.
 */
export const DEVICES = [
    { id: 'iphone-se', label: 'iPhone SE', w: 375, h: 667, radius: 24, notch: false, indicator: false },
    { id: 'iphone-15', label: 'iPhone 15', w: 393, h: 852, radius: 44, notch: true, indicator: true },
    { id: 'iphone-15-max', label: 'iPhone 15 Pro Max', w: 430, h: 932, radius: 48, notch: true, indicator: true },
    { id: 'pixel-8', label: 'Pixel 8', w: 412, h: 915, radius: 32, notch: false, indicator: true },
    { id: 'galaxy-s23', label: 'Galaxy S23', w: 360, h: 780, radius: 30, notch: false, indicator: true },
];

/** Widest the shell is allowed to get, however much rail there is. */
const MAX_SHELL = 300;

export default function templatePreview(config) {
    return {
        devices: DEVICES,
        deviceId: DEVICES[1].id,
        landscape: false,

        stale: false,
        loading: false,
        queued: false,
        error: '',
        locale: config.locale,
        scale: 1,

        // Object URLs for files chosen but not yet uploaded, so the preview
        // shows the new clip rather than the stored one.
        blobs: { intro_video: null, music: null },

        init() {
            this.measure();
            this.render();

            // Tidy each render once the document inside exists.
            this.$refs.frame.addEventListener('load', () => this.dressFrame());

            // The rail is fluid, so the scale has to follow it rather than be
            // computed once at a width the card may never actually have.
            this.observer = new ResizeObserver(() => this.measure());
            this.observer.observe(this.$refs.stage);

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

            // A select fires change, not input, so the opening style and the
            // base template would otherwise never reach the frame.
            form.addEventListener('change', (e) => {
                const name = e.target.name ?? '';

                if (e.target.type === 'file') {
                    const key = e.target.name;
                    if (!(key in this.blobs)) return;

                    if (this.blobs[key]) URL.revokeObjectURL(this.blobs[key]);
                    this.blobs[key] = e.target.files?.[0]
                        ? URL.createObjectURL(e.target.files[0])
                        : null;

                    this.markStale();
                    return;
                }

                if (name.startsWith('settings[') || name === 'base_template') {
                    this.markStale();
                }
            });
        },

        destroy() {
            this.observer?.disconnect();
            clearTimeout(this.timer);

            // Otherwise every clip chosen this session stays held in memory
            // for as long as the tab is open.
            for (const key of Object.keys(this.blobs)) {
                if (this.blobs[key]) URL.revokeObjectURL(this.blobs[key]);
            }
        },

        get device() {
            return this.devices.find((d) => d.id === this.deviceId) ?? this.devices[0];
        },

        /** The viewport as the frame will report it, rotation included. */
        get viewport() {
            const d = this.device;

            return this.landscape ? { w: d.h, h: d.w } : { w: d.w, h: d.h };
        },

        /**
         * Fit the chosen device into whatever rail there is.
         *
         * Only the shell shrinks — the frame keeps the device's real pixel
         * size and is scaled down visually, so the invitation still lays out
         * at a width and height a phone actually has.
         */
        measure() {
            const available = this.$refs.stage?.clientWidth ?? MAX_SHELL;

            this.scale = Math.min(MAX_SHELL, available) / this.viewport.w;
        },

        /**
         * Changing device changes innerHeight, and the names' lift was
         * measured from the old one — so this is a re-render, not a resize.
         */
        chooseDevice() {
            this.measure();
            this.render();
        },

        rotate() {
            this.landscape = !this.landscape;
            this.measure();
            this.render();
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

            body.append('_token', csrfToken());
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
            const stored = config.stored;
            for (const key of ['intro_video', 'music']) {
                const value = this.blobs[key] ?? stored[key];
                if (value) body.append(key, value);
            }

            return body;
        },

        async render() {
            // A render asked for while one is in flight used to be dropped on
            // the floor, and `stale` stayed true for good: the frame silently
            // stopped following the form until something happened to trigger
            // another one. Remember it instead and run it on the way out.
            if (this.loading) {
                this.queued = true;

                return;
            }

            // On the create form nothing is chosen yet, so there is nothing to
            // preview. Say so instead of posting a request that can only fail.
            const form = this.$root.closest('form');
            if (!form?.elements?.base_template?.value) {
                this.$refs.frame.srcdoc = '';
                this.stale = false;
                this.error = 'Choose a base template to preview.';

                return;
            }

            this.loading = true;
            this.error = '';

            try {
                const response = await fetch(config.previewUrl, {
                    method: 'POST',
                    // Without this Laravel answers a validation failure with a
                    // redirect back to the form, which fetch follows — handing
                    // back the admin page at status 200. Asking for JSON keeps
                    // a failure a failure.
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    // Belt and braces: never render whatever a redirect lands on.
                    redirect: 'error',
                    body: this.formValues(),
                });

                if (!response.ok || response.redirected) {
                    this.error = response.status === 422
                        ? (await response.text()) || 'Choose a base template to preview.'
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

                if (this.queued) {
                    this.queued = false;
                    this.render();
                }
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
            if (!doc?.head) return;

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
            if (!doc) return;

            const form = this.$root.closest('form');

            for (const el of Array.from(form?.elements ?? [])) {
                if (!el.name?.startsWith('palette[')) continue;

                // palette[gold_deep] -> --gold-deep
                const role = el.name.slice(8, -1).replaceAll('_', '-');
                doc.documentElement.style.setProperty('--' + role, el.value);
            }
        },
    };
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}
