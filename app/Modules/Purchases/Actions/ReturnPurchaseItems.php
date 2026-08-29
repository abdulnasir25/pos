<?php

namespace App\Modules\Purchases\Actions;

use App\Modules\Inventory\Actions\RecordPurchaseReturn;
use App\Modules\Products\Models\Product;
use App\Modules\Purchases\Enums\PurchaseStatus;
use App\Modules\Purchases\Exceptions\InvalidPurchaseStateException;
use App\Modules\Purchases\Exceptions\ReturnQuantityExceedsAvailableException;
use App\Modules\Purchases\Models\Purchase;
use App\Modules\Purchases\Models\PurchaseItem;
use App\Modules\Purchases\Models\PurchaseReturn;
use App\Modules\Suppliers\Enums\SupplierLedgerEntryType;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Suppliers\Models\SupplierLedgerEntry;
use Illuminate\Support\Facades\DB;

/**
 * A partial or full return of goods back to the supplier against a
 * confirmed purchase. Mirrors ReturnSaleItems exactly, opposite
 * direction — the Purchase row's own totals are never rewritten, a
 * return is new rows plus a status flip to Returned only once every
 * line has been fully returned.
 *
 * @param  \App\Modules\Purchases\DTOs\PurchaseReturnLine[]  $lines
 */
class ReturnPurchaseItems
{
    public function __construct(private readonly RecordPurchaseReturn $purchaseReturn) {}

    public function handle(Purchase $purchase, array $lines, int $actorId, ?string $notes = null): PurchaseReturn
    {
        if ($purchase->status !== PurchaseStatus::Confirmed) {
            throw InvalidPurchaseStateException::cannotReturn($purchase->id, $purchase->status->value);
        }

        return DB::connection(config('tenancy.tenant_connection', 'tenant'))->transaction(function () use ($purchase, $lines, $actorId, $notes) {
            $creditTotal = '0.00';
            $return = PurchaseReturn::create([
                'purchase_id' => $purchase->id,
                'processed_by' => $actorId,
                'credit_amount' => '0.00',
                'notes' => $notes,
            ]);

            foreach ($lines as $line) {
                $purchaseItem = PurchaseItem::where('purchase_id', $purchase->id)->findOrFail($line->purchaseItemId);
                $eligible = $purchaseItem->quantityEligibleForReturn();

                if (bccomp($line->quantity, $eligible, 4) === 1) {
                    throw ReturnQuantityExceedsAvailableException::forPurchaseItem($purchaseItem->id, $line->quantity, $eligible);
                }

                $product = Product::findOrFail($purchaseItem->product_id);

                $this->purchaseReturn->handle(
                    product: $product,
                    warehouseId: $purchase->warehouse_id,
                    unitId: $purchaseItem->unit_id,
                    quantity: $line->quantity,
                    referenceType: PurchaseReturn::class,
                    referenceId: $return->id,
                    actorId: $actorId,
                );

                $return->items()->create([
                    'purchase_item_id' => $purchaseItem->id,
                    'quantity' => $line->quantity,
                ]);

                // Proportional credit: the line's already-net (post-discount)
                // total, scaled by the fraction of that line being returned
                // — same technique ReturnSaleItems uses for a refund.
                $lineCredit = bcmul(
                    (string) $purchaseItem->line_total,
                    bcdiv($line->quantity, (string) $purchaseItem->quantity, 6),
                    2
                );
                $creditTotal = bcadd($creditTotal, $lineCredit, 2);
            }

            $return->update(['credit_amount' => $creditTotal]);

            Supplier::where('id', $purchase->supplier_id)->decrement('balance', $creditTotal);

            SupplierLedgerEntry::create([
                'supplier_id' => $purchase->supplier_id,
                'entry_type' => SupplierLedgerEntryType::ReturnCredit,
                'amount' => bcmul($creditTotal, '-1', 2),
                'reference_type' => PurchaseReturn::class,
                'reference_id' => $return->id,
                'entry_date' => now()->toDateString(),
            ]);

            if ($this->isFullyReturned($purchase)) {
                $purchase->update(['status' => PurchaseStatus::Returned]);
            }

            return $return->fresh('items');
        });
    }

    private function isFullyReturned(Purchase $purchase): bool
    {
        return $purchase->items()->get()->every(
            fn (PurchaseItem $item) => bccomp($item->quantityEligibleForReturn(), '0.0000', 4) === 0
        );
    }
}
