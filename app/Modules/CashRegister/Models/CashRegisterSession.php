<?php

namespace App\Modules\CashRegister\Models;

use App\Modules\CashRegister\Enums\CashRegisterSessionStatus;
use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Never call $session->update(['status' => ...]) directly — every
 * transition goes through this module's own Actions, which is the
 * only place transition validity (and the one-open-session-per-account
 * invariant) is enforced.
 */
class CashRegisterSession extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'financial_account_id',
        'opened_by',
        'closed_by',
        'opening_float',
        'counted_closing',
        'status',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'opening_float' => 'decimal:2',
            'counted_closing' => 'decimal:2',
            'status' => CashRegisterSessionStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }
}
