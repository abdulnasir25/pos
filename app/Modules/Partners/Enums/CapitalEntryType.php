<?php

namespace App\Modules\Partners\Enums;

enum CapitalEntryType: string
{
    case Contribution = 'contribution';
    case Withdrawal = 'withdrawal';
}
