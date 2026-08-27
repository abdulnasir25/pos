<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Immutable once confirmed — no update/delete route exists at the
     * application layer. Cancellation and returns are status transitions
     * and new rows, never edits to this row's totals.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('sales_employee_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('reference_no', 30)->unique();
            $table->string('status', 20)->default('confirmed');
            $table->decimal('subtotal', 14, 2);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->decimal('paid_total', 14, 2)->default(0);
            $table->decimal('balance_due', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('sales_employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
