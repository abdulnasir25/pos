<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Landlord-DB table — one bill for one subscription period.
     * paid_at is set only by RecordInvoicePayment (a manual,
     * administrative action — no payment gateway is integrated; the
     * SaaS owner marks an invoice paid after receiving payment through
     * whatever channel they actually use).
     */
    public function up(): void
    {
        Schema::connection('landlord')->create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('status', 20)->default('pending');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('invoices');
    }
};
