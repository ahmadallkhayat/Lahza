<x-layouts.admin :title="$invitation->groom_ar.' & '.$invitation->bride_ar">
    <x-ui.page-header
        :title="$invitation->groom_ar.' & '.$invitation->bride_ar"
        :description="'Guest link: /i/'.$invitation->slug"
    >
        <x-slot:actions>
            <x-ui.button :href="route('invitations.show', $invitation)" variant="secondary" target="_blank" icon="arrow-top-right-on-square">
                View
            </x-ui.button>

            <form method="POST" action="{{ route('admin.invitations.publish', $invitation) }}">
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
            This invitation is a draft. The public link returns 404 for guests, though you can preview it while signed in.
        </x-ui.alert>
    @endunless

    <form method="POST" action="{{ route('admin.invitations.update', $invitation) }}" class="space-y-6">
        @csrf
        @method('PUT')
        @include('admin.invitations._form')

        <div class="flex items-center gap-3">
            <x-ui.button type="submit">Save changes</x-ui.button>
            <x-ui.button :href="route('admin.invitations.index')" variant="ghost">Cancel</x-ui.button>
        </div>
    </form>
</x-layouts.admin>
