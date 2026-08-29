<?php

namespace App\Modules\Billing\Enums;

enum PlanStatus: string
{
    case Active = 'active';
    case Retired = 'retired';
}
