/**
 * Colour palette picker for the template editor.
 *
 * Registered here instead of written inline in x-data, so a literal double
 * quote in this code cannot close the attribute and spill the rest onto the
 * page as visible text.
 */
export default function palettePicker(config) {
    return {
        palette: config.palette,
        variants: [],
        chosen: null,
        loading: false,
        note: '',
        ok: false,

        async generate() {
            if (this.loading) return;

            this.loading = true;
            this.note = '';
            this.variants = [];

            const body = new FormData();
            body.append('_token', csrfToken());

            // A file chosen but not yet saved is sent as-is, so this works on
            // the create page too. Otherwise the stored video is used.
            const picked = document.getElementById('intro_video')?.files?.[0];

            if (picked) body.append('intro_video', picked);
            else if (config.templateId) body.append('template_id', config.templateId);

            try {
                const response = await fetch(config.palettesUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body,
                });

                const data = await response.json();

                if (! response.ok) {
                    this.ok = false;
                    this.note = data.message ?? 'Could not read that video.';
                    return;
                }

                this.variants = data.variants;
                this.ok = true;
                this.note = `${data.variants.length} palettes from this video — pick one.`;
            } catch (e) {
                this.ok = false;
                this.note = 'The request failed. Nothing was changed.';
            } finally {
                this.loading = false;
            }
        },

        apply(variant) {
            this.palette = { ...variant.palette };
            this.chosen = variant.key;
        },
    };
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}
