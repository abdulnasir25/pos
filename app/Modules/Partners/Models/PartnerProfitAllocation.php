<?php

namespace App\Modules\Partners\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Calculated entitlement — distinct from the actual payout, see
 * PartnerDistribution. One row per partner per sub-range of constant
 * ownership within a financial period.
 */
class PartnerProfitAllocation extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'financial_period_id',
        'partner_id',
        'sub_range_start',
        'sub_range_end',
        'applied_percentage',
        'allocated_amount',
    ];

    protected function casts(): array
    {
        return [
            'sub_range_start' => 'date',
            'sub_range_end' => 'date',
            'applied_percentage' => 'decimal:2',
            'allocated_amount' => 'decimal:2',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
