<?php

namespace App\Http\Requests\Admin;

use App\Models\Asset;
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

            // Built from whichever design this theme is being saved against,
            // rather than one fixed list. A design that animates on timings of
            // its own gets them validated with its own bounds, and a timing it
            // never declared has no rule here and is dropped by cleaned().
            ...$this->settingRules(),
        ];
    }

    /**
     * A rule per timing the chosen design lets the studio change.
     *
     * Unlabelled timings are deliberately absent: those are the design's own
     * safety margins, never rendered as a field, so accepting one from a
     * request would only let a hand-crafted post override something the
     * studio cannot even see.
     *
     * @return array<string, array<int, string>>
     */
    private function settingRules(): array
    {
        $rules = [];

        foreach (BaseTemplates::editableSettings($this->chosenBaseTemplate()) as $key => $setting) {
            $rule = ['nullable', isset($setting['step']) ? 'numeric' : 'integer'];

            if (isset($setting['min'])) {
                $rule[] = 'min:'.$setting['min'];
            }

            if (isset($setting['max'])) {
                $rule[] = 'max:'.$setting['max'];
            }

            $rules['settings.'.$key] = $rule;
        }

        return $rules;
    }

    /**
     * The design this save is for.
     *
     * Read from the request rather than the stored row, because the form can
     * move a theme from one design to another in the same save that sets its
     * timings — validating the incoming values against the design being left
     * behind would reject the ones being arrived at. Falls back to the stored
     * design for a request that does not mention one.
     */
    private function chosenBaseTemplate(): ?string
    {
        $submitted = $this->input('base_template');

        if (is_string($submitted) && BaseTemplates::exists($submitted)) {
            return $submitted;
        }

        return $this->route('template')?->base_template;
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
        $base = $this->chosenBaseTemplate();

        if ($this->has('palette')) {
            $data['palette'] = array_intersect_key(
                array_filter($data['palette'] ?? [], fn ($v) => filled($v)),
                BaseTemplates::palette($base)
            );
        } else {
            unset($data['palette']);
        }

        if ($this->has('settings')) {
            $editable = BaseTemplates::editableSettings($base);

            $settings = array_intersect_key(
                array_filter($data['settings'] ?? [], fn ($v) => $v !== null && $v !== ''),
                $editable
            );

            // Stored as numbers, not the strings a form sends. The model casts
            // on read too, but there is no reason to keep strings in the column
            // when the shape is known here.
            foreach ($settings as $key => $value) {
                $settings[$key] = isset($editable[$key]['step'])
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
