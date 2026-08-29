<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Immutable once created — no update or delete route exists anywhere
 * in this module. See Actions/RecordJournalEntry, the only supported
 * way to write one.
 */
class JournalEntry extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'entry_date',
        'description',
        'reference_type',
        'reference_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }
}
