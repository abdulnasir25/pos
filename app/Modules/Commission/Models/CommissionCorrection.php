<?php

namespace App\Modules\Commission\Models;

use App\Modules\Commission\Enums\CommissionCorrectionReason;
use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Forward-correction only — always lands in whichever period is open
 * when the correction is created, never edits the original (closed)
 * commission_entries row.
 */
class CommissionCorrection extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'employee_id',
        'original_commission_entry_id',
        'financial_period_id',
        'reason',
        'amount',
        'reference_type',
        'reference_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'reason' => CommissionCorrectionReason::class,
            'amount' => 'decimal:2',
        ];
    }

    public function originalEntry(): BelongsTo
    {
        return $this->belongsTo(CommissionEntry::class, 'original_commission_entry_id');
    }
}
