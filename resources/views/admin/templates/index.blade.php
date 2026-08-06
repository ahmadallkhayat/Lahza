<x-layouts.admin title="Templates">
    <x-ui.page-header title="Templates" description="Custom themes, each built on a base template.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.templates.create')" icon="plus">New template</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padded="false">
        @if ($templates->isEmpty())
            <x-ui.empty
                icon="swatch"
                title="No templates yet"
                description="A theme pairs a base template with its own media, colours and reveal timings."
            >
                <x-slot:actions>
                    <x-ui.button :href="route('admin.templates.create')" icon="plus">New template</x-ui.button>
                </x-slot:actions>
            </x-ui.empty>
        @else
            <x-ui.table>
                <x-slot:head>
                    <x-ui.th>Name</x-ui.th>
                    <x-ui.th>Base template</x-ui.th>
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

                        <x-ui.td>{{ $template->baseTemplateLabel() }}</x-ui.td>

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
                            <div class="flex items-center justify-end gap-0.5">
                                <x-ui.icon-button
                                    :href="route('admin.templates.edit', $template)"
                                    icon="pencil-square"
                                    :label="'Edit '.$template->name"
                                />

                                {{-- The controller refuses to delete a template that
                                     invitations still point at. Saying so on the button
                                     beats letting them click and be told afterwards. --}}
                                <x-ui.delete-button
                                    :action="route('admin.templates.destroy', $template)"
                                    :label="'Delete '.$template->name"
                                    :confirm="'Delete the template “'.$template->name.'”? This cannot be undone.'"
                                    :reason="$template->invitations_count > 0
                                        ? 'Cannot delete — '.$template->invitations_count.' '
                                            .Str::plural('invitation', $template->invitations_count).' still '
                                            .($template->invitations_count === 1 ? 'uses' : 'use').' this template'
                                        : null"
                                />
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
