<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * What the public site says about the studio.
 *
 * One form over App\Support\SiteSettings, which holds the defaults — so a
 * field left blank here is not a blank on the landing page, it is that
 * field's default coming back.
 */
class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', [
            'settings' => SiteSettings::all(),
            'templates' => Template::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'brand_name' => ['nullable', 'string', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:200'],

            'hero_heading' => ['nullable', 'string', 'max:120'],
            'hero_body' => ['nullable', 'string', 'max:600'],
            'hero_cta' => ['nullable', 'string', 'max:40'],

            'offer_heading' => ['nullable', 'string', 'max:120'],
            'offers' => ['nullable', 'array', 'max:12'],
            'offers.*.title' => ['nullable', 'string', 'max:80'],
            'offers.*.body' => ['nullable', 'string', 'max:400'],

            'designs_heading' => ['nullable', 'string', 'max:120'],
            'designs_body' => ['nullable', 'string', 'max:400'],
            'featured_templates' => ['nullable', 'array'],
            'featured_templates.*' => ['integer', 'exists:templates,id'],
            'featured_limit' => ['nullable', 'integer', 'min:1', 'max:24'],

            'contact_heading' => ['nullable', 'string', 'max:120'],
            'contact_body' => ['nullable', 'string', 'max:400'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_whatsapp' => ['nullable', 'string', 'max:40'],
            'contact_email' => ['nullable', 'email', 'max:120'],
            'contact_instagram' => ['nullable', 'string', 'max:80'],
            'contact_address' => ['nullable', 'string', 'max:200'],

            'footer_note' => ['nullable', 'string', 'max:200'],
        ]);

        // A row the studio emptied is a row it deleted; keeping it would put
        // a blank card on the landing page rather than one fewer card.
        $data['offers'] = collect($data['offers'] ?? [])
            ->map(fn ($offer) => [
                'title' => trim((string) ($offer['title'] ?? '')),
                'body' => trim((string) ($offer['body'] ?? '')),
            ])
            ->reject(fn ($offer) => $offer['title'] === '' && $offer['body'] === '')
            ->values()
            ->all();

        // Checkboxes send nothing when none are ticked, and "none ticked"
        // means "show them all" — which is the empty array, not the absent
        // key that would leave whatever was stored before in place.
        $data['featured_templates'] = array_map('intval', $request->input('featured_templates', []));

        SiteSettings::put($data);

        return back()->with('status', 'Site settings saved.');
    }
}
