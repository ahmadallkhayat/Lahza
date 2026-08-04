<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ isset($title) ? $title.' — Lahza' : 'Lahza' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex h-full items-center justify-center bg-ink-100 px-4 py-10">

<div class="w-full max-w-sm">
    <div class="mb-6 flex justify-center">
        <x-ui.brand />
    </div>

    {{ $slot }}

    <p class="mt-6 text-center text-xs text-ink-400">Studio access only</p>
</div>

</body>
</html>
