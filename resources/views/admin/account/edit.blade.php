<x-layouts.admin title="Account" width="form">
    <x-ui.page-header
        title="Account"
        description="Your sign-in details. There is one studio account — these are its settings."
    />

    {{-- Two independent forms. Keeping them separate means a failed password
         change cannot discard unsaved profile edits, and each posts only what
         it owns. Capped at prose width: full-bleed inputs on a wide screen are
         harder to scan, not easier. --}}
    <div class="space-y-6">

        {{-- ── Profile ─────────────────────────────────────────────── --}}
        <x-ui.card title="Profile" description="Your name, and the username you sign in with.">
            <form method="POST" action="{{ route('admin.account.update') }}" class="space-y-5">
                @csrf
                @method('PUT')

                <x-ui.field label="Name" name="name" required hint="Shown in the sidebar. Not used to sign in.">
                    <x-ui.input name="name" :value="old('name', $user->name)" required autocomplete="name" />
                </x-ui.field>

                <x-ui.field
                    label="Username"
                    name="username"
                    required
                    hint="This is what you sign in with. Letters, numbers, dashes and underscores."
                >
                    <x-ui.input name="username" :value="old('username', $user->username)" required autocomplete="username" />
                </x-ui.field>

                <x-ui.field
                    label="Email"
                    name="email"
                    required
                    hint="Kept for password resets and notices. It cannot be used to sign in."
                >
                    <x-ui.input type="email" name="email" :value="old('email', $user->email)" required autocomplete="email" />
                </x-ui.field>

                <div class="flex items-center gap-3 pt-1">
                    <x-ui.button type="submit">Save changes</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        {{-- ── Password ────────────────────────────────────────────── --}}
        <x-ui.card title="Password" description="Change the password used to sign in.">
            <form method="POST" action="{{ route('admin.account.password') }}" class="space-y-5">
                @csrf
                @method('PUT')

                <x-ui.field label="Current password" name="current_password" required>
                    <x-ui.input
                        type="password"
                        name="current_password"
                        required
                        autocomplete="current-password"
                    />
                </x-ui.field>

                <x-ui.field
                    label="New password"
                    name="password"
                    required
                    hint="At least 8 characters."
                >
                    <x-ui.input type="password" name="password" required autocomplete="new-password" />
                </x-ui.field>

                <x-ui.field label="Confirm new password" name="password_confirmation" required>
                    <x-ui.input
                        type="password"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                    />
                </x-ui.field>

                <div class="flex items-center gap-3 pt-1">
                    <x-ui.button type="submit">Update password</x-ui.button>
                </div>
            </form>
        </x-ui.card>

    </div>
</x-layouts.admin>
