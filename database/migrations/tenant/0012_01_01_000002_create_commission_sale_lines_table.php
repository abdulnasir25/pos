<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Traceability — exactly which sales contributed to a
     * commission_entry's total, required so a later return can be
     * matched back to the specific entry it should forward-correct
     * against. Under the corrected tenant-wide basis, this is every
     * confirmed sale in the period, not one employee's own sales.
     */
    public function up(): void
    {
        Schema::create('commission_sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_entry_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->decimal('eligible_gross_profit', 14, 2);
            $table->timestamps();

            $table->index('commission_entry_id');
            $table->index('sale_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_sale_lines');
    }
};
