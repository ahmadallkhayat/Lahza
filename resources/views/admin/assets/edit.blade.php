<x-layouts.admin :title="$asset->name" width="form">
    <x-ui.page-header :title="$asset->name" :description="$asset->kindLabel().' · '.$asset->baseTemplateLabel()">
        <x-slot:actions>
            <x-ui.button :href="route('admin.assets.index')" variant="secondary">Back</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Which templates would be affected by a change here, named rather than
         counted — replacing the file moves all of them onto the new one, and
         deleting is refused while any of them point at it. --}}
    @if ($inUse->isNotEmpty())
        <x-ui.alert tone="warn" title="In use" class="mb-6">
            {{ $inUse->pluck('name')->join(', ', ' and ') }}
            {{ $inUse->count() === 1 ? 'uses' : 'use' }} this asset.
            Replacing the file moves {{ $inUse->count() === 1 ? 'it' : 'them' }} onto the new one;
            deleting is refused until {{ $inUse->count() === 1 ? 'it points' : 'they point' }} elsewhere.
        </x-ui.alert>
    @endif

    <form method="POST" action="{{ route('admin.assets.update', $asset) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        @include('admin.assets._form', ['submitLabel' => 'Save changes'])
    </form>
</x-layouts.admin>
