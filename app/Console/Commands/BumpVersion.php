<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Raises the version and closes off the changelog's Unreleased section in one
 * step, so the two can never disagree about what shipped.
 */
class BumpVersion extends Command
{
    protected $signature = 'version:bump
        {level : major, minor or patch}
        {--tag : also create the matching annotated git tag}';

    protected $description = 'Raise the version number and date the changelog section';

    public function handle(): int
    {
        $level = strtolower((string) $this->argument('level'));

        if (! in_array($level, ['major', 'minor', 'patch'], true)) {
            $this->components->error("Level must be major, minor or patch — got \"{$level}\".");

            return self::FAILURE;
        }

        $versionFile = config('version.file');

        if (! is_file($versionFile)) {
            $this->components->error("No VERSION file at {$versionFile}.");

            return self::FAILURE;
        }

        $current = trim((string) file_get_contents($versionFile));

        if (! preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $current, $m)) {
            $this->components->error("VERSION does not hold a semantic version — got \"{$current}\".");

            return self::FAILURE;
        }

        [, $major, $minor, $patch] = array_map('intval', $m);

        [$major, $minor, $patch] = match ($level) {
            'major' => [$major + 1, 0, 0],
            'minor' => [$major, $minor + 1, 0],
            'patch' => [$major, $minor, $patch + 1],
        };

        $next = "{$major}.{$minor}.{$patch}";

        if (! $this->rollChangelog($next)) {
            return self::FAILURE;
        }

        file_put_contents($versionFile, $next."\n");

        $this->components->info("Version {$current} → {$next}");

        if ($this->option('tag') && ! $this->tag($next)) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->components->bulletList(array_filter([
            'Review CHANGELOG.md',
            'git commit -am "Release v'.$next.'"',
            $this->option('tag') ? 'git push && git push --tags' : 'git tag -a v'.$next.' -m "v'.$next.'"',
        ]));

        return self::SUCCESS;
    }

    /**
     * Move whatever sits under "## [Unreleased]" into a dated section for the
     * new version, leaving Unreleased empty and ready for the next change.
     */
    private function rollChangelog(string $next): bool
    {
        $path = config('version.changelog');

        if (! is_file($path)) {
            $this->components->error("No changelog at {$path}.");

            return false;
        }

        $changelog = (string) file_get_contents($path);
        $marker = '## [Unreleased]';

        if (! str_contains($changelog, $marker)) {
            $this->components->error("The changelog has no \"{$marker}\" heading to roll.");

            return false;
        }

        [$head, $rest] = explode($marker, $changelog, 2);

        // The Unreleased body runs until the next version heading, or to the
        // end of the file when this is the very first release.
        $nextHeading = strpos($rest, "\n## [");
        $body = trim($nextHeading === false ? $rest : substr($rest, 0, $nextHeading));
        $tail = $nextHeading === false ? '' : substr($rest, $nextHeading);

        if ($body === '') {
            $this->components->warn('Nothing listed under Unreleased — the new section will be empty.');
        }

        $parts = [
            rtrim($head),
            '',
            $marker,
            '',
            '## ['.$next.'] - '.now()->format('Y-m-d'),
        ];

        if ($body !== '') {
            $parts[] = '';
            $parts[] = $body;
        }

        $document = implode("\n", $parts);

        // The blank line matters: without it the next version's "## [x.y.z]"
        // sits directly under the last bullet and stops being a heading.
        if ($tail !== '') {
            $document .= "\n\n".ltrim($tail, "\n");
        }

        file_put_contents($path, rtrim($document)."\n");

        return true;
    }

    private function tag(string $next): bool
    {
        $result = Process::path(base_path())
            ->run(['git', 'tag', '-a', "v{$next}", '-m', "v{$next}"]);

        if (! $result->successful()) {
            $this->components->error('Could not create the tag: '.trim($result->errorOutput()));

            return false;
        }

        $this->components->info("Tagged v{$next} locally. Not pushed.");

        return true;
    }
}
