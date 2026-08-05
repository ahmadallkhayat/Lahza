<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One invitation can now be issued in several languages, each with its own
 * guest link.
 *
 * That means the translatable content has to exist per language — labels alone
 * are not enough, or an English link would show English headings above an
 * Arabic venue name. So hosts, venue and programme become JSON keyed by locale.
 * Names stay single-value: they are Arabic in every language by design.
 */
return new class extends Migration
{
    /** Fields whose text differs per language. */
    private const TRANSLATABLE = ['hosts', 'venue_name', 'venue_city'];

    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->json('locales')->nullable()->after('slug');
            $table->string('default_locale', 5)->nullable()->after('locales');

            // A pasted Google Maps link, which beats a search query when the
            // venue is hard to find by name.
            $table->string('map_url', 2048)->nullable()->after('map_query');
        });

        $this->convertExistingRows();

        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn('locale');

            $table->json('locales')->nullable(false)->change();
            $table->string('default_locale', 5)->nullable(false)->change();

            // Baghdad is where these are being sent, so it is the sensible
            // default rather than Amman.
            $table->string('timezone')->default('Asia/Baghdad')->change();

            foreach (self::TRANSLATABLE as $column) {
                $table->json($column)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->string('locale', 5)->default('ar')->after('slug');
        });

        // Collapse each translated field back to its default locale's value.
        foreach (DB::table('invitations')->get() as $row) {
            $locale = $row->default_locale ?: 'ar';
            $update = ['locale' => $locale];

            foreach (self::TRANSLATABLE as $column) {
                $decoded = json_decode((string) $row->{$column}, true);
                $update[$column] = is_array($decoded)
                    ? ($decoded[$locale] ?? reset($decoded) ?: null)
                    : $row->{$column};
            }

            $programme = json_decode((string) $row->programme, true);
            $update['programme'] = isset($programme[$locale])
                ? json_encode($programme[$locale], JSON_UNESCAPED_UNICODE)
                : $row->programme;

            DB::table('invitations')->where('id', $row->id)->update($update);
        }

        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn(['locales', 'default_locale', 'map_url']);
            $table->string('timezone')->default('Asia/Amman')->change();

            foreach (self::TRANSLATABLE as $column) {
                $table->string($column)->nullable()->change();
            }
        });
    }

    /**
     * Lift each existing single-language row into the keyed shape, so nothing
     * already created is lost.
     */
    private function convertExistingRows(): void
    {
        foreach (DB::table('invitations')->get() as $row) {
            $locale = $row->locale ?: 'ar';

            $update = [
                'locales' => json_encode([$locale]),
                'default_locale' => $locale,
            ];

            foreach (self::TRANSLATABLE as $column) {
                $update[$column] = filled($row->{$column})
                    ? json_encode([$locale => $row->{$column}], JSON_UNESCAPED_UNICODE)
                    : null;
            }

            $programme = json_decode((string) $row->programme, true);
            $update['programme'] = is_array($programme) && $programme !== []
                ? json_encode([$locale => $programme], JSON_UNESCAPED_UNICODE)
                : null;

            DB::table('invitations')->where('id', $row->id)->update($update);
        }
    }
};
