<x-layouts.admin title="New asset">
    <x-ui.page-header
        title="New asset"
        description="Something reusable: a clip, a track, or a block of text."
    >
        <x-slot:actions>
            <x-ui.button :href="route('admin.assets.index')" variant="secondary">Back</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST" action="{{ route('admin.assets.store') }}" enctype="multipart/form-data" class="max-w-3xl space-y-6">
        @csrf

        @include('admin.assets._form')

        <div class="flex items-center gap-3">
            <x-ui.button type="submit">Add to library</x-ui.button>
            <x-ui.button :href="route('admin.assets.index')" variant="ghost">Cancel</x-ui.button>
        </div>
    </form>
</x-layouts.admin>
