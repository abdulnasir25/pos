<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A single-row table — the shop's own display details (name,
     * address, phone, receipt footer, currency symbol), separate from
     * the landlord's Tenant.name/slug (the billing identity). No
     * seeded row here: Settings\Support\CurrentSettings creates one
     * on first access, defaulting shop_name to the landlord's tenant
     * name.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('shop_name');
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('currency_symbol', 10)->default('Rs.');
            $table->text('receipt_footer')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
