<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $invitations = $request->user()
            ->invitations()
            ->with('template')
            ->orderByDesc('starts_at')
            ->get();

        return view('account.dashboard', [
            'invitations' => $invitations,
            'published' => $invitations->where('published', true)->count(),

            // The next one to actually happen, which is what a couple with
            // more than one is here to check on.
            'next' => $invitations
                ->where('published', true)
                ->where('starts_at', '>=', now())
                ->sortBy('starts_at')
                ->first(),
        ]);
    }
}
