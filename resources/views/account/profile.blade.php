@php($user = auth()->user())

<x-layouts.account title="Profile">
    <x-ui.page-header title="Profile" description="Your details and password." />

    <div class="space-y-6">
        <form method="POST" action="{{ route('account.profile.update') }}">
            @csrf
            @method('PUT')

            <x-ui.card title="Details">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.field label="Name" name="name" required>
                        <x-ui.input name="name" :value="old('name', $user->name)" required />
                    </x-ui.field>

                    <x-ui.field label="Username" name="username" required hint="What you sign in with.">
                        <x-ui.input name="username" :value="old('username', $user->username)" required />
                    </x-ui.field>

                    <x-ui.field label="Email" name="email" required>
                        <x-ui.input type="email" name="email" :value="old('email', $user->email)" required />
                    </x-ui.field>

                    <x-ui.field label="Phone" name="phone">
                        <x-ui.input name="phone" :value="old('phone', $user->phone)" />
                    </x-ui.field>
                </div>

                <div class="mt-5">
                    <x-ui.button type="submit">Save details</x-ui.button>
                </div>
            </x-ui.card>
        </form>

        <form method="POST" action="{{ route('account.profile.password') }}">
            @csrf
            @method('PUT')

            <x-ui.card title="Password">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.field label="Current password" name="current_password" required class="sm:col-span-2">
                        <x-ui.input type="password" name="current_password" autocomplete="current-password" required />
                    </x-ui.field>

                    <x-ui.field label="New password" name="password" required>
                        <x-ui.input type="password" name="password" autocomplete="new-password" required />
                    </x-ui.field>

                    <x-ui.field label="Confirm new password" name="password_confirmation" required>
                        <x-ui.input type="password" name="password_confirmation" autocomplete="new-password" required />
                    </x-ui.field>
                </div>

                <div class="mt-5">
                    <x-ui.button type="submit">Change password</x-ui.button>
                </div>
            </x-ui.card>
        </form>
    </div>
</x-layouts.account>
