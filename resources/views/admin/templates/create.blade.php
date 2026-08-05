@php
    $invitationLocales = \App\Models\Invitation::latest()->first()?->localeList() ?? ['ar'];
@endphp

<x-layouts.admin title="New template">
    <x-ui.page-header title="New template" description="Pair a base template with its own media and settings." />

    <form method="POST" action="{{ route('admin.templates.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="grid items-start gap-6 @4xl:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="space-y-6">
                @include('admin.templates._form')

                <div class="flex items-center gap-3">
                    <x-ui.button type="submit">Create template</x-ui.button>
                    <x-ui.button :href="route('admin.templates.index')" variant="ghost">Cancel</x-ui.button>
                </div>
            </div>

            @include('admin.templates._preview')
        </div>
    </form>
</x-layouts.admin>
