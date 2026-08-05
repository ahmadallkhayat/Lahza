<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InvitationRequest;
use App\Models\Invitation;
use App\Models\Template;
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
            'invitation' => new Invitation(['timezone' => 'Asia/Amman', 'duration_hours' => 5]),
            'templates' => $this->selectableTemplates(),
        ]);
    }

    public function store(InvitationRequest $request): RedirectResponse
    {
        $data = $request->cleaned();
        $data['slug'] = $data['slug'] ?? $this->uniqueSlug($data);

        $invitation = Invitation::create($data);

        return to_route('admin.invitations.edit', $invitation)
            ->with('status', 'Invitation created.');
    }

    public function edit(Invitation $invitation): View
    {
        return view('admin.invitations.edit', [
            'invitation' => $invitation,
            'templates' => $this->selectableTemplates($invitation->template_id),
        ]);
    }

    public function update(InvitationRequest $request, Invitation $invitation): RedirectResponse
    {
        $data = $request->cleaned();
        $data['slug'] = $data['slug'] ?? $invitation->slug;

        $invitation->update($data);

        return back()->with('status', 'Invitation updated.');
    }

    public function destroy(Invitation $invitation): RedirectResponse
    {
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
     * @param  array<string, mixed>  $data
     */
    private function uniqueSlug(array $data): string
    {
        $names = trim(($data['groom_latin'] ?? '').' '.($data['bride_latin'] ?? ''))
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
