/**
 * The asset editor.
 *
 * Registered here rather than written inline in x-data, so a literal double
 * quote in this code cannot close the attribute and spill the rest of the
 * script onto the page as visible text. See resources/js/app.js.
 *
 * Two jobs: show only the fields the chosen kind actually uses, and — for text
 * assets — substitute placeholders live so the studio can see what a block
 * will read like before an invitation exists.
 */
export default function assetForm(config) {
    return {
        kind: config.kind,
        body: config.body,
        locale: config.locale,

        // Both come from App\Support\Placeholders, so the browser's
        // substitution and the server's cannot disagree about what
        // {bridename} means.
        aliases: config.aliases,
        samples: config.samples,

        rtl: config.rtl,

        get isMedia() {
            return this.kind === 'video' || this.kind === 'music';
        },

        get isText() {
            return this.kind === 'text';
        },

        /** Direction for the body box — the text is written in one language. */
        get dir() {
            return this.locale && this.rtl[this.locale] ? 'rtl' : 'ltr';
        },

        /** Resolve any accepted spelling to its canonical token, or null. */
        canonical(token) {
            const lower = String(token).toLowerCase();

            for (const [name, spellings] of Object.entries(this.aliases)) {
                if (spellings.includes(lower)) return name;
            }

            return null;
        },

        /**
         * The body with sample values in place of its placeholders.
         *
         * An unrecognised token is left as written rather than emptied: a
         * visible {grom} is a typo someone can see, a silently blank one is not.
         */
        get preview() {
            return String(this.body ?? '').replace(/\{\s*([a-z0-9_]+)\s*\}/gi, (whole, token) => {
                const name = this.canonical(token);

                return name && this.samples[name] ? this.samples[name] : whole;
            });
        },

        /** Tokens nothing will ever fill, so the form can say so while typing. */
        get unknown() {
            const found = String(this.body ?? '').match(/\{\s*[a-z0-9_]+\s*\}/gi) ?? [];

            const bad = found
                .map(whole => whole.replace(/[{}\s]/g, ''))
                .filter(token => this.canonical(token) === null);

            return [...new Set(bad)];
        },

        /** The same list, written out — so the markup holds no expression. */
        get unknownList() {
            return this.unknown.map(token => '{' + token + '}').join(', ');
        },

        /**
         * Drop a placeholder in at the cursor, so a token can be added without
         * remembering its exact spelling or hand-typing the braces.
         */
        insert(token) {
            const field = this.$refs.body;
            const text = '{' + token + '}';

            if (! field) {
                this.body = String(this.body ?? '') + text;

                return;
            }

            const start = field.selectionStart ?? field.value.length;
            const end = field.selectionEnd ?? start;
            const value = String(this.body ?? '');

            this.body = value.slice(0, start) + text + value.slice(end);

            // Put the caret after what was just inserted, on the next tick —
            // Alpine has not written the new value into the field yet.
            this.$nextTick(() => {
                field.focus();
                field.setSelectionRange(start + text.length, start + text.length);
            });
        },
    };
}
