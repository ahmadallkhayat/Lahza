<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A text asset holds one body per language rather than one body and a locale.
 *
 * A hosting line is the same sentence in Arabic, Kurdish and English, and
 * saying so used to take three library rows — three things to find, to keep in
 * step with each other, and to insert one at a time. This folds each existing
 * row's string into a map keyed by the language it was already filed under,
 * which is the shape the verse kind has used for its translation and reference
 * all along.
 *
 * The column stays `text`. A verse body is a single Arabic string that every
 * language prints as-is, so the two kinds genuinely hold different shapes and
 * casting the column to json would force the verse into a JSON scalar for no
 * benefit. Asset::bodies() is where the two are reconciled.
 *
 * `locale` is deliberately left populated. It stops being read for text assets,
 * but keeping it means down() can put the right language back rather than
 * guessing, and a half-applied migration is still recoverable.
 */
return new class extends Migration
{
    public function up(): void
    {
        $fallback = (string) array_key_first((array) config('templates.locales')) ?: 'ar';

        $this->eachTextAsset(function (object $row) use ($fallback) {
            // Already a map — the migration has run before, or the row was
            // written after it. Encoding again would nest it inside itself.
            if (is_array(json_decode((string) $row->body, true))) {
                return;
            }

            DB::table('assets')->where('id', $row->id)->update([
                'body' => json_encode(
                    [($row->locale ?: $fallback) => $row->body],
                    JSON_UNESCAPED_UNICODE
                ),
            ]);
        });
    }

    public function down(): void
    {
        $this->eachTextAsset(function (object $row) {
            $decoded = json_decode((string) $row->body, true);

            if (! is_array($decoded) || $decoded === []) {
                return;
            }

            // One language has to be the one that survives. The row's own
            // locale if it still names one, otherwise whichever was written
            // first — the rest cannot be represented in a single string.
            $body = $decoded[$row->locale] ?? reset($decoded);

            DB::table('assets')->where('id', $row->id)->update([
                'body' => is_string($body) ? $body : null,
            ]);
        });
    }

    /** Walked in batches: the library is meant to grow. */
    private function eachTextAsset(callable $handle): void
    {
        DB::table('assets')
            ->where('kind', 'text')
            ->whereNotNull('body')
            ->orderBy('id')
            ->chunkById(100, fn ($rows) => $rows->each($handle));
    }
};
