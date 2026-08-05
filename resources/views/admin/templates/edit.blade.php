<x-layouts.admin :title="$template->name">
    <x-ui.page-header :title="$template->name" description="Theme settings and media.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.templates.index')" variant="secondary">Back</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @unless ($template->isRenderable())
        <x-ui.alert tone="bad" title="This template cannot render" class="mb-6">
            No base template found at <code>resources/views/base-templates/{{ $template->base_template }}/show.blade.php</code>.
            Invitations using it will fail.
        </x-ui.alert>
    @endunless

    <form method="POST" action="{{ route('admin.templates.update', $template) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')
        @include('admin.templates._form')

        <div class="flex items-center gap-3">
            <x-ui.button type="submit">Save changes</x-ui.button>
            <x-ui.button :href="route('admin.templates.index')" variant="ghost">Cancel</x-ui.button>
        </div>
    </form>
</x-layouts.admin>
