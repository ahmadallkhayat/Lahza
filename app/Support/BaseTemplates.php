<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Discovers the base templates that exist in code, and reads what each one
 * declares about itself.
 *
 * A base template is a design: the markup and animation of an invitation, at
 * resources/views/base-templates/<name>/show.blade.php, with an optional
 * template.php beside it declaring what it is and what it can carry. Custom
 * themes are template rows that pick one and supply their own media, colours
 * and timings.
 *
 * Adding a base template means adding a directory here — "lux-wedding-v2",
 * "graduation", "baby-shower" — and nothing else. That claim only holds
 * because everything a design needs to describe itself lives in its own
 * manifest: its label, the occasion it belongs to, the colour roles it paints
 * with, the timings it animates on, and (via ContentSlots) the content it lets
 * an invitation write.
 *
 * Before this, the last three of those were hard-coded five times over — in
 * Template's constants, in TemplateRequest's rules, in the timings card and in
 * the colours card — all of them spelling out one design's vocabulary. A
 * second design could not have declared a timing of its own without editing
 * every one of them, and a design without petals still got a petal duration.
 * That is the same failure ContentSlots was written to end for slots; this is
 * the rest of it.
 *
 * Validating records against available() stops one pointing at a design that
 * is not there, which would otherwise only surface as a 500 for a guest.
 */
final class BaseTemplates
{
    public const ROOT = 'views/base-templates';

    /** Designs that name no occasion of their own. */
    public const UNCATEGORISED = 'Other';

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $cache = null;

    /** @return array<string, string> directory name => human label */
    public static function available(): array
    {
        $found = [];

        foreach (self::manifests() as $name => $manifest) {
            $found[$name] = self::label($name);
        }

        ksort($found);

        return $found;
    }

    /**
     * The same designs, grouped by the occasion each declares.
     *
     * What the studio picks from: a list of designs is a flat list of one
     * kind of thing, and a list of designs under Wedding, Graduation and
     * Baby Shower is a catalogue.
     *
     * @return array<string, array<string, string>> category => [name => label]
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::available() as $name => $label) {
            $grouped[self::category($name)][$name] = $label;
        }

        ksort($grouped);

        // The catch-all sorts last however it is spelled, since it is where
        // things land rather than somewhere chosen.
        if (isset($grouped[self::UNCATEGORISED])) {
            $other = $grouped[self::UNCATEGORISED];
            unset($grouped[self::UNCATEGORISED]);
            $grouped[self::UNCATEGORISED] = $other;
        }

        return $grouped;
    }

    public static function exists(?string $name): bool
    {
        return $name !== null && array_key_exists($name, self::manifests());
    }

    /** Blade view name for a base template. */
    public static function view(string $name): string
    {
        return 'base-templates.'.$name.'.show';
    }

    /**
     * A design's own label, falling back to its headlined directory name.
     * Lets a design be called "Luxury Wedding V1" rather than whatever
     * headlining its folder produces.
     */
    public static function label(string $name): string
    {
        $label = self::manifest($name)['label'] ?? null;

        return is_string($label) && $label !== '' ? $label : Str::headline($name);
    }

    /** The occasion a design is for, for grouping the catalogue. */
    public static function category(?string $name): string
    {
        $category = self::manifest($name)['category'] ?? null;

        return is_string($category) && $category !== '' ? $category : self::UNCATEGORISED;
    }

    /**
     * Colour roles a design paints with: role => declaration.
     *
     * Each declaration carries a `label` (what the colours card calls it) and
     * a `default`. A design that declares none falls back to the shared
     * vocabulary below, which is also what the palette extractor speaks.
     *
     * @return array<string, array{label: string, default: string}>
     */
    public static function palette(?string $name): array
    {
        $declared = self::manifest($name)['palette'] ?? null;

        if (! is_array($declared) || $declared === []) {
            return self::FALLBACK_PALETTE;
        }

        $roles = [];

        foreach ($declared as $key => $role) {
            $roles[$key] = [
                'label' => $role['label'] ?? Str::headline($key),
                'default' => $role['default'] ?? '#000000',
            ];
        }

        return $roles;
    }

