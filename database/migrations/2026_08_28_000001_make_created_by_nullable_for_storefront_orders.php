<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Storefront orders are placed by a customer, not by a member of staff.
 *
 * `created_by` is a foreign key to `users.id`, so the e-commerce checkout had no
 * valid value to put there — it was writing the *customer's* id, which either
 * mis-attributed the order to whichever staff user happened to share that id, or
 * failed outright with a foreign key violation once customer ids grew past the
 * number of users. Allowing NULL lets the checkout say "no staff member created
 * this" honestly. Every place that reads the column already tolerates NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE sales MODIFY created_by BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE stock_ledgers MODIFY created_by BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE payments MODIFY created_by BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        // Rows created by the storefront legitimately have no user, so they are
        // parked on the first available user before the column is tightened again.
        $fallback = DB::table('users')->orderBy('id')->value('id');

        if ($fallback !== null) {
            foreach (['sales', 'stock_ledgers', 'payments'] as $table) {
                DB::table($table)->whereNull('created_by')->update(['created_by' => $fallback]);
            }
        }

        DB::statement('ALTER TABLE sales MODIFY created_by BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE stock_ledgers MODIFY created_by BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE payments MODIFY created_by BIGINT UNSIGNED NOT NULL');
    }
};
