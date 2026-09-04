<?php

namespace App\Modules\Employees\Http\Controllers;

use App\Modules\Employees\Actions\ChangeEmployeeStatus;
use App\Modules\Employees\Actions\CreateEmployee;
use App\Modules\Employees\Actions\RecordSalaryChange;
use App\Modules\Employees\Actions\RecordSalaryPayment;
use App\Modules\Employees\Actions\UpdateEmployeeProfile;
use App\Modules\Employees\Enums\EmployeeStatus;
use App\Modules\Employees\Exceptions\InvalidCompensationRangeException;
use App\Modules\Employees\Exceptions\OverlappingCompensationException;
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Support\ResolveSalaryForDate;
use App\Modules\FinancialPeriods\Models\FinancialPeriod;
use App\Modules\Payments\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeesController extends \App\Http\Controllers\Controller
{
    public function show(ResolveSalaryForDate $resolveSalary): Response
    {
        $today = now()->toDateString();

        $employees = Employee::orderBy('name')
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'phone' => $employee->phone,
                'hired_at' => $employee->hired_at->toDateString(),
                'terminated_at' => $employee->terminated_at?->toDateString(),
                'status' => $employee->status->value,
                'current_salary' => $resolveSalary->handle($employee, $today),
            ]);

        $financialPeriods = FinancialPeriod::orderByDesc('period_start')
            ->get(['id', 'period_start', 'period_end', 'status'])
            ->map(fn (FinancialPeriod $period) => [
                'id' => $period->id,
                'period_start' => $period->period_start->toDateString(),
                'period_end' => $period->period_end->toDateString(),
                'status' => $period->status->value,
            ]);

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'financialPeriods' => $financialPeriods,
            'paymentMethods' => PaymentMethod::where('status', 'active')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'hired_at' => ['required', 'date'],
        ]);

        app(CreateEmployee::class)->handle(
            $validated['name'],
            $validated['hired_at'],
            $validated['phone'] ?? null,
        );

        return back()->with('success', 'Employee added.');
    }

    public function updateProfile(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        app(UpdateEmployeeProfile::class)->handle($employee, $validated['name'], $validated['phone'] ?? null);

        return back()->with('success', 'Employee updated.');
    }

    public function storeSalary(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'monthly_salary' => ['required', 'numeric', 'gt:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date'],
        ]);

        try {
            app(RecordSalaryChange::class)->handle(
                $employee,
                (string) $validated['monthly_salary'],
                $validated['effective_from'],
                $validated['effective_to'] ?? null,
            );
        } catch (InvalidCompensationRangeException|OverlappingCompensationException $e) {
            return back()->withErrors(['salary' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Salary recorded.');
    }

    public function storeStatus(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:active,inactive,terminated'],
            'terminated_at' => ['nullable', 'date'],
        ]);

        app(ChangeEmployeeStatus::class)->handle(
            $employee,
            EmployeeStatus::from($validated['status']),
            $validated['terminated_at'] ?? null,
        );

        return back()->with('success', 'Employee status updated.');
    }

    public function storeSalaryPayment(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'financial_period_id' => ['required', 'integer', 'exists:financial_periods,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
        ]);

        app(RecordSalaryPayment::class)->handle(
            $employee,
            FinancialPeriod::findOrFail($validated['financial_period_id']),
            (string) $validated['amount'],
            $validated['payment_method_id'],
            $request->user()->id,
        );

        return back()->with('success', 'Salary payment recorded.');
    }
}
