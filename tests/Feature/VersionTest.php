<?php

namespace Tests\Feature;

use Tests\TestCase;

class VersionTest extends TestCase
{
    private string $versionFile;

    private string $changelogFile;

    /**
     * The command edits files in place, so it is pointed at temporary copies —
     * a bug here must not rewrite the project's real VERSION or CHANGELOG.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->versionFile = tempnam(sys_get_temp_dir(), 'ver');
        $this->changelogFile = tempnam(sys_get_temp_dir(), 'log');

        config([
            'version.file' => $this->versionFile,
            'version.changelog' => $this->changelogFile,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->versionFile);
        @unlink($this->changelogFile);

        parent::tearDown();
    }

    private function given(string $version, string $changelog): void
    {
        file_put_contents($this->versionFile, $version."\n");
        file_put_contents($this->changelogFile, $changelog);
    }

    private function version(): string
    {
        return trim((string) file_get_contents($this->versionFile));
    }

    private function changelog(): string
    {
        return (string) file_get_contents($this->changelogFile);
    }

    private const CHANGELOG = <<<'MD'
        # Changelog

        ## [Unreleased]

        ### Added
        - Account settings page.

        ## [0.1.0] - 2026-08-06

        ### Added
        - First release.

        MD;

    public function test_the_shipped_version_file_holds_a_semantic_version(): void
    {
        $this->assertMatchesRegularExpression(
            '/^\d+\.\d+\.\d+$/',
            trim((string) file_get_contents(base_path('VERSION')))
        );
    }

    public function test_config_exposes_the_version(): void
    {
        $this->assertSame(
            trim((string) file_get_contents(base_path('VERSION'))),
            config('version.number')
        );
    }

    /** No REVISION file locally, so nothing should be claimed about a commit. */
    public function test_commit_is_null_outside_a_deployed_release(): void
    {
        $this->assertNull(config('version.commit'));
    }

    public function test_patch_bump(): void
    {
        $this->given('0.1.0', self::CHANGELOG);

        $this->artisan('version:bump patch')->assertSuccessful();

        $this->assertSame('0.1.1', $this->version());
    }

    public function test_minor_bump_resets_the_patch(): void
    {
        $this->given('0.1.7', self::CHANGELOG);

        $this->artisan('version:bump minor')->assertSuccessful();

        $this->assertSame('0.2.0', $this->version());
    }

    public function test_major_bump_resets_minor_and_patch(): void
    {
        $this->given('0.4.7', self::CHANGELOG);

        $this->artisan('version:bump major')->assertSuccessful();

        $this->assertSame('1.0.0', $this->version());
    }

    public function test_unreleased_notes_move_into_the_new_dated_section(): void
    {
        $this->given('0.1.0', self::CHANGELOG);

        $this->artisan('version:bump minor')->assertSuccessful();

        $changelog = $this->changelog();
        $today = now()->format('Y-m-d');

        $this->assertStringContainsString("## [0.2.0] - {$today}", $changelog);

        // The note moved down into the released section...
        $released = substr($changelog, strpos($changelog, '## [0.2.0]'));
        $this->assertStringContainsString('Account settings page.', $released);

        // ...and Unreleased is left empty for the next change.
        $unreleased = substr(
            $changelog,
            strpos($changelog, '## [Unreleased]'),
            strpos($changelog, '## [0.2.0]') - strpos($changelog, '## [Unreleased]')
        );
        $this->assertStringNotContainsString('Account settings page.', $unreleased);
    }

    /**
     * Regression: the rolled notes once ran straight into the previous
     * version's heading, which stops markdown treating it as a heading at all.
     * Every "## [" must have a blank line above it.
     */
    public function test_every_heading_keeps_a_blank_line_above_it(): void
    {
        $this->given('0.1.0', self::CHANGELOG);

        $this->artisan('version:bump minor')->assertSuccessful();

        $lines = explode("\n", $this->changelog());

        foreach ($lines as $i => $line) {
            if ($i === 0 || ! str_starts_with($line, '## [')) {
                continue;
            }

            $this->assertSame(
                '',
                trim($lines[$i - 1]),
                "Heading \"{$line}\" has no blank line above it."
            );
        }
    }

    public function test_the_file_ends_with_exactly_one_newline(): void
    {
        $this->given('0.1.0', self::CHANGELOG);

        $this->artisan('version:bump patch')->assertSuccessful();

        $changelog = $this->changelog();

        $this->assertStringEndsWith("\n", $changelog);
        $this->assertStringEndsNotWith("\n\n", $changelog);
    }

    public function test_earlier_releases_are_left_intact(): void
    {
        $this->given('0.1.0', self::CHANGELOG);

        $this->artisan('version:bump patch')->assertSuccessful();

        $this->assertStringContainsString('## [0.1.0] - 2026-08-06', $this->changelog());
        $this->assertStringContainsString('- First release.', $this->changelog());
    }

    public function test_an_empty_unreleased_section_warns_but_still_bumps(): void
    {
        $this->given('0.1.0', "# Changelog\n\n## [Unreleased]\n\n## [0.1.0] - 2026-08-06\n");

        $this->artisan('version:bump patch')
            ->expectsOutputToContain('Nothing listed under Unreleased')
            ->assertSuccessful();

        $this->assertSame('0.1.1', $this->version());
    }

    public function test_an_unknown_level_is_rejected(): void
    {
        $this->given('0.1.0', self::CHANGELOG);

        $this->artisan('version:bump sideways')->assertFailed();

        $this->assertSame('0.1.0', $this->version(), 'VERSION must be untouched after a rejected bump.');
    }

    public function test_a_malformed_version_file_is_rejected(): void
    {
        $this->given('not-a-version', self::CHANGELOG);

        $this->artisan('version:bump patch')->assertFailed();

        $this->assertSame('not-a-version', $this->version());
    }

    /**
     * The version is written only after the changelog rewrite succeeds, so a
     * broken changelog cannot leave the two disagreeing.
     */
    public function test_a_changelog_without_an_unreleased_heading_aborts_the_bump(): void
    {
        $this->given('0.1.0', "# Changelog\n\n## [0.1.0] - 2026-08-06\n");

        $this->artisan('version:bump patch')->assertFailed();

        $this->assertSame('0.1.0', $this->version(), 'VERSION must not move when the changelog cannot be rolled.');
    }
}
