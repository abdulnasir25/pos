<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Confirmed forward-correction mechanism. Never edits
     * commission_entries — always lands in whichever period is open
     * when the correction is created.
     */
    public function up(): void
    {
        Schema::create('commission_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('original_commission_entry_id')->constrained('commission_entries')->restrictOnDelete();
            $table->foreignId('financial_period_id')->constrained()->restrictOnDelete();
            $table->string('reason', 30);
            $table->decimal('amount', 14, 2);
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('employee_id');
            $table->index('original_commission_entry_id');
            $table->index('financial_period_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_corrections');
    }
};
