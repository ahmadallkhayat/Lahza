<x-layouts.auth title="Sign in">
    <x-ui.card>
        <h1 class="text-base font-semibold text-ink-900">Sign in</h1>
        <p class="mt-1 mb-5 text-sm text-ink-500">Welcome back.</p>

        @if (session('status'))
            <x-ui.alert tone="ok" class="mb-5">{{ session('status') }}</x-ui.alert>
        @endif

        @if ($errors->any())
            <x-ui.alert tone="bad" class="mb-5">{{ $errors->first() }}</x-ui.alert>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
            @csrf

            <x-ui.field label="Username" name="username" required>
                <x-ui.input
                    name="username"
                    type="text"
                    :value="old('username')"
                    autocomplete="username"
                    autocapitalize="none"
                    spellcheck="false"
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

        <p class="mt-5 text-center text-sm text-ink-500">
            No account yet?
            <a href="{{ route('register') }}" class="font-medium text-brand-700 hover:text-brand-800">Create one</a>
        </p>
    </x-ui.card>
</x-layouts.auth>
