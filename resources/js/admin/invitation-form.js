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

        // Ready-written blocks from the asset library, the placeholder
        // spellings they may use, and the meridiem words each language shows —
        // all so a {date} or {bride} can be filled in out here exactly as the
        // invitation itself would render it.
        assets: config.assets,
        placeholders: config.placeholders,
        meridiem: config.meridiem,

        // Slots whose value is Arabic in every language, so the form gives
        // them one box rather than one per language.
        arabicFields: config.arabicFields ?? [],

        // Slot groups — a verse and its parts — and the library assets that
        // fill one in a single click.
        groups: config.groups ?? {},
        groupAssets: config.groupAssets ?? {},
        groupPicked: config.groupPicked ?? {},
        groupNotes: config.groupNotes ?? {},

        picked: config.picked,
        assetNote: config.notes,

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

        /* ── Premade text ─────────────────────────────────────────────
           Placeholders are filled in at the moment a block is inserted,
           not kept as tokens in the database. Two reasons: what lands in
           the field is ordinary text the studio can then edit, and the
           guest page needs to know nothing about any of this — the
           invitation stores the same shape it always did.

           The trade is that changing a name later does not rewrite text
           already inserted. That is the right way round: the text is
           usually reworded by hand afterwards anyway, and silently
           rewriting a customer's copy would be worse. */

        /**
         * Read a plain form input by name.
         *
         * form.elements rather than a CSS attribute selector — such a selector
         * needs quoting, and quotes are what this whole file exists to avoid
         * having in an attribute.
         */
        formField(name) {
            return this.$root.closest('form')?.elements?.[name]?.value ?? '';
        },

        /** Sorani Kurdish is ckb to Intl, not ku — as it is to ICU. */
        icuLocale(code) {
            return code === 'ku' ? 'ckb' : code;
        },

        /** What each placeholder resolves to, in one language. */
        placeholderValues(code) {
            // Names are Arabic in every language. English is the one place the
            // Latin form reads better, and only when it is filled in.
            const latin = code === 'en';
            const groomLatin = this.formField('groom_latin');
            const brideLatin = this.formField('bride_latin');

            return {
                groom: latin && groomLatin ? groomLatin : this.formField('groom_ar'),
                bride: latin && brideLatin ? brideLatin : this.formField('bride_ar'),
                venue: this.text.venue_name?.[code] || '',
                city: this.text.venue_city?.[code] || '',
                date: this.formattedDate(code),
                time: this.formattedTime(code),
            };
        },

        /** Mirrors Invitation::formattedDate() — same shape, in the browser. */
        formattedDate(code) {
            const raw = this.formField('date');
            if (! raw) return '';

            const [y, m, d] = raw.split('-').map(Number);
            if (! y || ! m || ! d) return '';

            // Midday UTC and an explicit UTC timeZone: building at midnight
            // and formatting in a western zone lands on the previous day and
            // would show the wrong weekday.
            const at = new Date(Date.UTC(y, m - 1, d, 12));

            try {
                // Latin digits from Intl, then localiseDigits — the same split
                // the server makes, so both produce the same numerals.
                const formatted = new Intl.DateTimeFormat(this.icuLocale(code) + '-u-nu-latn', {
                    weekday: 'long',
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric',
                    timeZone: 'UTC',
                }).format(at);

                return this.localiseDigits(formatted, code);
            } catch (e) {
                return raw;
            }
        },

        /** Mirrors Invitation::formattedTime(), meridiem words and all. */
        formattedTime(code) {
            const raw = this.formField('time');
            if (! raw) return '';

            const hour = Number(String(raw).split(':')[0]);
            if (Number.isNaN(hour)) return '';

            const clock = this.localiseDigits(this.to12h(raw), code);
            const word = this.meridiem[hour < 12 ? 'am' : 'pm']?.[code] ?? '';

            return (clock + ' ' + word).trim();
        },

        /** Resolve any accepted spelling of a token to its canonical name. */
        canonicalToken(token) {
            const lower = String(token).toLowerCase();

            for (const [name, spellings] of Object.entries(this.placeholders)) {
                if (spellings.includes(lower)) return name;
            }

            return null;
        },

        /**
         * Fill a block's placeholders for one language.
         *
         * An unknown token, or one whose value is not filled in yet, is left
         * exactly as written — a visible {venue} is something the studio can
         * see and deal with, a silently emptied one is not.
         */
        resolveText(body, code) {
            const values = this.placeholderValues(code);

            return String(body ?? '').replace(/\{\s*([a-z0-9_]+)\s*\}/gi, (whole, token) => {
                const name = this.canonicalToken(token);
                const value = name ? values[name] : null;

                return value ? value : whole;
            });
        },

        /** Blocks worth offering for a field: its own first, then the rest. */
        assetsFor(field) {
            const suits = this.assets.filter(a => a.field === field || ! a.field);
            const others = this.assets.filter(a => a.field && a.field !== field);

            return [...suits, ...others];
        },

        /**
         * Drop the chosen block into a field.
         *
         * It goes into its own language when that language is on this
         * invitation — a Kurdish verse belongs in the Kurdish column — and into
         * the default language otherwise. Which column it landed in is stated
         * rather than left to be noticed.
         */
        insertAsset(field) {
            const id = this.picked[field];
            if (! id) return;

            const asset = this.assets.find(a => String(a.id) === String(id));
            if (! asset) return;

            // An Arabic-everywhere slot has one box, held under the default
            // language — so a block filed as Arabic still lands in it rather
            // than in a column that field does not have.
            const target = this.arabicFields.includes(field)
                ? this.def
                : (asset.locale && this.has(asset.locale) ? asset.locale : this.def);

            this.text[field][target] = this.resolveText(asset.body, target);

            this.assetNote[field] = asset.name + ' → ' + (this.labels[target] ?? target);
            this.picked[field] = '';
        },

        /**
         * Fill a whole slot group from one library asset.
         *
         * A verse arrives with its translation and its reference attached, so
         * one Insert writes all three fields rather than the studio finding
         * three separate blocks and trusting they belong to the same verse.
         *
         * Which part of the asset lands in which slot is declared by the
         * design, not inferred from the names here — see the group's `fills`.
         * Everything stays editable afterwards; this is a starting point.
         */
        insertGroup(groupKey) {
            const id = this.groupPicked[groupKey];
            if (! id) return;

            const group = this.groups[groupKey];
            const asset = (this.groupAssets[groupKey] ?? [])
                .find(a => String(a.id) === String(id));

            if (! group || ! asset) return;

            let filled = 0;

            for (const [slot, part] of Object.entries(group.fills ?? {})) {
                if (! this.text[slot]) continue;

                const value = asset[part];

                if (typeof value === 'string') {
                    // One value for every language — the verse itself.
                    this.text[slot][this.def] = value;
                    if (value) filled++;

                    continue;
                }

                // Keyed by language. Every language is written, blank included,
                // so a leftover from a previous insert cannot survive under a
                // language the new verse says nothing about.
                for (const code of Object.keys(this.labels)) {
                    this.text[slot][code] = value?.[code] ?? '';
                    if (value?.[code]) filled++;
                }
            }

            this.groupNotes[groupKey] = asset.name + ' inserted — '
                + filled + (filled === 1 ? ' field' : ' fields') + ' filled.';
            this.groupPicked[groupKey] = '';
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
