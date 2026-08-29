<?php

namespace App\Modules\CashRegister\Http\Controllers;

use App\Modules\CashRegister\Actions\CloseCashRegisterSession;
use App\Modules\CashRegister\Actions\CreateFinancialAccount;
use App\Modules\CashRegister\Actions\OpenCashRegisterSession;
use App\Modules\CashRegister\Enums\CashRegisterSessionStatus;
use App\Modules\CashRegister\Exceptions\AccountNotACashAccountException;
use App\Modules\CashRegister\Exceptions\SessionAlreadyClosedException;
use App\Modules\CashRegister\Exceptions\SessionAlreadyOpenException;
use App\Modules\CashRegister\Models\CashRegisterSession;
use App\Modules\CashRegister\Models\FinancialAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashRegisterController extends \App\Http\Controllers\Controller
{
    public function show(): Response
    {
        $accounts = FinancialAccount::orderBy('name')
            ->get()
            ->map(function (FinancialAccount $account) {
                $openSession = CashRegisterSession::where('financial_account_id', $account->id)
                    ->where('status', CashRegisterSessionStatus::Open)
                    ->first();

                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'account_type' => $account->account_type->value,
                    'open_session' => $openSession ? [
                        'id' => $openSession->id,
                        'opening_float' => (string) $openSession->opening_float,
                        'opened_at' => $openSession->opened_at->toDateTimeString(),
                    ] : null,
                ];
            });

        $recentSessions = CashRegisterSession::with('financialAccount:id,name')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (CashRegisterSession $s) => [
                'id' => $s->id,
                'account' => $s->financialAccount?->name,
                'opening_float' => (string) $s->opening_float,
                'counted_closing' => $s->counted_closing !== null ? (string) $s->counted_closing : null,
                'status' => $s->status->value,
                'opened_at' => $s->opened_at->toDateTimeString(),
                'closed_at' => $s->closed_at?->toDateTimeString(),
            ]);

        return Inertia::render('CashRegister/Index', [
            'accounts' => $accounts,
            'recentSessions' => $recentSessions,
        ]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'account_type' => ['required', 'in:cash,bank,digital_wallet'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
        ]);

        app(CreateFinancialAccount::class)->handle(
            $validated['name'],
            $validated['account_type'],
            (string) ($validated['opening_balance'] ?? '0.00'),
        );

        return back()->with('success', 'Financial account added.');
    }

    public function openSession(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'financial_account_id' => ['required', 'integer', 'exists:financial_accounts,id'],
            'opening_float' => ['required', 'numeric', 'min:0'],
        ]);

        $account = FinancialAccount::findOrFail($validated['financial_account_id']);

        try {
            app(OpenCashRegisterSession::class)->handle($account, $request->user()->id, (string) $validated['opening_float']);
        } catch (AccountNotACashAccountException|SessionAlreadyOpenException $e) {
            return back()->withErrors(['session' => $e->getMessage()]);
        }

        return back()->with('success', 'Session opened.');
    }

    public function closeSession(Request $request, CashRegisterSession $session): RedirectResponse
    {
        $validated = $request->validate([
            'counted_closing' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            app(CloseCashRegisterSession::class)->handle($session, $request->user()->id, (string) $validated['counted_closing']);
        } catch (SessionAlreadyClosedException $e) {
            return back()->withErrors(['session' => $e->getMessage()]);
        }

        return back()->with('success', 'Session closed.');
    }
}
