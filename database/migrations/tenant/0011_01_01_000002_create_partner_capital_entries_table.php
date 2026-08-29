<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Equity only. Never a loan — physically separate from partner_loans
     * so the two balances can never be summed together by accident.
     */
    public function up(): void
    {
        Schema::create('partner_capital_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->string('entry_type', 20);
            $table->decimal('amount', 14, 2);
            $table->date('entry_date');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['partner_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_capital_entries');
    }
};
