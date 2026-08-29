<?php

namespace App\Modules\Customers\Models;

use App\Modules\Customers\Enums\CustomerLedgerEntryType;
use App\Modules\Tenancy\Database\HasTenantScopedQueries;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only itemized feed behind customers.balance. No update or
 * delete route exists anywhere — every write comes from Sales/Purchases
 * actions or this module's own RecordCustomerPayment.
 */
class CustomerLedgerEntry extends Model
{
    use HasTenantScopedQueries;

    protected $fillable = [
        'customer_id', 'entry_type', 'amount', 'reference_type', 'reference_id', 'entry_date',
    ];

    protected function casts(): array
    {
        return [
            'entry_type' => CustomerLedgerEntryType::class,
            'amount' => 'decimal:2',
            'entry_date' => 'date',
        ];
    }
}
