<x-layouts.auth title="Create an account">
    <x-ui.card>
        <h1 class="text-base font-semibold text-ink-900">Create an account</h1>
        <p class="mt-1 mb-5 text-sm text-ink-500">Then build your invitation whenever you are ready.</p>

        @if ($errors->any())
            <x-ui.alert tone="bad" class="mb-5">{{ $errors->first() }}</x-ui.alert>
        @endif

        <form method="POST" action="{{ route('register.store') }}" class="space-y-4">
            @csrf

            <x-ui.field label="Your name" name="name" required>
                <x-ui.input name="name" :value="old('name')" autocomplete="name" required autofocus />
            </x-ui.field>

            <x-ui.field label="Username" name="username" required hint="What you will sign in with. Letters, numbers, - and _.">
                <x-ui.input
                    name="username"
                    :value="old('username')"
                    autocomplete="username"
                    autocapitalize="none"
                    spellcheck="false"
                    required
                />
            </x-ui.field>

            <x-ui.field label="Email" name="email" required>
                <x-ui.input type="email" name="email" :value="old('email')" autocomplete="email" required />
            </x-ui.field>

            <x-ui.field label="Phone" name="phone" hint="Optional. Only so we can reach you about your invitation.">
                <x-ui.input name="phone" :value="old('phone')" autocomplete="tel" />
            </x-ui.field>

            <x-ui.field label="Password" name="password" required>
                <x-ui.input type="password" name="password" autocomplete="new-password" required />
            </x-ui.field>

            <x-ui.field label="Confirm password" name="password_confirmation" required>
                <x-ui.input type="password" name="password_confirmation" autocomplete="new-password" required />
            </x-ui.field>

            <x-ui.button type="submit" class="w-full">Create account</x-ui.button>
        </form>

        <p class="mt-5 text-center text-sm text-ink-500">
            Already have one?
            <a href="{{ route('login') }}" class="font-medium text-brand-700 hover:text-brand-800">Sign in</a>
        </p>
    </x-ui.card>
</x-layouts.auth>
