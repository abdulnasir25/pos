<?php

namespace App\Modules\Sales\Actions;

use App\Modules\Customers\Models\Customer;
use App\Modules\Inventory\Actions\RecordSaleReturn;
use App\Modules\Products\Models\Product;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Exceptions\InvalidSaleStateException;
use App\Modules\Sales\Models\Sale;
use Illuminate\Support\Facades\DB;

/**
 * Voids a confirmed sale entirely: every line's stock is restored via
 * Inventory's RecordSaleReturn (the same action a partial customer
 * return uses — cancellation is, from Inventory's point of view, just
 * returning everything), any receivable it created is reversed off the
 * customer's balance, and the sale itself is never deleted — only its
 * status changes.
 */
class CancelSale
{
    public function __construct(private readonly RecordSaleReturn $saleReturn) {}

    public function handle(Sale $sale, int $actorId): Sale
    {
        if ($sale->status !== SaleStatus::Confirmed) {
            throw InvalidSaleStateException::cannotCancel($sale->id, $sale->status->value);
        }

        return DB::connection(config('tenancy.tenant_connection', 'tenant'))->transaction(function () use ($sale, $actorId) {
            foreach ($sale->items as $item) {
                $product = Product::findOrFail($item->product_id);

                $this->saleReturn->handle(
                    product: $product,
                    warehouseId: $sale->warehouse_id,
                    unitId: $item->unit_id,
                    quantity: (string) $item->quantity,
                    referenceType: Sale::class,
                    referenceId: $sale->id,
                    actorId: $actorId,
                );
            }

            if ($sale->customer_id !== null && bccomp((string) $sale->balance_due, '0.00', 2) === 1) {
                Customer::where('id', $sale->customer_id)->decrement('balance', $sale->balance_due);
            }

            $sale->update([
                'status' => SaleStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            return $sale->fresh(['items', 'payments']);
        });
    }
}
