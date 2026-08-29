<?php

namespace App\Modules\Customers\Enums;

enum CustomerLedgerEntryType: string
{
    case SaleCharge = 'sale_charge';
    case Payment = 'payment';
    case ReturnCredit = 'return_credit';
    case PaymentReversal = 'payment_reversal';
    case Adjustment = 'adjustment';
}
