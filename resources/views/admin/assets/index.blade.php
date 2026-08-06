{{-- The library, one section per design.

     Grouped rather than filtered by default because the point of the page is
     seeing what a design has to build with. The filters narrow it when a
     library gets long. --}}

<x-layouts.admin title="Assets">
    <x-ui.page-header
        title="Assets"
        description="Clips, music and ready-written text, reusable across templates and invitations."
    >
        <x-slot:actions>
            <x-ui.button
                :href="route('admin.assets.create', array_filter(['kind' => 'text', 'base' => $base !== 'shared' ? $base : null]))"
                variant="secondary"
                icon="document-text"
            >New text</x-ui.button>

            <x-ui.button
                :href="route('admin.assets.create', array_filter(['kind' => 'video', 'base' => $base !== 'shared' ? $base : null]))"
                icon="plus"
            >New media</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Filters submit on change and carry each other, so narrowing by design
         and then by kind does not lose the first choice. --}}
    @if ($total > 0)
        <form method="GET" action="{{ route('admin.assets.index') }}" class="mb-6">
            <div class="flex flex-wrap items-end gap-3">
                <x-ui.field label="Base template" name="base" class="w-full sm:w-64">
                    <x-ui.select name="base" onchange="this.form.submit()">
                        <option value="">Every design</option>
                        <option value="shared" @selected($base === 'shared')>Shared only</option>
                        @foreach ($baseTemplates as $key => $label)
                            <option value="{{ $key }}" @selected($base === $key)>{{ $label }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Kind" name="kind" class="w-full sm:w-48">
                    <x-ui.select name="kind" onchange="this.form.submit()">
                        <option value="">Every kind</option>
                        @foreach (\App\Models\Asset::KINDS as $value => $label)
                            <option value="{{ $value }}" @selected($kind === $value)>{{ $label }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                @if ($base || $kind)
                    <x-ui.button :href="route('admin.assets.index')" variant="ghost">Clear</x-ui.button>
                @endif
            </div>
        </form>
    @endif

    @if ($groups->isEmpty())
        <x-ui.card padded="false">
            <x-ui.empty
                icon="rectangle-stack"
                :title="$total > 0 ? 'Nothing matches those filters' : 'The library is empty'"
                :description="$total > 0
                    ? 'No asset in the library matches. Clear the filters to see everything.'
                    : 'Add an intro clip, a music track, or a block of text such as a verse. Media can then be picked from a dropdown on any template.'"
            >
                <x-slot:actions>
                    @if ($total > 0)
                        <x-ui.button :href="route('admin.assets.index')" variant="secondary">Clear filters</x-ui.button>
                    @else
                        <x-ui.button :href="route('admin.assets.create', ['kind' => 'video'])" icon="plus">New media</x-ui.button>
                        <x-ui.button :href="route('admin.assets.create', ['kind' => 'text'])" variant="secondary" icon="document-text">New text</x-ui.button>
                    @endif
                </x-slot:actions>
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="space-y-6">
            @foreach ($groups as $label => $assets)
                <x-ui.card :title="$label" :description="$assets->count().' '.Str::plural('asset', $assets->count())" padded="false">
                    <x-ui.table>
                        <x-slot:head>
                            <x-ui.th>Name</x-ui.th>
                            <x-ui.th>Kind</x-ui.th>
                            <x-ui.th>Preview</x-ui.th>
                            <x-ui.th>Status</x-ui.th>
                            <x-ui.th class="text-end">Actions</x-ui.th>
                        </x-slot:head>

                        @foreach ($assets as $asset)
                            @php($inUse = $asset->templatesInUse())
                            <tr>
                                <x-ui.td>
                                    <a href="{{ route('admin.assets.edit', $asset) }}" class="font-medium text-ink-900 hover:text-brand-700">
                                        {{ $asset->name }}
                                    </a>
                                    @if ($asset->description)
                                        <p class="mt-0.5 max-w-md truncate text-xs text-ink-500">{{ $asset->description }}</p>
                                    @endif
                                </x-ui.td>

                                <x-ui.td>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <x-ui.badge tone="neutral">{{ $asset->kindLabel() }}</x-ui.badge>
                                        @if ($asset->localeLabel())
                                            <x-ui.badge tone="brand">{{ $asset->localeLabel() }}</x-ui.badge>
                                        @endif
                                        @if ($asset->fieldLabel())
                                            <span class="text-xs text-ink-500">{{ $asset->fieldLabel() }}</span>
                                        @endif
                                    </div>
                                </x-ui.td>

                                <x-ui.td>
                                    @if ($asset->kind === \App\Models\Asset::KIND_VIDEO && $asset->path)
                                        {{-- Muted, metadata only: a library page must not
                                             pull a dozen full clips down to draw a list. --}}
                                        <video
                                            src="{{ $asset->path }}"
                                            class="block h-14 w-auto rounded border border-ink-200 bg-ink-900"
                                            muted
                                            playsinline
                                            preload="metadata"
                                        ></video>
                                    @elseif ($asset->kind === \App\Models\Asset::KIND_MUSIC && $asset->path)
                                        <audio src="{{ $asset->path }}" class="w-56 max-w-full" controls preload="none"></audio>
                                    @elseif ($asset->isText())
                                        <p
                                            class="max-w-sm truncate text-xs text-ink-600"
                                            @if ($asset->isRtl()) dir="rtl" @endif
                                        >{{ $asset->body }}</p>
                                    @else
                                        <span class="text-xs text-ink-400">Missing file</span>
                                    @endif
                                </x-ui.td>

                                <x-ui.td>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        @if ($asset->is_active)
                                            <x-ui.badge tone="ok">Active</x-ui.badge>
                                        @else
                                            <x-ui.badge tone="warn">Inactive</x-ui.badge>
                                        @endif

                                        @if ($inUse->isNotEmpty())
                                            <x-ui.badge tone="neutral">
                                                In use · {{ $inUse->count() }}
                                            </x-ui.badge>
                                        @endif
                                    </div>
                                </x-ui.td>

                                <x-ui.td class="text-end">
                                    <div class="flex items-center justify-end gap-0.5">
                                        <x-ui.icon-button
                                            :href="route('admin.assets.edit', $asset)"
                                            icon="pencil-square"
                                            :label="'Edit '.$asset->name"
                                        />

                                        {{-- The controller refuses while a template points
                                             at the file. Saying so on the button beats
                                             letting them click and be told afterwards. --}}
                                        <x-ui.delete-button
                                            :action="route('admin.assets.destroy', $asset)"
                                            :label="'Delete '.$asset->name"
                                            :confirm="'Delete the asset “'.$asset->name.'”? This cannot be undone.'"
                                            :reason="$inUse->isNotEmpty()
                                                ? 'Cannot delete — used by '.$inUse->pluck('name')->join(', ', ' and ')
                                                : null"
                                        />
                                    </div>
                                </x-ui.td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                </x-ui.card>
            @endforeach
        </div>
    @endif
</x-layouts.admin>
