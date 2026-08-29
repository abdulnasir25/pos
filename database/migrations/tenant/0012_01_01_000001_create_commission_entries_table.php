<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per employee per rule per Financial Period — the
     * finalized, (mostly) immutable commission result.
     *
     * CORRECTED basis (2026-08-29, confirmed by the business owner):
     * eligible_gross_profit is the tenant's TOTAL gross profit for the
     * period — every confirmed sale, not just this employee's own —
     * with the employee's rate applied against that whole figure. The
     * originally drafted design (FINANCIAL-SCHEMA-DESIGN.md §D) said
     * "this employee's sales' gross profit", which was wrong: the
     * confirmed rule is 10% of the WHOLE shop's profit to the
     * commission-earning employee, not 10% of what they personally
     * sold. The column name/shape is unchanged — only what it's
     * populated with differs from that document, which still needs a
     * correction pass.
     */
    public function up(): void
    {
        Schema::create('commission_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('commission_rule_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_period_id')->constrained()->restrictOnDelete();
            $table->decimal('eligible_gross_profit', 14, 2);
            $table->decimal('rate_applied', 5, 2);
            $table->decimal('commission_amount', 14, 2);
            $table->string('status', 20);
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // Named explicitly: Laravel's auto-generated name for this
            // composite (table + all 3 column names) exceeds MySQL's
            // 64-character identifier limit — fine on SQLite, a hard
            // error on MySQL.
            $table->unique(['employee_id', 'commission_rule_id', 'financial_period_id'], 'commission_entries_unique');
            $table->index('financial_period_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_entries');
    }
};
