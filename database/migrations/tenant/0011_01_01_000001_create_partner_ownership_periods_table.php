<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Effective-dated — never a mutable percentage on partners itself.
     * All active partners' percentages must sum to 100 as of any
     * effective date; enforced at the service layer (RecordOwnershipRebalance),
     * not by a DB constraint, since it's a cross-row/cross-partner rule.
     */
    public function up(): void
    {
        Schema::create('partner_ownership_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->decimal('percentage', 5, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['partner_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_ownership_periods');
    }
};
