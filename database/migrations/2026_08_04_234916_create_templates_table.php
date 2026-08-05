<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category');

            // Blade directory under resources/views/templates/. The markup and
            // animation of a design live in code; only its data lives here.
            $table->string('view');

            $table->text('description')->nullable();

            // Public URLs on the "public" disk.
            $table->string('intro_video')->nullable();
            $table->string('music')->nullable();

            // Colours the design renders with, emitted as CSS custom properties.
            $table->json('palette')->nullable();

            // Reveal timings — fade lead, crossfade length, music duck, etc.
            $table->json('settings')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['category', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
