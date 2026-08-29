<?php

namespace App\Modules\Commission\Enums;

enum CommissionEntryStatus: string
{
    case Calculated = 'calculated';
    case Approved = 'approved';
    case Finalized = 'finalized';
    case Paid = 'paid';
}
