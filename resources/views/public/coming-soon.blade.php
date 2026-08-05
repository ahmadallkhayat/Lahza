<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lahza</title>
    <meta name="description" content="Digital invitations. Coming soon.">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css'])
</head>
<body class="flex h-full items-center justify-center bg-white px-6">

    <main class="text-center">
        {{-- The mark is the hero here, so it gets the most room of anywhere. --}}
        <div class="mb-9 flex justify-center">
            <x-ui.brand size="h-24" />
        </div>

        <h1 class="text-2xl font-semibold tracking-tight text-ink-900 sm:text-3xl">
            Coming soon
        </h1>

        <p class="mx-auto mt-3 max-w-sm text-sm leading-relaxed text-ink-500">
            Digital invitations, built one moment at a time.
        </p>

        <div class="mx-auto mt-8 h-px w-16 bg-ink-200"></div>
    </main>

</body>
</html>
