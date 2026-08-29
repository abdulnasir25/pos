<?php

namespace App\Modules\Purchases\Actions;

use App\Modules\Inventory\Actions\RecordPurchaseReturn;
use App\Modules\Products\Models\Product;
use App\Modules\Purchases\Enums\PurchaseStatus;
use App\Modules\Purchases\Exceptions\InvalidPurchaseStateException;
use App\Modules\Purchases\Models\Purchase;
use App\Modules\Suppliers\Enums\SupplierLedgerEntryType;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Suppliers\Models\SupplierLedgerEntry;
use Illuminate\Support\Facades\DB;

/**
 * Voids a confirmed purchase entirely: every line's stock is reversed
 * via Inventory's RecordPurchaseReturn (stock sent back out, same as
 * it came in), any payable it created is reversed off the supplier's
 * balance, and the purchase itself is never deleted — only its status
 * changes. Mirrors CancelSale exactly, opposite direction.
 */
class CancelPurchase
{
    public function __construct(private readonly RecordPurchaseReturn $purchaseReturn) {}

    public function handle(Purchase $purchase, int $actorId): Purchase
    {
        if ($purchase->status !== PurchaseStatus::Confirmed) {
            throw InvalidPurchaseStateException::cannotCancel($purchase->id, $purchase->status->value);
        }

        return DB::connection(config('tenancy.tenant_connection', 'tenant'))->transaction(function () use ($purchase, $actorId) {
            foreach ($purchase->items as $item) {
                $product = Product::findOrFail($item->product_id);

                $this->purchaseReturn->handle(
                    product: $product,
                    warehouseId: $purchase->warehouse_id,
                    unitId: $item->unit_id,
                    quantity: (string) $item->quantity,
                    referenceType: Purchase::class,
                    referenceId: $purchase->id,
                    actorId: $actorId,
                );
            }

            if (bccomp((string) $purchase->balance_payable, '0.00', 2) === 1) {
                Supplier::where('id', $purchase->supplier_id)->decrement('balance', $purchase->balance_payable);

                SupplierLedgerEntry::create([
                    'supplier_id' => $purchase->supplier_id,
                    'entry_type' => SupplierLedgerEntryType::Adjustment,
                    'amount' => bcmul((string) $purchase->balance_payable, '-1', 2),
                    'reference_type' => Purchase::class,
                    'reference_id' => $purchase->id,
                    'entry_date' => now()->toDateString(),
                ]);
            }

            $purchase->update([
                'status' => PurchaseStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            return $purchase->fresh(['items', 'payments']);
        });
    }
}
