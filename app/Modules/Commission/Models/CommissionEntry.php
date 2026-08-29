<?php

namespace App\Modules\Commission\Models;

use App\Modules\Commission\Enums\CommissionEntryStatus;
use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * eligible_gross_profit is the tenant's TOTAL gross profit for the
 * period (confirmed correction, see the 0012 migration's docblock) —
 * not this employee's own sales. Immutable once status is Finalized;
 * only Actions in this module ever write to a row here.
 */
class CommissionEntry extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'employee_id',
        'commission_rule_id',
        'financial_period_id',
        'eligible_gross_profit',
        'rate_applied',
        'commission_amount',
        'status',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'eligible_gross_profit' => 'decimal:2',
            'rate_applied' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'status' => CommissionEntryStatus::class,
        ];
    }

    public function saleLines(): HasMany
    {
        return $this->hasMany(CommissionSaleLine::class);
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(CommissionCorrection::class, 'original_commission_entry_id');
    }
}
