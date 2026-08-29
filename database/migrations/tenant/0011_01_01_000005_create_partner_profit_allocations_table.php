<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Calculated entitlement — distinct from the actual payout
     * (partner_distributions). Multiple rows per partner per period if
     * ownership changed mid-period, one per sub-range of constant
     * ownership.
     */
    public function up(): void
    {
        Schema::create('partner_profit_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->date('sub_range_start');
            $table->date('sub_range_end');
            $table->decimal('applied_percentage', 5, 2);
            $table->decimal('allocated_amount', 14, 2);
            $table->timestamps();

            $table->unique(['financial_period_id', 'partner_id', 'sub_range_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_profit_allocations');
    }
};
