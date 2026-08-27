<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Immutable — no update/delete route exists at the application
     * layer. A correction is a new, opposite-signed movement, never an
     * edit to an existing row.
     */
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_base_unit', 14, 4);
            $table->decimal('unit_cost', 14, 4);
            $table->string('reason', 20);
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'warehouse_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
