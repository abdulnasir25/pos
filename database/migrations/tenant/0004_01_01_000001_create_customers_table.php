<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * balance is a maintained cache (positive = customer owes the
     * tenant) — always reconcilable from sale/payment history, never
     * the sole record of it.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->decimal('balance', 14, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('phone');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
