<?php

namespace App\Http\Requests\Admin;

use App\Models\Asset;
use App\Models\Template;
use App\Support\BaseTemplates;
use App\Support\UploadLimit;
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
                ...UploadLimit::rule('templates.max_video_kb'),
            ],
            'music' => [
                'nullable', 'file',
                'mimetypes:audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/ogg,audio/aac,audio/mp4,audio/x-m4a,audio/flac,audio/x-flac',
                ...UploadLimit::rule('templates.max_music_kb'),
            ],
            'remove_video' => ['nullable', 'boolean'],
            'remove_music' => ['nullable', 'boolean'],
            'derive_palette' => ['nullable', 'boolean'],

            // Media taken from the library instead of uploaded. Constrained to
            // the right kind so a music asset cannot be selected as the intro
            // clip, which would give a guest a video element playing an MP3.
            'intro_video_asset' => [
                'nullable',
                Rule::exists('assets', 'id')->where('kind', Asset::KIND_VIDEO),
            ],
            'music_asset' => [
                'nullable',
                Rule::exists('assets', 'id')->where('kind', Asset::KIND_MUSIC),
            ],

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

        // Only touch these when the request actually carries them. Defaulting
        // an absent key to an empty array meant a save that never mentioned
        // colours wiped the ones already stored — quiet data loss, and
        // especially unwelcome after they were derived from a video.
        if ($this->has('palette')) {
            $data['palette'] = array_intersect_key(
                array_filter($data['palette'] ?? [], fn ($v) => filled($v)),
                Template::DEFAULT_PALETTE
            );
        } else {
            unset($data['palette']);
        }

        if ($this->has('settings')) {
            $settings = array_intersect_key(
                array_filter($data['settings'] ?? [], fn ($v) => $v !== null && $v !== ''),
                Template::DEFAULT_SETTINGS
            );

            // Stored as numbers, not the strings a form sends. The model casts
            // on read too, but there is no reason to keep strings in the column
            // when the shape is known here.
            foreach ($settings as $key => $value) {
                $settings[$key] = is_float(Template::DEFAULT_SETTINGS[$key])
                    ? (float) $value
                    : (int) $value;
            }

            $data['settings'] = $settings;
        } else {
            unset($data['settings']);
        }

        // A checkbox absent from a submitted form genuinely means unticked, so
        // this one is read rather than preserved.
        $data['is_active'] = $this->boolean('is_active');

        // All four are instructions to the controller, not columns.
        unset(
            $data['remove_video'],
            $data['remove_music'],
            $data['derive_palette'],
            $data['intro_video_asset'],
            $data['music_asset'],
        );

        return $data;
    }
}
