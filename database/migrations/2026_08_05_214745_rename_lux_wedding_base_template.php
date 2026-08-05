<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Lux Wedding" becomes "Luxury Wedding V1", the directory with it, so the
 * versioning is explicit before there is a V2 to distinguish it from.
 *
 * base_template has to follow the directory or the record points at a view that
 * no longer exists. Name and slug are only touched where they still hold the
 * old defaults, so a theme someone has since renamed is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('templates')
            ->where('base_template', 'lux-wedding')
            ->update(['base_template' => 'luxury-wedding-v1']);

        DB::table('templates')
            ->where('slug', 'lux-wedding')
            ->update(['slug' => 'luxury-wedding-v1']);

        DB::table('templates')
            ->where('name', 'Lux Wedding')
            ->update(['name' => 'Luxury Wedding V1']);
    }

    public function down(): void
    {
        DB::table('templates')
            ->where('base_template', 'luxury-wedding-v1')
            ->update(['base_template' => 'lux-wedding']);

        DB::table('templates')
            ->where('slug', 'luxury-wedding-v1')
            ->update(['slug' => 'lux-wedding']);

        DB::table('templates')
            ->where('name', 'Luxury Wedding V1')
            ->update(['name' => 'Lux Wedding']);
    }
};
