@php
    $couple = $invitation->groom_ar.' & '.$invitation->bride_ar;

    $links = collect($invitation->localeList())
        ->sortByDesc(fn ($code) => $code === $invitation->defaultLocale())
        ->map(fn ($code) => [
            'code' => $code,
            'label' => config("templates.locales.{$code}.label"),
            'default' => $code === $invitation->defaultLocale(),
            'url' => $code === $invitation->defaultLocale()
                ? route('invitations.show', $invitation)
                : route('invitations.show.locale', [$invitation, $code]),
        ]);
@endphp

<x-layouts.account :title="$couple">
    <x-ui.page-header :title="$couple" description="Your invitation.">
        <x-slot:actions>
            <form method="POST" action="{{ route('account.invitations.publish', $invitation) }}">
                @csrf
                @method('PATCH')
                <x-ui.button type="submit" :variant="$invitation->published ? 'secondary' : 'primary'">
                    {{ $invitation->published ? 'Unpublish' : 'Publish' }}
                </x-ui.button>
            </form>
        </x-slot:actions>
    </x-ui.page-header>

    @unless ($invitation->published)
        <x-ui.alert tone="warn" class="mb-6">
            This is a draft — the links below show nothing to your guests yet. Publish when you are ready.
        </x-ui.alert>
    @endunless

    <x-ui.card class="mb-6" padded="false">
        <ul class="divide-y divide-ink-200">
            @foreach ($links as $link)
                <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                    <div class="min-w-0">
                        <p class="flex items-center gap-2 text-sm font-medium text-ink-900">
                            {{ $link['label'] }}
                            @if ($link['default'])
                                <x-ui.badge tone="brand">Default</x-ui.badge>
                            @endif
                        </p>
                        <p class="truncate font-mono text-xs text-ink-500">{{ $link['url'] }}</p>
                    </div>

                    <div class="flex items-center gap-1">
                        <x-ui.copy-button :value="$link['url']" :label="'Copy the '.$link['label'].' link'" />
                        <x-ui.button
                            :href="$link['url']"
                            variant="secondary"
                            size="sm"
                            target="_blank"
                            icon="arrow-top-right-on-square"
                        >Open</x-ui.button>
                    </div>
                </li>
            @endforeach
        </ul>
    </x-ui.card>

    <form method="POST" action="{{ route('account.invitations.update', $invitation) }}" class="space-y-6">
        @csrf
        @method('PUT')
        @include('admin.invitations._form')

        <div class="flex items-center gap-3">
            <x-ui.button type="submit">Save changes</x-ui.button>
            <x-ui.button :href="route('account.dashboard')" variant="ghost">Back</x-ui.button>
        </div>
    </form>
</x-layouts.account>
