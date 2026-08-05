<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('template_id')->constrained()->cascadeOnDelete();

            $table->string('slug')->unique();

            // One invitation is authored in one language. Guest-facing labels
            // come from lang files; the content below is written in this locale.
            $table->string('locale', 5)->default('ar');

            // Names are Arabic in every locale. The Latin forms show small
            // beneath them on English invitations only.
            $table->string('groom_ar');
            $table->string('bride_ar');
            $table->string('groom_latin')->nullable();
            $table->string('bride_latin')->nullable();

            $table->text('hosts')->nullable();

            // Stored UTC; rendered and counted down in the event's own zone.
            $table->dateTime('starts_at');
            $table->string('timezone')->default('Asia/Amman');
            $table->unsignedTinyInteger('duration_hours')->default(5);

            $table->string('venue_name')->nullable();
            $table->string('venue_city')->nullable();
            $table->string('map_query')->nullable();
            $table->string('phone')->nullable();

            // Ordered [{time, title}] rows. Owned by the invitation, so no
            // table of its own.
            $table->json('programme')->nullable();

            // Not every wedding wants a religious opening.
            $table->boolean('show_verse')->default(true);

            $table->boolean('published')->default(false);

            $table->timestamps();

            $table->index(['published', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
