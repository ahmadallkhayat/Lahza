<?php

namespace App\Support;

use App\Models\Invitation;

/**
 * "Add to calendar" targets. Google takes a URL; Apple, Outlook and the rest
 * take an .ics file, so both come from the same event details.
 */
final class EventCalendar
{
    public static function googleUrl(Invitation $invitation): string
    {
        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => self::title($invitation),
            // Google reads these as UTC because of the trailing Z.
            'dates' => $invitation->startsAtLocal()->utc()->format('Ymd\THis\Z')
                .'/'.$invitation->endsAtLocal()->utc()->format('Ymd\THis\Z'),
            'location' => self::location($invitation),
            'details' => self::url($invitation),
            'ctz' => $invitation->timezone,
        ]);
    }

    /**
     * RFC 5545 calendar file. Lines are CRLF-terminated as the spec requires —
     * Apple Calendar rejects the file outright with bare newlines.
     */
    public static function ics(Invitation $invitation): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Lahza//Invitation//'.strtoupper($invitation->locale()),
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$invitation->slug.'@'.(parse_url(config('app.url'), PHP_URL_HOST) ?: 'lahza'),
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$invitation->startsAtLocal()->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$invitation->endsAtLocal()->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.self::escape(self::title($invitation)),
            'LOCATION:'.self::escape(self::location($invitation)),
            'URL:'.self::url($invitation),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", $lines)."\r\n";
    }

    /** Link back in the same language, so the guest returns to what they read. */
    private static function url(Invitation $invitation): string
    {
        return $invitation->locale() === $invitation->defaultLocale()
            ? route('invitations.show', $invitation)
            : route('invitations.show.locale', [$invitation, $invitation->locale()]);
    }

    private static function title(Invitation $invitation): string
    {
        return $invitation->locale() === 'en' && filled($invitation->groom_latin)
            ? trim($invitation->groom_latin.' & '.$invitation->bride_latin)
            : trim($invitation->groom_ar.' & '.$invitation->bride_ar);
    }

    /** Venue in the language of the link this file was downloaded from. */
    private static function location(Invitation $invitation): string
    {
        return trim(implode(', ', array_filter([
            $invitation->text('venue_name'),
            $invitation->text('venue_city'),
        ])));
    }

    /** Escape what RFC 5545 reserves inside text values. */
    private static function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n'],
            $value
        );
    }
}
