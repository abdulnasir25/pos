<?php

namespace App\Modules\Expenses\Models;

use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable once created — no update or delete route exists anywhere
 * in this module. A correction is a new row (see
 * Actions/RecordExpenseCorrection), never an edit in place.
 */
class Expense extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'expense_category_id',
        'amount',
        'expense_date',
        'description',
        'payment_method_id',
        'reference_type',
        'reference_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }
}
