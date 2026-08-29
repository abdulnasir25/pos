<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every event that changes what a customer owes, as its own row.
     * customers.balance stays exactly as it is — a fast cache — this is
     * the itemized feed that makes it reconcilable:
     * SUM(customer_ledger_entries.amount) for a customer must always
     * equal customers.balance. Positive amount = increases what the
     * customer owes (a charge); negative = decreases it (a payment or
     * credit).
     */
    public function up(): void
    {
        Schema::create('customer_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('entry_type', 30);
            $table->decimal('amount', 14, 2);
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->date('entry_date');
            $table->timestamps();

            $table->index(['customer_id', 'entry_date']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_ledger_entries');
    }
};
