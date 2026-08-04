<x-layouts.auth title="Sign in">
    <x-ui.card>
        <h1 class="text-base font-semibold text-ink-900">Sign in</h1>
        <p class="mt-1 mb-5 text-sm text-ink-500">Enter your studio credentials to continue.</p>

        @if ($errors->any())
            <x-ui.alert tone="bad" class="mb-5">{{ $errors->first() }}</x-ui.alert>
        @endif

        <form method="POST" action="{{ route('admin.login.store') }}" class="space-y-4">
            @csrf

            <x-ui.field label="Email" name="email" required>
                <x-ui.input
                    name="email"
                    type="email"
                    :value="old('email')"
                    autocomplete="username"
                    required
                    autofocus
                />
            </x-ui.field>

            <x-ui.field label="Password" name="password" required>
                <x-ui.input
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                />
            </x-ui.field>

            <label class="flex items-center gap-2 text-sm text-ink-600">
                <input
                    type="checkbox"
                    name="remember"
                    value="1"
                    class="rounded border-ink-300 text-brand-600 focus:ring-brand-500"
                >
                Keep me signed in
            </label>

            <x-ui.button type="submit" class="w-full">Sign in</x-ui.button>
        </form>
    </x-ui.card>
</x-layouts.auth>
