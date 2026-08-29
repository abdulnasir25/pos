<?php

namespace App\Modules\Partners\Models;

use App\Modules\Partners\Enums\CapitalEntryType;
use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Equity only. Never a loan — see PartnerLoan for the physically
 * separate liability table.
 */
class PartnerCapitalEntry extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'partner_id',
        'entry_type',
        'amount',
        'entry_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_type' => CapitalEntryType::class,
            'amount' => 'decimal:2',
            'entry_date' => 'date',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
