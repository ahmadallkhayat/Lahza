@php
    $local = $invitation->exists ? $invitation->startsAtLocal() : null;
    $localeMeta = config('templates.locales');
    $codes = array_keys($localeMeta);

    $selected = array_values(array_intersect($codes, (array) old('locales', $invitation->localeList()))) ?: ['ar'];

    $default = old('default_locale', $invitation->exists ? $invitation->defaultLocale() : 'ar');
    $default = in_array($default, $selected, true) ? $default : $selected[0];

    /* ── what this invitation can say ─────────────────────────────────
       The core fields every design shares, plus the slots the chosen design
       declares in its template.php. Written as one list so the card, the
       library pickers and the translate button all iterate the same thing —
       which is what stops a field existing in one of them and not the others.

       Slots follow the theme selected when the page was rendered. Switching to
       a theme built on a different design shows its slots after saving, since
       only the server knows what that design declares. */
    $chosen = $templates->firstWhere('id', (int) old('template_id', $invitation->template_id))
        ?? $templates->first();

    $contentFields = [
        'hosts' => ['label' => 'Hosting line', 'hint' => 'The families extending the invitation.', 'rows' => 3],
        'venue_name' => ['label' => 'Venue'],
        'venue_city' => ['label' => 'City'],
    ];

    foreach ($contentFields as $key => $meta) {
        $contentFields[$key] = $meta + ['name' => $key, 'arabic' => false, 'except' => [], 'hint' => null, 'rows' => null];
    }

    $normaliseSlot = fn (array $meta, string $key) => [
        'label' => $meta['label'] ?? $key,
        'hint' => $meta['hint'] ?? null,
        'rows' => $meta['rows'] ?? null,
        'arabic' => (bool) ($meta['arabic'] ?? false),
        'except' => (array) ($meta['except'] ?? []),
        'name' => "content[$key]",
    ];

    foreach (\App\Support\ContentSlots::ungrouped($chosen?->base_template) as $key => $meta) {
        $contentFields[$key] = $normaliseSlot($meta, $key);
    }

    // Grouped slots get a card of their own rather than a row in Content: a
    // verse, its translation and its reference are one thing with three parts,
    // and filled from one library asset instead of three.
    $slotGroups = [];

    foreach (\App\Support\ContentSlots::groups($chosen?->base_template) as $groupKey => $group) {
        $fields = [];

        foreach ($group['slots'] as $key => $meta) {
            $fields[$key] = $normaliseSlot($meta, $key);
        }

        $slotGroups[$groupKey] = $group + ['fields' => $fields];
    }

    // The Content card shows the plain fields; each group gets its own card.
    // State and validation cover both, so this split is presentational only.
    $plainFields = $contentFields;

    foreach ($slotGroups as $group) {
        $contentFields += $group['fields'];
    }

    // Translation covers the per-language fields only. An Arabic-everywhere
    // slot is one value in every language by definition, so running the verse
    // through a machine translator would be actively wrong.
    $translatable = array_keys(array_filter($contentFields, fn ($f) => ! $f['arabic']));

    // Every language gets a slot whether or not it is ticked, so text typed
    // into one, unticked and reticked, is still there.
    $text = collect($contentFields)
        ->mapWithKeys(fn ($meta, $field) => [$field => collect($codes)
            ->mapWithKeys(fn ($c) => [$c => (string) ($meta['name'] === $field
                ? old("$field.$c", data_get($invitation->{$field}, $c, ''))
                : old("content.$field.$c", data_get($invitation->content, "$field.$c", '')))])
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

    // Ready-written blocks from the library, flattened to what the browser
    // needs to insert one and fill in its placeholders.
    // `bodies` is a map keyed by language, so one Insert fills every language
    // card at once — the same block used to be three library rows found and
    // inserted separately. A language the block was never written in is simply
    // absent from the map and its card is left alone.
    $assetOptions = $textAssets
        ->map(fn ($asset) => [
            'id' => $asset->id,
            'name' => $asset->name,
            'bodies' => $asset->bodies(),
            'field' => $asset->field,
        ])
        ->values()
        ->all();

    // Library assets that fill a whole slot group in one go, keyed by the kind
    // the group asks for. A verse arrives with its translation and reference
    // attached, so inserting it fills all three fields at once.
    $groupAssets = collect($slotGroups)
        ->mapWithKeys(fn ($group, $key) => [$key => $groupAssets[$group['asset_kind']] ?? []])
        ->all();

    // The meridiem word each language shows, so {time} resolves out here to
    // exactly what the invitation itself renders. ICU's own marker is a terse
    // "م", which is why these live in lang files rather than coming from Intl.
    $meridiem = [
        'am' => collect($codes)->mapWithKeys(fn ($c) => [$c => trans('invitation.am', [], $c)])->all(),
        'pm' => collect($codes)->mapWithKeys(fn ($c) => [$c => trans('invitation.pm', [], $c)])->all(),
    ];
@endphp

<div
    class="space-y-6"
    x-data="invitationForm(@js([
        'locales' => $selected,
        'def' => $default,
        'labels' => $labels,
        'numerals' => $numerals,
        'fields' => $translatable,
        'text' => $text,
        'steps' => $steps,
        'translateUrl' => route('admin.translate'),
        'assets' => $assetOptions,
        'placeholders' => \App\Support\Placeholders::aliases(),
        'meridiem' => $meridiem,
        'arabicFields' => array_keys(array_filter($contentFields, fn ($f) => $f['arabic'])),
        'groups' => collect($slotGroups)->map(fn ($g) => ['fills' => $g['fills'] ?? []])->all(),
        'groupAssets' => $groupAssets,
        'groupPicked' => array_fill_keys(array_keys($slotGroups), ''),
        'groupNotes' => array_fill_keys(array_keys($slotGroups), ''),
        'picked' => array_fill_keys(array_keys($contentFields), ''),
        'notes' => array_fill_keys(array_keys($contentFields), ''),
    ]))"
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
                <x-ui.input name="slug" :value="old('slug', $invitation->slug)" placeholder="yusuf-layla" />
            </x-ui.field>
        </div>
    </div>
