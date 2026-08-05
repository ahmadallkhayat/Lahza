<?php

namespace App\Http\Requests\Admin;

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

        return [
            'template_id' => ['required', 'exists:templates,id'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('invitations', 'slug')->ignore($id)],
            'locale' => ['required', Rule::in(array_keys(config('templates.locales')))],

            'groom_ar' => ['required', 'string', 'max:255'],
            'bride_ar' => ['required', 'string', 'max:255'],
            'groom_latin' => ['nullable', 'string', 'max:255'],
            'bride_latin' => ['nullable', 'string', 'max:255'],

            'hosts' => ['nullable', 'string', 'max:1000'],

            'date' => ['required', 'date_format:Y-m-d'],
            // The countdown and the calendar file both parse this, so it
            // cannot be an arbitrary string.
            'time' => ['required', 'date_format:H:i,H:i:s'],
            'timezone' => ['required', 'timezone'],
            'duration_hours' => ['required', 'integer', 'min:1', 'max:24'],

            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_city' => ['nullable', 'string', 'max:255'],
            'map_query' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],

            'programme' => ['nullable', 'array', 'max:30'],
            'programme.*.time' => ['nullable', 'string', 'max:50'],
            'programme.*.title' => ['nullable', 'string', 'max:255'],

            'show_verse' => ['nullable', 'boolean'],
            'published' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Combine the date and time fields into the single timestamp the model
     * stores, and tidy the programme rows.
     *
     * @return array<string, mixed>
     */
    public function cleaned(): array
    {
        $data = $this->validated();

        // Interpreted in the event's own zone, then stored UTC by the cast.
        $data['starts_at'] = CarbonImmutable::parse(
            $data['date'].' '.$data['time'],
            $data['timezone']
        );

        unset($data['date'], $data['time']);

        $data['programme'] = $this->programmeRows();
        $data['show_verse'] = $this->boolean('show_verse');
        $data['published'] = $this->boolean('published');

        return $data;
    }

    /**
     * Drop blank rows and reindex, so the stored JSON is a clean ordered list.
     *
     * @return array<int, array{time: string, title: string}>|null
     */
    private function programmeRows(): ?array
    {
        $rows = [];

        foreach ($this->input('programme', []) as $row) {
            if (blank($row['title'] ?? null)) {
                continue;
            }

            $rows[] = [
                'time' => trim((string) ($row['time'] ?? '')),
                'title' => trim($row['title']),
            ];
        }

        return $rows === [] ? null : $rows;
    }
}
