<x-layouts.admin title="Dashboard">
    <x-ui.page-header
        title="Dashboard"
        description="Overview of the studio."
    />

    {{-- Counts are literal zeros, not placeholders — nothing exists yet. --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-ui.stat label="Templates" value="0" icon="swatch" hint="Invitation designs available" />
        <x-ui.stat label="Invitations" value="0" icon="envelope-open" hint="Created for customers" />
        <x-ui.stat label="Published" value="0" icon="globe-alt" hint="Live and shareable" />
    </div>

</x-layouts.admin>
