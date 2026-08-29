<?php

namespace App\Modules\Expenses\Http\Controllers;

use App\Modules\Expenses\Actions\CreateExpenseCategory;
use App\Modules\Expenses\Actions\RecordExpense;
use App\Modules\Expenses\Actions\RecordExpenseCorrection;
use App\Modules\Expenses\Exceptions\DuplicateExpenseCategoryNameException;
use App\Modules\Expenses\Exceptions\InvalidExpenseAmountException;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Payments\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpensesController extends \App\Http\Controllers\Controller
{
    public function show(): Response
    {
        $expenses = Expense::with('category:id,name')
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (Expense $expense) => [
                'id' => $expense->id,
                'category' => $expense->category->name,
                'amount' => (string) $expense->amount,
                'expense_date' => $expense->expense_date->toDateString(),
                'description' => $expense->description,
                'is_correction' => $expense->reference_id !== null,
            ]);

        // bcadd, not a raw cast: SQLite's NUMERIC storage can round-trip
        // sum('amount') back as "5000" instead of "5000.00".
        $total = bcadd('0', (string) Expense::sum('amount'), 2);

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'categories' => ExpenseCategory::where('status', 'active')->get(['id', 'name']),
            'paymentMethods' => PaymentMethod::all(['id', 'name']),
            'total' => $total,
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        try {
            app(CreateExpenseCategory::class)->handle($validated['name']);
        } catch (DuplicateExpenseCategoryNameException $e) {
            return back()->withErrors(['category' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Category added.');
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'expense_date' => ['required', 'date'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $category = ExpenseCategory::findOrFail($validated['expense_category_id']);

        try {
            app(RecordExpense::class)->handle(
                $category,
                (string) $validated['amount'],
                $validated['expense_date'],
                $validated['payment_method_id'],
                $request->user()->id,
                $validated['description'] ?? null,
            );
        } catch (InvalidExpenseAmountException $e) {
            return back()->withErrors(['expense' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Expense recorded.');
    }

    public function storeCorrection(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'expense_id' => ['required', 'integer', 'exists:expenses,id'],
            'amount' => ['required', 'numeric'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $original = Expense::findOrFail($validated['expense_id']);

        app(RecordExpenseCorrection::class)->handle(
            $original,
            (string) $validated['amount'],
            $request->user()->id,
            $validated['description'] ?? null,
        );

        return back()->with('success', 'Correction recorded.');
    }
}
