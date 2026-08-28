<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The one shared closing boundary future financial modules (Commission,
     * Profit Sharing, Partner Distribution) will depend on. Immutable once
     * closed — no update/delete route exists at the application layer for
     * a closed period; a correction is always a new record in whichever
     * period is currently open, never an edit here.
     */
    public function up(): void
    {
        Schema::create('financial_periods', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('open');
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['period_start', 'period_end']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_periods');
    }
};
