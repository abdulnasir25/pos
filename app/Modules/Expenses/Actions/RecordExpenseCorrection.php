<?php

namespace App\Modules\Expenses\Actions;

use App\Modules\Expenses\Models\Expense;

/**
 * The only supported way to reduce a previously-recorded expense (a
 * refund, a billing correction) — never edits the original row. A
 * negative amount, referencing the original for traceability.
 */
class RecordExpenseCorrection
{
    public function handle(Expense $original, string $amount, int $createdBy, ?string $description = null): Expense
    {
        return Expense::create([
            'expense_category_id' => $original->expense_category_id,
            'amount' => $amount,
            'expense_date' => now()->toDateString(),
            'description' => $description,
            'payment_method_id' => $original->payment_method_id,
            'reference_type' => Expense::class,
            'reference_id' => $original->id,
            'created_by' => $createdBy,
        ]);
    }
}
