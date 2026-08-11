<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Open sign-up, for a couple who want to build their own invitation.
 *
 * Only ever creates a customer. The role is set here rather than taken from
 * the request for the obvious reason — it is the one field that decides
 * whether an account can reach the studio, so it is not a field at all.
 */
class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],

            // Sign-in is by username throughout, so this is a credential and
            // has the same shape rule the studio's own accounts do.
            'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:40', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            ...$data,
            'role' => User::ROLE_CUSTOMER,
        ]);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account.dashboard')
            ->with('status', 'Welcome. Let’s build your invitation.');
    }
}
