<?php

use App\Models\Asset;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A religious verse is its own kind of asset, not a block of text that happens
 * to be filed under "verse".
 *
 * It carries three things that travel together and are useless apart: the
 * verse itself, its translation, and the surah and ayah it comes from. Filed
 * as plain text they were three separate library rows that had to be found and
 * inserted one at a time, with nothing recording that they belonged together.
 *
 * `body` still holds the verse — Arabic, the same in every language, as the
 * names are. The translation and the reference differ per language, so both
 * are keyed by locale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->json('translation')->nullable()->after('body');
            $table->json('reference')->nullable()->after('translation');
        });

        // Verses already in the library were text rows filed under "verse".
        // Move them to the kind that now exists rather than leaving them in a
        // category nothing offers any more.
        DB::table('assets')
            ->where('kind', Asset::KIND_TEXT)
            ->where('field', 'verse')
            ->update(['kind' => Asset::KIND_VERSE, 'field' => null]);
    }

    public function down(): void
    {
        DB::table('assets')
            ->where('kind', Asset::KIND_VERSE)
            ->update(['kind' => Asset::KIND_TEXT, 'field' => 'verse']);

        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['translation', 'reference']);
        });
    }
};
