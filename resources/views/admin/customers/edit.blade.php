<x-layouts.admin :title="$customer->name" width="form">
    <x-ui.page-header :title="$customer->name" :description="'@'.$customer->username">
        <x-slot:actions>
            <x-ui.button :href="route('admin.customers.show', $customer)" variant="secondary">
                View
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST" action="{{ route('admin.customers.update', $customer) }}" class="space-y-6">
        @csrf
        @method('PUT')
        @include('admin.customers._form')

        <div class="flex items-center gap-3">
            <x-ui.button type="submit">Save changes</x-ui.button>
            <x-ui.button :href="route('admin.customers.index')" variant="ghost">Cancel</x-ui.button>
        </div>
    </form>
</x-layouts.admin>
