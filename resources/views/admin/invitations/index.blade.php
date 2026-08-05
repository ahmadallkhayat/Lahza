<x-layouts.admin title="Invitations">
    <x-ui.page-header title="Invitations" description="One per customer, built from a template.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.invitations.create')" icon="plus">New invitation</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padded="false">
        @if ($invitations->isEmpty())
            <x-ui.empty
                icon="envelope-open"
                title="No invitations yet"
                description="Each invitation pairs a customer's details with one of your templates."
            >
                <x-slot:actions>
                    <x-ui.button :href="route('admin.invitations.create')" icon="plus">New invitation</x-ui.button>
                </x-slot:actions>
            </x-ui.empty>
        @else
            <x-ui.table>
                <x-slot:head>
                    <x-ui.th>Couple</x-ui.th>
                    <x-ui.th>Template</x-ui.th>
                    <x-ui.th>Date</x-ui.th>
                    <x-ui.th>Language</x-ui.th>
                    <x-ui.th>Status</x-ui.th>
                    <x-ui.th class="text-end">Actions</x-ui.th>
                </x-slot:head>

                @foreach ($invitations as $invitation)
                    <tr>
                        <x-ui.td>
                            <a href="{{ route('admin.invitations.edit', $invitation) }}" class="font-medium text-ink-900 hover:text-brand-700">
                                {{ $invitation->groom_ar }} &amp; {{ $invitation->bride_ar }}
                            </a>
                            <p class="font-mono text-xs text-ink-500">/i/{{ $invitation->slug }}</p>
                        </x-ui.td>

                        <x-ui.td>{{ $invitation->template->name }}</x-ui.td>

                        <x-ui.td class="whitespace-nowrap tabular-nums">
                            {{ $invitation->startsAtLocal()->format('d M Y, H:i') }}
                        </x-ui.td>

                        <x-ui.td>{{ config("templates.locales.{$invitation->locale}.label") }}</x-ui.td>

                        <x-ui.td>
                            @if ($invitation->published)
                                <x-ui.badge tone="ok">Published</x-ui.badge>
                            @else
                                <x-ui.badge tone="warn">Draft</x-ui.badge>
                            @endif
                        </x-ui.td>

                        <x-ui.td class="text-end">
                            <div class="flex items-center justify-end gap-1">
                                <x-ui.button
                                    :href="route('invitations.show', $invitation)"
                                    variant="ghost"
                                    size="sm"
                                    target="_blank"
                                >
                                    View
                                </x-ui.button>

                                <form method="POST" action="{{ route('admin.invitations.publish', $invitation) }}">
                                    @csrf
                                    @method('PATCH')
                                    <x-ui.button type="submit" variant="ghost" size="sm">
                                        {{ $invitation->published ? 'Unpublish' : 'Publish' }}
                                    </x-ui.button>
                                </form>

                                <form
                                    method="POST"
                                    action="{{ route('admin.invitations.destroy', $invitation) }}"
                                    onsubmit="return confirm('Delete this invitation?')"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" variant="ghost" size="sm" class="text-bad-600 hover:bg-bad-50">
                                        Delete
                                    </x-ui.button>
                                </form>
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
</x-layouts.admin>
