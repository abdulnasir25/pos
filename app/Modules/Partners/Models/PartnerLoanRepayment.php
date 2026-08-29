<?php

namespace App\Modules\Partners\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerLoanRepayment extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'partner_loan_id',
        'amount',
        'repaid_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'repaid_at' => 'date',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(PartnerLoan::class, 'partner_loan_id');
    }
}
