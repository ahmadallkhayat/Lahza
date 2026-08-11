<x-layouts.admin title="Customers">
    <x-ui.page-header title="Customers" description="Everyone with an account, and what they hold.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.customers.create')" icon="plus">New customer</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="GET" action="{{ route('admin.customers.index') }}" class="mb-4">
        <x-ui.input
            name="q"
            :value="$search"
            placeholder="Search by name, username, email or phone"
            aria-label="Search customers"
        />
    </form>

    <x-ui.card padded="false">
        @if ($customers->isEmpty())
            <x-ui.empty
                icon="user-circle"
                :title="$search ? 'Nobody matches that' : 'No customers yet'"
                :description="$search
                    ? 'Try a different name, username, email or phone.'
                    : 'Accounts appear here when someone signs up, or when you create one.'"
            >
                <x-slot:actions>
                    @if ($search)
                        <x-ui.button :href="route('admin.customers.index')" variant="secondary">Clear search</x-ui.button>
                    @else
                        <x-ui.button :href="route('admin.customers.create')" icon="plus">New customer</x-ui.button>
                    @endif
                </x-slot:actions>
            </x-ui.empty>
        @else
            <x-ui.table>
                <x-slot:head>
                    <x-ui.th>Name</x-ui.th>
                    <x-ui.th>Contact</x-ui.th>
                    <x-ui.th>Invitations</x-ui.th>
                    <x-ui.th>Joined</x-ui.th>
                    <x-ui.th class="text-end">Actions</x-ui.th>
                </x-slot:head>

                @foreach ($customers as $customer)
                    <tr>
                        <x-ui.td>
                            <a href="{{ route('admin.customers.show', $customer) }}" class="font-medium text-ink-900 hover:text-brand-700">
                                {{ $customer->name }}
                            </a>
                            <p class="text-xs text-ink-500">{{ '@'.$customer->username }}</p>
                        </x-ui.td>

                        <x-ui.td>
                            <p class="text-sm text-ink-700">{{ $customer->email }}</p>
                            @if ($customer->phone)
                                <p class="text-xs text-ink-500">{{ $customer->phone }}</p>
                            @endif
                        </x-ui.td>

                        <x-ui.td class="tabular-nums">
                            @if ($customer->invitations_count > 0)
                                <a href="{{ route('admin.customers.show', $customer) }}" class="font-medium text-brand-700 hover:text-brand-800">
                                    {{ $customer->invitations_count }}
                                </a>
                            @else
                                <span class="text-ink-400">0</span>
                            @endif
                        </x-ui.td>

                        <x-ui.td class="whitespace-nowrap text-sm text-ink-500">
                            {{ $customer->created_at?->format('d M Y') }}
                        </x-ui.td>

                        <x-ui.td class="text-end">
                            <div class="flex items-center justify-end gap-0.5">
                                <x-ui.icon-button
                                    :href="route('admin.customers.show', $customer)"
                                    icon="eye"
                                    :label="'View '.$customer->name"
                                />
                                <x-ui.icon-button
                                    :href="route('admin.customers.edit', $customer)"
                                    icon="pencil-square"
                                    :label="'Edit '.$customer->name"
                                />
                                <x-ui.delete-button
                                    :action="route('admin.customers.destroy', $customer)"
                                    :label="'Delete '.$customer->name"
                                    :confirm="'Delete the account for '.$customer->name.'? Their '
                                        .$customer->invitations_count.' '
                                        .Str::plural('invitation', $customer->invitations_count)
                                        .' stay, unassigned. This cannot be undone.'"
                                />
                            </div>
                        </x-ui.td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>

    @if ($customers->hasPages())
        <div class="mt-4">{{ $customers->links() }}</div>
    @endif
</x-layouts.admin>
