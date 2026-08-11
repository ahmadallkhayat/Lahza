@props(['title' => null])

@php
    use App\Support\SiteSettings;

    $brand = SiteSettings::text('brand_name') ?: 'Lahza';

    $tabs = [
        ['label' => 'My invitations', 'route' => 'account.dashboard', 'active' => 'account.dashboard'],
        ['label' => 'All', 'route' => 'account.invitations.index', 'active' => 'account.invitations.*'],
        ['label' => 'Profile', 'route' => 'account.profile.edit', 'active' => 'account.profile.*'],
    ];
@endphp

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    {{-- Read by the form components' fetch calls. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' — '.$brand : $brand }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col bg-ink-50/40">

<header class="border-b border-ink-200 bg-white">
    <div class="mx-auto flex h-16 max-w-5xl items-center justify-between gap-4 px-5">
        <x-ui.brand :href="route('home')" wordmark />

        <div class="flex items-center gap-3">
            <span class="hidden text-sm text-ink-500 sm:inline">{{ auth()->user()->name }}</span>

            @if (auth()->user()->isAdmin())
                <x-ui.button :href="route('admin.dashboard')" variant="ghost" size="sm">Studio</x-ui.button>
            @endif

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-ui.button type="submit" variant="ghost" size="sm">Sign out</x-ui.button>
            </form>
        </div>
    </div>

    <div class="mx-auto max-w-5xl px-5">
        <nav class="-mb-px flex gap-6 overflow-x-auto">
            @foreach ($tabs as $tab)
                @php($on = request()->routeIs($tab['active']))
                <a
                    href="{{ route($tab['route']) }}"
                    @class([
                        'whitespace-nowrap border-b-2 px-1 pb-3 pt-1 text-sm transition-colors',
                        'border-brand-600 font-medium text-brand-700' => $on,
                        'border-transparent text-ink-500 hover:border-ink-300 hover:text-ink-800' => ! $on,
                    ])
                >{{ $tab['label'] }}</a>
            @endforeach
        </nav>
    </div>
</header>

<main class="mx-auto w-full max-w-5xl flex-1 px-5 py-8">
    @if (session('status'))
        <x-ui.alert tone="ok" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    @if (session('error'))
        <x-ui.alert tone="bad" class="mb-6">{{ session('error') }}</x-ui.alert>
    @endif

    {{ $slot }}
</main>

</body>
</html>
