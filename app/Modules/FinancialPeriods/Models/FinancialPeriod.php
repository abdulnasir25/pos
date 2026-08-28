<?php

namespace App\Modules\FinancialPeriods\Models;

use App\Modules\FinancialPeriods\Enums\FinancialPeriodStatus;
use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;

/**
 * The shared closing boundary future financial modules (Commission,
 * Profit Sharing, Partner Distribution — none implemented yet) will
 * depend on. Deliberately has no relationships to those modules here;
 * this class knows nothing about them, by design.
 *
 * Never call $period->update(['status' => ...]) directly — every
 * lifecycle transition goes through Actions/MoveFinancialPeriod, which
 * is the only place transition validity is enforced. This model exposes
 * no convenience method that would let a caller bypass that.
 */
class FinancialPeriod extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'period_start',
        'period_end',
        'status',
        'calculated_at',
        'reviewed_by',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'status' => FinancialPeriodStatus::class,
            'calculated_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function isClosed(): bool
    {
        return $this->status === FinancialPeriodStatus::Closed;
    }
}
