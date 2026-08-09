@if ($otherLocales->isNotEmpty())
    <nav id="langs" aria-label="{{ __('invitation.language') }}">
        @foreach ($otherLocales as $otherLocale)
            @php $other = (string) $otherLocale; @endphp
            <a
                href="{{ $localeUrl($other) }}"
                hreflang="{{ $other }}"
                lang="{{ $other }}"
                data-switch
            >{{ config("templates.locales.{$other}.short", strtoupper($other)) }}</a>
        @endforeach
    </nav>
@endif
