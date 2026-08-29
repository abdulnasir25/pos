<?php

namespace App\Modules\CashRegister\Enums;

enum CashRegisterSessionStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
