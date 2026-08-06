/**
 * The invitation editor's Alpine component.
 *
 * Lives here rather than in an x-data="..." attribute on purpose. That
 * attribute is delimited by a double quote, so a single literal double quote
 * anywhere inside it — even in a comment — closes it early and dumps the rest
 * of the script onto the page as visible text, silently. In a .js file a
 * quote is just a quote.
 *
 * Server-side values arrive as one config object, which Blade escapes.
 */
export default function invitationForm(config) {
    return {
        locales: config.locales,
        def: config.def,
        labels: config.labels,
        numerals: config.numerals,
        fields: config.fields,

        text: config.text,
        steps: config.steps,

        reviewed: {},
        translating: false,
        translateNote: '',
        translateOk: false,

        has(code) { return this.locales.includes(code) },

        init() {
            // The checkboxes use x-model, so Alpine keeps `locales` in step
            // with them natively. These two rules are enforced afterwards
            // rather than by intercepting the click — intercepting meant
            // fighting the browser over the checkbox's own state.
            this.$watch('locales', () => {
                // Never leave an invitation with no language at all.
                if (this.locales.length === 0) {
                    this.locales = [this.def || 'ar'];

                    return;
                }

                // The default has to stay one of the chosen languages.
                if (! this.has(this.def)) this.def = this.locales[0];
            });
        },

        addStep() {
            const blank = {};
            Object.keys(this.labels).forEach(c => blank[c] = { time: '', title: '' });
            this.steps.push(blank);
        },

        /* Times differ between languages only in their digits, so they are
           converted rather than translated — no API call, always correct. */
        localiseDigits(value, code) {
            if (this.numerals[code] !== 'arab') return value;
            const arab = '٠١٢٣٤٥٦٧٨٩';
            return String(value).replace(/[0-9]/g, d => arab[d]);
        },

        /* ── Programme times ──────────────────────────────────────────
           Stored per language as a display string, because the digits
           differ (٧:٠٠ vs 7:00). The picker holds one real time for the
           row and writes the right string into every language, so the
           same time is never typed three times in two numeral systems. */

        /* 24h 19:00 from the picker becomes 7:00, the studio's convention.
           No double quotes anywhere in this attribute — see the note above. */
        to12h(value) {
            const [h, m] = String(value).split(':').map(Number);
            if (Number.isNaN(h) || Number.isNaN(m)) return '';

            const hour = h % 12 === 0 ? 12 : h % 12;

            return hour + ':' + String(m).padStart(2, '0');
        },

        /*
         * Best-effort read of a stored string back into HH:MM for the
         * picker. Arabic-Indic digits are folded to Latin first.
         *
         * 12-hour strings carry no meridiem, so 7:00 is genuinely ambiguous.
         * Evening is assumed for anything from 1 to 11, which is when these
         * events run — and the picker shows what it chose, so a wrong guess
         * is visible and one click to fix rather than silent.
         */
        pickerValue(step) {
            const raw = String(step[this.def]?.time ?? step[this.locales[0]]?.time ?? '').trim();
            if (! raw) return '';

            const latin = raw.replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
            const match = latin.match(/^(\d{1,2}):(\d{2})$/);
            if (! match) return '';

            let hour = Number(match[1]);
            const minute = Number(match[2]);
            if (hour > 23 || minute > 59) return '';

            if (hour >= 1 && hour <= 11) hour += 12;

            return String(hour).padStart(2, '0') + ':' + match[2];
        },

        /** Write one picked time into every language on that row. */
        setStepTime(i, value) {
            const step = this.steps[i];
            if (! step) return;

            const display = value ? this.to12h(value) : '';

            Object.keys(this.labels).forEach(code => {
                if (step[code]) step[code].time = this.localiseDigits(display, code);
            });
        },

        async translateFromEnglish() {
            if (this.translating) return;

            const targets = this.locales.filter(c => c !== 'en');
            if (! this.has('en') || targets.length === 0) return;

            // Keys tell the response where each string belongs.
            const texts = {};
            this.fields.forEach(field => {
                const value = (this.text[field]['en'] || '').trim();
                if (value) texts['text:' + field] = value;
            });
            this.steps.forEach((step, i) => {
                const title = (step.en?.title || '').trim();
                if (title) texts['step:' + i + ':title'] = title;
            });

            if (Object.keys(texts).length === 0) {
                this.translateOk = false;
                this.translateNote = 'Fill in the English content first.';
                return;
            }

            this.translating = true;
            this.translateNote = '';

            try {
                const response = await fetch(config.translateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        // Read from the meta tag, which the old inline version
                        // could not do: the selector needs quotes, and quotes
                        // were exactly what broke the attribute.
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ source: 'en', targets, texts }),
                });

                if (! response.ok) throw new Error('Request failed');

                const data = await response.json();
                this.applyTranslations(data);
            } catch (e) {
                this.translateOk = false;
                this.translateNote = 'The translation request failed. Nothing was changed.';
            } finally {
                this.translating = false;
            }
        },

        applyTranslations(data) {
            const filled = [];

            for (const [code, values] of Object.entries(data.translations || {})) {
                for (const [key, value] of Object.entries(values)) {
                    if (key.startsWith('text:')) {
                        this.text[key.slice(5)][code] = value;
                    } else if (key.startsWith('step:')) {
                        const i = Number(key.split(':')[1]);
                        if (this.steps[i]) this.steps[i][code].title = value;
                    }
                }

                // Times are digit conversions, not translations.
                this.steps.forEach(step => {
                    if (step.en?.time) step[code].time = this.localiseDigits(step.en.time, code);
                });

                this.reviewed[code] = true;
                filled.push(this.labels[code]);
            }

            const declined = Object.values(data.declined || {});

            this.translateOk = filled.length > 0;
            this.translateNote = [
                filled.length
                    ? `${filled.join(' and ')} filled — review before publishing.`
                    : 'Nothing was translated.',
                ...declined,
            ].join(' ');
        },
    };
}

/** Read once from the meta tag the layout already renders. */
function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}
