<?php

namespace App\Modules\Commission\Enums;

enum CommissionRuleStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
