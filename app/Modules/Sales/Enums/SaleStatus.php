<?php

namespace App\Modules\Sales\Enums;

enum SaleStatus: string
{
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
}
