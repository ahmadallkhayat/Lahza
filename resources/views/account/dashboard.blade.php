<x-layouts.account title="My invitations">
    <x-ui.page-header
        title="My invitations"
        :description="'Signed in as '.auth()->user()->username"
    >
        <x-slot:actions>
            <x-ui.button :href="route('account.invitations.create')" icon="plus">New invitation</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($invitations->isEmpty())
        <x-ui.card>
            <x-ui.empty
                icon="envelope-open"
                title="Nothing here yet"
                description="Build your first invitation — pick a design, add your details, and share the link when you are ready."
            >
                <x-slot:actions>
                    <x-ui.button :href="route('account.invitations.create')" icon="plus">New invitation</x-ui.button>
                </x-slot:actions>
            </x-ui.empty>
        </x-ui.card>
    @else
        @if ($next)
            <x-ui.card class="mb-6">
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-400">Coming up</p>
                <p class="mt-2 text-lg font-medium text-ink-900">
                    {{ $next->groom_ar }} &amp; {{ $next->bride_ar }}
                </p>
                <p class="mt-1 text-sm text-ink-500">
                    {{ $next->startsAtLocal()->format('d M Y, H:i') }}
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <x-ui.button :href="route('invitations.show', $next)" size="sm" target="_blank" icon="arrow-top-right-on-square">
                        Open
                    </x-ui.button>
                    <x-ui.copy-button
                        :value="route('invitations.show', $next)"
                        label="Copy the guest link"
                    />
                </div>
            </x-ui.card>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($invitations as $invitation)
                <x-ui.card>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a
                                href="{{ route('account.invitations.edit', $invitation) }}"
                                class="text-base font-medium text-ink-900 hover:text-brand-700"
                            >
                                {{ $invitation->groom_ar }} &amp; {{ $invitation->bride_ar }}
                            </a>
                            <p class="mt-1 text-sm text-ink-500">
                                {{ $invitation->startsAtLocal()->format('d M Y, H:i') }}
                            </p>
                            <p class="mt-0.5 text-xs text-ink-400">{{ $invitation->template->name }}</p>
                        </div>

                        @if ($invitation->published)
                            <x-ui.badge tone="ok">Live</x-ui.badge>
                        @else
                            <x-ui.badge tone="warn">Draft</x-ui.badge>
                        @endif
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <x-ui.button
                            :href="route('account.invitations.edit', $invitation)"
                            variant="secondary"
                            size="sm"
                            icon="pencil-square"
                        >Edit</x-ui.button>

                        @if ($invitation->published)
                            <x-ui.copy-button
                                :value="route('invitations.show', $invitation)"
                                :label="'Copy the link for '.$invitation->groom_ar"
                            />
                            <x-ui.icon-button
                                :href="route('invitations.show', $invitation)"
                                icon="arrow-top-right-on-square"
                                :label="'Open '.$invitation->groom_ar"
                                target="_blank"
                                rel="noopener"
                            />
                        @endif
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    @endif
</x-layouts.account>
