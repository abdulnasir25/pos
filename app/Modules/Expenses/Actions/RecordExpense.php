<?php

namespace App\Modules\Expenses\Actions;

use App\Modules\Expenses\Exceptions\InvalidExpenseAmountException;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;

class RecordExpense
{
    public function handle(ExpenseCategory $category, string $amount, string $expenseDate, int $paymentMethodId, int $createdBy, ?string $description = null): Expense
    {
        if (bccomp($amount, '0.00', 2) !== 1) {
            throw InvalidExpenseAmountException::mustBePositive($amount);
        }

        return Expense::create([
            'expense_category_id' => $category->id,
            'amount' => $amount,
            'expense_date' => $expenseDate,
            'description' => $description,
            'payment_method_id' => $paymentMethodId,
            'created_by' => $createdBy,
        ]);
    }
}
