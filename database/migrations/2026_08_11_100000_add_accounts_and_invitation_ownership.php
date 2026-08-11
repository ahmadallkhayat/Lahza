<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts stop being only the studio's.
 *
 * Until now there was one kind of user — whoever runs Lahza — and an
 * invitation belonged to nobody, because there was nobody for it to belong
 * to. Customers can now sign up and build their own, so a user needs to say
 * which of the two it is, and an invitation needs to say whose it is.
 *
 * Both columns are deliberately forgiving of what is already there:
 *
 *   role     defaults to customer, which is what a row created by an open
 *            signup should be — and then every user that exists *now* is
 *            promoted to admin below, because the only accounts that exist
 *            before this migration are the studio's own.
 *
 *   user_id  nullable, because every invitation that exists was made by the
 *            studio on someone's behalf and has no account to point at.
 *            Ownership is what scopes the customer's own list; a null owner
 *            simply means the studio holds it, which is exactly what was
 *            true before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('customer')->after('username');
            $table->string('phone')->nullable()->after('email');

            // Both listings page off this: customers by newest, and the
            // studio's own accounts filtered out of it.
            $table->index(['role', 'created_at']);
        });

        // Everyone who exists right now is the studio. A default of customer
        // is right for every row after this and wrong for every row before.
        DB::table('users')->update(['role' => 'admin']);

        Schema::table('invitations', function (Blueprint $table) {
            // nullOnDelete, not cascade: deleting a customer must not take
            // their wedding invitation down with the account. It reverts to
            // being the studio's, which is where it started.
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'created_at']);
            $table->dropColumn(['role', 'phone']);
        });
    }
};
