<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssetRequest;
use App\Models\Asset;
use App\Models\Template;
use App\Support\BaseTemplates;
use App\Support\MediaStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The asset library: intro clips, music, and blocks of ready-written text,
 * held per base template so a design's own material stays together.
 *
 * Media is uploaded once here and then chosen from a dropdown on a template,
 * rather than re-uploaded for every theme that wants the same clip.
 */
class AssetController extends Controller
{
    public function index(Request $request): View
    {
        $base = $request->query('base');
        $kind = $request->query('kind');

        $assets = Asset::query()
            // "shared" is a real filter value, and null base_template is what
            // it means — hence the explicit branch rather than a where().
            ->when($base === 'shared', fn ($q) => $q->whereNull('base_template'))
            ->when($base !== null && $base !== 'shared' && $base !== '',
                fn ($q) => $q->where('base_template', $base))
            ->when($kind !== null && array_key_exists($kind, Asset::KINDS),
                fn ($q) => $q->where('kind', $kind))
            ->ordered()
            ->get();

        return view('admin.assets.index', [
            // Grouped by kind: clips, tracks and text are different things that
            // happen to share a table, and a section each is how the library
            // gets read. Which design an asset belongs to is a property of the
            // row, shown in its own column, not a reason to split the page.
            //
            // Sorted into KINDS order rather than whatever the rows arrived in,
            // so the sections do not reshuffle as the library fills up.
            'groups' => $assets
                ->groupBy('kind')
                ->sortBy(fn ($rows, $kind) => array_search($kind, array_keys(Asset::KINDS), true)),
            'baseTemplates' => BaseTemplates::available(),
            'base' => $base,
            'kind' => $kind,
            'total' => Asset::count(),
        ]);
    }

    public function create(Request $request): View
    {
        $kind = (string) $request->query('kind');

        return view('admin.assets.create', [
            // No kind by default. Choosing what this is comes first, and the
            // rest of the form follows from it — defaulting to text put the
            // studio in the middle of one answer before it had been asked.
            'asset' => new Asset([
                'kind' => array_key_exists($kind, Asset::KINDS) ? $kind : '',
                'base_template' => $request->query('base') ?: null,
                'is_active' => true,
            ]),
            'baseTemplates' => BaseTemplates::available(),
        ]);
    }

    public function store(AssetRequest $request): RedirectResponse
    {
        $data = $request->cleaned();

        if (in_array($data['kind'], Asset::MEDIA_KINDS, true)) {
            $data['path'] = MediaStore::put(
                $request,
                'file',
                Asset::DIRECTORIES[$data['kind']]
            );
        }

        $asset = Asset::create($data);

        return to_route('admin.assets.index', $this->filters($asset))
            ->with('status', 'Asset added to the library.');
    }

    public function edit(Asset $asset): View
    {
        return view('admin.assets.edit', [
            'asset' => $asset,
            'baseTemplates' => BaseTemplates::available(),
            'inUse' => $asset->templatesInUse(),
        ]);
    }

    public function update(AssetRequest $request, Asset $asset): RedirectResponse
    {
        $data = $request->cleaned();

        if ($asset->isMedia() && ($uploaded = MediaStore::put($request, 'file', $asset->directory()))) {
            $previous = $asset->path;
            $data['path'] = $uploaded;

            // Templates hold the URL, not a foreign key, so replacing the file
            // silently re-points every template that had selected this asset.
            // Better than leaving them on a file the library no longer lists.
            $repointed = $this->repointTemplates($previous, $uploaded);

            MediaStore::forget($previous);

            $asset->update($data);

            return back()->with('status', $repointed > 0
                ? 'Asset updated. '.$repointed.' '.Str::plural('template', $repointed)
                    .' now use'.($repointed === 1 ? 's' : '').' the new file.'
                : 'Asset updated.');
        }

        $asset->update($data);

        return back()->with('status', 'Asset updated.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        // Refuse rather than leave a template pointing at a file that is gone —
        // the guest page would render with no intro clip and no explanation.
        //
        // Flashed as an error, not a status: a refusal in success green reads
        // as though the delete worked.
        $inUse = $asset->templatesInUse();

        if ($inUse->isNotEmpty()) {
            return back()->with('error', 'That asset is still used by '
                .$inUse->pluck('name')->join(', ', ' and ')
                .'. Change those templates first.');
        }

        MediaStore::forget($asset->path);

        $asset->delete();

        return to_route('admin.assets.index')->with('status', 'Asset deleted.');
    }

    /**
     * Move every template that pointed at the old file onto the new one.
     *
     * @return int templates changed
     */
    private function repointTemplates(?string $from, string $to): int
    {
        if (blank($from)) {
            return 0;
        }

        return Template::where('intro_video', $from)->update(['intro_video' => $to])
            + Template::where('music', $from)->update(['music' => $to]);
    }

    /**
     * Return to the library filtered to the asset just saved, so a run of
     * uploads for one design keeps that design in view.
     *
     * @return array<string, string>
     */
    private function filters(Asset $asset): array
    {
        return array_filter([
            'base' => $asset->base_template ?? 'shared',
            'kind' => $asset->kind,
        ]);
    }
}
