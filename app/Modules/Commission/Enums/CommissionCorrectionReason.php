<?php

namespace App\Modules\Commission\Enums;

enum CommissionCorrectionReason: string
{
    case SaleReturn = 'sale_return';
    case SaleCancellation = 'sale_cancellation';
    case ManualAdjustment = 'manual_adjustment';
}
