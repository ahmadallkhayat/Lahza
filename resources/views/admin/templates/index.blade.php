<x-layouts.admin title="Templates">
    <x-ui.page-header title="Templates" description="Invitation designs, grouped by occasion.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.templates.create')" icon="plus">New template</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padded="false">
        @if ($templates->isEmpty())
            <x-ui.empty
                icon="swatch"
                title="No templates yet"
                description="A template pairs a design in code with its media, colours and reveal timings."
            >
                <x-slot:actions>
                    <x-ui.button :href="route('admin.templates.create')" icon="plus">New template</x-ui.button>
                </x-slot:actions>
            </x-ui.empty>
        @else
            <x-ui.table>
                <x-slot:head>
                    <x-ui.th>Name</x-ui.th>
                    <x-ui.th>Category</x-ui.th>
                    <x-ui.th>Media</x-ui.th>
                    <x-ui.th>Invitations</x-ui.th>
                    <x-ui.th>Status</x-ui.th>
                    <x-ui.th class="text-end">Actions</x-ui.th>
                </x-slot:head>

                @foreach ($templates as $template)
                    <tr>
                        <x-ui.td>
                            <a href="{{ route('admin.templates.edit', $template) }}" class="font-medium text-ink-900 hover:text-brand-700">
                                {{ $template->name }}
                            </a>
                            <p class="text-xs text-ink-500">{{ $template->slug }}</p>
                        </x-ui.td>

                        <x-ui.td>{{ $template->categoryLabel() }}</x-ui.td>

                        <x-ui.td>
                            <div class="flex items-center gap-1.5">
                                @if ($template->intro_video)
                                    <x-ui.badge tone="neutral">Video</x-ui.badge>
                                @endif
                                @if ($template->music)
                                    <x-ui.badge tone="neutral">Music</x-ui.badge>
                                @endif
                                @if (! $template->intro_video && ! $template->music)
                                    <span class="text-xs text-ink-400">None</span>
                                @endif
                            </div>
                        </x-ui.td>

                        <x-ui.td class="tabular-nums">{{ $template->invitations_count }}</x-ui.td>

                        <x-ui.td>
                            @if (! $template->isRenderable())
                                <x-ui.badge tone="bad">View missing</x-ui.badge>
                            @elseif ($template->is_active)
                                <x-ui.badge tone="ok">Active</x-ui.badge>
                            @else
                                <x-ui.badge tone="warn">Inactive</x-ui.badge>
                            @endif
                        </x-ui.td>

                        <x-ui.td class="text-end">
                            <div class="flex items-center justify-end gap-1">
                                <x-ui.button :href="route('admin.templates.edit', $template)" variant="ghost" size="sm">Edit</x-ui.button>

                                <form
                                    method="POST"
                                    action="{{ route('admin.templates.destroy', $template) }}"
                                    onsubmit="return confirm('Delete this template?')"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" variant="ghost" size="sm" class="text-bad-600 hover:bg-bad-50">
                                        Delete
                                    </x-ui.button>
                                </form>
                            </div>
                        </x-ui.td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>

    @if ($templates->hasPages())
        <div class="mt-4">{{ $templates->links() }}</div>
    @endif
</x-layouts.admin>
