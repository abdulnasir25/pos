<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Actual payout events — immutable, one row per payout, never
     * edited or deleted. A correction is a future financial transaction,
     * not an edit here (out of scope for this task).
     */
    public function up(): void
    {
        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_period_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->timestamp('paid_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('employee_id');
            $table->index('financial_period_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payments');
    }
};
