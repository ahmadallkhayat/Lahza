<?php

namespace App\Http\Requests\Admin;

use App\Models\Template;
use App\Support\BaseTemplates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TemplateRequest extends FormRequest
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
        $id = $this->route('template')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('templates', 'slug')->ignore($id)],
            'base_template' => ['required', Rule::in(array_keys(BaseTemplates::available()))],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],

            'intro_video' => [
                'nullable', 'file',
                'mimetypes:video/mp4,video/webm,video/quicktime',
                'max:'.config('templates.max_video_kb'),
            ],
            'music' => [
                'nullable', 'file',
                'mimetypes:audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/ogg,audio/aac,audio/mp4,audio/x-m4a,audio/flac,audio/x-flac',
                'max:'.config('templates.max_music_kb'),
            ],
            'remove_music' => ['nullable', 'boolean'],
            'derive_palette' => ['nullable', 'boolean'],

            // Colours and timings arrive as sub-arrays keyed by role.
            'palette' => ['array'],
            'palette.*' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],

            'settings' => ['array'],
            'settings.keepVideoAudioMs' => ['nullable', 'integer', 'min:0', 'max:60000'],
            'settings.musicFadeMs' => ['nullable', 'integer', 'min:0', 'max:60000'],
            'settings.fadeOutLeadMs' => ['nullable', 'integer', 'min:0', 'max:60000'],
            'settings.crossfadeMs' => ['nullable', 'integer', 'min:100', 'max:60000'],
            'settings.duckTo' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'settings.fogHz' => ['nullable', 'integer', 'min:100', 'max:20000'],
            'settings.duckMs' => ['nullable', 'integer', 'min:0', 'max:60000'],
            'settings.revealFallbackMs' => ['nullable', 'integer', 'min:1000', 'max:120000'],
        ];
    }

    public function messages(): array
    {
        return [
            'palette.*.regex' => 'Colours must be six-digit hex values, like #a8874f.',
            'base_template.in' => 'That base template has no directory under resources/'.BaseTemplates::ROOT.'.',
        ];
    }

    /**
     * Only keep colour and timing keys the model understands, so a hand-crafted
     * request cannot stuff arbitrary data into the JSON columns.
     *
     * @return array<string, mixed>
     */
    public function cleaned(): array
    {
        $data = $this->validated();

        $data['palette'] = array_intersect_key(
            array_filter($data['palette'] ?? [], fn ($v) => filled($v)),
            Template::DEFAULT_PALETTE
        );

        $data['settings'] = array_intersect_key(
            array_filter($data['settings'] ?? [], fn ($v) => $v !== null && $v !== ''),
            Template::DEFAULT_SETTINGS
        );

        $data['is_active'] = $this->boolean('is_active');

        unset($data['remove_music'], $data['derive_palette']);

        return $data;
    }
}
