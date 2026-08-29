<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Exceptions\TenantAlreadyHasActiveSubscriptionException;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Platform\Models\Tenant;
use Carbon\Carbon;

/**
 * The only supported way to start a tenant's paid subscription. A
 * tenant can hold at most one active subscription at a time — a plan
 * change is CancelSubscription followed by a new CreateSubscription,
 * never an edit to an existing row (matches the effective-dated /
 * never-rewrite-history convention used throughout this codebase).
 */
class CreateSubscription
{
    public function handle(Tenant $tenant, Plan $plan, string $startDate): Subscription
    {
        $hasActive = Subscription::where('tenant_id', $tenant->id)
            ->where('status', SubscriptionStatus::Active)
            ->exists();

        if ($hasActive) {
            throw TenantAlreadyHasActiveSubscriptionException::forTenant($tenant->id);
        }

        $start = Carbon::parse($startDate);
        $end = $plan->billing_interval === BillingInterval::Yearly
            ? $start->clone()->addYear()->subDay()
            : $start->clone()->addMonth()->subDay();

        return Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'current_period_start' => $start->toDateString(),
            'current_period_end' => $end->toDateString(),
        ]);
    }
}
