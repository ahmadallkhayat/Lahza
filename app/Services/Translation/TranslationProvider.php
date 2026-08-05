<?php

namespace App\Services\Translation;

interface TranslationProvider
{
    /**
     * Whether this provider can translate into the given locale at a quality
     * worth putting in front of a customer.
     *
     * Providers must return false rather than produce something wrong: a bad
     * translation silently written into an invitation is worse than no
     * translation at all.
     */
    public function supports(string $target): bool;

    /**
     * Translate a keyed set of strings, preserving the keys.
     *
     * @param  array<string, string>  $texts
     * @return array<string, string>
     *
     * @throws TranslationFailed
     */
    public function translate(array $texts, string $source, string $target): array;

    /** Human name, for messages back to the studio. */
    public function name(): string;
}
