<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors sale_return_items, minus `condition`: that column exists
     * on the Sales side to route a damaged customer return to
     * Inventory's RecordDamage instead of a normal restock. A purchase
     * return always sends stock away to the supplier (RecordPurchaseReturn),
     * so there's no equivalent branch here.
     */
    public function up(): void
    {
        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 4);
            $table->timestamps();

            $table->index('purchase_return_id');
            $table->index('purchase_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
    }
};