</x-ui.card>

<x-ui.card title="The couple" description="Names render in Arabic in every language.">
    <div class="grid gap-5 sm:grid-cols-2" x-data="{ groomText: '{{ old('groom_ar', $invitation->groom_ar) }}', brideText: '{{ old('bride_ar', $invitation->bride_ar) }}' }">
        <x-ui.field label="Groom (Arabic)" name="groom_ar" required hint="Include tashkeel if you want it shown. Paste voweled text here or use the tool below.">
            <div class="flex gap-2">
                <x-ui.input name="groom_ar" :value="old('groom_ar', $invitation->groom_ar)" dir="rtl" required />
                <button
                    type="button"
                    @click="window.open('https://www.i2text.com/diacritize-arabic-text', '_blank')"
                    class="shrink-0 rounded-lg border border-ink-300 bg-white px-3 py-2 text-sm font-medium text-ink-700 hover:bg-ink-50 transition-colors"
                    title="Open online tool to add vowel marks"
                >
                    Diacritize
                </button>
            </div>
        </x-ui.field>

        <x-ui.field label="Bride (Arabic)" name="bride_ar" required>
            <div class="flex gap-2">
                <x-ui.input name="bride_ar" :value="old('bride_ar', $invitation->bride_ar)" dir="rtl" required />
                <button
                    type="button"
                    @click="window.open('https://www.i2text.com/diacritize-arabic-text', '_blank')"
                    class="shrink-0 rounded-lg border border-ink-300 bg-white px-3 py-2 text-sm font-medium text-ink-700 hover:bg-ink-50 transition-colors"
                    title="Open online tool to add vowel marks"
                >
                    Diacritize
                </button>
            </div>
        </x-ui.field>

        <x-ui.field label="Groom (Latin)" name="groom_latin" hint="Shown small beneath the Arabic on the English link only.">
            <x-ui.input name="groom_latin" :value="old('groom_latin', $invitation->groom_latin)" placeholder="Yusuf" />
        </x-ui.field>

        <x-ui.field label="Bride (Latin)" name="bride_latin">
            <x-ui.input name="bride_latin" :value="old('bride_latin', $invitation->bride_latin)" placeholder="Layla" />
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

@include('admin.invitations._slot-group-card')

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
