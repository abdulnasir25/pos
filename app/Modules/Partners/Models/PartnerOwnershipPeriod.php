<?php

namespace App\Modules\Partners\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Effective-dated ownership percentage. Never a mutable percentage on
 * Partner itself — a change is a new row, closing out the previous
 * one's effective_to. See Actions/RecordOwnershipRebalance, the only
 * supported way to write these.
 */
class PartnerOwnershipPeriod extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'partner_id',
        'percentage',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
