@props([
    'title' => null,
    'description' => null,
])

@php
    use App\Support\SiteSettings;

    $brand = SiteSettings::text('brand_name') ?: 'Lahza';
    $tagline = SiteSettings::text('tagline');
    $footerNote = SiteSettings::text('footer_note');
@endphp

<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' — '.$brand : $brand }}</title>
    <meta name="description" content="{{ $description ?: $tagline }}">

    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $title ? $title.' — '.$brand : $brand }}">
    <meta property="og:description" content="{{ $description ?: $tagline }}">
    <meta property="og:url" content="{{ url()->current() }}">

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col bg-white text-ink-800 antialiased">

<header class="sticky top-0 z-30 border-b border-ink-200/70 bg-white/85 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-5">
        <x-ui.brand :href="route('home')" wordmark />

        <nav class="flex items-center gap-2 sm:gap-3">
            @auth
                {{-- Straight to whichever side of the app is actually theirs. --}}
                <x-ui.button
                    :href="auth()->user()->isAdmin() ? route('admin.dashboard') : route('account.dashboard')"
                    size="sm"
                >
                    {{ auth()->user()->isAdmin() ? 'Studio' : 'My invitations' }}
                </x-ui.button>
            @else
                <x-ui.button :href="route('login')" variant="ghost" size="sm">Sign in</x-ui.button>
                <x-ui.button :href="route('register')" size="sm">Get started</x-ui.button>
            @endauth
        </nav>
    </div>
</header>

<main class="flex-1">
    {{ $slot }}
</main>

<footer class="border-t border-ink-200 bg-ink-50/60">
    <div class="mx-auto flex max-w-6xl flex-col gap-2 px-5 py-8 text-sm text-ink-500 sm:flex-row sm:items-center sm:justify-between">
        <p>&copy; {{ now()->year }} {{ $brand }}@if ($footerNote) · {{ $footerNote }}@endif</p>

        @auth
            <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('account.dashboard') }}" class="hover:text-ink-800">
                {{ auth()->user()->name }}
            </a>
        @else
            <a href="{{ route('login') }}" class="hover:text-ink-800">Sign in</a>
        @endauth
    </div>
</footer>

</body>
</html>
