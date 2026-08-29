<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unifying per-partner statement — capital, loans, withdrawals,
     * distributions, all in one feed, without merging what they mean.
     * Positive amount = increases what the business owes the partner
     * (capital contribution, loan issued); negative = decreases it
     * (withdrawal, loan repayment, distribution paid out) — the same
     * accrual/payment sign convention as employee_ledger_entries.
     */
    public function up(): void
    {
        Schema::create('partner_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->string('entry_type', 30);
            $table->decimal('amount', 14, 2);
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamps();

            $table->index('partner_id');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_ledger_entries');
    }
};
