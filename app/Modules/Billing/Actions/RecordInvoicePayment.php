<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Exceptions\InvoiceAlreadyPaidException;
use App\Modules\Billing\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Manual, administrative — no payment gateway is integrated anywhere
 * in this module. The SaaS owner calls this after actually receiving
 * payment through whatever channel they use. If the tenant was
 * suspended (by MarkOverdueInvoicesAndSuspendTenants) over exactly
 * this invoice, paying it also reactivates them — the whole reason
 * they were suspended just went away.
 */
class RecordInvoicePayment
{
    public function handle(Invoice $invoice, string $paidAt): Invoice
    {
        if ($invoice->status === InvoiceStatus::Paid) {
            throw InvoiceAlreadyPaidException::forInvoice($invoice->id);
        }

        return DB::connection('landlord')->transaction(function () use ($invoice, $paidAt) {
            $invoice->update([
                'status' => InvoiceStatus::Paid,
                'paid_at' => $paidAt,
            ]);

            $tenant = $invoice->tenant;

            if ($tenant->isSuspended()) {
                $tenant->update(['status' => 'active', 'suspended_at' => null]);
            }

            return $invoice;
        });
    }
}
