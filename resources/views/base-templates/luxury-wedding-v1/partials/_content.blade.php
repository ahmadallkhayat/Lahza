<main id="content">

    @if ($invitation->show_verse && $verse !== '')
        <section class="verse pane">
            <p class="verse-ar" lang="ar" dir="rtl">{{ $verse }}</p>
            @if ($verseTranslation !== '')
                <p class="verse-en">{{ $verseTranslation }}</p>
            @endif
            @if ($verseRef !== '')
                <cite>{{ $verseRef }}</cite>
            @endif
        </section>
    @endif

    {{-- Released first, while the clip is still running, and lifted to the
         middle of it. The line that introduces the couple travels with them —
         it is part of the same sentence, not a separate announcement. --}}
    <section class="couple-section">
        @if ($hosts = $invitation->text('hosts'))
            <p class="hosts">{{ $hosts }}</p>
            <div class="rule"><i></i></div>
        @endif

        <h1 class="couple" lang="ar" dir="rtl">
            <span class="name">{{ $invitation->groom_ar }}@if ($invitation->showsLatinNames())<em class="latin">{{ $invitation->groom_latin }}</em>@endif</span>
            <span class="amp">&amp;</span>
            <span class="name">{{ $invitation->bride_ar }}@if ($invitation->showsLatinNames())<em class="latin">{{ $invitation->bride_latin }}</em>@endif</span>
        </h1>
        <div class="rule"><i></i></div>
    </section>

    {{-- When, as one pane. The date, the reading of it, the time and the count
         down to it are one fact told four ways — split across two panes they
         read as two unrelated boxes, and each was too thin to be a card. --}}
    <section class="pane">
        <p class="label">{{ __('invitation.date') }}</p>
        <p class="when">{{ $invitation->formattedDate() }}</p>
        @if ($hijri = $invitation->hijriDate())
            <p class="hijri">{{ $hijri }}</p>
        @endif
        <p class="at-time">
            {{ $invitation->formattedTime() }}
            @if ($endTime = $invitation->formattedEndTime())
                <span class="until">&ndash; {{ $endTime }}</span>
            @endif
        </p>

        <div class="countdown" id="countdown">
            <div class="unit"><b data-u="d">00</b><small>{{ __('invitation.days') }}</small></div>
            <div class="unit"><b data-u="h">00</b><small>{{ __('invitation.hours') }}</small></div>
            <div class="unit"><b data-u="m">00</b><small>{{ __('invitation.minutes') }}</small></div>
            <div class="unit"><b data-u="s">00</b><small>{{ __('invitation.seconds') }}</small></div>
        </div>
        <p class="begun" id="begun" hidden>{{ __('invitation.begun') }}</p>
    </section>

    @php
        $venueName = $invitation->text('venue_name');
        $venueCity = $invitation->text('venue_city');
    @endphp

    {{-- Where and what happens there, likewise one pane: a venue is two lines
         and a programme is the evening that runs in it. --}}
    @if ($venueName || $venueCity || $programme)
        <section class="pane">
            @if ($venueName || $venueCity)
                <p class="label">{{ __('invitation.venue') }}</p>
                <p class="venue">
                    {{ $venueName }}@if ($venueName && $venueCity)<br>@endif{{ $venueCity }}
                </p>
            @endif

            @if ($programme)
                @if ($venueName || $venueCity)
                    <div class="divide"></div>
                @endif

                <p class="label">{{ __('invitation.programme') }}</p>
                <ul class="programme">
                    @foreach ($programme as $row)
                        <li>
                            <time>{{ $row['time'] ?? '' }}</time>
                            <span>{{ $row['title'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    <section class="actions">
        @if ($mapUrl = $invitation->mapUrl())
            <a class="btn solid" href="{{ $mapUrl }}" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                {{ __('invitation.map') }}
            </a>
        @endif

        <a class="btn" href="{{ $calendarUrl }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/></svg>
            {{ __('invitation.calendar') }}
        </a>

        @if ($invitation->phone)
            <a class="btn" href="tel:{{ $invitation->phone }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 3h3l2 5-2.5 1.5a12 12 0 0 0 6 6L16 13l5 2v3a2 2 0 0 1-2.2 2A17 17 0 0 1 4 5.2A2 2 0 0 1 6 3z"/></svg>
                {{ __('invitation.call') }}
            </a>
        @endif
    </section>

    <footer>{{ __('invitation.brand') }}</footer>
</main>
