<?php

namespace App\Modules\Partners\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The actual payout — a separate transaction type from
 * PartnerProfitAllocation. Can happen after the allocating period has
 * closed, since paying out an already-allocated amount doesn't change
 * the closed period's figures.
 */
class PartnerDistribution extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'partner_id',
        'financial_period_id',
        'amount',
        'payment_method_id',
        'paid_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
