<x-layouts.account title="New invitation">
    <x-ui.page-header
        title="New invitation"
        description="Pick a design and fill in the evening. Nothing goes live until you publish it."
    />

    <form method="POST" action="{{ route('account.invitations.store') }}" class="space-y-6">
        @csrf
        @include('admin.invitations._form')

        <div class="flex items-center gap-3">
            <x-ui.button type="submit">Create invitation</x-ui.button>
            <x-ui.button :href="route('account.dashboard')" variant="ghost">Cancel</x-ui.button>
        </div>
    </form>
</x-layouts.account>
