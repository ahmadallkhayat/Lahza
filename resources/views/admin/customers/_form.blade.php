<x-ui.card title="Account">
    <div class="grid gap-5 sm:grid-cols-2">
        <x-ui.field label="Name" name="name" required>
            <x-ui.input name="name" :value="old('name', $customer->name)" required autofocus />
        </x-ui.field>

        <x-ui.field label="Username" name="username" required hint="What they sign in with.">
            <x-ui.input
                name="username"
                :value="old('username', $customer->username)"
                autocapitalize="none"
                spellcheck="false"
                required
            />
        </x-ui.field>

        <x-ui.field label="Email" name="email" required>
            <x-ui.input type="email" name="email" :value="old('email', $customer->email)" required />
        </x-ui.field>

        <x-ui.field label="Phone" name="phone">
            <x-ui.input name="phone" :value="old('phone', $customer->phone)" />
        </x-ui.field>

        <x-ui.field
            label="Password"
            name="password"
            :required="! $customer->exists"
            :hint="$customer->exists
                ? 'Leave empty to keep the current one.'
                : 'You will need to pass this on to them.'"
            class="sm:col-span-2"
        >
            <x-ui.input
                type="password"
                name="password"
                autocomplete="new-password"
                :required="! $customer->exists"
            />
        </x-ui.field>
    </div>
</x-ui.card>
