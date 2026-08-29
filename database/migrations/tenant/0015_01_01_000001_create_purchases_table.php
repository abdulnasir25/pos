<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * employee_id supports the confirmed requirement that an employee
     * may buy fabric from the external market — distinct from
     * created_by, which is the system actor recording the purchase.
     * Mirrors the Sale header's shape (see 0005_..._create_sales_table)
     * in the opposite direction — no separate draft-then-confirm flow,
     * same as Sales.
     */
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reference_no', 30)->unique();
            $table->string('status', 20)->default('confirmed');
            $table->decimal('subtotal', 14, 2);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->decimal('paid_total', 14, 2)->default(0);
            $table->decimal('balance_payable', 14, 2)->default(0);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('supplier_id');
            $table->index('status');
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
