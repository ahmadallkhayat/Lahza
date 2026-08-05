<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Uploads for template media. Files live on the public disk and the database
 * keeps the public URL, so views can use the value directly.
 */
final class MediaStore
{
    /** Store an upload and return its public URL, or null if none was sent. */
    public static function put(Request $request, string $field, string $directory): ?string
    {
        $file = $request->file($field);

        if (! $file instanceof UploadedFile) {
            return null;
        }

        $path = $file->store($directory, 'public');

        return $path ? Storage::url($path) : null;
    }

    /** Delete a stored file, given the public URL held in the database. */
    public static function forget(?string $url): void
    {
        if (blank($url)) {
            return;
        }

        $path = self::pathFromUrl($url);

        if ($path !== '') {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Absolute filesystem path for a stored public URL, or null when the file
     * is not there. Needed by anything that has to read the bytes — ffmpeg
     * cannot open a URL on the public disk.
     */
    public static function absolutePath(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $path = self::pathFromUrl($url);

        return $path !== '' && Storage::disk('public')->exists($path)
            ? Storage::disk('public')->path($path)
            : null;
    }

    /**
     * Strip the /storage/ prefix a public-disk URL carries, leaving the
     * relative path the disk itself understands.
     */
    private static function pathFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;

        return ltrim((string) preg_replace('#^/?storage/#', '', $path), '/');
    }
}
