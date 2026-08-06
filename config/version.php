<?php

/*
|--------------------------------------------------------------------------
| Version
|--------------------------------------------------------------------------
|
| `number` is the released version, kept in the VERSION file at the project
| root so one line is the single source of truth — `php artisan version:bump`
| moves it and opens a matching CHANGELOG section.
|
| `commit` is the exact revision serving the request. The deploy writes a
| REVISION file into each release before warming the config cache, so a cached
| config carries the right SHA for that release and there is no file read per
| request. Locally there is no REVISION file and this is simply null.
|
*/

$read = static function (string $file): ?string {
    $path = base_path($file);

    if (! is_file($path) || ! is_readable($path)) {
        return null;
    }

    return trim((string) file_get_contents($path)) ?: null;
};

return [

    'number' => $read('VERSION') ?? '0.0.0',

    'commit' => $read('REVISION'),

    /*
    | Paths the bump command rewrites. Configurable so tests can point at
    | fixtures instead of editing the real files.
    */

    'file' => base_path('VERSION'),

    'changelog' => base_path('CHANGELOG.md'),

];
