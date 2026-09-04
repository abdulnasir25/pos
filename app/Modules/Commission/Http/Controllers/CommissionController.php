<?php

namespace App\Modules\Commission\Http\Controllers;

use App\Modules\Commission\Actions\ApproveCommissionEntry;
use App\Modules\Commission\Actions\CalculateCommissionForPeriod;
use App\Modules\Commission\Actions\CreateCommissionRule;
use App\Modules\Commission\Actions\FinalizeCommissionEntry;
use App\Modules\Commission\Actions\RecordCommissionCorrection;
use App\Modules\Commission\Actions\RecordCommissionPayment;
use App\Modules\Commission\Enums\CommissionCorrectionReason;
use App\Modules\Commission\Enums\CommissionRuleStatus;
use App\Modules\Commission\Exceptions\CommissionAlreadyCalculatedException;
use App\Modules\Commission\Exceptions\CorrectionMustLandInAnOpenPeriodException;
use App\Modules\Commission\Exceptions\InvalidCommissionEntryTransitionException;
use App\Modules\Commission\Models\CommissionEntry;
use App\Modules\Commission\Models\CommissionRule;
use App\Modules\Employees\Models\Employee;
use App\Modules\FinancialPeriods\Actions\CreateFinancialPeriod;
use App\Modules\FinancialPeriods\Exceptions\InvalidPeriodRangeException;
use App\Modules\FinancialPeriods\Exceptions\OverlappingPeriodException;
use App\Modules\FinancialPeriods\Models\FinancialPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommissionController extends \App\Http\Controllers\Controller
{
    public function show(): Response
    {
        // CommissionRule/CommissionEntry deliberately carry no Eloquent
        // relation to Employee — modules in this codebase don't reach
        // into each other's internals that way (see FinancialPeriod's
        // own docblock for the same rule). Names are resolved with a
        // plain id-keyed lookup instead, same as ReceiptBuilder does
        // for Customer.
        $employeeNames = Employee::pluck('name', 'id');

        $rules = CommissionRule::orderByDesc('effective_from')
            ->get()
            ->map(fn (CommissionRule $rule) => [
                'id' => $rule->id,
                'employee' => $employeeNames[$rule->employee_id] ?? null,
                'rate' => (string) $rule->rate,
                'effective_from' => $rule->effective_from->toDateString(),
                'status' => $rule->status->value,
            ]);

        $periods = FinancialPeriod::orderByDesc('period_start')->get()->map(fn (FinancialPeriod $p) => [
            'id' => $p->id,
            'period_start' => $p->period_start->toDateString(),
            'period_end' => $p->period_end->toDateString(),
            'status' => $p->status->value,
        ]);

        $entries = CommissionEntry::orderByDesc('id')
            ->get()
            ->map(function (CommissionEntry $entry) use ($employeeNames) {
                $period = FinancialPeriod::find($entry->financial_period_id);

                return [
                    'id' => $entry->id,
                    'employee' => $employeeNames[$entry->employee_id] ?? null,
                    'period' => $period ? "{$period->period_start->toDateString()} – {$period->period_end->toDateString()}" : '—',
                    'eligible_gross_profit' => (string) $entry->eligible_gross_profit,
                    'rate_applied' => (string) $entry->rate_applied,
                    'commission_amount' => (string) $entry->commission_amount,
                    'status' => $entry->status->value,
                ];
            });

        return Inertia::render('Commission/Index', [
            'employees' => Employee::where('status', 'active')->get(['id', 'name']),
            'rules' => $rules,
            'periods' => $periods,
            'entries' => $entries,
            'openPeriods' => $periods->where('status', 'open')->values(),
            'finalizedEntries' => $entries->where('status', 'finalized')->values(),
        ]);
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'effective_from' => ['required', 'date'],
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);

        app(CreateCommissionRule::class)->handle($employee, (string) $validated['rate'], $validated['effective_from']);

        return back()->with('success', 'Commission rule added.');
    }

    public function toggleRuleStatus(CommissionRule $rule): RedirectResponse
    {
        $rule->update(['status' => $rule->status === CommissionRuleStatus::Active ? CommissionRuleStatus::Inactive : CommissionRuleStatus::Active]);

        return back()->with('success', 'Commission rule status updated.');
    }

    public function storePeriod(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
        ]);

        try {
            app(CreateFinancialPeriod::class)->handle($validated['period_start'], $validated['period_end']);
        } catch (InvalidPeriodRangeException|OverlappingPeriodException $e) {
            return back()->withErrors(['period' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Financial period created.');
    }

    public function calculate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'financial_period_id' => ['required', 'integer', 'exists:financial_periods,id'],
        ]);

        $period = FinancialPeriod::findOrFail($validated['financial_period_id']);

        try {
            $entries = app(CalculateCommissionForPeriod::class)->handle($period);
        } catch (CommissionAlreadyCalculatedException $e) {
            return back()->withErrors(['calculate' => $e->getMessage()]);
        }

        return back()->with('success', $entries->isEmpty()
            ? 'No employee has an active commission rule for this period — nothing to calculate.'
            : 'Commission calculated for '.$entries->count().' employee(s).');
    }

    public function approve(Request $request, CommissionEntry $entry): RedirectResponse
    {
        try {
            app(ApproveCommissionEntry::class)->handle($entry, $request->user()->id);
        } catch (InvalidCommissionEntryTransitionException $e) {
            return back()->withErrors(['entry' => $e->getMessage()]);
        }

        return back()->with('success', 'Commission entry approved.');
    }

    public function finalize(CommissionEntry $entry): RedirectResponse
    {
        try {
            app(FinalizeCommissionEntry::class)->handle($entry);
        } catch (InvalidCommissionEntryTransitionException $e) {
            return back()->withErrors(['entry' => $e->getMessage()]);
        }

        return back()->with('success', 'Commission entry finalized.');
    }

    public function pay(CommissionEntry $entry): RedirectResponse
    {
        try {
            app(RecordCommissionPayment::class)->handle($entry);
        } catch (InvalidCommissionEntryTransitionException $e) {
            return back()->withErrors(['entry' => $e->getMessage()]);
        }

        return back()->with('success', 'Commission payment recorded.');
    }

    public function storeCorrection(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'original_commission_entry_id' => ['required', 'integer', 'exists:commission_entries,id'],
            'financial_period_id' => ['required', 'integer', 'exists:financial_periods,id'],
            'amount' => ['required', 'numeric'],
            'reason' => ['required', 'in:sale_return,sale_cancellation,manual_adjustment'],
        ]);

        $originalEntry = CommissionEntry::findOrFail($validated['original_commission_entry_id']);
        $openPeriod = FinancialPeriod::findOrFail($validated['financial_period_id']);

        try {
            app(RecordCommissionCorrection::class)->handle(
                $originalEntry,
                $openPeriod,
                (string) $validated['amount'],
                CommissionCorrectionReason::from($validated['reason']),
                $request->user()->id,
            );
        } catch (CorrectionMustLandInAnOpenPeriodException $e) {
            return back()->withErrors(['correction' => $e->getMessage()]);
        }

        return back()->with('success', 'Correction recorded.');
    }
}
