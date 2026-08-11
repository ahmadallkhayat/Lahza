<?php

namespace App\Models;

use App\Support\ContentSlots;
use App\Support\Numerals;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use IntlCalendar;
use IntlDateFormatter;

class Invitation extends Model
{
    use HasFactory;

    /**
     * Content that differs per language. Stored as JSON keyed by locale, since
     * an invitation issued in three languages needs three venue names, not one.
     */
    public const TRANSLATABLE = ['hosts', 'venue_name', 'venue_city'];

    protected $fillable = [
        'user_id',
        'template_id',
        'slug',
        'locales',
        'default_locale',
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
        'map_url',
        'phone',
        'programme',
        'content',
        'show_verse',
        'published',
        'og_image',
    ];

    protected $casts = [
        'locales' => 'array',
        'hosts' => 'array',
        'venue_name' => 'array',
        'venue_city' => 'array',
        'programme' => 'array',
        'content' => 'array',
        'show_verse' => 'boolean',
        'published' => 'boolean',
        'duration_hours' => 'integer',
    ];

    /** Locale the page is currently being rendered in. */
    protected ?string $renderLocale = null;

    /**
     * `starts_at` is always UTC in the database.
     *
     * A plain datetime cast is not enough: Eloquent writes whatever wall clock
     * the Carbon instance carries, so handing it a zoned instance stored the
     * local time and read it back as UTC — an event set for 19:00 in Baghdad
     * came out as 22:00. Converting on the way in makes that impossible
     * regardless of what the caller passes.
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

    /**
     * The customer this belongs to, if it belongs to one.
     *
     * Null means the studio holds it — either because it was built before
     * accounts existed, or because the studio made it on someone's behalf
     * without one. That is a normal state, not a missing relation.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function belongsToUser(?User $user): bool
    {
        return $user !== null
            && $this->user_id !== null
            && $this->user_id === $user->id;
    }

    /* ── locales ──────────────────────────────────────────────────── */

    /** @return array<int, string> */
    public function localeList(): array
    {
        $locales = array_values(array_filter((array) $this->locales));

        return $locales !== [] ? $locales : [$this->default_locale ?: 'ar'];
    }

    public function hasLocale(?string $locale): bool
    {
        return $locale !== null && in_array($locale, $this->localeList(), true);
    }

    public function isMultilingual(): bool
    {
        return count($this->localeList()) > 1;
    }

    /** Fixes the locale this instance renders in for the rest of the request. */
    public function renderIn(string $locale): static
    {
        $this->renderLocale = $this->hasLocale($locale) ? $locale : $this->defaultLocale();
        $this->renderLocale ??= 'ar';

        return $this;
    }

    public function locale(): string
    {
        return $this->renderLocale ?? $this->defaultLocale();
    }

    public function defaultLocale(): string
    {
        return $this->hasLocale($this->default_locale)
            ? $this->default_locale
            : Arr::first($this->localeList()) ?? 'ar';
    }

    /* ── translated content ───────────────────────────────────────── */

    /**
     * A translated field in the current (or given) language.
     *
     * Falls back to the default locale and then to any value present, so a
     * language left half-filled still renders something rather than a gap.
     */
    public function text(string $field, ?string $locale = null): ?string
    {
        $values = (array) ($this->{$field} ?? []);

        if ($values === []) {
            return null;
        }

        $locale ??= $this->locale();

        $value = $values[$locale]
            ?? $values[$this->defaultLocale()]
            ?? Arr::first(array_filter($values, fn ($v) => filled($v)));

        return filled($value) ? $value : null;
    }

