<?php

namespace App\Modules\Partners\Enums;

enum LoanStatus: string
{
    case Outstanding = 'outstanding';
    case Repaid = 'repaid';
}
