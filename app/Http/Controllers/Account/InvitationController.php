<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InvitationRequest;
use App\Models\Asset;
use App\Models\Invitation;
use App\Models\Template;
use App\Services\OgImageGenerator;
use App\Support\BaseTemplates;
use App\Support\ContentSlots;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * A customer building their own invitation.
 *
 * The same form, the same validation and the same rendering as the studio's —
 * see the note on $studio in the shared _form partial. What differs is only
 * what may be reached: every query here is scoped to the signed-in account,
 * and every theme offered is an active one.
 *
 * Scoping is done by constraining the query rather than by checking ownership
 * after loading, so a wrong id is a 404 and never a row that briefly existed
 * in memory before being refused.
 */
class InvitationController extends Controller
{
    public function index(): View
    {
        return view('account.invitations.index', [
            'invitations' => $this->own()->with('template')->latest()->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('account.invitations.create', [
            'invitation' => new Invitation([
                'timezone' => config('templates.default_timezone'),
                'duration_hours' => 5,
                'locales' => ['ar'],
                'default_locale' => 'ar',
            ]),
            ...$this->formData(),
        ]);
    }

    public function store(InvitationRequest $request): RedirectResponse
    {
        $data = $request->cleaned();

        $this->guardTemplate((int) $data['template_id']);

        $data['slug'] = $data['slug'] ?? $this->uniqueSlug($data);

        // Never from the request. Ownership is the whole of what separates
        // one customer's invitations from another's, so it is set here and
        // is not a field anything can submit.
        $data['user_id'] = $request->user()->id;

        // A customer's invitation starts unpublished whatever the form said:
        // the link should not be live before they have looked at it once.
        $data['published'] = false;

        $invitation = Invitation::create($data);

        $this->syncCard($invitation, forceRedraw: true);

        return to_route('account.invitations.edit', $invitation)
            ->with('status', 'Invitation created. Publish it when you are happy with it.');
    }

    public function edit(Invitation $invitation): View
    {
        $this->authorise($invitation);

        return view('account.invitations.edit', [
            'invitation' => $invitation,
            ...$this->formData($invitation->template_id),
        ]);
    }

    public function update(InvitationRequest $request, Invitation $invitation): RedirectResponse
    {
        $this->authorise($invitation);

        $data = $request->cleaned();

        $this->guardTemplate((int) $data['template_id']);

        $data['slug'] = $data['slug'] ?? $invitation->slug;

        // Not theirs to move, and not theirs to flip from this form — the
        // publish button is its own route.
        unset($data['user_id'], $data['published']);

        $invitation->update($data);

        $this->syncCard($invitation, forceRedraw: $invitation->wasChanged([
            'groom_ar', 'bride_ar', 'starts_at', 'template_id', 'slug',
        ]));

        return back()->with('status', 'Saved.');
    }

    public function destroy(Invitation $invitation): RedirectResponse
    {
        $this->authorise($invitation);

        app(OgImageGenerator::class)->forget($invitation->og_image);

        $invitation->delete();

        return to_route('account.invitations.index')->with('status', 'Invitation deleted.');
    }

    public function togglePublish(Invitation $invitation): RedirectResponse
    {
        $this->authorise($invitation);

        $invitation->update(['published' => ! $invitation->published]);

        return back()->with('status', $invitation->published
            ? 'Published — your link is live.'
            : 'Unpublished — the link now shows nothing.');
    }

    /** @return Builder<Invitation> */
    private function own()
    {
        return Invitation::query()->where('user_id', auth()->id());
    }

    /**
     * Refuse anything that is not this account's.
     *
     * Route-model binding resolves by slug without knowing who is asking, so
     * this is what stops one customer reading another's by guessing a link.
     * 404 rather than 403, for the same reason an unpublished invitation is:
     * a refusal confirms the slug is real.
     */
    private function authorise(Invitation $invitation): void
    {
        abort_unless($invitation->belongsToUser(auth()->user()), 404);
    }

    /** Only a live theme, whatever a crafted request asked for. */
    private function guardTemplate(int $templateId): void
    {
        abort_unless(
            Template::where('id', $templateId)->where('is_active', true)->exists(),
            404,
        );
    }

    /**
     * What the shared form needs.
     *
     * `$studio` is false throughout: the form is the same one the studio
     * uses, with the affordances that are genuinely its own left out.
     *
     * @return array<string, mixed>
     */
    private function formData(?int $keepTemplateId = null): array
    {
        return [
            'studio' => false,
            'templates' => $this->selectableTemplates($keepTemplateId),
            'textAssets' => $this->textAssets(),
            'groupAssets' => $this->groupAssets(),
        ];
    }

    /**
     * Active themes, plus whichever one this invitation already uses even if
     * it has since been deactivated — otherwise saving would silently
     * reassign it to something the customer never chose.
     */
    private function selectableTemplates(?int $keepId = null)
    {
        return Template::query()
            ->where(fn ($q) => $q->where('is_active', true)->when($keepId, fn ($q2) => $q2->orWhere('id', $keepId)))
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, Asset> */
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
     * Ready-written blocks that fill a whole slot group — a verse with its
     * translation and reference, chosen once rather than typed three times.
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

    /** Same contract as the studio's: draw it if it is missing or stale. */
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

    /** @param  array<string, mixed>  $data */
    private function uniqueSlug(array $data): string
    {
        $names = trim(($data['groom_latin'] ?? '').'-'.($data['bride_latin'] ?? ''), '-')
            ?: $data['groom_ar'].' '.$data['bride_ar'];

        $base = Str::slug($names) ?: 'invitation-'.Str::lower(Str::random(6));
        $slug = $base;
        $i = 1;

        while (Invitation::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
