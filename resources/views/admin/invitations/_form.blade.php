@php
    $local = $invitation->exists ? $invitation->startsAtLocal() : null;
    $programmeRows = old('programme', $invitation->programme ?? []);
@endphp

<x-ui.card title="Template &amp; language">
    <div class="grid gap-5 sm:grid-cols-2">
        <x-ui.field label="Template" name="template_id" required>
            <x-ui.select name="template_id" required>
                @foreach ($templates as $t)
                    <option value="{{ $t->id }}" @selected(old('template_id', $invitation->template_id) == $t->id)>
                        {{ $t->name }} — {{ $t->categoryLabel() }}
                    </option>
                @endforeach
            </x-ui.select>
        </x-ui.field>

        <x-ui.field
            label="Language"
            name="locale"
            required
            hint="Sets the page direction, fonts, digits and translated month names."
        >
            <x-ui.select name="locale" required>
                @foreach (config('templates.locales') as $key => $meta)
                    <option value="{{ $key }}" @selected(old('locale', $invitation->locale ?? 'ar') === $key)>
                        {{ $meta['label'] }}{{ $meta['rtl'] ? ' (RTL)' : '' }}
                    </option>
                @endforeach
            </x-ui.select>
        </x-ui.field>

        <x-ui.field
            label="Guest link"
            name="slug"
            class="sm:col-span-2"
            hint="Left blank, it is generated from the names. Changing it breaks links already sent."
        >
            <x-ui.input name="slug" :value="old('slug', $invitation->slug)" placeholder="ahmad-sidra" />
        </x-ui.field>
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

        <x-ui.field label="Groom (Latin)" name="groom_latin" hint="Shown small beneath the Arabic on English invitations only.">
            <x-ui.input name="groom_latin" :value="old('groom_latin', $invitation->groom_latin)" placeholder="Ahmad" />
        </x-ui.field>

        <x-ui.field label="Bride (Latin)" name="bride_latin">
            <x-ui.input name="bride_latin" :value="old('bride_latin', $invitation->bride_latin)" placeholder="Sidra" />
        </x-ui.field>

        <x-ui.field
            label="Hosting line"
            name="hosts"
            class="sm:col-span-2"
            hint="The families extending the invitation. Written in the invitation's language."
        >
            <x-ui.textarea name="hosts" rows="2">{{ old('hosts', $invitation->hosts) }}</x-ui.textarea>
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
            <x-ui.input
                name="date"
                type="date"
                :value="old('date', $local?->format('Y-m-d'))"
                required
            />
        </x-ui.field>

        <x-ui.field label="Time" name="time" required>
            <x-ui.input
                name="time"
                type="time"
                :value="old('time', $local?->format('H:i'))"
                required
            />
        </x-ui.field>

        <x-ui.field label="Timezone" name="timezone" required>
            <x-ui.select name="timezone" required>
                @foreach (config('templates.timezones') as $tz)
                    <option value="{{ $tz }}" @selected(old('timezone', $invitation->timezone ?? 'Asia/Amman') === $tz)>{{ $tz }}</option>
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

<x-ui.card title="Where &amp; contact">
    <div class="grid gap-5 sm:grid-cols-2">
        <x-ui.field label="Venue" name="venue_name">
            <x-ui.input name="venue_name" :value="old('venue_name', $invitation->venue_name)" />
        </x-ui.field>

        <x-ui.field label="City" name="venue_city">
            <x-ui.input name="venue_city" :value="old('venue_city', $invitation->venue_city)" />
        </x-ui.field>

        <x-ui.field
            label="Map search"
            name="map_query"
            hint="What to look up on Google Maps. Falls back to venue and city."
        >
            <x-ui.input name="map_query" :value="old('map_query', $invitation->map_query)" placeholder="Four Seasons Hotel Amman" />
        </x-ui.field>

        <x-ui.field label="Contact number" name="phone" hint="For questions. Shown as a call button.">
            <x-ui.input name="phone" :value="old('phone', $invitation->phone)" placeholder="+962790000000" />
        </x-ui.field>
    </div>
</x-ui.card>

{{-- Programme repeater --}}
<x-ui.card
    title="Programme"
    description="The order of the evening. Blank rows are discarded on save."
    x-data="{ rows: {{ Js::from($programmeRows) }} }"
>
    <x-slot:actions>
        <x-ui.button
            type="button"
            variant="secondary"
            size="sm"
            icon="plus"
            x-on:click="rows.push({ time: '', title: '' })"
        >
            Add row
        </x-ui.button>
    </x-slot:actions>

    <div class="space-y-3">
        <template x-for="(row, i) in rows" :key="i">
            <div class="flex items-start gap-3">
                <input
                    type="text"
                    :name="`programme[${i}][time]`"
                    x-model="row.time"
                    placeholder="19:00"
                    class="w-28 shrink-0 rounded-lg border-ink-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    aria-label="Time"
                >
                <input
                    type="text"
                    :name="`programme[${i}][title]`"
                    x-model="row.title"
                    placeholder="Guests received"
                    class="w-full rounded-lg border-ink-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    aria-label="What happens"
                >
                <button
                    type="button"
                    x-on:click="rows.splice(i, 1)"
                    class="rounded-md p-2 text-ink-400 hover:bg-bad-50 hover:text-bad-600"
                >
                    <x-heroicon-o-trash class="h-4 w-4" />
                    <span class="sr-only">Remove row</span>
                </button>
            </div>
        </template>

        <p x-show="rows.length === 0" class="text-sm text-ink-400">No programme rows.</p>
    </div>

    <x-ui.error name="programme" />
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
                While unpublished the guest link returns 404, but you can still preview it while signed in.
            </span>
        </span>
    </label>
</x-ui.card>
