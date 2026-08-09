{{-- The library, one section per kind.

     Clips, tracks and text are different things that happen to share a table,
     so each gets its own section. Which design an asset belongs to is a
     property of the row, not a reason to split the page — it reads in the
     Library column. The filters narrow both axes when the library gets long. --}}

@php
    use App\Models\Asset;
@endphp

<x-layouts.admin title="Assets">
    <x-ui.page-header
        title="Assets"
        description="Clips, music and ready-written text, reusable across templates and invitations."
    >
        <x-slot:actions>
            {{-- One way in. The kind is chosen on the next page, where it is a
                 question with room to explain itself rather than two buttons
                 that quietly split the library in half. --}}
            <x-ui.button
                :href="route('admin.assets.create', array_filter(['base' => $base !== 'shared' ? $base : null]))"
                icon="plus"
            >New asset</x-ui.button>
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
                        @foreach (Asset::KINDS as $value => $label)
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

    @if ($sections->isEmpty())
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
                        <x-ui.button :href="route('admin.assets.create')" icon="plus">New asset</x-ui.button>
                    @endif
                </x-slot:actions>
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="space-y-6">
            @foreach ($sections as $section)
                @php($assets = $section['assets'])

                <x-ui.card padded="false">
                    {{-- Written out rather than passed as :title, so the section
                         heading can carry its icon and its count. --}}
                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b border-ink-200 px-5 py-4">
                        <div class="flex min-w-0 items-center gap-2.5">
                            <x-dynamic-component
                                :component="'heroicon-o-'.$section['icon']"
                                class="h-4 w-4 shrink-0 text-ink-400"
                            />
                            <h2 class="text-sm font-semibold text-ink-900">{{ $section['label'] }}</h2>
                            <span class="text-xs text-ink-500">
                                {{ $assets->count() }} {{ Str::plural('asset', $assets->count()) }}
                            </span>
                        </div>

                        {{-- Opens the form already filed under this section, so
                             adding a second verse never means re-answering what
                             it is. --}}
                        <x-ui.button
                            :href="route('admin.assets.create', array_filter([
                                'kind' => $section['kind'],
                                'field' => $section['field'],
                                'base' => $base !== 'shared' ? $base : null,
                            ]))"
                            variant="ghost"
                            size="sm"
                            icon="plus"
                        >Add</x-ui.button>
                    </div>

                    <x-ui.table>
                        <x-slot:head>
                            <x-ui.th>Name</x-ui.th>
                            <x-ui.th>Library</x-ui.th>
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

                                {{-- Which design owns this, and the language it
                                     is written in. Not what it is for — that is
                                     the section heading, and repeating it in
                                     every row of that section says nothing. --}}
                                <x-ui.td>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <x-ui.badge :tone="$asset->base_template === null ? 'neutral' : 'brand'">
                                            {{ $asset->base_template === null ? 'Shared — all templates' : $asset->baseTemplateLabel() }}
                                        </x-ui.badge>
                                        @if ($asset->localeLabel())
                                            <span class="text-xs text-ink-500">{{ $asset->localeLabel() }}</span>
                                        @endif
                                    </div>
                                </x-ui.td>

                                <x-ui.td>
                                    @if ($asset->kind === Asset::KIND_VIDEO && $asset->path)
                                        {{-- Muted, metadata only: a library page must not
                                             pull a dozen full clips down to draw a list. --}}
                                        <video
                                            src="{{ $asset->path }}"
                                            class="block h-14 w-auto rounded border border-ink-200 bg-ink-900"
                                            muted
                                            playsinline
                                            preload="metadata"
                                        ></video>
                                    @elseif ($asset->kind === Asset::KIND_MUSIC && $asset->path)
                                        <audio src="{{ $asset->path }}" class="w-56 max-w-full" controls preload="none"></audio>
                                    @elseif ($asset->isText())
                                        {{-- One line per language the block was written in. A
                                             hosting line is one row carrying three languages
                                             now, and a list that showed only one of them would
                                             hide the two that are also about to be inserted. --}}
                                        <div class="max-w-sm space-y-1">
                                            @foreach ($asset->bodies() as $code => $text)
                                                <p
                                                    class="flex items-baseline gap-2 text-xs text-ink-600"
                                                    @if (Asset::rtlFor($code)) dir="rtl" @endif
                                                >
                                                    <span class="shrink-0 text-[0.6rem] font-semibold uppercase tracking-wider text-ink-400">
                                                        {{ config("templates.locales.{$code}.short", strtoupper($code)) }}
                                                    </span>
                                                    <span class="truncate">{{ $text }}</span>
                                                </p>
                                            @endforeach
                                        </div>
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
