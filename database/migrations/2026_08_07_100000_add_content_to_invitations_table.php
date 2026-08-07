<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content for the slots a base template declares, keyed slot => locale =>
 * text — the same per-language shape the translatable columns already use.
 *
 * One JSON column rather than a column per slot, because the whole point of
 * declaring slots in a design's manifest is that adding one is an entry in a
 * file. A slot that needed a migration would put the schema back in charge of
 * what a design is allowed to say.
 *
 * Nullable throughout: an empty slot means "use the design's own wording", not
 * "blank". Existing invitations therefore need no backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->json('content')->nullable()->after('programme');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn('content');
        });
    }
};
