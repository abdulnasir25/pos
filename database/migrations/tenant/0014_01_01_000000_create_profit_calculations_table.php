<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One finalized snapshot per period. distributable_profit is always
     * an explicit input, never automatically 100% of net_profit — the
     * confirmed retained-profit rule structurally encoded here.
     */
    public function up(): void
    {
        Schema::create('profit_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_period_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('revenue', 16, 2);
            $table->decimal('cogs', 16, 2);
            $table->decimal('gross_profit', 16, 2);
            $table->decimal('salary_expense', 16, 2);
            $table->decimal('commission_expense', 16, 2);
            $table->decimal('other_operating_expenses', 16, 2)->default(0);
            $table->decimal('net_profit', 16, 2);
            $table->decimal('distributable_profit', 16, 2);
            $table->decimal('retained_profit', 16, 2);
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_calculations');
    }
};
