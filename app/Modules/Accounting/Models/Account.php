<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Accounting\Enums\AccountStatus;
use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per line in the chart of accounts. parent_id makes it a
 * tree — e.g. a per-partner Capital sub-account would sit under the
 * fixed "Partner Capital" parent seeded on every tenant, rather than
 * needing its own top-level code.
 */
class Account extends Model
{
    use HasTenantScopedQueries;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'code',
        'name',
        'type',
        'parent_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'status' => AccountStatus::class,
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }
}
