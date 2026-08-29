<?php

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\PlanStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lives only in the landlord database, same isolation rule as Tenant —
 * a plan is something the SaaS itself sells, never tenant business
 * data.
 */
class Plan extends Model
{
    protected $connection = 'landlord';

    protected $fillable = [
        'name',
        'slug',
        'price',
        'billing_interval',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'billing_interval' => BillingInterval::class,
            'status' => PlanStatus::class,
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
