<?php

namespace App\Modules\Suppliers\Models;

use App\Modules\Suppliers\Enums\SupplierLedgerEntryType;
use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only itemized feed behind suppliers.balance. Symmetric to
 * CustomerLedgerEntry.
 */
class SupplierLedgerEntry extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'supplier_id', 'entry_type', 'amount', 'reference_type', 'reference_id', 'entry_date',
    ];

    protected function casts(): array
    {
        return [
            'entry_type' => SupplierLedgerEntryType::class,
            'amount' => 'decimal:2',
            'entry_date' => 'date',
        ];
    }
}
