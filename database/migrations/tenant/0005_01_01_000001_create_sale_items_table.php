<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * unit_cost_snapshot is never computed here — it's copied verbatim
     * from the InventoryMovement that RecordSaleStockOut returns, which
     * is Inventory's own weighted-average cost at the moment of sale.
     * Sales does not own or duplicate costing logic.
     */
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 4);
            $table->decimal('unit_price', 14, 4);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('unit_cost_snapshot', 14, 4);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();

            $table->index('sale_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
