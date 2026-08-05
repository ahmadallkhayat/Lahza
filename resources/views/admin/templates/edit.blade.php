@php
    // Languages the preview can be shown in — those of whichever invitation
    // the preview borrows its content from, falling back to Arabic.
    $invitationLocales = \App\Models\Invitation::query()
        ->whereRelation('template', 'base_template', $template->base_template)
        ->latest()
        ->first()?->localeList()
        ?? \App\Models\Invitation::latest()->first()?->localeList()
        ?? ['ar'];
@endphp

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

    <form method="POST" action="{{ route('admin.templates.update', $template) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        {{-- Side by side as soon as the content area is wide enough for both
             (container query, not viewport — the sidebar eats 16rem). --}}
        <div class="grid items-start gap-6 @4xl:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="space-y-6">
                @include('admin.templates._form')

                <div class="flex items-center gap-3">
                    <x-ui.button type="submit">Save changes</x-ui.button>
                    <x-ui.button :href="route('admin.templates.index')" variant="ghost">Cancel</x-ui.button>
                </div>
            </div>

            @include('admin.templates._preview')
        </div>
    </form>
</x-layouts.admin>
