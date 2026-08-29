<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * Meant to run on a schedule (not wired to one here — a console
 * command/scheduler entry is a deployment concern, not this Action's).
 * Every pending invoice past its due_date becomes overdue, and its
 * tenant gets suspended — reusing the exact suspension mechanism
 * IdentifyTenant already enforces (see the Tenancy module), so no new
 * access-blocking logic is needed here. A tenant with several overdue
 * invoices is only suspended once; RecordInvoicePayment reactivates
 * them when they pay.
 */
class MarkOverdueInvoicesAndSuspendTenants
{
    /**
     * @return Collection<int, Invoice>
     */
    public function handle(): Collection
    {
        $overdue = Invoice::where('status', InvoiceStatus::Pending)
            ->whereDate('due_date', '<', now()->toDateString())
            ->get();

        foreach ($overdue as $invoice) {
            $invoice->update(['status' => InvoiceStatus::Overdue]);

            Tenant::where('id', $invoice->tenant_id)
                ->where('status', '!=', 'suspended')
                ->update(['status' => 'suspended', 'suspended_at' => now()]);
        }

        return $overdue;
    }
}
