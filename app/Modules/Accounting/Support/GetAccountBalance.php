<?php

namespace App\Modules\Accounting\Support;

use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Models\Account;

/**
 * An account's normal balance side depends on its type: Asset,
 * Expense, and ContraEquity increase with a debit; Liability, Equity,
 * and Revenue increase with a credit. Returns the balance signed from
 * that account's own normal-balance perspective — always the "amount
 * this account actually holds," never a raw debit-minus-credit that
 * would read negative for every credit-normal account.
 */
class GetAccountBalance
{
    private const DEBIT_NORMAL = [
        AccountType::Asset,
        AccountType::Expense,
        AccountType::ContraEquity,
    ];

    public function handle(Account $account): string
    {
        $totalDebits = bcadd('0', (string) $account->lines()->sum('debit'), 2);
        $totalCredits = bcadd('0', (string) $account->lines()->sum('credit'), 2);

        return in_array($account->type, self::DEBIT_NORMAL, true)
            ? bcsub($totalDebits, $totalCredits, 2)
            : bcsub($totalCredits, $totalDebits, 2);
    }
}
