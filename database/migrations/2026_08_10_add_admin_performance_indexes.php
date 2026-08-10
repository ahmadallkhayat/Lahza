<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            // The dashboard and index pages query each of these independently.
            // The existing composite index (published, starts_at) doesn't help
            // when filtering by only one column, so add individual indexes.
            $table->index('published');
            $table->index('starts_at');
        });

        Schema::table('templates', function (Blueprint $table) {
            // Templates index uses withCount('invitations'), and the create/edit
            // forms need to list active templates. Adding is_active alone lets
            // the existing (category, is_active) be dropped eventually if needed.
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropIndex(['published']);
            $table->dropIndex(['starts_at']);
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
        });
    }
};
