<x-layouts.admin title="New asset" width="form">
    <x-ui.page-header
        title="New asset"
        description="Something reusable: a clip, a track, or a block of text."
    >
        <x-slot:actions>
            <x-ui.button :href="route('admin.assets.index')" variant="secondary">Back</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST" action="{{ route('admin.assets.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        @include('admin.assets._form', ['submitLabel' => 'Add to library'])
    </form>
</x-layouts.admin>
