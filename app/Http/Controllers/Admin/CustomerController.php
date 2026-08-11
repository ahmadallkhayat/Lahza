<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The people who have accounts.
 *
 * The studio's own accounts are deliberately not listed or editable here —
 * this is the customer register, and a page that can change an admin's
 * password is a different thing with different stakes.
 */
class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        return view('admin.customers.index', [
            'search' => $search,
            'customers' => User::query()
                ->customers()
                ->withCount('invitations')
                ->when($search !== '', fn ($query) => $query->where(
                    fn ($q) => $q->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%"),
                ))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.customers.create', ['customer' => new User]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:40', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', Password::defaults()],
        ]);

        // Always a customer. This page exists to make customers, and taking
        // the role from the request would make it a way to mint admins.
        $customer = User::create([...$data, 'role' => User::ROLE_CUSTOMER]);

        return to_route('admin.customers.show', $customer)
            ->with('status', 'Account created. Give them the username and password you set.');
    }

    public function show(User $customer): View
    {
        $this->guard($customer);

        return view('admin.customers.show', [
            'customer' => $customer,
            'invitations' => $customer->invitations()->with('template')->latest()->get(),
        ]);
    }

    public function edit(User $customer): View
    {
        $this->guard($customer);

        return view('admin.customers.edit', ['customer' => $customer]);
    }

    public function update(Request $request, User $customer): RedirectResponse
    {
        $this->guard($customer);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:40', Rule::unique('users', 'username')->ignore($customer->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($customer->id)],
            'phone' => ['nullable', 'string', 'max:40'],

            // Optional: only set when the studio is resetting it for someone
            // who cannot get in.
            'password' => ['nullable', Password::defaults()],
        ]);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $customer->update($data);

        return back()->with('status', 'Account updated.');
    }

    public function destroy(User $customer): RedirectResponse
    {
        $this->guard($customer);

        // Their invitations are not deleted with them — the foreign key is
        // nullOnDelete, so anything they built reverts to the studio's rather
        // than a wedding link going dark because an account was tidied up.
        $customer->delete();

        return to_route('admin.customers.index')
            ->with('status', 'Account deleted. Their invitations are still here, now unassigned.');
    }

    /** This page is for customers; the studio's own accounts are not its business. */
    private function guard(User $customer): void
    {
        abort_if($customer->isAdmin(), 404);
    }
}
