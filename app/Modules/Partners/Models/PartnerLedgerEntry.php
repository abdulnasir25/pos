<?php

namespace App\Modules\Partners\Models;

use App\Modules\Partners\Enums\PartnerLedgerEntryType;
use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unifying per-partner statement — capital, loans, withdrawals,
 * distributions, all in one feed. Positive amount = increases what the
 * business owes the partner; negative = decreases it. Never edited —
 * every write is a new row, produced by this module's own Actions.
 */
class PartnerLedgerEntry extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'partner_id',
        'entry_type',
        'amount',
        'reference_type',
        'reference_id',
    ];

    protected function casts(): array
    {
        return [
            'entry_type' => PartnerLedgerEntryType::class,
            'amount' => 'decimal:2',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
