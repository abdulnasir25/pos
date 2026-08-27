<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Enums\MovementReason;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;

/**
 * The single write path every other Inventory action funnels through.
 * Owns three things atomically, in one DB transaction:
 *
 *   1. The stock_levels row: an unconditional increment for reasons that
 *      only ever add stock, or a conditional atomic decrement (guarded
 *      by the row's own current quantity in the SQL WHERE clause) for
 *      reasons that remove it — see the race-condition analysis for why
 *      this specific shape is what makes stock-out safe.
 *   2. The weighted-average cost recompute, for reasons that bring stock
 *      in at a cost (opening, purchase, purchase_return) — done as a single SQL
 *      expression that reads and writes the row in the same statement,
 *      never as a separate read-then-write in application code.
 *   3. The immutable inventory_movements ledger row, with unit_cost
 *      frozen to whatever the stock_levels row's average_cost is
 *      *after* this movement — the permanent historical record.
 */
class PostInventoryMovement
{
    /**
     * @param  string  $quantityBaseUnit  Signed for adjustments; unsigned magnitude for every other reason — direction is implied by $reason except for Adjustment.
     * @param  string|null  $incomingUnitCost  Required for Opening/Purchase/PurchaseReturn; ignored otherwise.
     */
    public function handle(
        int $productId,
        int $warehouseId,
        MovementReason $reason,
        string $quantityBaseUnit,
        ?string $incomingUnitCost = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $actorId = null,
    ): InventoryMovement {
        $connection = DB::connection(config('tenancy.tenant_connection', 'tenant'));

        return $connection->transaction(function () use (
            $connection, $productId, $warehouseId, $reason, $quantityBaseUnit,
            $incomingUnitCost, $referenceType, $referenceId, $actorId,
        ) {
            $this->ensureStockLevelRowExists($connection, $productId, $warehouseId);

            // Every reason except Adjustment carries an unsigned magnitude —
            // direction comes from the reason itself. Adjustment is the one
            // reason a caller signs explicitly, since it corrects stock in
            // either direction.
            $isIncrease = $reason === MovementReason::Adjustment
                ? bccomp($quantityBaseUnit, '0', 4) >= 0
                : $reason->isAlwaysIncrease();

            $magnitude = ltrim($quantityBaseUnit, '+-');
            $signedQuantity = $isIncrease ? $magnitude : '-'.$magnitude;

            if ($isIncrease) {
                $this->applyIncrease($connection, $productId, $warehouseId, $signedQuantity, $incomingUnitCost, $reason);
            } else {
                $this->applyConditionalDecrease($connection, $productId, $warehouseId, $magnitude);
            }

            $averageCost = $connection->table('stock_levels')
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->value('average_cost');

            return InventoryMovement::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity_base_unit' => $signedQuantity,
                'unit_cost' => $averageCost,
                'reason' => $reason,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => $actorId,
            ]);
        }, attempts: 3);
    }

    private function ensureStockLevelRowExists($connection, int $productId, int $warehouseId): void
    {
        $connection->statement(
            'INSERT INTO stock_levels (product_id, warehouse_id, quantity_base_unit, average_cost, created_at, updated_at)
             SELECT ?, ?, 0, 0, ?, ?
             WHERE NOT EXISTS (
                 SELECT 1 FROM stock_levels WHERE product_id = ? AND warehouse_id = ?
             )',
            [$productId, $warehouseId, now(), now(), $productId, $warehouseId]
        );
    }

    /**
     * Increases never risk going negative, so no conditional guard is
     * needed here — but the weighted-average-cost recompute still has to
     * be one atomic SQL statement, not a read-then-write in PHP, or two
     * concurrent purchases would race on the read and the second one to
     * write would silently discard the first one's contribution to the
     * average (a classic lost-update bug).
     */
    private function applyIncrease($connection, int $productId, int $warehouseId, string $quantity, ?string $unitCost, MovementReason $reason): void
    {
        $hasCost = $unitCost !== null && in_array($reason, [
            MovementReason::Opening, MovementReason::Purchase, MovementReason::PurchaseReturn,
        ], true);

        if ($hasCost) {
            $connection->statement(
                'UPDATE stock_levels
                 SET average_cost = CASE
                         WHEN (quantity_base_unit + ?) <= 0 THEN average_cost
                         ELSE ((quantity_base_unit * average_cost) + (? * ?)) / (quantity_base_unit + ?)
                     END,
                     quantity_base_unit = quantity_base_unit + ?,
                     updated_at = ?
                 WHERE product_id = ? AND warehouse_id = ?',
                [$quantity, $quantity, $unitCost, $quantity, $quantity, now(), $productId, $warehouseId]
            );
        } else {
            $connection->statement(
                'UPDATE stock_levels
                 SET quantity_base_unit = quantity_base_unit + ?, updated_at = ?
                 WHERE product_id = ? AND warehouse_id = ?',
                [$quantity, now(), $productId, $warehouseId]
            );
        }
    }

    /**
     * The core of the race-condition fix: the check ("is there enough
     * stock?") and the write happen in the same SQL statement, via the
     * WHERE clause, rather than as a separate SELECT in PHP followed by
     * an UPDATE. Two concurrent callers both attempting to oversell can
     * both issue this statement; only one can actually reduce the row
     * below zero, because the WHERE clause re-evaluates against
     * whatever the row's *current* committed value is at the moment
     * each statement actually executes, not a value read earlier in
     * application code.
     */
    private function applyConditionalDecrease($connection, int $productId, int $warehouseId, string $quantity): void
    {
        $affected = $connection->update(
            'UPDATE stock_levels
             SET quantity_base_unit = quantity_base_unit - ?, updated_at = ?
             WHERE product_id = ? AND warehouse_id = ? AND quantity_base_unit >= ?',
            [$quantity, now(), $productId, $warehouseId, $quantity]
        );

        if ($affected === 0) {
            throw InsufficientStockException::forProduct($productId, $warehouseId, $quantity);
        }
    }
}
