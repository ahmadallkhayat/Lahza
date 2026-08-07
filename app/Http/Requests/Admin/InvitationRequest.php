<?php

namespace App\Http\Requests\Admin;

use App\Models\Invitation;
use App\Models\Template;
use App\Support\ContentSlots;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('invitation')?->id;
        $codes = array_keys(config('templates.locales'));

        return [
            'template_id' => ['required', 'exists:templates,id'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('invitations', 'slug')->ignore($id)],

            // At least one language, and the default has to be one of them.
            'locales' => ['required', 'array', 'min:1'],
            'locales.*' => [Rule::in($codes)],
            'default_locale' => ['required', Rule::in($codes), 'in_array:locales.*'],

            // Arabic in every language, so single-valued.
            'groom_ar' => ['required', 'string', 'max:255'],
            'bride_ar' => ['required', 'string', 'max:255'],
            'groom_latin' => ['nullable', 'string', 'max:255'],
            'bride_latin' => ['nullable', 'string', 'max:255'],

            // Per-language text, keyed by locale.
            'hosts' => ['array'],
            'hosts.*' => ['nullable', 'string', 'max:1000'],
            'venue_name' => ['array'],
            'venue_name.*' => ['nullable', 'string', 'max:255'],
            'venue_city' => ['array'],
            'venue_city.*' => ['nullable', 'string', 'max:255'],

            // Slot content is filtered against what the design declares in
            // cleaned(), so the rules only have to bound its size.
            'content' => ['array'],
            'content.*' => ['array'],
            'content.*.*' => ['nullable', 'string', 'max:4000'],

            'programme' => ['array'],
            'programme.*' => ['array', 'max:30'],
            'programme.*.*.time' => ['nullable', 'string', 'max:50'],
            'programme.*.*.title' => ['nullable', 'string', 'max:255'],

            'date' => ['required', 'date_format:Y-m-d'],
            // The countdown and the calendar file both parse this, so it
            // cannot be an arbitrary string.
            'time' => ['required', 'date_format:H:i,H:i:s'],
            'timezone' => ['required', 'timezone'],
            'duration_hours' => ['required', 'integer', 'min:1', 'max:24'],

            'map_query' => ['nullable', 'string', 'max:500'],
            'map_url' => ['nullable', 'url', 'max:2048'],
            'phone' => ['nullable', 'string', 'max:50'],

            'show_verse' => ['nullable', 'boolean'],
            'published' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'default_locale.in_array' => 'The default language must be one of the languages you selected.',
            'locales.required' => 'Choose at least one language.',
        ];
    }

    /**
     * Combine date and time into the single timestamp the model stores, and
     * keep only the languages actually selected — otherwise text typed into a
     * language then unticked would linger in the JSON.
     *
     * @return array<string, mixed>
     */
    public function cleaned(): array
    {
        $data = $this->validated();
        $locales = array_values($data['locales']);

        $data['starts_at'] = CarbonImmutable::parse(
            $data['date'].' '.$data['time'],
            $data['timezone']
        );

        unset($data['date'], $data['time']);

        foreach (Invitation::TRANSLATABLE as $field) {
            $data[$field] = $this->trimmedTexts($data[$field] ?? [], $locales);
        }

        $data['programme'] = $this->programmes($locales);
        $data['content'] = $this->slots($locales);
        $data['locales'] = $locales;
        $data['show_verse'] = $this->boolean('show_verse');
        $data['published'] = $this->boolean('published');

        return $data;
    }

    /**
     * @param  array<string, string|null>  $values
     * @param  array<int, string>  $locales
     * @return array<string, string>|null
     */
    private function trimmedTexts(array $values, array $locales): ?array
    {
        $kept = [];

        foreach ($locales as $locale) {
            $value = trim((string) ($values[$locale] ?? ''));

            if ($value !== '') {
                $kept[$locale] = $value;
            }
        }

        return $kept === [] ? null : $kept;
    }

    /**
     * Values for the slots this invitation's design declares.
     *
     * Only declared slots are kept, so a posted key that no design asked for
     * cannot end up in the column — the form is generated from the same list,
     * making anything else a forged field rather than a mistake.
     *
     * An empty slot is dropped rather than stored blank, because empty means
     * "use the design's own wording" and a stored empty string would instead
     * mean "show nothing".
     *
     * @param  array<int, string>  $locales
     * @return array<string, array<string, string>>|null
     */
    private function slots(array $locales): ?array
    {
        $template = Template::find($this->input('template_id'));
        $base = $template?->base_template;

        $kept = [];

        foreach (ContentSlots::for($base) as $key => $slot) {
            $posted = (array) $this->input("content.{$key}", []);

            // An Arabic-everywhere slot is one box on the form, posted under
            // the default locale, and read back the same way.
            $values = ($slot['arabic'] ?? false)
                ? $this->trimmedTexts($posted, [$this->input('default_locale')])
                : $this->trimmedTexts($posted, $locales);

            if ($values !== null) {
                $kept[$key] = $values;
            }
        }

        return $kept === [] ? null : $kept;
    }

    /**
     * Programme rows per language, blank rows dropped and reindexed so the
     * stored JSON is a clean ordered list.
     *
     * @param  array<int, string>  $locales
     * @return array<string, array<int, array{time: string, title: string}>>|null
     */
    private function programmes(array $locales): ?array
    {
        $all = [];

        foreach ($locales as $locale) {
            $rows = [];

            foreach ((array) $this->input("programme.{$locale}", []) as $row) {
                if (blank($row['title'] ?? null)) {
                    continue;
                }

                $rows[] = [
                    'time' => trim((string) ($row['time'] ?? '')),
                    'title' => trim($row['title']),
                ];
            }

            if ($rows !== []) {
                $all[$locale] = $rows;
            }
        }

        return $all === [] ? null : $all;
    }
}
