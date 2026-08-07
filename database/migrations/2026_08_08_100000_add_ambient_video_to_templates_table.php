<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The backdrop loop: the intro clip reversed and re-encoded small, played
 * behind the invitation so the glass panels have something moving to refract.
 *
 * Derived from `intro_video` rather than uploaded, so it is nullable and
 * regenerated whenever that changes. Null simply means the design blurs the
 * intro clip in place instead — no worse than before it existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->string('ambient_video')->nullable()->after('intro_video');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn('ambient_video');
        });
    }
};
