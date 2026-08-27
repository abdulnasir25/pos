<?php

namespace App\Modules\Sales\Actions;

use App\Modules\Customers\Models\Customer;
use App\Modules\Inventory\Actions\RecordSaleStockOut;
use App\Modules\Sales\DTOs\CartLine;
use App\Modules\Sales\DTOs\PaymentAllocation;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Exceptions\OverpaymentException;
use App\Modules\Sales\Exceptions\WalkInCreditNotAllowedException;
use App\Modules\Sales\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The entire POS checkout, atomically: header + line items + inventory
 * stock-out + payment allocation + customer balance update, or none of
 * it. This is the only place a Sale is ever created — there is no
 * separate draft-then-confirm flow in this phase (see module design
 * notes: no POS UI yet to "hold" a cart, so a two-step flow has nothing
 * to serve).
 *
 * @param  CartLine[]  $lines
 * @param  PaymentAllocation[]  $payments
 */
class ConfirmSale
{
    public function __construct(private readonly RecordSaleStockOut $stockOut) {}

    public function handle(
        ?int $customerId,
        int $warehouseId,
        int $cashierId,
        ?int $salesEmployeeId,
        array $lines,
        array $payments,
        ?string $notes = null,
    ): Sale {
        return DB::connection(config('tenancy.tenant_connection', 'tenant'))->transaction(function () use (
            $customerId, $warehouseId, $cashierId, $salesEmployeeId, $lines, $payments, $notes,
        ) {
            $subtotal = '0.00';
            $discountTotal = '0.00';
            $total = '0.00';

            foreach ($lines as $line) {
                $subtotal = bcadd($subtotal, bcmul($line->quantity, $line->unitPrice, 4), 2);
                $discountTotal = bcadd($discountTotal, $line->discount, 2);
                $total = bcadd($total, $line->lineTotal(), 2);
            }

            $paidTotal = '0.00';
            foreach ($payments as $payment) {
                $paidTotal = bcadd($paidTotal, $payment->amount, 2);
            }

            if (bccomp($paidTotal, $total, 2) === 1) {
                throw OverpaymentException::forSale($total, $paidTotal);
            }

            $balanceDue = bcsub($total, $paidTotal, 2);

            if ($customerId === null && bccomp($balanceDue, '0.00', 2) === 1) {
                throw WalkInCreditNotAllowedException::make();
            }

            $sale = Sale::create([
                'customer_id' => $customerId,
                'warehouse_id' => $warehouseId,
                'cashier_id' => $cashierId,
                'sales_employee_id' => $salesEmployeeId,
                'reference_no' => $this->generateReferenceNo(),
                'status' => SaleStatus::Confirmed,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'total' => $total,
                'paid_total' => $paidTotal,
                'balance_due' => $balanceDue,
                'notes' => $notes,
                'confirmed_at' => now(),
            ]);

            foreach ($lines as $line) {
                // Inventory owns the stock decrement AND the cost basis —
                // its own atomic guard throws InsufficientStockException
                // if this line can't be fulfilled, which rolls back the
                // whole sale via this method's outer transaction.
                $movement = $this->stockOut->handle(
                    product: $line->product,
                    warehouseId: $warehouseId,
                    unitId: $line->unitId,
                    quantity: $line->quantity,
                    referenceType: Sale::class,
                    referenceId: $sale->id,
                    actorId: $cashierId,
                );

                $sale->items()->create([
                    'product_id' => $line->product->id,
                    'unit_id' => $line->unitId,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unitPrice,
                    'discount' => $line->discount,
                    'unit_cost_snapshot' => $movement->unit_cost,
                    'line_total' => $line->lineTotal(),
                ]);
            }

            foreach ($payments as $payment) {
                $sale->payments()->create([
                    'payment_method_id' => $payment->paymentMethodId,
                    'amount' => $payment->amount,
                    'paid_at' => now(),
                ]);
            }

            if ($customerId !== null && bccomp($balanceDue, '0.00', 2) === 1) {
                Customer::where('id', $customerId)->increment('balance', $balanceDue);
            }

            return $sale->fresh(['items', 'payments']);
        });
    }

    private function generateReferenceNo(): string
    {
        return 'S-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }
}
