<?php

namespace App\Modules\Billing\Enums;

enum BillingInterval: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';
}
