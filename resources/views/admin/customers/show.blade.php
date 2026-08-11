<x-layouts.admin :title="$customer->name">
    <x-ui.page-header :title="$customer->name" :description="'@'.$customer->username">
        <x-slot:actions>
            <x-ui.button :href="route('admin.customers.edit', $customer)" variant="secondary" icon="pencil-square">
                Edit
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Invitations" :value="$invitations->count()" />
        <x-ui.stat label="Published" :value="$invitations->where('published', true)->count()" />
        <x-ui.stat label="Joined" :value="$customer->created_at?->format('d M Y') ?? '—'" />
    </div>

    <x-ui.card title="Contact" class="mb-6">
        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-ink-400">Email</dt>
                <dd class="mt-1 text-sm text-ink-800">
                    <a href="mailto:{{ $customer->email }}" class="hover:text-brand-700">{{ $customer->email }}</a>
                </dd>
            </div>

            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-ink-400">Phone</dt>
                <dd class="mt-1 text-sm text-ink-800">
                    @if ($customer->phone)
                        <a href="tel:{{ $customer->phone }}" class="hover:text-brand-700">{{ $customer->phone }}</a>
                    @else
                        <span class="text-ink-400">Not given</span>
                    @endif
                </dd>
            </div>
        </dl>
    </x-ui.card>

    <x-ui.card title="Their invitations" padded="false">
        @if ($invitations->isEmpty())
            <x-ui.empty
                icon="envelope-open"
                title="None yet"
                description="This account has not built an invitation."
            />
        @else
            <x-ui.table>
                <x-slot:head>
                    <x-ui.th>Couple</x-ui.th>
                    <x-ui.th>Date</x-ui.th>
                    <x-ui.th>Theme</x-ui.th>
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

                        <x-ui.td class="whitespace-nowrap tabular-nums">
                            {{ $invitation->startsAtLocal()->format('d M Y, H:i') }}
                        </x-ui.td>

                        <x-ui.td>{{ $invitation->template->name }}</x-ui.td>

                        <x-ui.td>
                            @if ($invitation->published)
                                <x-ui.badge tone="ok">Published</x-ui.badge>
                            @else
                                <x-ui.badge tone="warn">Draft</x-ui.badge>
                            @endif
                        </x-ui.td>

                        <x-ui.td class="text-end">
                            <div class="flex items-center justify-end gap-0.5">
                                <x-ui.icon-button
                                    :href="route('admin.invitations.edit', $invitation)"
                                    icon="pencil-square"
                                    label="Edit"
                                />
                                <x-ui.icon-button
                                    :href="route('invitations.show', $invitation)"
                                    icon="arrow-top-right-on-square"
                                    label="Open"
                                    target="_blank"
                                    rel="noopener"
                                />
                            </div>
                        </x-ui.td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>
</x-layouts.admin>