    /**
     * Timings a design animates on: key => declaration.
     *
     * Each carries a `default`, and — when the studio is allowed to change it
     * — a `label`, `hint` and bounds. A timing with no label is the design's
     * own business: it still reaches the page, but the timings card does not
     * offer it and no request can set it. That is the rule rather than a
     * separate flag, because "the studio can change it" and "the design gave
     * it a name the studio would understand" are the same statement.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function settings(?string $name): array
    {
        $declared = self::manifest($name)['settings'] ?? null;

        return is_array($declared) && $declared !== []
            ? $declared
            : self::FALLBACK_SETTINGS;
    }

    /**
     * Just the values, for merging a stored row over.
     *
     * @return array<string, string>
     */
    public static function paletteDefaults(?string $name): array
    {
        return array_map(fn ($role) => $role['default'], self::palette($name));
    }

    /**
     * @return array<string, int|float>
     */
    public static function settingDefaults(?string $name): array
    {
        return array_map(fn ($setting) => $setting['default'] ?? 0, self::settings($name));
    }

    /**
     * The timings a design lets the studio change — those it gave a label.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function editableSettings(?string $name): array
    {
        return array_filter(
            self::settings($name),
            fn ($setting) => filled($setting['label'] ?? null)
        );
    }

    /**
     * One design's parsed manifest, or an empty array.
     *
     * @return array<string, mixed>
     */
    public static function manifest(?string $name): array
    {
        return $name === null ? [] : (self::manifests()[$name] ?? []);
    }

    /**
     * Every design that exists, as name => manifest. A design with no
     * template.php is still a design; it simply declares nothing, which is
     * what lets one be added a directory at a time.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function manifests(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $found = [];
        $root = resource_path(self::ROOT);

        if (File::isDirectory($root)) {
            foreach (File::directories($root) as $dir) {
                // show.blade.php is what makes a directory a design — a
                // manifest without one is a design that cannot render.
                if (! File::exists($dir.'/show.blade.php')) {
                    continue;
                }

                $manifest = File::exists($dir.'/template.php')
                    ? require $dir.'/template.php'
                    : [];

                $found[basename($dir)] = is_array($manifest) ? $manifest : [];
            }
        }

        return self::$cache = $found;
    }

    /**
     * Forget the parsed manifests. For tests that write one at runtime.
     *
     * This is the only cache — ContentSlots reads the same manifests rather
     * than parsing its own copy, so flushing here flushes for both, and
     * ContentSlots::flush() delegates back to this.
     */
    public static function flush(): void
    {
        self::$cache = null;
    }

    /**
     * The shared colour vocabulary, used by a design that declares none of
     * its own. Also what VideoPaletteExtractor produces, which is why these
     * particular roles are the ones worth defaulting to.
     */
    private const FALLBACK_PALETTE = [
        'paper' => ['label' => 'Page background', 'default' => '#f7f1e8'],
        'paper_deep' => ['label' => 'Panel fill', 'default' => '#ede1d0'],
        'ink' => ['label' => 'Primary text', 'default' => '#2e1d16'],
        'ink_soft' => ['label' => 'Secondary text', 'default' => '#6b5648'],
        'gold' => ['label' => 'Accent', 'default' => '#a8874f'],
        'gold_deep' => ['label' => 'Accent (text safe)', 'default' => '#856a3c'],
        'curtain' => ['label' => 'Intro backdrop', 'default' => '#1a0f0d'],
    ];

    /**
     * Timings for a design that declares none. Deliberately the bare minimum
     * a reveal needs rather than any one design's full sequence — a design
     * with its own choreography declares it.
     */
    private const FALLBACK_SETTINGS = [
        'musicFadeMs' => [
            'label' => 'Music fade in',
            'hint' => 'ms for the music to reach full volume.',
            'default' => 1600,
            'min' => 0,
            'max' => 60000,
        ],
        'revealFallbackMs' => [
            'label' => 'Safety fallback',
            'hint' => 'ms after which the invitation shows even if the clip never plays.',
            'default' => 13000,
            'min' => 1000,
            'max' => 120000,
        ],
    ];
}
