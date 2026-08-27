<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per method per sale — never a single amount/method pair on
     * the sale header. A sale can be simultaneously part-paid across
     * cash, bank, and digital.
     */
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index('sale_id');
            $table->index('payment_method_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
