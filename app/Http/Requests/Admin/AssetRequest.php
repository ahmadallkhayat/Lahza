<?php

namespace App\Http\Requests\Admin;

use App\Models\Asset;
use App\Support\BaseTemplates;
use App\Support\UploadLimit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

        // Written kinds. 4000 rather than 1000: a verse with its translation
        // beneath it runs long, and this is the one field with no other
        // ceiling on it.
        if ($this->kind() === Asset::KIND_TEXT) {
            // One box per language, and at least one of them filled. The
            // required rule sits on the array rather than on any single
            // language: which languages a block is written in is the studio's
            // choice, but a block written in none of them is not a block.
            $rules['body'] = ['required', 'array'];
            $rules['body.*'] = ['nullable', 'string', 'max:4000'];

            $rules['field'] = ['nullable', Rule::in(array_keys(Asset::textFields()))];

            return $rules;
        }

        $rules['body'] = ['required', 'string', 'max:4000'];
        $rules['locale'] = ['nullable', Rule::in(array_keys(config('templates.locales')))];

        if ($this->kind() === Asset::KIND_VERSE) {
            // A verse carries its translation and its reference, both per
            // language. Stray keys are dropped in cleaned(), which keeps only
            // real locales — so the rules only have to bound the values.
            $rules['translation'] = ['array'];
            $rules['translation.*'] = ['nullable', 'string', 'max:4000'];
            $rules['reference'] = ['array'];
            $rules['reference.*'] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * A text block has to say something somewhere.
     *
     * `required` on the array cannot express this: the array arrives with a key
     * per language and is therefore never empty, however blank every box in it
     * is. Which languages get filled is the studio's choice — none of them is
     * not a choice.
     */
    public function withValidator(Validator $validator): void
    {
        if ($this->kind() !== Asset::KIND_TEXT) {
            return;
        }

        $validator->after(function (Validator $validator) {
            $written = array_filter(
                (array) $this->input('body', []),
                fn ($value) => is_string($value) && trim($value) !== ''
            );

            if ($written === []) {
                $validator->errors()->add(
                    'body',
                    'Write the text in at least one language.'
                );
            }
        });
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
            $data['translation'] = null;
            $data['reference'] = null;
        } elseif ($kind === Asset::KIND_VERSE) {
            // A verse is Arabic, as it is on the page, and is filed under
            // nothing — being a verse is the whole of what it is for.
            $data['locale'] = null;
            $data['field'] = null;
            // Matching the form, which draws no box for these: the verse is
            // read as it is in Arabic and Kurdish, so a translation into either
            // is dropped however it was posted.
            $data['translation'] = $this->perLocale(
                $data['translation'] ?? [],
                Asset::VERSE_UNTRANSLATED
            );
            $data['reference'] = $this->perLocale($data['reference'] ?? []);
        } else {
            /* Text: one body per language, stored as a map keyed by locale.

               `locale` is not written here and not read for text any more —
               which language a block is in became a property of each box
               rather than of the row. The stored value is left alone rather
               than nulled, so the migration that folded these rows into maps
               still has the information it needs to be reversed. */
            $data['body'] = json_encode(
                $this->perLocale($data['body'] ?? []) ?? [],
                JSON_UNESCAPED_UNICODE
            );

            $data['field'] = filled($data['field'] ?? null) ? $data['field'] : null;
            $data['translation'] = null;
            $data['reference'] = null;
        }

        unset($data['file']);

        return $data;
    }

    /**
     * Keep only real languages with something written in them, so an untouched
     * language is absent rather than stored blank.
     *
     * @param  array<string, string|null>  $values
     * @param  array<int, string>  $except  languages this field is not offered in
     * @return array<string, string>|null
     */
    private function perLocale(array $values, array $except = []): ?array
    {
        $kept = [];

        foreach (array_keys(config('templates.locales')) as $locale) {
            if (in_array($locale, $except, true)) {
                continue;
            }

            $value = trim((string) ($values[$locale] ?? ''));

            if ($value !== '') {
                $kept[$locale] = $value;
            }
        }

        return $kept === [] ? null : $kept;
    }
}
