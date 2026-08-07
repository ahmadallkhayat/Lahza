<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * The content a base template declares it can carry.
 *
 * Read from each design's template.php manifest. One declaration drives the
 * fields on the invitation form, the categories a text asset can be filed
 * under, and what the guest page renders — so a slot cannot exist in one of
 * those and be missing from another.
 *
 * That was the failure this replaces: Asset::TEXT_FIELDS offered "verse" and
 * "programme step" as places to file a block of text, while the invitation
 * form rendered neither. The library let you write a verse and gave it nowhere
 * to go. Deriving the list from what designs actually declare makes that
 * particular mistake unrepresentable.
 *
 * A design with no manifest simply has no slots, which is why adding one to an
 * existing installation breaks nothing.
 */
final class ContentSlots
{
    /**
     * The core fields every design shares. Real columns on the invitation, not
     * slots — but a text asset can be filed against them, so they belong in
     * the same list when the library asks what a block of text is for.
     *
     * @var array<string, string>
     */
    public const CORE = [
        'hosts' => 'Hosting line',
        'venue_name' => 'Venue',
        'venue_city' => 'City',
    ];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $cache = null;

    /**
     * Slots declared by one design, in declaration order.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function for(?string $baseTemplate): array
    {
        if ($baseTemplate === null) {
            return [];
        }

        return self::manifests()[$baseTemplate]['slots'] ?? [];
    }

    /**
     * A design's own label, falling back to the one derived from its directory
     * name. Lets a design be called "Luxury Wedding V1" rather than whatever
     * headlining its folder produces.
     */
    public static function label(string $baseTemplate, string $fallback): string
    {
        $label = self::manifests()[$baseTemplate]['label'] ?? null;

        return is_string($label) && $label !== '' ? $label : $fallback;
    }

    /**
     * Every slot any design declares, plus the core fields — the full set of
     * things a text asset can be written for.
     *
     * Keys can collide across designs, which is intended: two designs both
     * declaring "verse" means one library of verses serves both.
     *
     * @return array<string, string> key => label
     */
    public static function all(): array
    {
        $fields = self::CORE;

        foreach (self::manifests() as $manifest) {
            foreach ($manifest['slots'] ?? [] as $key => $slot) {
                $fields[$key] ??= $slot['label'] ?? $key;
            }
        }

        return $fields;
    }

    public static function has(?string $baseTemplate, string $key): bool
    {
        return array_key_exists($key, self::for($baseTemplate));
    }

    /** Whether a slot holds one Arabic value rather than one per language. */
    public static function isArabicEverywhere(?string $baseTemplate, string $key): bool
    {
        return (bool) (self::for($baseTemplate)[$key]['arabic'] ?? false);
    }

    /**
     * What a slot falls back to when the invitation leaves it empty: the
     * design's own wording, translated for the locale being rendered.
     */
    public static function default(?string $baseTemplate, string $key, ?string $locale = null): string
    {
        $slot = self::for($baseTemplate)[$key] ?? null;
        $key = $slot['default'] ?? null;

        if (! is_string($key) || $key === '') {
            return '';
        }

        return trim((string) __($key, [], $locale));
    }

    /** Forget the parsed manifests. For tests that write one at runtime. */
    public static function flush(): void
    {
        self::$cache = null;
    }

    /**
     * Every design's manifest, parsed once.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function manifests(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $found = [];
        $root = resource_path(BaseTemplates::ROOT);

        // Scanned here rather than asked of BaseTemplates::available(), which
        // reads label() off this class to let a design name itself — going the
        // other way too would be an infinite loop.
        if (File::isDirectory($root)) {
            foreach (File::directories($root) as $dir) {
                $path = $dir.'/template.php';

                if (! File::exists($path)) {
                    continue;
                }

                $manifest = require $path;

                if (is_array($manifest)) {
                    $found[basename($dir)] = $manifest;
                }
            }
        }

        return self::$cache = $found;
    }
}
