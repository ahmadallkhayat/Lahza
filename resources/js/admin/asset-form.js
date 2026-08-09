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
 *
 * A text block is written in every language at once, so most of what follows
 * is per-language: one box, one preview and one unknown-token warning each.
 * A verse keeps a single body, because the verse is Arabic whoever is reading.
 */
export default function assetForm(config) {
    return {
        kind: config.kind,

        /** Text assets: { ar: '…', ku: '…', en: '…' }. */
        bodies: config.bodies,

        /** Verse assets: the Arabic verse, one box. */
        verseBody: config.verseBody,

        locales: config.locales,

        // Both come from App\Support\Placeholders, so the browser's
        // substitution and the server's cannot disagree about what
        // {bridename} means.
        aliases: config.aliases,
        samples: config.samples,

        rtl: config.rtl,

        /**
         * Whether the type question has been answered. On create it starts
         * unanswered and the rest of the form waits, because every field below
         * it belongs to one kind or the other. On edit the kind is fixed, so
         * this is true from the first paint.
         */
        get hasKind() {
            return this.kind !== '' && this.kind != null;
        },

        get isMedia() {
            return this.kind === 'video' || this.kind === 'music';
        },

        get isText() {
            return this.kind === 'text';
        },

        /**
         * A religious verse: its own kind, with a translation and a reference
         * beside the verse itself.
         */
        get isVerse() {
            return this.kind === 'verse';
        },

        /** Writing direction for a given language's box. */
        dirFor(locale) {
            return this.rtl[locale] ? 'rtl' : 'ltr';
        },

        /** Languages with something written in them. */
        get written() {
            return this.locales.filter(code => String(this.bodies[code] ?? '').trim() !== '');
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
         * One language's text with sample values in place of its placeholders.
         *
         * An unrecognised token is left as written rather than emptied: a
         * visible {grom} is a typo someone can see, a silently blank one is not.
         */
        preview(locale) {
            return String(this.bodies[locale] ?? '').replace(/\{\s*([a-z0-9_]+)\s*\}/gi, (whole, token) => {
                const name = this.canonical(token);

                return name && this.samples[name] ? this.samples[name] : whole;
            });
        },

        /** Tokens nothing will ever fill, so the form can say so while typing. */
        unknown(locale) {
            const found = String(this.bodies[locale] ?? '').match(/\{\s*[a-z0-9_]+\s*\}/gi) ?? [];

            const bad = found
                .map(whole => whole.replace(/[{}\s]/g, ''))
                .filter(token => this.canonical(token) === null);

            return [...new Set(bad)];
        },

        /** The same list, written out — so the markup holds no expression. */
        unknownList(locale) {
            return this.unknown(locale).map(token => '{' + token + '}').join(', ');
        },

        /**
         * Drop a placeholder in at the cursor of one language's box, so a token
         * can be added without remembering its exact spelling or hand-typing
         * the braces.
         *
         * Each box has its own ref, because there is a row of them now and the
         * caret belongs to whichever one the studio is typing in.
         */
        insert(locale, token) {
            const field = this.$refs['body_' + locale];
            const text = '{' + token + '}';
            const value = String(this.bodies[locale] ?? '');

            if (! field) {
                this.bodies[locale] = value + text;

                return;
            }

            const start = field.selectionStart ?? value.length;
            const end = field.selectionEnd ?? start;

            this.bodies[locale] = value.slice(0, start) + text + value.slice(end);

            // Put the caret after what was just inserted, on the next tick —
            // Alpine has not written the new value into the field yet.
            this.$nextTick(() => {
                field.focus();
                field.setSelectionRange(start + text.length, start + text.length);
            });
        },
    };
}
