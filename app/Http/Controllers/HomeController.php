<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Support\SiteSettings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

/**
 * The public landing page.
 *
 * Everything it says comes from SiteSettings, and everything it shows comes
 * from the themes that actually exist — so the page cannot advertise a design
 * that was deactivated, and the studio changes its own copy without a deploy.
 */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('public.home', [
            'settings' => SiteSettings::all(),
            'designs' => $this->designs(),
        ]);
    }

    /**
     * The themes to show off.
     *
     * A picked set if the studio picked one, otherwise the newest few active
     * themes — so the page has something on it from the first deploy without
     * anyone having to curate first.
     *
     * Both paths are narrowed to active themes with a video: a theme with no
     * clip has nothing to show, and one that was switched off was switched
     * off for a reason.
     *
     * @return Collection<int, Template>
     */
    private function designs()
    {
        $picked = array_filter((array) SiteSettings::get('featured_templates', []));

        return Template::query()
            ->where('is_active', true)
            ->whereNotNull('intro_video')
            ->when(
                $picked !== [],
                fn ($query) => $query->whereIn('id', $picked),
                fn ($query) => $query->limit((int) SiteSettings::get('featured_limit', 6)),
            )
            ->latest()
            ->get();
    }
}
