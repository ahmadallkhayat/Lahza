<?php

namespace App\Support;

use App\Models\Invitation;

/**
 * The `{groom}` / `{bride}` tokens a text asset can carry.
 *
 * A hosting line or a blessing is the same sentence every time bar the names,
 * so it is written once with placeholders and filled in per invitation.
 *
 * Two rules the substitution follows deliberately:
 *
 *   - Aliases are accepted. Someone writing this by hand will type
 *     {bridename}, {bride_name} or {bride} and mean the same thing, and being
 *     strict about which one is right only produces text with a stray token in
 *     it. Matching is case-insensitive for the same reason.
 *
 *   - An unrecognised token is left exactly as written rather than replaced
 *     with nothing. A visible {grom} is a typo someone can see and fix; a
 *     silently emptied one ships to a customer's guests.
 */
final class Placeholders
{
    /**
     * Canonical token => [accepted spellings, label, example].
     *
     * The first spelling is the canonical one and is what the studio's
     * placeholder buttons insert.
     *
     * @var array<string, array{aliases: array<int, string>, label: string, sample: string}>
     */
    private const TOKENS = [
        'groom' => [
            'aliases' => ['groom', 'groomname', 'groom_name'],
            'label' => "Groom's name",
            'sample' => 'يُوسُف',
        ],
        'bride' => [
            'aliases' => ['bride', 'bridename', 'bride_name'],
            'label' => "Bride's name",
            'sample' => 'لَيْلَى',
        ],
        'venue' => [
            'aliases' => ['venue', 'venuename', 'venue_name'],
            'label' => 'Venue',
            'sample' => 'قاعة بابل',
        ],
        'city' => [
            'aliases' => ['city', 'venuecity', 'venue_city'],
            'label' => 'City',
            'sample' => 'بغداد',
        ],
        'date' => [
            'aliases' => ['date'],
            'label' => 'Date',
            'sample' => 'الجمعة، ٤ ديسمبر ٢٠٢٦',
        ],
        'time' => [
            'aliases' => ['time'],
            'label' => 'Time',
            'sample' => '٧:٠٠ مساءً',
        ],
    ];

    /**
     * Tokens for the studio's UI: canonical name, what it means, and a sample
     * so the preview has something to show before an invitation exists.
     *
     * @return array<int, array{token: string, label: string, sample: string}>
     */
    public static function all(): array
    {
        $tokens = [];

        foreach (self::TOKENS as $canonical => $meta) {
            $tokens[] = [
                'token' => $canonical,
                'label' => $meta['label'],
                'sample' => $meta['sample'],
            ];
        }

        return $tokens;
    }

    /**
     * Every accepted spelling, keyed by the canonical token.
     *
     * Handed to the browser so the studio's live substitution and this one
     * cannot disagree about what {bridename} means.
     *
     * @return array<string, array<int, string>>
     */
    public static function aliases(): array
    {
        return array_map(fn ($meta) => $meta['aliases'], self::TOKENS);
    }

    /**
     * Fill a body's placeholders from a set of canonical values.
     *
     * Values are looked up by canonical name; a token whose value is blank is
     * left in place, on the same reasoning as an unknown one — nothing is
     * quietly deleted.
     *
     * @param  array<string, string|null>  $values
     */
    public static function render(?string $body, array $values): string
    {
        if (blank($body)) {
            return '';
        }

        return (string) preg_replace_callback(
            '/\{\s*([a-z0-9_]+)\s*\}/i',
            function (array $match) use ($values) {
                $canonical = self::canonical($match[1]);

                if ($canonical === null) {
                    return $match[0];
                }

                $value = $values[$canonical] ?? null;

                return filled($value) ? (string) $value : $match[0];
            },
            $body
        );
    }

    /**
     * The values an invitation supplies, in the language being rendered.
     *
     * @return array<string, string|null>
     */
    public static function valuesFor(Invitation $invitation, ?string $locale = null): array
    {
        $locale ??= $invitation->locale();

        // Names are Arabic in every language, so the Arabic is the default.
        // English is the one place the Latin form reads better — and only when
        // it is actually filled in.
        $latin = $locale === 'en';

        return [
            'groom' => $latin && filled($invitation->groom_latin)
                ? $invitation->groom_latin
                : $invitation->groom_ar,
            'bride' => $latin && filled($invitation->bride_latin)
                ? $invitation->bride_latin
                : $invitation->bride_ar,
            'venue' => $invitation->text('venue_name', $locale),
            'city' => $invitation->text('venue_city', $locale),
            'date' => $invitation->exists ? $invitation->formattedDate() : null,
            'time' => $invitation->exists ? $invitation->formattedTime() : null,
        ];
    }

    /** Sample values, for previewing a block with no invitation to hand. */
    public static function samples(): array
    {
        return array_map(fn ($meta) => $meta['sample'], self::TOKENS);
    }

    /**
     * Tokens in this body that nothing will ever fill, so the form can say so
     * while it is being written rather than after it reaches an invitation.
     *
     * @return array<int, string>
     */
    public static function unknown(?string $body): array
    {
        if (blank($body)) {
            return [];
        }

        preg_match_all('/\{\s*([a-z0-9_]+)\s*\}/i', $body, $matches);

        $unknown = array_filter(
            $matches[1],
            fn ($token) => self::canonical($token) === null
        );

        return array_values(array_unique($unknown));
    }

    /** Resolve any accepted spelling to its canonical token, or null. */
    private static function canonical(string $token): ?string
    {
        $token = mb_strtolower($token);

        foreach (self::TOKENS as $canonical => $meta) {
            if (in_array($token, $meta['aliases'], true)) {
                return $canonical;
            }
        }

        return null;
    }
}
