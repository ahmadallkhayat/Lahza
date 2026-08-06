<?php

namespace App\Http\Requests\Admin;

use App\Models\Asset;
use App\Support\BaseTemplates;
use App\Support\UploadLimit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The kind cannot be changed once an asset exists: a video row has a file
     * and no body, and letting it become a text row would leave an orphaned
     * upload behind and a media picker pointing at nothing. On update the
     * stored kind is what the rules are built from, whatever was posted.
     */
    public function kind(): string
    {
        $asset = $this->route('asset');

        if ($asset instanceof Asset) {
            return $asset->kind;
        }

        $kind = (string) $this->input('kind');

        return array_key_exists($kind, Asset::KINDS) ? $kind : Asset::KIND_TEXT;
    }

    private function isMedia(): bool
    {
        return in_array($this->kind(), Asset::MEDIA_KINDS, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $asset = $this->route('asset');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],

            // Absent means the shared library, which every design can use.
            'base_template' => ['nullable', Rule::in(array_keys(BaseTemplates::available()))],

            'is_active' => ['nullable', 'boolean'],
        ];

        // Only validated when creating; on update the stored value stands.
        if (! $asset instanceof Asset) {
            $rules['kind'] = ['required', Rule::in(array_keys(Asset::KINDS))];
        }

        if ($this->isMedia()) {
            // Required on create, optional on update — a rename should not mean
            // re-uploading a 300MB clip.
            $required = $asset instanceof Asset ? 'nullable' : 'required';

            $rules['file'] = $this->kind() === Asset::KIND_VIDEO
                ? [
                    $required, 'file',
                    'mimetypes:video/mp4,video/webm,video/quicktime',
                    ...UploadLimit::rule('templates.max_video_kb'),
                ]
                : [
                    $required, 'file',
                    'mimetypes:audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/ogg,audio/aac,audio/mp4,audio/x-m4a,audio/flac,audio/x-flac',
                    ...UploadLimit::rule('templates.max_music_kb'),
                ];

            return $rules;
        }

        // Text. 4000 rather than 1000: a verse with its translation beneath it
        // runs long, and this is the one field with no other ceiling on it.
        $rules['body'] = ['required', 'string', 'max:4000'];
        $rules['locale'] = ['nullable', Rule::in(array_keys(config('templates.locales')))];
        $rules['field'] = ['nullable', Rule::in(array_keys(Asset::TEXT_FIELDS))];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choose a file — a '.($this->kind() === Asset::KIND_VIDEO ? 'video' : 'music')
                .' asset is the file itself.',
            'body.required' => 'Write the text. Placeholders such as {bride} are filled in per invitation.',
            'base_template.in' => 'That base template has no directory under resources/'.BaseTemplates::ROOT.'.',
        ];
    }

    /**
     * Values ready to save. The file itself is handled by the controller, which
     * is where the old one gets released.
     *
     * @return array<string, mixed>
     */
    public function cleaned(): array
    {
        $data = $this->validated();
        $kind = $this->kind();

        $data['kind'] = $kind;
        $data['is_active'] = $this->boolean('is_active');

        // Blank rather than empty string, so "shared" is one value and not two.
        $data['base_template'] = filled($data['base_template'] ?? null)
            ? $data['base_template']
            : null;

        // Keep each kind's own columns and nothing else — a media asset with a
        // body, or a text asset with a locale it never uses, is just confusion
        // waiting to be read back out.
        if (in_array($kind, Asset::MEDIA_KINDS, true)) {
            $data['body'] = null;
            $data['locale'] = null;
            $data['field'] = null;
        } else {
            $data['locale'] = filled($data['locale'] ?? null) ? $data['locale'] : null;
            $data['field'] = filled($data['field'] ?? null) ? $data['field'] : null;
        }

        unset($data['file']);

        return $data;
    }
}
