<?php

namespace App\Console\Commands;

use App\Models\Invitation;
use App\Services\OgImageGenerator;
use Illuminate\Console\Command;

/**
 * Draws the link-preview card for invitations that have none.
 *
 * New invitations get one when they are saved, so this exists for the ones that
 * already existed when the feature did not — and for after `fonts:fetch`, since
 * without the render face every attempt before that quietly produced nothing.
 */
class DrawPreviewCards extends Command
{
    protected $signature = 'invitations:cards {--force : Redraw cards that already exist}';

    protected $description = 'Draw the social preview card for invitations missing one';

    public function handle(OgImageGenerator $cards): int
    {
        $query = Invitation::with('template');

        if (! $this->option('force')) {
            $query->whereNull('og_image');
        }

        $invitations = $query->get();

        if ($invitations->isEmpty()) {
            $this->info('Nothing to draw.');

            return self::SUCCESS;
        }

        $drawn = 0;

        foreach ($invitations as $invitation) {
            $previous = $invitation->og_image;
            $url = $cards->generate($invitation);

            if ($url === null) {
                $this->warn(sprintf('  %-28s could not be drawn', $invitation->slug));

                continue;
            }

            $invitation->update(['og_image' => $url]);
            $cards->forget($previous);
            $drawn++;

            $this->line(sprintf('  %-28s %s', $invitation->slug, $url));
        }

        $this->newLine();

        if ($drawn < $invitations->count()) {
            $this->warn('Some cards were not drawn. Check that ffmpeg is installed and that '
                .'`php artisan fonts:fetch` has downloaded the render face.');
        }

        $this->info($drawn.' of '.$invitations->count().' drawn.');

        return self::SUCCESS;
    }
}
