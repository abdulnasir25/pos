<?php

namespace App\Modules\Partners\Models;

use App\Modules\Partners\Enums\PartnerStatus;
use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The person/entity. Ownership itself never lives here — see
 * PartnerOwnershipPeriod. No delete route exists anywhere in this
 * module; exiting is ChangePartnerStatus, never a row removal.
 */
class Partner extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'name',
        'phone',
        'joined_at',
        'exited_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'date',
            'exited_at' => 'date',
            'status' => PartnerStatus::class,
        ];
    }

    public function ownershipPeriods(): HasMany
    {
        return $this->hasMany(PartnerOwnershipPeriod::class);
    }

    public function capitalEntries(): HasMany
    {
        return $this->hasMany(PartnerCapitalEntry::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(PartnerLoan::class);
    }

    public function profitAllocations(): HasMany
    {
        return $this->hasMany(PartnerProfitAllocation::class);
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(PartnerDistribution::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(PartnerLedgerEntry::class);
    }
}
