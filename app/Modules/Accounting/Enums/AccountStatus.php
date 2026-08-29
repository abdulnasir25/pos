<?php

namespace App\Modules\Accounting\Enums;

enum AccountStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
