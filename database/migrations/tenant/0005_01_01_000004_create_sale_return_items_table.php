<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `condition` is schema-ready for a future damaged-return path that
     * routes to Inventory's RecordDamage instead of RecordSaleReturn —
     * this phase only implements the sellable path, so it's unused
     * beyond its default for now rather than half-wired.
     */
    public function up(): void
    {
        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 4);
            $table->string('condition', 20)->default('sellable');
            $table->timestamps();

            $table->index('sale_return_id');
            $table->index('sale_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
    }
};
