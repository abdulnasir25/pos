<?php

namespace App\Modules\Expenses\Enums;

enum ExpenseCategoryStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
