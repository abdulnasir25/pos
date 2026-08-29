<?php

namespace App\Modules\Accounting\Enums;

enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case ContraEquity = 'contra_equity';
    case Revenue = 'revenue';
    case Expense = 'expense';
}
