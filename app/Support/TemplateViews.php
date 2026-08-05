<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Discovers which template designs exist in code.
 *
 * A template record only stores which Blade directory renders it; the markup
 * and animation live under resources/views/templates/<name>/show.blade.php.
 * Validating against this list stops a record pointing at a view that isn't
 * there, which would otherwise only surface as a 500 for a guest.
 */
final class TemplateViews
{
    /** @return array<string, string> view directory => human label */
    public static function available(): array
    {
        $root = resource_path('views/templates');

        if (! File::isDirectory($root)) {
            return [];
        }

        $views = [];

        foreach (File::directories($root) as $dir) {
            $name = basename($dir);

            if (File::exists($dir.'/show.blade.php')) {
                $views[$name] = Str::headline($name);
            }
        }

        ksort($views);

        return $views;
    }

    public static function exists(?string $name): bool
    {
        return $name !== null && array_key_exists($name, self::available());
    }
}
