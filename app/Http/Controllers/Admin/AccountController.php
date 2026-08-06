<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PasswordRequest;
use App\Http\Requests\Admin\ProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The studio account's own settings. There is one account and no user
 * management, so this edits whoever is signed in — never a user picked by id,
 * which is why no route model binding appears here.
 */
class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.account.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(ProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $usernameChanged = $user->username !== $data['username'];

        $user->update($data);

        // Worth calling out: the username is what signs you in, so a silent
        // "Saved" here would be how someone locks themselves out.
        return back()->with('status', $usernameChanged
            ? 'Account updated. You now sign in as '.$user->username.'.'
            : 'Account updated.');
    }

    public function updatePassword(PasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->update(['password' => $request->validated()['password']]);

        // A new password should end any session the old one opened.
        $request->session()->regenerate();
        $this->forgetOtherSessions($request);

        return back()->with('status', 'Password updated. Any other signed-in device has been signed out.');
    }

    /**
     * Drop this account's other session rows, so a password change actually
     * evicts a session someone else left open.
     *
     * Only possible on the database driver — with file or cookie sessions
     * there is nothing to query, and the password change stands on its own.
     */
    private function forgetOtherSessions(Request $request): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();
    }
}
