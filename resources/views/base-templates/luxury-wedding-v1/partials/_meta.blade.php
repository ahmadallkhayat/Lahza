{{-- The invitation is already in the language the guest chose a link for, so
     Chrome offering to translate it is a banner over the reveal and nothing
     more. Both are needed: the meta stops the offer, the attribute stops the
     translation itself if it is triggered another way. --}}
<meta name="google" content="notranslate">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{{ $invitation->groom_ar }} &amp; {{ $invitation->bride_ar }}</title>

@php
    $description = $invitation->formattedDate() . ($invitation->text('venue_name') ? ' — '.$invitation->text('venue_name') : '');
@endphp

<!-- Search Engine -->
<meta name="author" content="lahza-invites">
<meta name="description" content="{{ $description }}">

<!-- Open Graph -->
<meta property="article:author" content="lahza-invites">
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $invitation->groom_ar }} & {{ $invitation->bride_ar }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $localeUrl($locale) }}">

<!-- X/Twitter -->
<meta name="twitter:title" content="{{ $invitation->groom_ar }} & {{ $invitation->bride_ar }}">
<meta name="twitter:description" content="{{ $description }}">

@if ($invitation->og_image)
    {{-- Absolute: a scraper does not resolve relative URLs. --}}
    <meta property="og:image" content="{{ url($invitation->og_image) }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $invitation->groom_ar }} & {{ $invitation->bride_ar }}">
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ url($invitation->og_image) }}">
@else
    {{-- Fallback image when the invitation has no specific og_image set --}}
    <meta property="og:image" content="{{ asset('og-image.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $invitation->groom_ar }} & {{ $invitation->bride_ar }}">
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ asset('og-image.png') }}">
@endif

<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

@if ($selfHosted)
    @foreach ($fonts as $font)
        <link rel="stylesheet" href="{{ asset("fonts/{$font}.css") }}">
    @endforeach
@else
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family={{ collect($fonts)->map(fn ($f) => $cdnSpec[$f])->implode('&family=') }}&display=swap">
@endif