    /**
     * A slot declared by this invitation's base template.
     *
     * Empty means "use the design's own wording", not "blank" — so an
     * invitation that says nothing about its verse still shows one. That is
     * why this returns the manifest default rather than null: a slot with a
     * default is a field the design has already answered, and the studio is
     * only ever overriding it.
     *
     * Slots marked `arabic` are stored once under the default locale, like the
     * names, since the value is the same in every language.
     */
    public function slot(string $key, ?string $locale = null): string
    {
        $base = $this->template?->base_template;
        $locale ??= $this->locale();

        $values = (array) (($this->content ?? [])[$key] ?? []);

        // No borrowing across languages here, unlike text(). A slot always has
        // the design's own wording to fall back on, so a language left empty
        // has something correct to show — whereas lending it another
        // language's value puts "QURAN 30:21" under an Arabic verse.
        $value = ContentSlots::isArabicEverywhere($base, $key)
            ? Arr::first(array_filter($values, fn ($v) => filled($v)))
            : ($values[$locale] ?? null);

        return filled($value)
            ? (string) $value
            : ContentSlots::default($base, $key, $locale);
    }

    /**
     * Programme rows for the current language, blank ones dropped.
     *
     * @return array<int, array{time: string, title: string}>
     */
    public function programmeRows(?string $locale = null): array
    {
        $all = (array) ($this->programme ?? []);
        $locale ??= $this->locale();

        $rows = $all[$locale] ?? $all[$this->defaultLocale()] ?? Arr::first($all) ?? [];

        return array_values(array_filter(
            (array) $rows,
            fn ($row) => is_array($row) && filled($row['title'] ?? null)
        ));
    }

    /* ── presentation ─────────────────────────────────────────────── */

    public function isRtl(): bool
    {
        return (bool) config("templates.locales.{$this->locale()}.rtl", false);
    }

    /** Sorani Kurdish is `ckb` to ICU, not `ku`. */
    private function icuBase(): string
    {
        return $this->locale() === 'ku' ? 'ckb' : $this->locale();
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
        // than the Arabic does.
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
            $this->locale()
        );
    }

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

    /** e.g. "الجمعة، ٤ ديسمبر ٢٠٢٦" / "Friday, 4 December 2026" */
    public function formattedDate(): string
    {
        // Arabic and Kurdish take the Arabic comma; Latin scripts take theirs.
        return $this->format($this->isRtl() ? 'EEEE، d MMMM y' : 'EEEE, d MMMM y');
    }

    /**
     * Hijri alongside Gregorian, Arabic and Kurdish only.
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

    /** Time with a translated meridiem; ICU's own marker is a terse "م". */
    public function formattedTime(): string
    {
        $clock = Numerals::localise($this->startsAtLocal()->format('g:i'), $this->locale());
        $meridiem = __($this->startsAtLocal()->hour < 12 ? 'invitation.am' : 'invitation.pm');

        return $clock.' '.$meridiem;
    }

    /**
     * When it finishes, in the same shape as the start time.
     *
     * Null when the end lands on a later day. "7:00 – 1:00" reads as a
     * twelve-hour error rather than a wedding that ran past midnight, and the
     * page has nowhere to put the extra day.
     */
    public function formattedEndTime(): ?string
    {
        $start = $this->startsAtLocal();
        $end = $start->addHours($this->duration_hours ?: 0);

        if ($this->duration_hours <= 0 || ! $end->isSameDay($start)) {
            return null;
        }

        $clock = Numerals::localise($end->format('g:i'), $this->locale());

        return $clock.' '.__($end->hour < 12 ? 'invitation.am' : 'invitation.pm');
    }

    /** Latin names only appear beneath the Arabic on English invitations. */
    public function showsLatinNames(): bool
    {
        return $this->locale() === 'en'
            && (filled($this->groom_latin) || filled($this->bride_latin));
    }

    /**
     * A pasted Google Maps link wins over a search query — it points at the
     * exact place rather than whatever the name happens to match.
     */
    public function mapUrl(): ?string
    {
        if (filled($this->map_url)) {
            return $this->map_url;
        }

        $query = $this->map_query
            ?: trim($this->text('venue_name').' '.$this->text('venue_city'));

        return filled($query)
            ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($query)
            : null;
    }
}
