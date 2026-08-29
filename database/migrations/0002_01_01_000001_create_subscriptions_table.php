<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Landlord-DB table — links a tenant to the plan it's paying for.
     * One tenant can have historical subscriptions (a cancelled one,
     * then a new one on a different plan) — no unique constraint on
     * tenant_id, current status is what CreateSubscription/
     * CancelSubscription actually enforce.
     */
    public function up(): void
    {
        Schema::connection('landlord')->create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->date('current_period_start');
            $table->date('current_period_end');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('subscriptions');
    }
};
