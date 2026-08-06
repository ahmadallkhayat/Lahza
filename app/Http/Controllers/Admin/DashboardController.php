<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Template;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'templateCount' => Template::count(),
            'invitationCount' => Invitation::count(),
            'publishedCount' => Invitation::where('published', true)->count(),

            // The next few events, soonest first. More useful than "newest
            // created" for a studio: what is coming up is what needs checking.
            'upcoming' => Invitation::with('template')
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at')
                ->take(5)
                ->get(),

            // Drafts are invitations nobody can open yet, so they are the
            // thing most likely to have been forgotten.
            'drafts' => Invitation::with('template')
                ->where('published', false)
                ->latest()
                ->take(5)
                ->get(),
        ]);
    }
}
