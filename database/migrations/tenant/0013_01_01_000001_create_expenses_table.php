<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A true Operating Expense (rent, utilities, ...) — reduces Net
     * Profit, which is what partners split. No per-partner attribution:
     * expenses simply reduce the shared profit pool before it's
     * divided, which is what "expenses are split between the partners"
     * means in practice. Immutable once created — a correction is a new
     * row (signed amount), optionally referencing the one it corrects
     * via reference_type/reference_id, never an edit in place.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('expense_date');
            $table->string('description', 255)->nullable();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('expense_category_id');
            $table->index('expense_date');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
