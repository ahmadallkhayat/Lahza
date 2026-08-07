<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Discovers the base templates that exist in code.
 *
 * A base template is a design: the markup and animation of an invitation, at
 * resources/views/base-templates/<name>/show.blade.php. Custom themes are
 * template rows that pick one and supply their own media, colours and timings.
 *
 * Adding a base template means adding a directory here — "lux-wedding-v2",
 * "graduation", "baby-shower" — and nothing else. Validating records against
 * this list stops one pointing at a design that is not there, which would
 * otherwise only surface as a 500 for a guest.
 */
final class BaseTemplates
{
    public const ROOT = 'views/base-templates';

    /** @return array<string, string> directory name => human label */
    public static function available(): array
    {
        $root = resource_path(self::ROOT);

        if (! File::isDirectory($root)) {
            return [];
        }

        $found = [];

        foreach (File::directories($root) as $dir) {
            if (File::exists($dir.'/show.blade.php')) {
                // A design may name itself in template.php; the headlined
                // directory name is only the fallback.
                $found[basename($dir)] = ContentSlots::label(
                    basename($dir),
                    Str::headline(basename($dir))
                );
            }
        }

        ksort($found);

        return $found;
    }

    public static function exists(?string $name): bool
    {
        return $name !== null && array_key_exists($name, self::available());
    }

    /** Blade view name for a base template. */
    public static function view(string $name): string
    {
        return 'base-templates.'.$name.'.show';
    }
}
