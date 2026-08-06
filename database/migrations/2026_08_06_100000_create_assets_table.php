<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A reusable library of the things an invitation is built from: intro clips,
 * music, and blocks of ready-written text.
 *
 * Media used to be uploaded straight onto a template, which meant the same
 * clip was re-uploaded for every theme that wanted it and there was nowhere to
 * keep a verse or a hosting line worth reusing. An asset is stored once and
 * then picked from a dropdown.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();

            // Which design's library this belongs to. Null means shared by
            // every base template, which is what a Quran verse wants to be —
            // it is not specific to one design.
            $table->string('base_template')->nullable();

            // video, music or text. Media carries `path`; text carries `body`.
            $table->string('kind');

            $table->string('name');
            $table->text('description')->nullable();

            // Public URL on the "public" disk, as elsewhere, so views can use
            // the value directly. Media only.
            $table->string('path')->nullable();

            // The text itself, placeholders and all. Text only.
            $table->text('body')->nullable();

            // Text only: the language it is written in, and the invitation
            // field it is meant for. Both nullable — a block that suits any
            // field, or any language, says so by leaving them unset.
            $table->string('locale', 5)->nullable();
            $table->string('field')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // The library is always read as "assets of this kind, for this base
            // template", so that pair is the index worth having.
            $table->index(['base_template', 'kind', 'is_active']);

            // Releasing a template's media has to know whether the file is
            // owned by an asset before deleting it. That is a lookup by path.
            $table->index('path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
