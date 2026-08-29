<?php

namespace App\Modules\Expenses\Actions;

use App\Modules\Expenses\Enums\ExpenseCategoryStatus;
use App\Modules\Expenses\Exceptions\DuplicateExpenseCategoryNameException;
use App\Modules\Expenses\Models\ExpenseCategory;

class CreateExpenseCategory
{
    public function handle(string $name): ExpenseCategory
    {
        if (ExpenseCategory::where('name', $name)->exists()) {
            throw DuplicateExpenseCategoryNameException::forName($name);
        }

        return ExpenseCategory::create([
            'name' => $name,
            'status' => ExpenseCategoryStatus::Active,
        ]);
    }
}
