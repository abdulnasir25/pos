<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PARTIAL, DELIBERATELY. The originally-planned final step —
     * dropping sales_employee_id (→ users) and renaming this new column
     * into its place — was attempted and reverted. SQLite's native
     * ALTER TABLE DROP COLUMN refuses to drop a column that appears in
     * the table's own inline FOREIGN KEY clause, and this is NOT gated
     * by the `foreign_keys` pragma (confirmed by direct test: disabling
     * the pragma and re-attempting the drop fails with the identical
     * error) — it's a structural DDL check, since leaving the FK clause
     * in place would reference a column that no longer exists. The only
     * reliable way around it is a full legacy table-rebuild (create a
     * replacement `sales` table without the old FK, copy every row,
     * drop the original, rename the replacement) — real surgery on a
     * table five other tables have foreign keys into, and squarely the
     * "requires broader Sales refactoring — stop and report" case this
     * task's instructions anticipated, not a same-task extension of
     * this migration.
     *
     * What ships here is fully safe and fully backward compatible:
     *   - sales.employee_id (nullable, → employees.id) is added
     *   - it's backfilled from every existing sales_employee_id value,
     *     creating one Employee per distinct User referenced so the
     *     relationship is preserved rather than discarded
     *   - sales.sales_employee_id (→ users.id) is left completely
     *     untouched — still present, still nullable, still populated
     *     exactly as it was; no existing Sales code path changes
     *     behavior
     *
     * See this task's final report for the recommended follow-up
     * migration (the table-rebuild) as its own reviewed step.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->constrained('employees')->restrictOnDelete();
        });

        $distinctUserIds = DB::table('sales')->whereNotNull('sales_employee_id')->distinct()->pluck('sales_employee_id');

        foreach ($distinctUserIds as $userId) {
            $employeeId = DB::table('employees')->where('user_id', $userId)->value('id');

            if ($employeeId === null) {
                $user = DB::table('users')->find($userId);

                // An orphaned reference (the user row no longer exists)
                // has nothing safe to link to — leave those sales rows'
                // employee_id null rather than fabricate an identity for
                // a person we have no record of.
                if ($user === null) {
                    continue;
                }

                $employeeId = DB::table('employees')->insertGetId([
                    'user_id' => $userId,
                    'name' => $user->name,
                    'hired_at' => now()->toDateString(),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('sales')->where('sales_employee_id', $userId)->update(['employee_id' => $employeeId]);
        }
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('employee_id');
        });

        // Employee rows synthesized by up()'s backfill are intentionally
        // left in place — deleting them here would be exactly the kind
        // of destructive, easy-to-get-wrong data operation this task's
        // instructions asked to avoid. They're harmless if the Employee
        // module itself is also being rolled back.
    }
};
