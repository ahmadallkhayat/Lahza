<?php

namespace App\Models;

use App\Support\Numerals;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use IntlCalendar;
use IntlDateFormatter;

class Invitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'slug',
        'locale',
        'groom_ar',
        'bride_ar',
        'groom_latin',
        'bride_latin',
        'hosts',
        'starts_at',
        'timezone',
        'duration_hours',
        'venue_name',
        'venue_city',
        'map_query',
        'phone',
        'programme',
        'show_verse',
        'published',
    ];

    protected $casts = [
        'programme' => 'array',
        'show_verse' => 'boolean',
        'published' => 'boolean',
        'duration_hours' => 'integer',
    ];

    /**
     * `starts_at` is always UTC in the database.
     *
     * A plain datetime cast is not enough: Eloquent writes whatever wall clock
     * the Carbon instance happens to carry, so handing it a zoned instance
     * stored the local time and read it back as UTC — an event set for 19:00
     * in Amman came out as 22:00. Converting on the way in makes that
     * impossible regardless of what the caller passes.
     *
     * Callers should pass a timezone-aware instance, or a string that is
     * already UTC.
     */
    protected function startsAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => CarbonImmutable::parse($value, 'UTC'),
            set: fn ($value) => CarbonImmutable::parse($value)->utc()->format('Y-m-d H:i:s'),
        );
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /* ── time ─────────────────────────────────────────────────────── */

    /** Event start in its own timezone rather than the app's. */
    public function startsAtLocal(): CarbonImmutable
    {
        return $this->starts_at->setTimezone($this->timezone);
    }

    public function endsAtLocal(): CarbonImmutable
    {
        return $this->startsAtLocal()->addHours($this->duration_hours);
    }

    /** ISO 8601 with offset, for the browser countdown. */
    public function startsAtIso(): string
    {
        return $this->startsAtLocal()->toIso8601String();
    }

    /* ── presentation ─────────────────────────────────────────────── */

    public function isRtl(): bool
    {
        return (bool) config("templates.locales.{$this->locale}.rtl", false);
    }

    /** Sorani Kurdish is `ckb` to ICU, not `ku`. */
    private function icuBase(): string
    {
        return $this->locale === 'ku' ? 'ckb' : $this->locale;
    }

    private function format(string $pattern, bool $hijri = false): string
    {
        // A `calendar` locale keyword is silently ignored by IntlDateFormatter
        // — it keeps returning Gregorian. Only an explicit IntlCalendar passed
        // as the constructor's calendar argument actually switches it.
        $calendar = $hijri
            ? IntlCalendar::createInstance($this->timezone, 'ar@calendar=islamic-umalqura')
            : IntlDateFormatter::GREGORIAN;

        // ICU has no Kurdish names for the Hijri months and falls back to
        // English ("Jumada II"), which reads worse on an Arabic-script page
        // than the Arabic names do. Hijri is therefore always formatted in
        // Arabic; the Gregorian line stays in the invitation's own language.
        $formatLocale = $hijri ? 'ar' : $this->icuBase();

        $formatter = new IntlDateFormatter(
            // Latin digits, so Numerals owns the conversion in one place.
            $formatLocale.'@numbers=latn',
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            $this->timezone,
            $calendar
        );

        $formatter->setPattern($pattern);

        return Numerals::localise(
            (string) $formatter->format($this->startsAtLocal()),
            $this->locale
        );
    }

    /** e.g. "الجمعة، ٤ ديسمبر ٢٠٢٦" / "Friday, 4 December 2026" */
    public function formattedDate(): string
    {
        // Arabic and Kurdish take the Arabic comma; Latin scripts take theirs.
        return $this->format($this->isRtl() ? 'EEEE، d MMMM y' : 'EEEE, d MMMM y');
    }

    /**
     * Hijri alongside Gregorian, Arabic and Kurdish only — an English
     * invitation has no use for it.
     *
     * Umm al-Qura rather than the tabular `islamic` calendar: it is the civil
     * one actually in use, and the two differ by a day.
     */
    public function hijriDate(): ?string
    {
        return $this->isRtl()
            ? $this->format('d MMMM y G', hijri: true)
            : null;
    }

    /**
     * Time with a translated meridiem. ICU's own marker is a terse "م", so the
     * fuller wording comes from the lang files instead.
     */
    public function formattedTime(): string
    {
        $clock = Numerals::localise($this->startsAtLocal()->format('g:i'), $this->locale);
        $meridiem = __($this->startsAtLocal()->hour < 12 ? 'invitation.am' : 'invitation.pm');

        return $clock.' '.$meridiem;
    }

    /* ── content ──────────────────────────────────────────────────── */

    /**
     * Programme rows with something to show, in the order the studio set.
     *
     * @return array<int, array{time: string, title: string}>
     */
    public function programmeRows(): array
    {
        return array_values(array_filter(
            $this->programme ?? [],
            fn ($row) => filled($row['title'] ?? null)
        ));
    }

    /** Latin names only appear beneath the Arabic on English invitations. */
    public function showsLatinNames(): bool
    {
        return $this->locale === 'en'
            && (filled($this->groom_latin) || filled($this->bride_latin));
    }

    public function mapUrl(): ?string
    {
        $query = $this->map_query ?: trim($this->venue_name.' '.$this->venue_city);

        return filled($query)
            ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($query)
            : null;
    }
}
