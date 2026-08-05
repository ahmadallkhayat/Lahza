@php
    $local = $invitation->exists ? $invitation->startsAtLocal() : null;
    $localeMeta = config('templates.locales');
    $codes = array_keys($localeMeta);
    $translatable = ['hosts', 'venue_name', 'venue_city'];

    $selected = array_values(array_intersect($codes, (array) old('locales', $invitation->localeList()))) ?: ['ar'];

    $default = old('default_locale', $invitation->exists ? $invitation->defaultLocale() : 'ar');
    $default = in_array($default, $selected, true) ? $default : $selected[0];

    // Every language gets a slot whether or not it is ticked, so text typed
    // into one, unticked and reticked, is still there.
    $text = collect($translatable)
        ->mapWithKeys(fn ($field) => [$field => collect($codes)
            ->mapWithKeys(fn ($c) => [$c => (string) old("$field.$c", data_get($invitation->{$field}, $c, ''))])
            ->all(),
        ])
        ->all();

    // The programme is stored per language but edited as aligned rows: one row
    // is one moment of the evening, described in each language. Zip the stored
    // arrays by index to rebuild that view of it.
    $byLocale = collect($codes)
        ->mapWithKeys(fn ($c) => [
            $c => array_values((array) old("programme.$c", data_get($invitation->programme, $c, []))),
        ])
        ->all();

    $stepCount = max([0, ...array_map('count', $byLocale)]);

    $steps = [];
    for ($i = 0; $i < $stepCount; $i++) {
        $steps[] = collect($codes)
            ->mapWithKeys(fn ($c) => [$c => [
                'time' => (string) ($byLocale[$c][$i]['time'] ?? ''),
                'title' => (string) ($byLocale[$c][$i]['title'] ?? ''),
            ]])
            ->all();
    }

    $labels = collect($localeMeta)->map(fn ($m) => $m['label'])->all();
    $numerals = collect($localeMeta)->map(fn ($m) => $m['numerals'])->all();
@endphp

<div
    class="space-y-6"
    x-data="{
        locales: @js($selected),
        def: @js($default),
        labels: @js($labels),
        numerals: @js($numerals),
        fields: @js($translatable),

        text: @js($text),
        steps: @js($steps),

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
                const response = await fetch(@js(route('admin.translate')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']')?.content ?? '',
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
    }"
>

<x-ui.card title="Theme & languages">
    <div class="space-y-5">
        <x-ui.field label="Theme" name="template_id" required>
            <x-ui.select name="template_id" required>
                @foreach ($templates as $t)
                    <option value="{{ $t->id }}" @selected(old('template_id', $invitation->template_id) == $t->id)>
                        {{ $t->name }} — {{ $t->baseTemplateLabel() }}
                    </option>
                @endforeach
            </x-ui.select>
        </x-ui.field>

        <div>
            <x-ui.label>Languages <span class="text-bad-600" aria-hidden="true">*</span></x-ui.label>
            <p class="mb-2.5 text-xs text-ink-500">
                Each language gets its own guest link. Direction, fonts, digits and month names follow it.
            </p>

            <div class="flex flex-wrap gap-2">
                @foreach ($localeMeta as $code => $meta)
                    <label
                        class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm transition-colors"
                        :class="has('{{ $code }}')
                            ? 'border-brand-300 bg-brand-50 text-brand-800'
                            : 'border-ink-300 bg-white text-ink-600 hover:bg-ink-50'"
                    >
                        {{-- x-model on a shared array is how Alpine does
                             checkbox groups: it adds and removes the value,
                             keeps the element's checked property right, and the
                             value still submits normally. --}}
                        <input
                            type="checkbox"
                            name="locales[]"
                            value="{{ $code }}"
                            x-model="locales"
                            class="rounded border-ink-300 text-brand-600 focus:ring-brand-500"
                        >
                        {{ $meta['label'] }}{{ $meta['rtl'] ? ' (RTL)' : '' }}
                    </label>
                @endforeach
            </div>

            <x-ui.error name="locales" />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-ui.field
                label="Default language"
                name="default_locale"
                required
                hint="What /i/{slug} shows. The others get /i/{slug}/{code}."
            >
                {{-- Not x-model: on a select whose options come from x-for,
                     x-model initialises before the options exist and the
                     selection does not stick. Binding :selected and reading
                     the change event avoids that ordering entirely. --}}
                <x-ui.select name="default_locale" x-on:change="def = $event.target.value" required>
                    <template x-for="code in locales" :key="code">
                        <option :value="code" :selected="code === def" x-text="labels[code]"></option>
                    </template>
                </x-ui.select>
            </x-ui.field>

            <x-ui.field
                label="Guest link"
                name="slug"
                hint="Left blank, generated from the names. Changing it breaks links already sent."
            >
                <x-ui.input name="slug" :value="old('slug', $invitation->slug)" placeholder="ahmad-sidra" />
            </x-ui.field>
        </div>
    </div>
