<?php

namespace App\Modules\Commission\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionSaleLine extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'commission_entry_id',
        'sale_id',
        'eligible_gross_profit',
    ];

    protected function casts(): array
    {
        return [
            'eligible_gross_profit' => 'decimal:2',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(CommissionEntry::class, 'commission_entry_id');
    }
}
