<?php

namespace App\Modules\Purchases\Actions;

use App\Modules\Inventory\Actions\RecordPurchaseStockIn;
use App\Modules\Purchases\DTOs\PurchaseLine;
use App\Modules\Purchases\DTOs\PurchasePaymentAllocation;
use App\Modules\Purchases\Enums\PurchaseStatus;
use App\Modules\Purchases\Exceptions\OverpaymentException;
use App\Modules\Purchases\Models\Purchase;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The entire purchase-confirmation flow, atomically: header + line
 * items + inventory stock-in (which also sets the new weighted-average
 * cost) + payment allocation + supplier balance update, or none of it.
 * Mirrors ConfirmSale exactly, opposite direction — the only place a
 * Purchase is ever created, no separate draft-then-confirm flow.
 *
 * @param  PurchaseLine[]  $lines
 * @param  PurchasePaymentAllocation[]  $payments
 */
class ConfirmPurchase
{
    public function __construct(private readonly RecordPurchaseStockIn $stockIn) {}

    public function handle(
        int $supplierId,
        int $warehouseId,
        ?int $employeeId,
        int $createdBy,
        array $lines,
        array $payments,
    ): Purchase {
        return DB::connection(config('tenancy.tenant_connection', 'tenant'))->transaction(function () use (
            $supplierId, $warehouseId, $employeeId, $createdBy, $lines, $payments,
        ) {
            $subtotal = '0.00';
            $discountTotal = '0.00';
            $total = '0.00';

            foreach ($lines as $line) {
                $subtotal = bcadd($subtotal, bcmul($line->quantity, $line->unitCost, 4), 2);
                $discountTotal = bcadd($discountTotal, $line->discount, 2);
                $total = bcadd($total, $line->lineTotal(), 2);
            }

            $paidTotal = '0.00';
            foreach ($payments as $payment) {
                $paidTotal = bcadd($paidTotal, $payment->amount, 2);
            }

            if (bccomp($paidTotal, $total, 2) === 1) {
                throw OverpaymentException::forPurchase($total, $paidTotal);
            }

            $balancePayable = bcsub($total, $paidTotal, 2);

            $purchase = Purchase::create([
                'supplier_id' => $supplierId,
                'warehouse_id' => $warehouseId,
                'employee_id' => $employeeId,
                'reference_no' => $this->generateReferenceNo(),
                'status' => PurchaseStatus::Confirmed,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'total' => $total,
                'paid_total' => $paidTotal,
                'balance_payable' => $balancePayable,
                'confirmed_at' => now(),
                'created_by' => $createdBy,
            ]);

            foreach ($lines as $line) {
                // Inventory owns the stock increment AND becomes the new
                // weighted-average cost basis for this product — its own
                // atomic guard means this can never race with a
                // concurrent sale of the same product.
                $this->stockIn->handle(
                    product: $line->product,
                    warehouseId: $warehouseId,
                    unitId: $line->unitId,
                    quantity: $line->quantity,
                    unitCost: $line->unitCost,
                    referenceType: Purchase::class,
                    referenceId: $purchase->id,
                    actorId: $createdBy,
                );

                $purchase->items()->create([
                    'product_id' => $line->product->id,
                    'unit_id' => $line->unitId,
                    'quantity' => $line->quantity,
                    'unit_cost' => $line->unitCost,
                    'discount' => $line->discount,
                    'line_total' => $line->lineTotal(),
                ]);
            }

            foreach ($payments as $payment) {
                $purchase->payments()->create([
                    'payment_method_id' => $payment->paymentMethodId,
                    'amount' => $payment->amount,
                    'paid_at' => now(),
                ]);
            }

            if (bccomp($balancePayable, '0.00', 2) === 1) {
                Supplier::where('id', $supplierId)->increment('balance', $balancePayable);
            }

            return $purchase->fresh(['items', 'payments']);
        });
    }

    private function generateReferenceNo(): string
    {
        return 'P-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }
}
