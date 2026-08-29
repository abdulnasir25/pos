<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Symmetric to customer_ledger_entries. Positive amount = increases
     * what the tenant owes the supplier (a purchase charge); negative =
     * decreases it (a payment or a return credit). No standalone
     * supplier_payments table — unlike the customer side, nothing in
     * the confirmed design calls for a payment not tied to a specific
     * purchase.
     */
    public function up(): void
    {
        Schema::create('supplier_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('entry_type', 30);
            $table->decimal('amount', 14, 2);
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->date('entry_date');
            $table->timestamps();

            $table->index(['supplier_id', 'entry_date']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_ledger_entries');
    }
};
