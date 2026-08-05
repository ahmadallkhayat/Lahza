<x-layouts.admin title="New template">
    <x-ui.page-header title="New template" description="Pair a design in code with its media and settings." />

    <form method="POST" action="{{ route('admin.templates.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @include('admin.templates._form')

        <div class="flex items-center gap-3">
            <x-ui.button type="submit">Create template</x-ui.button>
            <x-ui.button :href="route('admin.templates.index')" variant="ghost">Cancel</x-ui.button>
        </div>
    </form>
</x-layouts.admin>
