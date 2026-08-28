<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unifying running statement per employee. This task only ever
     * writes salary_accrual / salary_payment entry types — the column
     * itself is a plain string so a later Commission module can add its
     * own entry types without a migration here.
     */
    public function up(): void
    {
        Schema::create('employee_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('entry_type', 30);
            $table->decimal('amount', 14, 2);
            $table->foreignId('financial_period_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamps();

            $table->index('employee_id');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_ledger_entries');
    }
};
