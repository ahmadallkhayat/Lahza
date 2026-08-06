<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Services\VideoPaletteExtractor;
use App\Support\MediaStore;
use App\Support\UploadLimit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Offers the palettes a clip can produce, for the studio to choose from.
 *
 * Takes either a file straight off the form — so the button works on the create
 * page, before anything is saved — or the id of a template whose video is
 * already stored.
 */
class PaletteController extends Controller
{
    public function __invoke(Request $request, VideoPaletteExtractor $extractor): JsonResponse
    {
        $request->validate([
            'intro_video' => [
                'nullable', 'file',
                'mimetypes:video/mp4,video/webm,video/quicktime',
                ...UploadLimit::rule('templates.max_video_kb'),
            ],
            'template_id' => ['nullable', 'exists:templates,id'],
        ]);

        $path = $this->videoPath($request);

        if ($path === null) {
            return response()->json([
                'variants' => [],
                'message' => 'Choose an intro video first — the palettes come from its colours.',
            ], 422);
        }

        $variants = $extractor->variants($path);

        if ($variants === []) {
            return response()->json([
                'variants' => [],
                'message' => 'No frame could be read from that video. Is ffmpeg installed?',
            ], 422);
        }

        return response()->json(['variants' => $variants]);
    }

    /**
     * A freshly chosen file is read from its upload temp path — there is no
     * reason to store it just to look at its colours, and on the create page
     * there is nowhere to store it yet.
     */
    private function videoPath(Request $request): ?string
    {
        if ($file = $request->file('intro_video')) {
            return $file->getRealPath() ?: null;
        }

        if ($id = $request->input('template_id')) {
            return MediaStore::absolutePath(Template::find($id)?->intro_video);
        }

        return null;
    }
}
