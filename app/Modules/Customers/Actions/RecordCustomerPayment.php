<?php

namespace App\Modules\Customers\Actions;

use App\Modules\Customers\Enums\CustomerLedgerEntryType;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerLedgerEntry;
use App\Modules\Customers\Models\CustomerPayment;
use Illuminate\Support\Facades\DB;

/**
 * A standalone payment against a customer's running balance, not tied
 * to any specific sale — "customer walks in and pays down their tab."
 */
class RecordCustomerPayment
{
    public function handle(Customer $customer, string $amount, int $paymentMethodId, int $createdBy): CustomerPayment
    {
        return DB::transaction(function () use ($customer, $amount, $paymentMethodId, $createdBy) {
            $payment = CustomerPayment::create([
                'customer_id' => $customer->id,
                'payment_method_id' => $paymentMethodId,
                'amount' => $amount,
                'paid_at' => now(),
                'created_by' => $createdBy,
            ]);

            CustomerLedgerEntry::create([
                'customer_id' => $customer->id,
                'entry_type' => CustomerLedgerEntryType::Payment,
                'amount' => bcmul($amount, '-1', 2),
                'reference_type' => CustomerPayment::class,
                'reference_id' => $payment->id,
                'entry_date' => now()->toDateString(),
            ]);

            Customer::where('id', $customer->id)->decrement('balance', $amount);

            return $payment;
        });
    }
}
