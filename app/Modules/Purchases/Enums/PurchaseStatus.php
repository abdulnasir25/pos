<?php

namespace App\Modules\Purchases\Enums;

enum PurchaseStatus: string
{
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Returned = 'returned';
}
