<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Downloads the invitation's web fonts into public/fonts so guest pages make
 * no request to an external font host.
 *
 * It localises Google's own stylesheet rather than shipping whole font files:
 * the CSS is already split into unicode-range subsets, so an Arabic invitation
 * fetches Arabic glyphs only. Grabbing the raw TTFs instead would mean ~400KB
 * per family for guests on mobile data.
 *
 * Idempotent — run it again after changing the families below.
 */
class FetchFonts extends Command
{
    protected $signature = 'fonts:fetch {--force : Re-download files that already exist}';

    protected $description = 'Download invitation web fonts into public/fonts';

    /** Family => the css2 API spec for the weights we use. */
    private const FAMILIES = [
        'Amiri' => 'wght@400;700',
        'Aref Ruqaa' => 'wght@400;700',
        'Cairo' => 'wght@300;400;600',
        'Noto Sans Arabic' => 'wght@300;400;600',
        'Cormorant Garamond' => 'ital,wght@0,300;0,400;1,400',
    ];

    /** Google serves woff2 only to user agents it believes support it. */
    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
        .'(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    public function handle(): int
    {
        $filesDir = public_path('fonts/files');
        File::ensureDirectoryExists($filesDir);

        foreach (self::FAMILIES as $family => $spec) {
            $slug = Str::slug($family);

            $url = 'https://fonts.googleapis.com/css2?family='
                .str_replace('%20', '+', rawurlencode($family)).':'.$spec.'&display=swap';

            $response = Http::withHeaders(['User-Agent' => self::UA])->get($url);

            if (! $response->successful()) {
                $this->error("  {$family}: stylesheet request failed ({$response->status()})");

                return self::FAILURE;
            }

            $css = $response->body();
            $remote = [];
            preg_match_all('#https://fonts\.gstatic\.com/[^)\'"]+#', $css, $remote);
            $remote = array_unique($remote[0]);

            if ($remote === []) {
                $this->error("  {$family}: no font files referenced in the stylesheet");

                return self::FAILURE;
            }

            $bytes = 0;

            foreach ($remote as $fontUrl) {
                // Names are content-hashed by Google, so the basename is a safe
                // cache key across families.
                $name = basename(parse_url($fontUrl, PHP_URL_PATH));
                $target = $filesDir.DIRECTORY_SEPARATOR.$name;

                if ($this->option('force') || ! File::exists($target)) {
                    $file = Http::withHeaders(['User-Agent' => self::UA])->get($fontUrl);

                    if (! $file->successful()) {
                        $this->error("  {$family}: could not download {$name}");

                        return self::FAILURE;
                    }

                    File::put($target, $file->body());
                }

                $bytes += File::size($target);
                $css = str_replace($fontUrl, '/fonts/files/'.$name, $css);
            }

            File::put(public_path("fonts/{$slug}.css"), $css);

            $this->line(sprintf(
                '  %-20s %2d subsets  %6.1f KB',
                $family,
                count($remote),
                $bytes / 1024
            ));
        }

        $this->newLine();
        $this->info('Fonts written to public/fonts. Invitations now load them locally.');

        return self::SUCCESS;
    }
}
