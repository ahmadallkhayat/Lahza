<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The card a shared link expands into: the couple's names, drawn once and
 * stored, rather than rendered per request.
 *
 * Nullable because drawing it needs ffmpeg and a downloaded font face, and an
 * install without either should still serve invitations — it just serves them
 * with no og:image, which is where the page started.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->string('og_image')->nullable()->after('published');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn('og_image');
        });
    }
};
