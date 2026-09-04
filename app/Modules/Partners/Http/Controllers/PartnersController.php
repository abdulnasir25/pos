<?php

namespace App\Modules\Partners\Http\Controllers;

use App\Modules\Partners\Actions\CreatePartner;
use App\Modules\Partners\Actions\IssuePartnerLoan;
use App\Modules\Partners\Actions\RecordCapitalContribution;
use App\Modules\Partners\Actions\RecordCapitalWithdrawal;
use App\Modules\Partners\Actions\RecordLoanRepayment;
use App\Modules\Partners\Actions\ExitPartner;
use App\Modules\Partners\Actions\RecordOwnershipRebalance;
use App\Modules\Partners\Actions\UpdatePartnerProfile;
use App\Modules\Partners\Enums\LoanStatus;
use App\Modules\Partners\Exceptions\InvalidOwnershipDateRangeException;
use App\Modules\Partners\Exceptions\OwnershipPercentagesMustSumTo100Exception;
use App\Modules\Partners\Exceptions\RebalanceMustCoverEveryActivePartnerException;
use App\Modules\Partners\Exceptions\RepaymentExceedsOutstandingBalanceException;
use App\Modules\Partners\Models\Partner;
use App\Modules\Partners\Models\PartnerLoan;
use App\Modules\Payments\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartnersController extends \App\Http\Controllers\Controller
{
    public function show(): Response
    {
        $partners = Partner::with(['ownershipPeriods', 'loans'])
            ->orderBy('name')
            ->get()
            ->map(function (Partner $partner) {
                $currentOwnership = $partner->ownershipPeriods->firstWhere('effective_to', null);
                $capitalBalance = $partner->capitalEntries()
                    ->get()
                    ->reduce(fn ($carry, $entry) => bcadd(
                        $carry,
                        $entry->entry_type->value === 'contribution' ? $entry->amount : bcmul($entry->amount, '-1', 2),
                        2,
                    ), '0.00');
                $loanBalance = $partner->loans->where('status', LoanStatus::Outstanding)->reduce(
                    fn ($carry, $loan) => bcadd($carry, bcsub((string) $loan->principal_amount, (string) $loan->repayments()->sum('amount'), 2), 2),
                    '0.00',
                );

                return [
                    'id' => $partner->id,
                    'name' => $partner->name,
                    'phone' => $partner->phone,
                    'status' => $partner->status->value,
                    'joined_at' => $partner->joined_at->toDateString(),
                    'ownership_percentage' => $currentOwnership?->percentage,
                    'capital_balance' => $capitalBalance,
                    'loan_balance' => $loanBalance,
                    'loans' => $partner->loans->where('status', LoanStatus::Outstanding)->values()->map(fn ($loan) => [
                        'id' => $loan->id,
                        'principal_amount' => (string) $loan->principal_amount,
                        'outstanding' => bcsub((string) $loan->principal_amount, (string) $loan->repayments()->sum('amount'), 2),
                    ]),
                ];
            });

        return Inertia::render('Partners/Index', [
            'partners' => $partners,
            'paymentMethods' => PaymentMethod::all(['id', 'name']),
        ]);
    }

    public function storePartner(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'joined_at' => ['required', 'date'],
        ]);

        app(CreatePartner::class)->handle($validated['name'], $validated['joined_at'], $validated['phone'] ?? null);

        return back()->with('success', 'Partner added.');
    }

    public function updateProfile(Request $request, Partner $partner): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        app(UpdatePartnerProfile::class)->handle($partner, $validated['name'], $validated['phone'] ?? null);

        return back()->with('success', 'Partner updated.');
    }

    public function exit(Request $request, Partner $partner): RedirectResponse
    {
        $validated = $request->validate([
            'exited_at' => ['required', 'date'],
        ]);

        app(ExitPartner::class)->handle($partner, $validated['exited_at']);

        return back()->with('success', 'Partner marked as exited.');
    }

    public function storeRebalance(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'effective_from' => ['required', 'date'],
            'percentages' => ['required', 'array', 'min:1'],
            'percentages.*' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        try {
            app(RecordOwnershipRebalance::class)->handle(
                array_map(fn ($p) => (string) $p, $validated['percentages']),
                $validated['effective_from'],
                $request->user()->id,
            );
        } catch (OwnershipPercentagesMustSumTo100Exception|RebalanceMustCoverEveryActivePartnerException|InvalidOwnershipDateRangeException $e) {
            return back()->withErrors(['rebalance' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Ownership rebalanced.');
    }

    public function storeCapital(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
            'type' => ['required', 'in:contribution,withdrawal'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'entry_date' => ['required', 'date'],
        ]);

        $partner = Partner::findOrFail($validated['partner_id']);
        $amount = (string) $validated['amount'];

        $validated['type'] === 'contribution'
            ? app(RecordCapitalContribution::class)->handle($partner, $amount, $validated['entry_date'], $request->user()->id)
            : app(RecordCapitalWithdrawal::class)->handle($partner, $amount, $validated['entry_date'], $request->user()->id);

        return back()->with('success', 'Capital entry recorded.');
    }

    public function storeLoan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
            'principal_amount' => ['required', 'numeric', 'gt:0'],
            'issued_at' => ['required', 'date'],
        ]);

        $partner = Partner::findOrFail($validated['partner_id']);

        app(IssuePartnerLoan::class)->handle($partner, (string) $validated['principal_amount'], $validated['issued_at'], $request->user()->id);

        return back()->with('success', 'Loan issued.');
    }

    public function storeRepayment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'loan_id' => ['required', 'integer', 'exists:partner_loans,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'repaid_at' => ['required', 'date'],
        ]);

        $loan = PartnerLoan::findOrFail($validated['loan_id']);

        try {
            app(RecordLoanRepayment::class)->handle($loan, (string) $validated['amount'], $validated['repaid_at'], $request->user()->id);
        } catch (RepaymentExceedsOutstandingBalanceException $e) {
            return back()->withErrors(['repayment' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Repayment recorded.');
    }
}
