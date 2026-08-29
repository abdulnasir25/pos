<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A real, reconcilable balance — a cash till, a specific bank
     * account, a wallet. Distinct from payment_methods (still just a
     * label for how money moved) — multiple payment methods (e.g. "Bank
     * Transfer" and "Card") can eventually post to the same underlying
     * Financial Account, once payment_methods gains an optional link to
     * one (not built this pass — purely additive, no existing table
     * needs to change for Cash Register to work).
     */
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('account_type', 20);
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('account_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_accounts');
    }
};
