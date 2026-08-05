<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Added nullable first so existing rows can be backfilled before the
        // column becomes required — adding a NOT NULL unique column outright
        // would fail on any table that already has users.
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
        });

        $this->backfill();

        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }

    /**
     * Seed a username from the local part of each existing email, falling back
     * to the row id where that would collide or produce nothing usable.
     */
    private function backfill(): void
    {
        $taken = [];

        foreach (DB::table('users')->select('id', 'email')->orderBy('id')->get() as $user) {
            $base = Str::slug(Str::before((string) $user->email, '@'), '_') ?: 'user';
            $candidate = $base;

            while (in_array($candidate, $taken, true)) {
                $candidate = $base.'_'.$user->id;
            }

            $taken[] = $candidate;

            DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
        }
    }
};
