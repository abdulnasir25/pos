<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per posting event. reference_type/reference_id point back
     * at whichever document caused it (a Sale, a SalaryPayment, a
     * manual adjustment) — polymorphic, matching the reference-field
     * pattern already used throughout the ledger tables built earlier
     * (employee_ledger_entries, partner_ledger_entries, and others).
     * Immutable once created — no update/delete route exists anywhere
     * in this module; a correction is a new, reversing entry.
     */
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date');
            $table->string('description', 255)->nullable();
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('entry_date');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
