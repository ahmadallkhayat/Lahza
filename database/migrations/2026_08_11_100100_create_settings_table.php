<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the public site says about the studio.
 *
 * Key and value rather than a column per field, because most of what the
 * landing page carries is a list — the things Lahza offers, the ways to reach
 * it — and a list is not a column. The shape of each value is the Settings
 * class's business; this table only has to hold it.
 *
 * Values are JSON so a list stays a list. A single string is stored as JSON
 * too, which costs two quote characters and means nothing reading this has to
 * know which kind it is getting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
