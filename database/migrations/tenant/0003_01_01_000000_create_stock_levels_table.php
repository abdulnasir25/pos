<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A derived cache, reconcilable at any time from inventory_movements
     * (see RecalculateStockLevel) — the ledger stays the source of truth
     * for history. This table exists for a second reason beyond read
     * performance: it's the row a stock-out's atomic conditional UPDATE
     * runs against, which is what makes stock-out safe under concurrency.
     * See the Inventory module's race-condition analysis.
     */
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_base_unit', 14, 4)->default(0);
            $table->decimal('average_cost', 14, 4)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
