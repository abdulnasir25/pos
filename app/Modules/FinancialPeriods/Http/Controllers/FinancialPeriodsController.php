<?php

namespace App\Modules\FinancialPeriods\Http\Controllers;

use App\Models\User;
use App\Modules\FinancialPeriods\Actions\ClosePeriod;
use App\Modules\FinancialPeriods\Actions\CreateFinancialPeriod;
use App\Modules\FinancialPeriods\Actions\MoveToReview;
use App\Modules\FinancialPeriods\Actions\StartCalculation;
use App\Modules\FinancialPeriods\Exceptions\InvalidPeriodRangeException;
use App\Modules\FinancialPeriods\Exceptions\InvalidPeriodTransitionException;
use App\Modules\FinancialPeriods\Exceptions\OverlappingPeriodException;
use App\Modules\FinancialPeriods\Models\FinancialPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinancialPeriodsController extends \App\Http\Controllers\Controller
{
    public function show(): Response
    {
        $reviewerNames = User::pluck('name', 'id');

        $periods = FinancialPeriod::orderByDesc('period_start')
            ->get()
            ->map(fn (FinancialPeriod $period) => [
                'id' => $period->id,
                'period_start' => $period->period_start->toDateString(),
                'period_end' => $period->period_end->toDateString(),
                'status' => $period->status->value,
                'calculated_at' => $period->calculated_at?->toDateTimeString(),
                'reviewed_by' => $period->reviewed_by ? ($reviewerNames[$period->reviewed_by] ?? null) : null,
                'closed_at' => $period->closed_at?->toDateTimeString(),
            ]);

        return Inertia::render('FinancialPeriods/Index', [
            'periods' => $periods,
        ]);
    }

    public function store(Request $request): RedirectResponse
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

    public function storeCalculation(FinancialPeriod $period): RedirectResponse
    {
        try {
            app(StartCalculation::class)->handle($period);
        } catch (InvalidPeriodTransitionException $e) {
            return back()->withErrors(['transition' => $e->getMessage()]);
        }

        return back()->with('success', 'Calculation started.');
    }

    public function storeReview(Request $request, FinancialPeriod $period): RedirectResponse
    {
        try {
            app(MoveToReview::class)->handle($period, $request->user()->id);
        } catch (InvalidPeriodTransitionException $e) {
            return back()->withErrors(['transition' => $e->getMessage()]);
        }

        return back()->with('success', 'Period moved to review.');
    }

    public function storeClose(FinancialPeriod $period): RedirectResponse
    {
        try {
            app(ClosePeriod::class)->handle($period);
        } catch (InvalidPeriodTransitionException $e) {
            return back()->withErrors(['transition' => $e->getMessage()]);
        }

        return back()->with('success', 'Period closed.');
    }
}
