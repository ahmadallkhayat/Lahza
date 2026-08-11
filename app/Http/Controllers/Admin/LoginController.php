<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Studio sign-in. There is deliberately no registration route — accounts
 * are created by seeder or console, never through the browser.
 */
class LoginController extends Controller
{
    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Guards against session fixation: the pre-login session id is
        // discarded once the user is authenticated.
        $request->session()->regenerate();

        // A customer who found this door still gets in — to their own side of
        // the app. Sending them to the dashboard would sign them in and then
        // 404 them, which reads as the password having been wrong.
        if (! $request->user()->isAdmin()) {
            return redirect()->route('account.dashboard');
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
