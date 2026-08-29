<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Landlord-DB table — a subscription tier the SaaS itself sells to
     * tenants. Never touched by tenant-scoped code, same isolation
     * rule as `tenants`.
     */
    public function up(): void
    {
        Schema::connection('landlord')->create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 50)->unique();
            $table->decimal('price', 10, 2);
            $table->string('billing_interval', 20);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('plans');
    }
};
