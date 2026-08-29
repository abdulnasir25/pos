<?php

namespace App\Modules\ProfitCalculation\Enums;

enum ProfitCalculationStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';
}
