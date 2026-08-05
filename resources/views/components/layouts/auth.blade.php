<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ isset($title) ? $title.' — Lahza' : 'Lahza' }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex h-full items-center justify-center bg-ink-100 px-4 py-10">

<div class="w-full max-w-sm">
    {{-- Room to breathe here, so the mark runs larger than in the sidebar. --}}
    <div class="mb-7 flex justify-center">
        <x-ui.brand size="h-16" />
    </div>

    {{ $slot }}

    <p class="mt-6 text-center text-xs text-ink-400">Studio access only</p>
</div>

</body>
</html>
