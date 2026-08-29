<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\PlanStatus;
use App\Modules\Billing\Exceptions\DuplicatePlanSlugException;
use App\Modules\Billing\Models\Plan;

class CreatePlan
{
    public function handle(string $name, string $slug, string $price, string $billingInterval): Plan
    {
        if (Plan::where('slug', $slug)->exists()) {
            throw DuplicatePlanSlugException::forSlug($slug);
        }

        return Plan::create([
            'name' => $name,
            'slug' => $slug,
            'price' => $price,
            'billing_interval' => $billingInterval,
            'status' => PlanStatus::Active,
        ]);
    }
}
