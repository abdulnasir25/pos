<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Subscription;

/**
 * One invoice for a subscription's current period, billed upfront —
 * due_date is the period's own start date, not its end: the tenant
 * pays for access before using it, the standard SaaS pattern.
 */
class GenerateInvoiceForSubscription
{
    public function handle(Subscription $subscription): Invoice
    {
        return Invoice::create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'amount' => $subscription->plan->price,
            'status' => InvoiceStatus::Pending,
            'period_start' => $subscription->current_period_start,
            'period_end' => $subscription->current_period_end,
            'due_date' => $subscription->current_period_start,
        ]);
    }
}
