<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separate from the tenant `users` table (which lives inside each
     * tenant's own database) — this is the platform owner's own login,
     * used only for landlord-domain routes like SaaS Billing.
     */
    public function up(): void
    {
        Schema::connection('landlord')->create('landlord_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('landlord_users');
    }
};
