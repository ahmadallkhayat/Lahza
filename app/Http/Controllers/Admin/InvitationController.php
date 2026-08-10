<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InvitationRequest;
use App\Models\Asset;
use App\Models\Invitation;
use App\Models\Template;
use App\Services\OgImageGenerator;
use App\Support\BaseTemplates;
use App\Support\ContentSlots;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function index(): View
    {
        return view('admin.invitations.index', [
            'invitations' => Invitation::with('template')->latest()->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.invitations.create', [
            'invitation' => new Invitation([
                'timezone' => config('templates.default_timezone'),
                'duration_hours' => 5,
                'locales' => ['ar'],
                'default_locale' => 'ar',
            ]),
            'templates' => $this->selectableTemplates(),
            'textAssets' => $this->textAssets(),
            'groupAssets' => $this->groupAssets(),
        ]);
    }

    public function store(InvitationRequest $request): RedirectResponse
    {
        $data = $request->cleaned();
        $data['slug'] = $data['slug'] ?? $this->uniqueSlug($data);

        $invitation = Invitation::create($data);

        $this->syncCard($invitation, forceRedraw: true);

        return to_route('admin.invitations.edit', $invitation)
            ->with('status', 'Invitation created.');
    }

    public function edit(Invitation $invitation): View
    {
        return view('admin.invitations.edit', [
            'invitation' => $invitation,
            'templates' => $this->selectableTemplates($invitation->template_id),
            'textAssets' => $this->textAssets(),
            'groupAssets' => $this->groupAssets(),
        ]);
    }

    public function update(InvitationRequest $request, Invitation $invitation): RedirectResponse
    {
        $data = $request->cleaned();
        $data['slug'] = $data['slug'] ?? $invitation->slug;

        $invitation->update($data);

        $this->syncCard($invitation, forceRedraw: $invitation->wasChanged([
            'groom_ar', 'bride_ar', 'starts_at', 'template_id', 'slug',
        ]));

        return back()->with('status', 'Invitation updated.');
    }

    /**
     * Make sure this invitation's link-preview card is actually there,
     * redrawing it if it needs to be.
     *
     * Runs on every save, not just ones that touched a field the card shows.
     * A card is drawn once and then trusted forever by everything else in
     * the app — the column holds a URL and nobody re-checks it — so this is
     * the one place a stale or missing file gets caught: gone from disk
     * (storage wiped, deleted by hand), never drawn in the first place (an
     * old save that failed before the renderer could self-heal), or simply
     * out of date because $forceRedraw says the content moved on since.
     *
     * Silent either way: a card is decorative, and failing to draw one a
     * second time is exactly as unsurprising as failing to draw it the
     * first — generate() has already logged why.
     */
    private function syncCard(Invitation $invitation, bool $forceRedraw): void
    {
        $card = app(OgImageGenerator::class);

        if (! $forceRedraw && $card->exists($invitation->og_image)) {
            return;
        }

        $previous = $invitation->og_image;
        $generated = $card->generate($invitation);

        if ($generated === null) {
            return;
        }

        $invitation->update(['og_image' => $generated]);
        $card->forget($previous);
    }

    public function destroy(Invitation $invitation): RedirectResponse
    {
        app(OgImageGenerator::class)->forget($invitation->og_image);

        $invitation->delete();

        return to_route('admin.invitations.index')->with('status', 'Invitation deleted.');
    }

    public function togglePublish(Invitation $invitation): RedirectResponse
    {
        $invitation->update(['published' => ! $invitation->published]);

        return back()->with('status', $invitation->published
            ? 'Invitation published — the link is live.'
            : 'Invitation unpublished — the link now returns 404.');
    }

    /**
     * Active templates, plus whichever one this invitation already uses even
     * if it has since been deactivated — otherwise editing would silently
     * reassign it.
     */
    private function selectableTemplates(?int $keepId = null)
    {
        return Template::query()
            ->where(fn ($q) => $q->where('is_active', true)->when($keepId, fn ($q2) => $q2->orWhere('id', $keepId)))
            ->orderBy('name')
            ->get();
    }

    /**
     * Ready-written blocks the studio can drop into the content fields —
     * verses, hosting lines, and anything else worth keeping.
     *
     * Not scoped to the chosen template's base template, unlike the media
     * library: the template can be changed in this same form, and a blessing
     * is not specific to a design anyway. The option list names the design each
     * block belongs to instead.
     *
     * @return Collection<int, Asset>
     */
    private function textAssets()
    {
        return Asset::query()
            ->active()
            ->ofKind(Asset::KIND_TEXT)
            ->whereNotNull('body')
            ->ordered()
            ->get();
    }

    /**
     * Assets that fill a whole slot group in one go, keyed by kind.
     *
     * A verse is one asset carrying three parts, so the form can offer a
     * single Insert that fills the verse, its translation and its reference
     * together — rather than the studio finding three text blocks and hoping
     * they are the same verse.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupAssets(): array
    {
        $kinds = collect(BaseTemplates::available())
            ->flatMap(fn ($label, $base) => collect(ContentSlots::groups($base))->pluck('asset_kind'))
            ->filter()
            ->unique()
            ->values();

        return $kinds
            ->mapWithKeys(fn (string $kind) => [$kind => Asset::query()
                ->active()
                ->ofKind($kind)
                ->whereNotNull('body')
                ->ordered()
                ->get()
                ->map(fn (Asset $asset) => [
                    'id' => $asset->id,
                    'name' => $asset->name,
                    'body' => (string) $asset->body,
                    'translation' => (array) $asset->translation,
                    'reference' => (array) $asset->reference,
                ])
                ->all(),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function uniqueSlug(array $data): string
    {
        $names = trim(($data['groom_latin'] ?? '').'-'.($data['bride_latin'] ?? ''), '-')
            ?: $data['groom_ar'].' '.$data['bride_ar'];

        // Str::slug transliterates Arabic; the fallback covers names that
        // reduce to nothing at all.
        $base = Str::slug($names) ?: 'invitation-'.Str::lower(Str::random(6));
        $slug = $base;
        $i = 1;

        while (Invitation::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
