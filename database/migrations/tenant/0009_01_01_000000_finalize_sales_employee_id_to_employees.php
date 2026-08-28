<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Completes the migration 0008 left deliberately partial. SQLite
     * cannot drop a column that's part of its own inline FOREIGN KEY
     * clause (confirmed by direct test — not gated by the foreign_keys
     * pragma), so the old sales_employee_id (→ users) can't simply be
     * dropped in place. This performs the full legacy table-rebuild
     * instead: a replacement `sales` table with the correct final
     * shape, every row copied across, the original dropped, the
     * replacement renamed into its place.
     *
     * sale_items/sale_payments/sale_returns need no changes at all —
     * their FKs reference `sales` by name and by `id` value, both of
     * which are preserved exactly (SQLite resolves a FK's parent table
     * by current name, and AUTOINCREMENT bookkeeping follows explicit
     * id values inserted into it, so IDs continue exactly where they
     * left off after the rename).
     */
    public $withinTransaction = false;

    public function up(): void
    {
        $this->verifyEveryLegacyAttributionMapsToAVerifiedEmployee();

        $originalCount = DB::table('sales')->count();

        Schema::create('sales_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('sales_employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->string('reference_no', 30)->unique();
            $table->string('status', 20)->default('confirmed');
            $table->decimal('subtotal', 14, 2);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->decimal('paid_total', 14, 2)->default(0);
            $table->decimal('balance_due', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('sales_employee_id');
        });

        // employee_id (the new-target column 0008 added) supplies the
        // final sales_employee_id value; the legacy sales_employee_id
        // (→ users) is intentionally not selected — it's already been
        // verified above to carry no attribution the new column doesn't
        // already have.
        DB::statement('
            INSERT INTO sales_new (
                id, customer_id, warehouse_id, cashier_id, sales_employee_id,
                reference_no, status, subtotal, discount_total, total, paid_total,
                balance_due, notes, confirmed_at, cancelled_at, created_at, updated_at
            )
            SELECT
                id, customer_id, warehouse_id, cashier_id, employee_id,
                reference_no, status, subtotal, discount_total, total, paid_total,
                balance_due, notes, confirmed_at, cancelled_at, created_at, updated_at
            FROM sales
        ');

        $newCount = DB::table('sales_new')->count();

        if ($newCount !== $originalCount) {
            Schema::dropIfExists('sales_new');

            throw new RuntimeException(
                "Sales table rebuild aborted: copied {$newCount} rows but the original table had "
                ."{$originalCount}. The original `sales` table was left untouched; `sales_new` was "
                .'dropped. No data was lost.'
            );
        }

        // SQLite's native DROP COLUMN restriction (see this file's class
        // docblock) is what forces the rebuild in the first place — the
        // same restriction applies to dropping a table that other
        // tables hold live foreign keys into, unless enforcement is
        // off for this DDL sequence. Re-enabled immediately after.
        Schema::disableForeignKeyConstraints();

        Schema::drop('sales');
        Schema::rename('sales_new', 'sales');

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Refuses to proceed if any historical sale's legacy
     * sales_employee_id (→ users) doesn't map to a verified Employee —
     * i.e. an Employee whose user_id is exactly that same user. This is
     * the last point before the legacy column's data becomes
     * unrecoverable; if this can't confirm nothing is about to be lost,
     * it stops here rather than guessing.
     */
    private function verifyEveryLegacyAttributionMapsToAVerifiedEmployee(): void
    {
        $rows = DB::table('sales')
            ->whereNotNull('sales_employee_id')
            ->get(['id', 'sales_employee_id', 'employee_id']);

        $problems = [];

        foreach ($rows as $row) {
            if ($row->employee_id === null) {
                $problems[] = "sale #{$row->id}: legacy sales_employee_id={$row->sales_employee_id} "
                    .'has no employee_id backfilled at all';

                continue;
            }

            $employee = DB::table('employees')->find($row->employee_id);

            if ($employee === null) {
                $problems[] = "sale #{$row->id}: employee_id={$row->employee_id} does not exist";

                continue;
            }

            if ((int) $employee->user_id !== (int) $row->sales_employee_id) {
                $problems[] = "sale #{$row->id}: employee_id={$row->employee_id} maps to user_id="
                    .var_export($employee->user_id, true)." — does not match the original "
                    ."sales_employee_id={$row->sales_employee_id}";
            }
        }

        if ($problems !== []) {
            throw new RuntimeException(
                "Cannot safely finalize sales_employee_id migration — every historical attribution "
                ."must verifiably map before the legacy column is dropped. No schema change was made.\n"
                .implode("\n", $problems)
            );
        }
    }

    /**
     * BEST-EFFORT ONLY — read this before relying on it. This rebuilds
     * the pre-0009 shape (legacy sales_employee_id → users, plus
     * employee_id → employees) and derives the legacy value from each
     * employee's *current* user_id. That derivation is only correct if
     * no employee's user_id has changed (via LinkEmployeeToUser /
     * UnlinkEmployeeFromUser) since up() ran — a rollback executed any
     * meaningful time after go-live, once employees' login accounts
     * have actually changed, WILL silently reconstruct incorrect
     * historical attribution. This is not a true, safe rollback; it
     * exists so `migrate:rollback` doesn't hard-fail, not as a
     * guarantee. Prefer a fresh forward migration over rolling this one
     * back in any environment with real data.
     */
    public function down(): void
    {
        $originalCount = DB::table('sales')->count();

        Schema::create('sales_old', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('sales_employee_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->string('reference_no', 30)->unique();
            $table->string('status', 20)->default('confirmed');
            $table->decimal('subtotal', 14, 2);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->decimal('paid_total', 14, 2)->default(0);
            $table->decimal('balance_due', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('sales_employee_id');
        });

        DB::statement('
            INSERT INTO sales_old (
                id, customer_id, warehouse_id, cashier_id, sales_employee_id, employee_id,
                reference_no, status, subtotal, discount_total, total, paid_total,
                balance_due, notes, confirmed_at, cancelled_at, created_at, updated_at
            )
            SELECT
                s.id, s.customer_id, s.warehouse_id, s.cashier_id, e.user_id, s.sales_employee_id,
                s.reference_no, s.status, s.subtotal, s.discount_total, s.total, s.paid_total,
                s.balance_due, s.notes, s.confirmed_at, s.cancelled_at, s.created_at, s.updated_at
            FROM sales s
            LEFT JOIN employees e ON e.id = s.sales_employee_id
        ');

        if (DB::table('sales_old')->count() !== $originalCount) {
            Schema::dropIfExists('sales_old');

            throw new RuntimeException('Rollback aborted: row count mismatch during sales_old rebuild.');
        }

        Schema::disableForeignKeyConstraints();
        Schema::drop('sales');
        Schema::rename('sales_old', 'sales');
        Schema::enableForeignKeyConstraints();
    }
};
