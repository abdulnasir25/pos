<?php

namespace App\Modules\Commission\Models;

use App\Modules\Commission\Enums\CommissionRuleStatus;
use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * employee_id null means a tenant-wide default rule. Effective-dated —
 * a rate change is a new row (see Actions/CreateCommissionRule), never
 * an edit to an existing one still referenced by finalized entries.
 */
class CommissionRule extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'employee_id',
        'basis',
        'rate',
        'effective_from',
        'effective_to',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'status' => CommissionRuleStatus::class,
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CommissionEntry::class);
    }
}
