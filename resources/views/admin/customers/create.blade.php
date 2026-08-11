<x-layouts.admin title="New customer" width="form">
    <x-ui.page-header
        title="New customer"
        description="Create an account on someone's behalf, then pass on the username and password."
    />

    <form method="POST" action="{{ route('admin.customers.store') }}" class="space-y-6">
        @csrf
        @include('admin.customers._form')

        <div class="flex items-center gap-3">
            <x-ui.button type="submit">Create account</x-ui.button>
            <x-ui.button :href="route('admin.customers.index')" variant="ghost">Cancel</x-ui.button>
        </div>
    </form>
</x-layouts.admin>
