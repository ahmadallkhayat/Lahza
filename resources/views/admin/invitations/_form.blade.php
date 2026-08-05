@php
    $local = $invitation->exists ? $invitation->startsAtLocal() : null;
    $codes = array_keys(config('templates.locales'));

    $selected = array_values(array_intersect(
        $codes,
        (array) old('locales', $invitation->localeList())
    )) ?: ['ar'];

    $default = old('default_locale', $invitation->exists ? $invitation->defaultLocale() : 'ar');
    $default = in_array($default, $selected, true) ? $default : $selected[0];

    // Every language starts with an array so the repeater has something to bind
    // to, whether or not it has rows yet.
    $programme = collect($codes)
        ->mapWithKeys(fn ($code) => [
            $code => array_values((array) old("programme.$code", data_get($invitation->programme, $code, []))),
        ])
        ->all();

    $shortLabels = collect(config('templates.locales'))
        ->map(fn ($meta) => $meta['label'])
        ->all();

    // Per-language value for a translated field.
    $textFor = fn (string $field, string $code) => old(
        "$field.$code",
        data_get($invitation->{$field}, $code)
    );
@endphp

<div
    class="space-y-6"
    x-data="{
        locales: @js($selected),
        def: @js($default),
        labels: @js($shortLabels),
        programme: @js($programme),

        has(code) { return this.locales.includes(code) },

        toggle(code) {
            if (this.has(code)) {
                // Never leave an invitation with no language at all.
                if (this.locales.length === 1) return;
                this.locales = this.locales.filter(c => c !== code);
            } else {
                this.locales.push(code);
            }

            // The default has to stay one of the chosen languages.
            if (! this.has(this.def)) this.def = this.locales[0];
        },
    }"
>

<x-ui.card title="Theme &amp; languages">
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
                Each language gets its own guest link, and its own content below.
                Direction, fonts, digits and month names follow the language.
            </p>

            <div class="flex flex-wrap gap-2">
                @foreach (config('templates.locales') as $code => $meta)
                    <label
                        class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm transition-colors"
                        :class="has('{{ $code }}')
                            ? 'border-brand-300 bg-brand-50 text-brand-800'
                            : 'border-ink-300 bg-white text-ink-600 hover:bg-ink-50'"
                    >
                        <input
                            type="checkbox"
                            name="locales[]"
                            value="{{ $code }}"
                            :checked="has('{{ $code }}')"
                            x-on:click.prevent="toggle('{{ $code }}')"
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
                <x-ui.select name="default_locale" x-model="def" required>
                    <template x-for="code in locales" :key="code">
                        <option :value="code" x-text="labels[code]"></option>
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

{{-- One content block per language, shown only while that language is ticked.
     Inputs stay in the DOM so nothing typed is lost by unticking and reticking;
     the request drops any language not selected on save. --}}
@foreach (config('templates.locales') as $code => $meta)
    <div x-show="has('{{ $code }}')" x-cloak>
        <x-ui.card :title="$meta['label'].' content'">
            <x-slot:actions>
                <x-ui.badge tone="brand" x-show="def === '{{ $code }}'" x-cloak>Default</x-ui.badge>
                <code class="text-xs text-ink-500">/i/{{ $invitation->slug ?: '…' }}@if ($code !== $default)/{{ $code }}@endif</code>
            </x-slot:actions>

            <div class="grid gap-5 sm:grid-cols-2" @if ($meta['rtl']) dir="rtl" @endif>
                <x-ui.field
                    :label="'Hosting line'"
                    :name="'hosts.'.$code"
                    :for="'hosts_'.$code"
                    class="sm:col-span-2"
                    hint="The families extending the invitation."
                >
                    <x-ui.textarea
                        :name="'hosts['.$code.']'"
                        :id="'hosts_'.$code"
                        rows="2"
                    >{{ $textFor('hosts', $code) }}</x-ui.textarea>
                </x-ui.field>

                <x-ui.field :label="'Venue'" :name="'venue_name.'.$code" :for="'venue_name_'.$code">
                    <x-ui.input
                        :name="'venue_name['.$code.']'"
                        :id="'venue_name_'.$code"
                        :value="$textFor('venue_name', $code)"
                    />
                </x-ui.field>

                <x-ui.field :label="'City'" :name="'venue_city.'.$code" :for="'venue_city_'.$code">
                    <x-ui.input
                        :name="'venue_city['.$code.']'"
                        :id="'venue_city_'.$code"
                        :value="$textFor('venue_city', $code)"
                    />
                </x-ui.field>
            </div>

            {{-- Programme for this language --}}
            <div class="mt-6 border-t border-ink-200 pt-5">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-ink-900">Programme</p>
                        <p class="text-xs text-ink-500">The order of the evening. Blank rows are discarded on save.</p>
                    </div>
                    <x-ui.button
                        type="button"
                        variant="secondary"
                        size="sm"
                        icon="plus"
                        x-on:click="programme['{{ $code }}'].push({ time: '', title: '' })"
                    >
                        Add row
                    </x-ui.button>
                </div>

                <div class="space-y-3" @if ($meta['rtl']) dir="rtl" @endif>
                    <template x-for="(row, i) in programme['{{ $code }}']" :key="i">
                        <div class="flex items-start gap-3">
                            <input
                                type="text"
                                :name="`programme[{{ $code }}][${i}][time]`"
                                x-model="row.time"
                                placeholder="{{ $meta['numerals'] === 'arab' ? '٧:٠٠' : '7:00' }}"
                                class="w-28 shrink-0 rounded-lg border-ink-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                aria-label="Time"
                            >
                            <input
                                type="text"
                                :name="`programme[{{ $code }}][${i}][title]`"
                                x-model="row.title"
                                class="w-full rounded-lg border-ink-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                aria-label="What happens"
                            >
                            <button
                                type="button"
                                x-on:click="programme['{{ $code }}'].splice(i, 1)"
                                class="rounded-md p-2 text-ink-400 hover:bg-bad-50 hover:text-bad-600"
                            >
                                <x-heroicon-o-trash class="h-4 w-4" />
                                <span class="sr-only">Remove row</span>
                            </button>
                        </div>
                    </template>

                    <p x-show="programme['{{ $code }}'].length === 0" class="text-sm text-ink-400">No programme rows.</p>
                </div>
            </div>
        </x-ui.card>
    </div>
@endforeach

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
