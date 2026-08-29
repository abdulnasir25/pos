<?php

namespace App\Modules\CashRegister\Enums;

enum FinancialAccountType: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case DigitalWallet = 'digital_wallet';
}
