<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    {{-- Read by the translate button's fetch call. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' — Lahza' : 'Lahza' }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full" x-data="{ drawer: false }">

{{-- ── Sidebar ─────────────────────────────────────────────────────
     Fixed from lg up; an off-canvas drawer below that. Contents are
     rendered once and shared by both presentations. --}}
<div
    x-show="drawer"
    x-transition.opacity
    @click="drawer = false"
    x-cloak
    class="fixed inset-0 z-30 bg-ink-900/40 lg:hidden"
></div>

<aside
    class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-ink-200 bg-white transition-transform lg:translate-x-0"
    :class="drawer ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
>
    <div class="flex h-14 shrink-0 items-center justify-between border-b border-ink-200 px-4">
        {{-- Wordmark shown here: at sidebar size the mark's own letterforms
             are too small to carry the name. --}}
        <x-ui.brand :href="route('admin.dashboard')" wordmark />
        <button type="button" @click="drawer = false" class="rounded-md p-1 text-ink-400 hover:bg-ink-100 lg:hidden">
            <x-heroicon-o-x-mark class="h-5 w-5" />
            <span class="sr-only">Close navigation</span>
        </button>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
        @foreach (config('admin.nav') as $group)
            <div class="space-y-1">
                @if ($group['heading'])
                    <p class="px-3 pb-1 text-[0.65rem] font-semibold uppercase tracking-wider text-ink-400">
                        {{ $group['heading'] }}
                    </p>
                @endif

                @foreach ($group['items'] as $item)
                    <x-ui.nav-item
                        :label="$item['label']"
                        :href="route($item['route'])"
                        :icon="$item['icon']"
                        :active="request()->routeIs($item['active'])"
                    />
                @endforeach
            </div>
        @endforeach
    </nav>

    {{-- Account block, pinned to the bottom --}}
    <div class="shrink-0 border-t border-ink-200 p-3">
        <div class="flex items-center gap-3 px-1 pb-3">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-ink-100 text-xs font-semibold text-ink-600">
                {{ Str::upper(Str::substr(auth()->user()->name, 0, 1)) }}
            </span>
            <div class="min-w-0">
                <p class="truncate text-sm font-medium text-ink-900">{{ auth()->user()->name }}</p>
                {{-- The username, since that is what signs you in. Built as a
                     string so Blade never sees a bare @ to interpret. --}}
                <p class="truncate text-xs text-ink-500">{{ '@'.auth()->user()->username }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <x-ui.button type="submit" variant="ghost" size="sm" icon="arrow-left-start-on-rectangle" class="w-full justify-start">
                Sign out
            </x-ui.button>
        </form>
    </div>
</aside>

{{-- ── Main ───────────────────────────────────────────────────────── --}}
<div class="lg:pl-64">
    {{-- Slim bar exists only to hold the drawer trigger on small screens.
         Navigation itself lives in the sidebar. --}}
    <div class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-ink-200 bg-white/90 px-4 backdrop-blur lg:hidden">
        <button type="button" @click="drawer = true" class="rounded-md p-1.5 text-ink-500 hover:bg-ink-100">
            <x-heroicon-o-bars-3 class="h-5 w-5" />
            <span class="sr-only">Open navigation</span>
        </button>
        <x-ui.brand wordmark />
    </div>

    <main class="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <div class="mx-auto max-w-6xl">
            @if (session('status'))
                <x-ui.alert tone="ok" class="mb-6">{{ session('status') }}</x-ui.alert>
            @endif

            {{ $slot }}
        </div>
    </main>
</div>

</body>
</html>
