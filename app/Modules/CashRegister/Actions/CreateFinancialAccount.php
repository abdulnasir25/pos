<?php

namespace App\Modules\CashRegister\Actions;

use App\Modules\CashRegister\Enums\FinancialAccountStatus;
use App\Modules\CashRegister\Models\FinancialAccount;

class CreateFinancialAccount
{
    public function handle(string $name, string $accountType, string $openingBalance = '0.00'): FinancialAccount
    {
        return FinancialAccount::create([
            'name' => $name,
            'account_type' => $accountType,
            'opening_balance' => $openingBalance,
            'status' => FinancialAccountStatus::Active,
        ]);
    }
}
