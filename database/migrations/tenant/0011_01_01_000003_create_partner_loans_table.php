<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Liability. Principal + optional future interest — never merged
     * with partner_capital_entries.
     */
    public function up(): void
    {
        Schema::create('partner_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->decimal('principal_amount', 14, 2);
            $table->decimal('interest_rate', 5, 2)->nullable();
            $table->string('status', 20)->default('outstanding');
            $table->date('issued_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['partner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_loans');
    }
};
