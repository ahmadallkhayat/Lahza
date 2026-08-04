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

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <x-ui.card title="Getting started" description="What's wired up so far." class="lg:col-span-2">
            <ul class="space-y-3 text-sm text-ink-600">
                <li class="flex gap-2.5">
                    <x-heroicon-o-check-circle class="mt-0.5 h-4 w-4 shrink-0 text-ok-600" />
                    <span>Studio shell, sign-in, and the shared component library.</span>
                </li>
                <li class="flex gap-2.5">
                    <x-heroicon-o-check-circle class="mt-0.5 h-4 w-4 shrink-0 text-ok-600" />
                    <span>
                        The lux wedding page is parked in
                        <code class="rounded bg-ink-100 px-1 py-0.5 text-xs text-ink-700">archive/lux-wedding/</code>.
                    </span>
                </li>
                <li class="flex gap-2.5">
                    <x-heroicon-o-clock class="mt-0.5 h-4 w-4 shrink-0 text-ink-400" />
                    <span>Template and invitation models — not built yet.</span>
                </li>
            </ul>
        </x-ui.card>

        <x-ui.card title="Environment">
            <dl class="space-y-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-ink-500">Database</dt>
                    <dd class="font-medium text-ink-800">{{ config('database.default') }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-ink-500">Environment</dt>
                    <dd><x-ui.badge tone="brand">{{ app()->environment() }}</x-ui.badge></dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-ink-500">PHP</dt>
                    <dd class="font-medium tabular-nums text-ink-800">{{ PHP_VERSION }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-ink-500">Laravel</dt>
                    <dd class="font-medium tabular-nums text-ink-800">{{ app()->version() }}</dd>
                </div>
            </dl>
        </x-ui.card>
    </div>
</x-layouts.admin>
