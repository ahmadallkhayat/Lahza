<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TemplateRequest;
use App\Models\Asset;
use App\Models\Template;
use App\Services\AmbientVideoEncoder;
use App\Services\BoomerangVideoEncoder;
use App\Services\VideoPaletteExtractor;
use App\Support\BaseTemplates;
use App\Support\MediaStore;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function __construct(private readonly VideoPaletteExtractor $palettes) {}

    public function index(): View
    {
        return view('admin.templates.index', [
            'templates' => Template::withCount('invitations')->latest()->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.templates.create', [
            'template' => new Template,
            'baseTemplates' => BaseTemplates::available(),
            'library' => $this->library(null, everything: true),
        ]);
    }

    public function store(TemplateRequest $request): RedirectResponse
    {
        $data = $request->cleaned();
        $data['slug'] = $data['slug'] ?? $this->uniqueSlug($data['name']);

        // An upload wins over a library pick — asking for both most likely
        // means "use this one instead".
        $data['intro_video'] = MediaStore::put($request, 'intro_video', 'templates/videos')
            ?? $this->selectedAsset($request, 'intro_video_asset')?->path;

        $data['music'] = MediaStore::put($request, 'music', 'templates/music')
            ?? $this->selectedAsset($request, 'music_asset')?->path;

        $note = $this->maybeDerivePalette($request, $data, $data['intro_video']);
        $data['ambient_video'] = $this->ambient($data['intro_video']);

        if ($request->boolean('boomerang_intro')) {
            $data['boomerang_intro'] = true;
            $data['boomerang_intro_path'] = $this->boomerang($data['intro_video']);
        }

        $template = Template::create($data);

        return to_route('admin.templates.edit', $template)
            ->with('status', trim('Template created. '.$note));
    }

    public function edit(Template $template): View
    {
        return view('admin.templates.edit', [
            'template' => $template,
            'baseTemplates' => BaseTemplates::available(),
            'library' => $this->library($template->base_template),
        ]);
    }

    public function update(TemplateRequest $request, Template $template): RedirectResponse
    {
        $data = $request->cleaned();
        $data['slug'] = $data['slug'] ?? $template->slug;

        $this->applyMedia($request, $data, $template, 'intro_video', 'templates/videos', 'remove_video', 'intro_video_asset');
        $this->applyMedia($request, $data, $template, 'music', 'templates/music', 'remove_music', 'music_asset');

        // array_key_exists, not ??: a removal sets the key to null on purpose,
        // and `?? $template->intro_video` would resurrect the deleted file's URL.
        $videoUrl = array_key_exists('intro_video', $data)
            ? $data['intro_video']
            : $template->intro_video;

        $note = $this->maybeDerivePalette($request, $data, $videoUrl);

        // Only when the clip itself changed. Re-encoding on every save would
        // spend a minute of ffmpeg to rename a template.
        if ($videoUrl !== $template->intro_video) {
            app(AmbientVideoEncoder::class)->forget($template->ambient_video);
            $data['ambient_video'] = $this->ambient($videoUrl);

            app(BoomerangVideoEncoder::class)->forget($template->boomerang_intro_path);
            if ($request->boolean('boomerang_intro')) {
                $data['boomerang_intro'] = true;
                $data['boomerang_intro_path'] = $this->boomerang($videoUrl);
            } else {
                $data['boomerang_intro'] = false;
                $data['boomerang_intro_path'] = null;
            }
        } elseif ($request->boolean('boomerang_intro') !== $template->boomerang_intro) {
            // Boomerang toggle changed but video didn't
            if ($request->boolean('boomerang_intro')) {
                $data['boomerang_intro'] = true;
                $data['boomerang_intro_path'] = $this->boomerang($videoUrl);
            } else {
                app(BoomerangVideoEncoder::class)->forget($template->boomerang_intro_path);
                $data['boomerang_intro'] = false;
                $data['boomerang_intro_path'] = null;
            }
        }

        $template->update($data);

        return back()->with('status', trim('Template updated. '.$note));
    }

    public function destroy(Template $template): RedirectResponse
    {
        // Invitations cascade at the database level, so refuse rather than
        // silently take a customer's invitation down with the design.
        //
        // Flashed as an error, not a status: a refusal dressed as a green
        // "success" banner reads as though the delete worked.
        if ($template->invitations()->exists()) {
            return back()->with('error', 'That template still has invitations. Delete those first.');
        }

        $this->releaseMedia($template->intro_video);
        app(AmbientVideoEncoder::class)->forget($template->ambient_video);
        app(BoomerangVideoEncoder::class)->forget($template->boomerang_intro_path);
        $this->releaseMedia($template->music);

        $template->delete();

        return to_route('admin.templates.index')->with('status', 'Template deleted.');
    }

    /**
     * Settle one media field on update: replace, take from the library, clear,
     * or leave alone.
     *
     * Precedence is upload, then library pick, then the remove box — asking for
     * more than one most likely means "use this one instead". Absent all three,
     * the stored file is left untouched rather than nulled.
     *
     * @param  array<string, mixed>  $data
     */
    private function applyMedia(
        TemplateRequest $request,
        array &$data,
        Template $template,
        string $field,
        string $directory,
        string $removeKey,
        string $assetKey,
    ): void {
        if ($uploaded = MediaStore::put($request, $field, $directory)) {
            $this->releaseMedia($template->{$field});
            $data[$field] = $uploaded;

            return;
        }

        if ($asset = $this->selectedAsset($request, $assetKey)) {
            $this->releaseMedia($template->{$field});
            $data[$field] = $asset->path;

            return;
        }

        if ($request->boolean($removeKey)) {
            $this->releaseMedia($template->{$field});
            $data[$field] = null;

            return;
        }

        unset($data[$field]);
    }

    /**
     * Delete a file this template is letting go of — unless the library owns it.
     *
     * Templates hold the media URL rather than a foreign key, so several can
     * point at the same library file. Deleting on replacement would empty that
     * asset for every other template using it, and for the library itself.
     * A file the template uploaded for its own use has no such claim on it and
     * is deleted as before.
     */
    private function releaseMedia(?string $url): void
    {
        if (blank($url) || Asset::ownsPath($url)) {
            return;
        }

        MediaStore::forget($url);
    }

    /**
     * The library asset picked in a dropdown, if one was and it has a file.
     *
     * The selects default to blank on every render, so a value here is always a
     * deliberate act rather than a stored setting being resubmitted — which is
     * what lets picking a clip re-derive the palette without every later save
     * doing it again.
     */
    private function selectedAsset(TemplateRequest $request, string $key): ?Asset
    {
        $id = $request->input($key);

        if (blank($id)) {
            return null;
        }

        $asset = Asset::find($id);

        return $asset && filled($asset->path) ? $asset : null;
    }

    /**
     * Media in the library this design can pick from — its own and the shared
     * ones — keyed by kind.
     *
     * @return array<string, Collection<int, Asset>>
     */
    private function library(?string $baseTemplate, bool $everything = false): array
    {
        $assets = Asset::query()
            ->active()
            ->whereIn('kind', Asset::MEDIA_KINDS)
            ->whereNotNull('path')
            // On create there is no design yet — it is chosen in this same
            // form — so narrowing to the shared library would hide every clip
            // that belongs to a design, which is most of them. The option list
            // names the design each one belongs to instead.
            ->when(! $everything, fn ($query) => $query->forBaseTemplate($baseTemplate))
            ->ordered()
            ->get();

        return [
            Asset::KIND_VIDEO => $assets->where('kind', Asset::KIND_VIDEO),
            Asset::KIND_MUSIC => $assets->where('kind', Asset::KIND_MUSIC),
        ];
    }

    /**
     * Set the palette from the intro video's own colours.
     *
     * A newly uploaded clip does this automatically: the video defines how the
     * invitation looks, so its colours are the starting point, and requiring a
     * separate checkbox for it meant uploading a video appeared to do nothing.
     * The checkbox is for re-reading the colours from a clip already attached.
     *
     * Returns a note for the flash message. Extraction failing is not an
     * error: the submitted colours stand and the studio is told why.
     *
     * @param  array<string, mixed>  $data
     */
    private function maybeDerivePalette(TemplateRequest $request, array &$data, ?string $videoUrl): string
    {
        // Picking a clip from the library is the same act as uploading one, so
        // it derives the colours too. The select is blank on every render, so
        // this only fires on the save that chose it — a later save does not
        // quietly overwrite colours that have since been tuned by hand.
        $fromNewUpload = $request->hasFile('intro_video')
            || filled($request->input('intro_video_asset'));

        if (! $fromNewUpload && ! $request->boolean('derive_palette')) {
            return '';
        }

        $path = MediaStore::absolutePath($videoUrl);

        if ($path === null) {
            // Only worth saying when it was asked for explicitly; a save that
            // removed the video should not complain about the video being gone.
            return $request->boolean('derive_palette')
                ? 'Colours were not derived — this template has no intro video.'
                : '';
        }

        $derived = $this->palettes->extract($path);

        if ($derived === null) {
            return 'Colours were not derived — no frame could be read from the video (is ffmpeg installed?).';
        }

        $data['palette'] = $derived;

        return 'Colours were read from the intro video.';
    }

    /**
     * The backdrop loop for a clip, or null when one cannot be made.
     *
     * Never fatal: ffmpeg may be absent, the clip may be too long to reverse.
     * The design blurs the intro clip in place instead, so a null here costs
     * the refraction some of its movement and nothing else.
     */
    private function ambient(?string $videoUrl): ?string
    {
        return filled($videoUrl)
            ? app(AmbientVideoEncoder::class)->encode($videoUrl)
            : null;
    }

    private function boomerang(?string $videoUrl): ?string
    {
        return filled($videoUrl)
            ? app(BoomerangVideoEncoder::class)->encode($videoUrl)
            : null;
    }

    private function uniqueSlug(string $name): string
    {
        // Str::slug transliterates Arabic, but a name of only punctuation or
        // emoji reduces to nothing and would collide on the unique index.
        $base = Str::slug($name) ?: 'template-'.Str::lower(Str::random(6));
        $slug = $base;
        $i = 1;

        while (Template::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
