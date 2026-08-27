<?php

namespace App\Modules\Sales\Actions;

use App\Modules\Customers\Models\Customer;
use App\Modules\Inventory\Actions\RecordSaleReturn;
use App\Modules\Products\Models\Product;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Exceptions\InvalidSaleStateException;
use App\Modules\Sales\Exceptions\ReturnQuantityExceedsAvailableException;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Sales\Models\SaleReturn;
use Illuminate\Support\Facades\DB;

/**
 * A partial or full customer return against a confirmed sale. Only the
 * sellable-condition path is implemented this phase — sale_return_items
 * has a `condition` column reserved for a future damaged-return branch
 * that would call Inventory's RecordDamage instead, left unwired rather
 * than half-built.
 *
 * The Sale row's own totals are never rewritten (see CancelSale and the
 * Sale model's docblock) — a return is new rows, plus a status flip to
 * Refunded only once every line has been fully returned.
 *
 * @param  \App\Modules\Sales\DTOs\ReturnLine[]  $lines
 */
class ReturnSaleItems
{
    public function __construct(private readonly RecordSaleReturn $saleReturn) {}

    public function handle(Sale $sale, array $lines, int $actorId, ?string $notes = null): SaleReturn
    {
        if ($sale->status !== SaleStatus::Confirmed) {
            throw InvalidSaleStateException::cannotReturn($sale->id, $sale->status->value);
        }

        return DB::connection(config('tenancy.tenant_connection', 'tenant'))->transaction(function () use ($sale, $lines, $actorId, $notes) {
            $refundTotal = '0.00';
            $return = SaleReturn::create([
                'sale_id' => $sale->id,
                'processed_by' => $actorId,
                'refund_amount' => '0.00',
                'notes' => $notes,
            ]);

            foreach ($lines as $line) {
                $saleItem = SaleItem::where('sale_id', $sale->id)->findOrFail($line->saleItemId);
                $eligible = $saleItem->quantityEligibleForReturn();

                if (bccomp($line->quantity, $eligible, 4) === 1) {
                    throw ReturnQuantityExceedsAvailableException::forSaleItem($saleItem->id, $line->quantity, $eligible);
                }

                $product = Product::findOrFail($saleItem->product_id);

                $this->saleReturn->handle(
                    product: $product,
                    warehouseId: $sale->warehouse_id,
                    unitId: $saleItem->unit_id,
                    quantity: $line->quantity,
                    referenceType: SaleReturn::class,
                    referenceId: $return->id,
                    actorId: $actorId,
                );

                $return->items()->create([
                    'sale_item_id' => $saleItem->id,
                    'quantity' => $line->quantity,
                    'condition' => 'sellable',
                ]);

                // Proportional refund: the line's already-net (post-discount)
                // total, scaled by the fraction of that line being returned.
                $lineRefund = bcmul(
                    (string) $saleItem->line_total,
                    bcdiv($line->quantity, (string) $saleItem->quantity, 6),
                    2
                );
                $refundTotal = bcadd($refundTotal, $lineRefund, 2);
            }

            $return->update(['refund_amount' => $refundTotal]);

            if ($sale->customer_id !== null) {
                Customer::where('id', $sale->customer_id)->decrement('balance', $refundTotal);
            }

            if ($this->isFullyReturned($sale)) {
                $sale->update(['status' => SaleStatus::Refunded]);
            }

            return $return->fresh('items');
        });
    }

    private function isFullyReturned(Sale $sale): bool
    {
        return $sale->items()->get()->every(
            fn (SaleItem $item) => bccomp($item->quantityEligibleForReturn(), '0.0000', 4) === 0
        );
    }
}
