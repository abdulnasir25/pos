<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Minimal catalog row — enough for Inventory to have something to
     * point at. No category_id (Categories out of scope this phase), no
     * pricing. sku reserved, unused — barcode-ready without a future
     * migration.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('base_unit_id')->constrained('units')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('sku', 64)->nullable()->unique();
            $table->decimal('low_stock_threshold', 14, 4)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
