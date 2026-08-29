<?php

namespace App\Modules\Partners\Models;

use App\Modules\Partners\Enums\LoanStatus;
use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Liability. Principal + optional future interest — never merged with
 * PartnerCapitalEntry.
 */
class PartnerLoan extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'partner_id',
        'principal_amount',
        'interest_rate',
        'status',
        'issued_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'principal_amount' => 'decimal:2',
            'interest_rate' => 'decimal:2',
            'status' => LoanStatus::class,
            'issued_at' => 'date',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(PartnerLoanRepayment::class);
    }
}
