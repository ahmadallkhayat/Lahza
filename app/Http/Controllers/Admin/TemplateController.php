<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TemplateRequest;
use App\Models\Template;
use App\Services\VideoPaletteExtractor;
use App\Support\BaseTemplates;
use App\Support\MediaStore;
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
        ]);
    }

    public function store(TemplateRequest $request): RedirectResponse
    {
        $data = $request->cleaned();
        $data['slug'] = $data['slug'] ?? $this->uniqueSlug($data['name']);

        $data['intro_video'] = MediaStore::put($request, 'intro_video', 'templates/videos');
        $data['music'] = MediaStore::put($request, 'music', 'templates/music');

        $note = $this->maybeDerivePalette($request, $data, $data['intro_video']);

        $template = Template::create($data);

        return to_route('admin.templates.edit', $template)
            ->with('status', trim('Template created. '.$note));
    }

    public function edit(Template $template): View
    {
        return view('admin.templates.edit', [
            'template' => $template,
            'baseTemplates' => BaseTemplates::available(),
        ]);
    }

    public function update(TemplateRequest $request, Template $template): RedirectResponse
    {
        $data = $request->cleaned();
        $data['slug'] = $data['slug'] ?? $template->slug;

        $this->applyMedia($request, $data, $template, 'intro_video', 'templates/videos', 'remove_video');
        $this->applyMedia($request, $data, $template, 'music', 'templates/music', 'remove_music');

        // array_key_exists, not ??: a removal sets the key to null on purpose,
        // and `?? $template->intro_video` would resurrect the deleted file's URL.
        $videoUrl = array_key_exists('intro_video', $data)
            ? $data['intro_video']
            : $template->intro_video;

        $note = $this->maybeDerivePalette($request, $data, $videoUrl);

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

        MediaStore::forget($template->intro_video);
        MediaStore::forget($template->music);

        $template->delete();

        return to_route('admin.templates.index')->with('status', 'Template deleted.');
    }

    /**
     * Settle one media field on update: replace, clear, or leave alone.
     *
     * A new upload wins over a tick in the remove box — asking for both most
     * likely means "use this one instead". Absent both, the stored file is left
     * untouched rather than nulled.
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
    ): void {
        if ($uploaded = MediaStore::put($request, $field, $directory)) {
            MediaStore::forget($template->{$field});
            $data[$field] = $uploaded;

            return;
        }

        if ($request->boolean($removeKey)) {
            MediaStore::forget($template->{$field});
            $data[$field] = null;

            return;
        }

        unset($data[$field]);
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
        $fromNewUpload = $request->hasFile('intro_video');

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
