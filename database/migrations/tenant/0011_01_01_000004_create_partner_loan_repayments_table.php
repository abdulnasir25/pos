<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reduces the liability created by partner_loans — structurally
     * distinct from a capital withdrawal.
     */
    public function up(): void
    {
        Schema::create('partner_loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_loan_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('repaid_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('partner_loan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_loan_repayments');
    }
};
