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

        if ($video = MediaStore::put($request, 'intro_video', 'templates/videos')) {
            MediaStore::forget($template->intro_video);
            $data['intro_video'] = $video;
        } else {
            unset($data['intro_video']);
        }

        if ($music = MediaStore::put($request, 'music', 'templates/music')) {
            MediaStore::forget($template->music);
            $data['music'] = $music;
        } else {
            unset($data['music']);
        }

        if ($request->boolean('remove_music')) {
            MediaStore::forget($template->music);
            $data['music'] = null;
        }

        $note = $this->maybeDerivePalette(
            $request,
            $data,
            $data['intro_video'] ?? $template->intro_video
        );

        $template->update($data);

        return back()->with('status', trim('Template updated. '.$note));
    }

    public function destroy(Template $template): RedirectResponse
    {
        // Invitations cascade at the database level, so refuse rather than
        // silently take a customer's invitation down with the design.
        if ($template->invitations()->exists()) {
            return back()->with('status', 'That template still has invitations. Delete those first.');
        }

        MediaStore::forget($template->intro_video);
        MediaStore::forget($template->music);

        $template->delete();

        return to_route('admin.templates.index')->with('status', 'Template deleted.');
    }

    /**
     * Replace the submitted colours with ones read from the video, but only
     * when the studio asked for it — silently overwriting hand-picked values
     * on every save would be surprising.
     *
     * Returns a note for the flash message. Extraction failing is not an
     * error: the submitted colours stand and the studio is told why.
     *
     * @param  array<string, mixed>  $data
     */
    private function maybeDerivePalette(TemplateRequest $request, array &$data, ?string $videoUrl): string
    {
        if (! $request->boolean('derive_palette')) {
            return '';
        }

        $path = MediaStore::absolutePath($videoUrl);

        if ($path === null) {
            return 'Colours were not derived — this template has no intro video.';
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
