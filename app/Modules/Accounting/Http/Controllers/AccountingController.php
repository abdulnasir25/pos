<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Actions\CreateAccount;
use App\Modules\Accounting\Actions\RecordJournalEntry;
use App\Modules\Accounting\DTOs\JournalLine;
use App\Modules\Accounting\Exceptions\DuplicateAccountCodeException;
use App\Modules\Accounting\Exceptions\InvalidJournalLineException;
use App\Modules\Accounting\Exceptions\UnbalancedJournalEntryException;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Support\GetAccountBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountingController extends \App\Http\Controllers\Controller
{
    public function show(): Response
    {
        $balance = app(GetAccountBalance::class);

        $accounts = Account::orderBy('code')
            ->get()
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type->value,
                'balance' => $balance->handle($account),
            ]);

        $entries = JournalEntry::with('lines.account:id,code,name')
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn (JournalEntry $entry) => [
                'id' => $entry->id,
                'entry_date' => $entry->entry_date->toDateString(),
                'description' => $entry->description,
                'lines' => $entry->lines->map(fn ($line) => [
                    'account' => "{$line->account->code} — {$line->account->name}",
                    'debit' => (string) $line->debit,
                    'credit' => (string) $line->credit,
                ]),
            ]);

        return Inertia::render('Accounting/Index', [
            'accounts' => $accounts,
            'entries' => $entries,
        ]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:asset,liability,equity,contra_equity,revenue,expense'],
            'parent_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
        ]);

        try {
            app(CreateAccount::class)->handle(
                $validated['code'],
                $validated['name'],
                $validated['type'],
                isset($validated['parent_id']) ? Account::find($validated['parent_id']) : null,
            );
        } catch (DuplicateAccountCodeException $e) {
            return back()->withErrors(['account' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Account added.');
    }

    public function storeEntry(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $lines = collect($validated['lines'])->map(fn ($line) => new JournalLine(
            accountId: $line['account_id'],
            debit: (string) ($line['debit'] ?? '0.00'),
            credit: (string) ($line['credit'] ?? '0.00'),
        ))->all();

        try {
            app(RecordJournalEntry::class)->handle(
                $validated['entry_date'],
                $lines,
                $request->user()->id,
                $validated['description'] ?? null,
            );
        } catch (InvalidJournalLineException|UnbalancedJournalEntryException $e) {
            return back()->withErrors(['entry' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Journal entry posted.');
    }
}
