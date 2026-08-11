<x-layouts.account title="All invitations">
    <x-ui.page-header title="All invitations" description="Everything on your account.">
        <x-slot:actions>
            <x-ui.button :href="route('account.invitations.create')" icon="plus">New invitation</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padded="false">
        @if ($invitations->isEmpty())
            <x-ui.empty
                icon="envelope-open"
                title="Nothing here yet"
                description="Build your first invitation and it will show up here."
            >
                <x-slot:actions>
                    <x-ui.button :href="route('account.invitations.create')" icon="plus">New invitation</x-ui.button>
                </x-slot:actions>
            </x-ui.empty>
        @else
            <x-ui.table>
                <x-slot:head>
                    <x-ui.th>Couple</x-ui.th>
                    <x-ui.th>Date</x-ui.th>
                    <x-ui.th>Design</x-ui.th>
                    <x-ui.th>Status</x-ui.th>
                    <x-ui.th class="text-end">Actions</x-ui.th>
                </x-slot:head>

                @foreach ($invitations as $invitation)
                    <tr>
                        <x-ui.td>
                            <a href="{{ route('account.invitations.edit', $invitation) }}" class="font-medium text-ink-900 hover:text-brand-700">
                                {{ $invitation->groom_ar }} &amp; {{ $invitation->bride_ar }}
                            </a>
                            <p class="font-mono text-xs text-ink-500">/i/{{ $invitation->slug }}</p>
                        </x-ui.td>

                        <x-ui.td class="whitespace-nowrap tabular-nums">
                            {{ $invitation->startsAtLocal()->format('d M Y, H:i') }}
                        </x-ui.td>

                        <x-ui.td>{{ $invitation->template->name }}</x-ui.td>

                        <x-ui.td>
                            @if ($invitation->published)
                                <x-ui.badge tone="ok">Live</x-ui.badge>
                            @else
                                <x-ui.badge tone="warn">Draft</x-ui.badge>
                            @endif
                        </x-ui.td>

                        <x-ui.td class="text-end">
                            <div class="flex items-center justify-end gap-0.5">
                                <x-ui.icon-button
                                    :href="route('account.invitations.edit', $invitation)"
                                    icon="pencil-square"
                                    label="Edit"
                                />

                                @if ($invitation->published)
                                    <x-ui.copy-button
                                        :value="route('invitations.show', $invitation)"
                                        label="Copy the guest link"
                                    />
                                @endif

                                <x-ui.delete-button
                                    :action="route('account.invitations.destroy', $invitation)"
                                    label="Delete"
                                    confirm="Delete this invitation? The link will stop working. This cannot be undone."
                                />
                            </div>
                        </x-ui.td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>

    @if ($invitations->hasPages())
        <div class="mt-4">{{ $invitations->links() }}</div>
    @endif
</x-layouts.account>