</x-ui.card>

<x-ui.card title="The couple" description="Names render in Arabic in every language.">
    <div class="grid gap-5 sm:grid-cols-2">
        <x-ui.field label="Groom (Arabic)" name="groom_ar" required hint="Include tashkeel if you want it shown, e.g. أَحْمَد">
            <x-ui.input name="groom_ar" :value="old('groom_ar', $invitation->groom_ar)" dir="rtl" required />
        </x-ui.field>

        <x-ui.field label="Bride (Arabic)" name="bride_ar" required>
            <x-ui.input name="bride_ar" :value="old('bride_ar', $invitation->bride_ar)" dir="rtl" required />
        </x-ui.field>

        <x-ui.field label="Groom (Latin)" name="groom_latin" hint="Shown small beneath the Arabic on the English link only.">
            <x-ui.input name="groom_latin" :value="old('groom_latin', $invitation->groom_latin)" placeholder="Ahmad" />
        </x-ui.field>

        <x-ui.field label="Bride (Latin)" name="bride_latin">
            <x-ui.input name="bride_latin" :value="old('bride_latin', $invitation->bride_latin)" placeholder="Sidra" />
        </x-ui.field>

        <label class="flex items-center gap-2 text-sm text-ink-700 sm:col-span-2">
            <input
                type="checkbox"
                name="show_verse"
                value="1"
                @checked(old('show_verse', $invitation->exists ? $invitation->show_verse : true))
                class="rounded border-ink-300 text-brand-600 focus:ring-brand-500"
            >
            Show the opening verse
        </label>
    </div>
</x-ui.card>

@include('admin.invitations._content-card')

<x-ui.card title="When" description="The countdown runs to this moment in the event's own timezone.">
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.field label="Date" name="date" required>
            <x-ui.input name="date" type="date" :value="old('date', $local?->format('Y-m-d'))" required />
        </x-ui.field>

        <x-ui.field label="Time" name="time" required>
            <x-ui.input name="time" type="time" :value="old('time', $local?->format('H:i'))" required />
        </x-ui.field>

        <x-ui.field label="Timezone" name="timezone" required>
            <x-ui.select name="timezone" required>
                @foreach (config('templates.timezones') as $tz)
                    <option
                        value="{{ $tz }}"
                        @selected(old('timezone', $invitation->timezone ?? config('templates.default_timezone')) === $tz)
                    >{{ $tz }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>

        <x-ui.field label="Duration (hours)" name="duration_hours" required hint="Used for the calendar entry.">
            <x-ui.input
                name="duration_hours"
                type="number"
                min="1"
                max="24"
                :value="old('duration_hours', $invitation->duration_hours ?? 5)"
                required
            />
        </x-ui.field>
    </div>
</x-ui.card>

<x-ui.card title="Getting there" description="Shared by every language.">
    <div class="grid gap-5 sm:grid-cols-2">
        <x-ui.field
            label="Google Maps link"
            name="map_url"
            class="sm:col-span-2"
            hint="Paste a share link from Google Maps. Used in preference to the search below, since it points at the exact place."
        >
            <x-ui.input
                name="map_url"
                type="url"
                :value="old('map_url', $invitation->map_url)"
                placeholder="https://maps.app.goo.gl/..."
            />
        </x-ui.field>

        <x-ui.field
            label="Map search"
            name="map_query"
            hint="Fallback when there is no link. Defaults to the venue and city."
        >
            <x-ui.input name="map_query" :value="old('map_query', $invitation->map_query)" placeholder="Babylon Hall Baghdad" />
        </x-ui.field>

        <x-ui.field label="Contact number" name="phone" hint="For questions. Shown as a call button.">
            <x-ui.input name="phone" :value="old('phone', $invitation->phone)" placeholder="+9647700000000" />
        </x-ui.field>
    </div>
</x-ui.card>

<x-ui.card title="Publishing">
    <label class="flex items-start gap-2.5 text-sm text-ink-700">
        <input
            type="checkbox"
            name="published"
            value="1"
            @checked(old('published', $invitation->published))
            class="mt-0.5 rounded border-ink-300 text-brand-600 focus:ring-brand-500"
        >
        <span>
            Published
            <span class="mt-0.5 block text-xs text-ink-500">
                While unpublished every language link returns 404, but you can still preview them while signed in.
            </span>
        </span>
    </label>
</x-ui.card>

</div>
