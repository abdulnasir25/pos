<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The actual payout — a separate transaction type from allocation.
     * Paying out an already-allocated amount can happen even after the
     * period it was allocated from has closed.
     */
    public function up(): void
    {
        Schema::create('partner_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_period_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->timestamp('paid_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['partner_id', 'financial_period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_distributions');
    }
};
