<x-layouts.admin title="Invitations">
    <x-ui.page-header title="Invitations" description="One per customer, with a link per language.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.invitations.create')" icon="plus">New invitation</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padded="false">
        @if ($invitations->isEmpty())
            <x-ui.empty
                icon="envelope-open"
                title="No invitations yet"
                description="Each invitation pairs a customer's details with one of your themes."
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
                    <x-ui.th>Links</x-ui.th>
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

                        <x-ui.td>
                            <div class="flex flex-wrap items-center gap-1">
                                @foreach ($invitation->localeList() as $code)
                                    <a
                                        href="{{ $code === $invitation->defaultLocale()
                                            ? route('invitations.show', $invitation)
                                            : route('invitations.show.locale', [$invitation, $code]) }}"
                                        target="_blank"
                                        rel="noopener"
                                        title="Open the {{ config("templates.locales.{$code}.label") }} link in a new tab"
                                    >
                                        <x-ui.badge :tone="$code === $invitation->defaultLocale() ? 'brand' : 'neutral'">
                                            {{ strtoupper($code) }}
                                        </x-ui.badge>
                                    </a>
                                @endforeach
                            </div>
                        </x-ui.td>

                        <x-ui.td>
                            @if ($invitation->published)
                                <x-ui.badge tone="ok">Published</x-ui.badge>
                            @else
                                <x-ui.badge tone="warn">Draft</x-ui.badge>
                            @endif
                        </x-ui.td>

                        @php($couple = $invitation->groom_ar.' & '.$invitation->bride_ar)

                        <x-ui.td class="text-end">
                            <div class="flex items-center justify-end gap-0.5">
                                <x-ui.icon-button
                                    :href="route('admin.invitations.edit', $invitation)"
                                    icon="pencil-square"
                                    :label="'Edit the invitation for '.$couple"
                                />

                                <x-ui.copy-button
                                    :value="route('invitations.show', $invitation)"
                                    :label="'Copy the link for '.$couple"
                                />

                                <x-ui.icon-button
                                    :href="route('invitations.show', $invitation)"
                                    icon="arrow-top-right-on-square"
                                    :label="'Open the invitation for '.$couple.' in a new tab'"
                                    target="_blank"
                                    rel="noopener"
                                />

                                {{-- Publishing is what makes the link resolve at all, so
                                     the icon reflects the state rather than the action:
                                     a lit globe means live. --}}
                                <form method="POST" action="{{ route('admin.invitations.publish', $invitation) }}">
                                    @csrf
                                    @method('PATCH')
                                    <x-ui.icon-button
                                        type="submit"
                                        :icon="$invitation->published ? 'globe-alt' : 'eye-slash'"
                                        :tone="$invitation->published ? 'ok' : 'default'"
                                        :label="$invitation->published
                                            ? 'Unpublish — the link will stop working'
                                            : 'Publish — make the link live'"
                                    />
                                </form>

                                <x-ui.delete-button
                                    :action="route('admin.invitations.destroy', $invitation)"
                                    :label="'Delete the invitation for '.$couple"
                                    :confirm="'Delete the invitation for '.$couple.'? The link will stop working. This cannot be undone.'"
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
</x-layouts.admin>
