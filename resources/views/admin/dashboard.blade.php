<x-layouts.admin title="Dashboard">
    <x-ui.page-header title="Dashboard" description="Overview of the studio.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.invitations.create')" icon="plus">New invitation</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-ui.stat
            label="Templates"
            :value="$templateCount"
            icon="swatch"
            hint="Invitation designs available"
            :href="route('admin.templates.index')"
        />
        <x-ui.stat
            label="Invitations"
            :value="$invitationCount"
            icon="envelope-open"
            hint="Created for customers"
            :href="route('admin.invitations.index')"
        />
        <x-ui.stat
            label="Published"
            :value="$publishedCount"
            icon="globe-alt"
            :hint="$invitationCount === 0
                ? 'Live and shareable'
                : $publishedCount.' of '.$invitationCount.' links are live'"
            :href="route('admin.invitations.index')"
        />
    </div>

    {{-- Side by side once the content area can hold both. Container query,
         not viewport: the sidebar takes 16rem off the width. --}}
    <div class="mt-6 grid gap-6 @4xl:grid-cols-2">

        <x-ui.card title="Coming up" description="The next events, soonest first.">
            @if ($upcoming->isEmpty())
                <p class="py-2 text-sm text-ink-500">
                    No upcoming events. Invitations with a date in the future appear here.
                </p>
            @else
                <ul class="divide-y divide-ink-200">
                    @foreach ($upcoming as $invitation)
                        <li class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
                            <div class="min-w-0">
                                <a
                                    href="{{ route('admin.invitations.edit', $invitation) }}"
                                    class="block truncate text-sm font-medium text-ink-900 hover:text-brand-700"
                                >
                                    {{ $invitation->groom_ar }} &amp; {{ $invitation->bride_ar }}
                                </a>
                                <p class="truncate text-xs text-ink-500">{{ $invitation->template->name }}</p>
                            </div>

                            <div class="shrink-0 text-end">
                                <p class="whitespace-nowrap text-xs tabular-nums text-ink-700">
                                    {{ $invitation->startsAtLocal()->format('d M Y') }}
                                </p>
                                <p class="text-xs text-ink-400">
                                    {{ $invitation->startsAtLocal()->diffForHumans(['parts' => 1]) }}
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card title="Drafts" description="Not published — these links do not open yet.">
            @if ($drafts->isEmpty())
                <p class="py-2 text-sm text-ink-500">
                    Nothing waiting. Every invitation is published.
                </p>
            @else
                <ul class="divide-y divide-ink-200">
                    @foreach ($drafts as $invitation)
                        <li class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
                            <div class="min-w-0">
                                <a
                                    href="{{ route('admin.invitations.edit', $invitation) }}"
                                    class="block truncate text-sm font-medium text-ink-900 hover:text-brand-700"
                                >
                                    {{ $invitation->groom_ar }} &amp; {{ $invitation->bride_ar }}
                                </a>
                                <p class="truncate font-mono text-xs text-ink-500">/i/{{ $invitation->slug }}</p>
                            </div>

                            <form
                                method="POST"
                                action="{{ route('admin.invitations.publish', $invitation) }}"
                                class="shrink-0"
                            >
                                @csrf
                                @method('PATCH')
                                <x-ui.button type="submit" variant="secondary" size="sm" icon="globe-alt">
                                    Publish
                                </x-ui.button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

    </div>
</x-layouts.admin>
