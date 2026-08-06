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
export default function templatePreview(config) {
    return {
        stale: false,
        loading: false,
        error: '',
        locale: config.locale,

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
            if (this.loading) return;

            // On the create form nothing is chosen yet, so there is nothing to
            // preview. Say so instead of posting a request that can only fail.
            const form = this.$root.closest('form');
            if (! form?.elements?.base_template?.value) {
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

                if (! response.ok || response.redirected) {
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
    };
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}
