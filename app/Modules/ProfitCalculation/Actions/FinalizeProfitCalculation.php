<?php

namespace App\Modules\ProfitCalculation\Actions;

use App\Modules\ProfitCalculation\Enums\ProfitCalculationStatus;
use App\Modules\ProfitCalculation\Exceptions\InvalidProfitCalculationTransitionException;
use App\Modules\ProfitCalculation\Models\ProfitCalculation;

/**
 * The last transition before this snapshot becomes immutable — after
 * this, CalculateProfitForPeriod refuses to touch it again. There is
 * no unfinalize; a mistake found after this point needs a fresh
 * Financial Period, not a reopened one (matching FinancialPeriod's own
 * "no reopen path" rule).
 */
class FinalizeProfitCalculation
{
    public function handle(ProfitCalculation $calculation): ProfitCalculation
    {
        if ($calculation->status === ProfitCalculationStatus::Finalized) {
            throw InvalidProfitCalculationTransitionException::alreadyFinalized($calculation->id);
        }

        $calculation->update(['status' => ProfitCalculationStatus::Finalized]);

        return $calculation;
    }
}
