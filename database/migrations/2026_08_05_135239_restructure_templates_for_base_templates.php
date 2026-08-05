<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A base template is a design in code — resources/views/base-templates/<name>.
 * A template row is a custom theme built on one: the same markup and animation,
 * with its own media, colours and timings.
 *
 * So `view` becomes `base_template`, which says what it actually is, and
 * `category` goes — the base template is the axis things organise by now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropIndex(['category', 'is_active']);
            $table->dropColumn('category');
            $table->renameColumn('view', 'base_template');
        });

        // Separate statement: the index cannot reference a column renamed in
        // the same batch.
        Schema::table('templates', function (Blueprint $table) {
            $table->index(['base_template', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropIndex(['base_template', 'is_active']);
            $table->renameColumn('base_template', 'view');
            $table->string('category')->default('wedding')->after('slug');
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->index(['category', 'is_active']);
        });
    }
};
