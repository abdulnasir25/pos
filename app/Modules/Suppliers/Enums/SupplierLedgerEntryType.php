<?php

namespace App\Modules\Suppliers\Enums;

enum SupplierLedgerEntryType: string
{
    case PurchaseCharge = 'purchase_charge';
    case Payment = 'payment';
    case ReturnCredit = 'return_credit';
    case Adjustment = 'adjustment';
}
