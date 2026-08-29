<?php

namespace App\Modules\CashRegister\Enums;

enum FinancialAccountStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
