<x-layouts.admin title="New invitation">
    <x-ui.page-header title="New invitation" description="Customer details for one event." />

    @if ($templates->isEmpty())
        <x-ui.alert tone="warn" title="No active templates">
            An invitation needs a template to render with.
            <a href="{{ route('admin.templates.create') }}" class="font-medium underline">Create one first</a>.
        </x-ui.alert>
    @else
        <form method="POST" action="{{ route('admin.invitations.store') }}" class="space-y-6">
            @csrf
            @include('admin.invitations._form')

            <div class="flex items-center gap-3">
                <x-ui.button type="submit">Create invitation</x-ui.button>
                <x-ui.button :href="route('admin.invitations.index')" variant="ghost">Cancel</x-ui.button>
            </div>
        </form>
    @endif
</x-layouts.admin>
