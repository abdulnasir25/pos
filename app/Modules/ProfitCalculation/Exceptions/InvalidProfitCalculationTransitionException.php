<?php

namespace App\Modules\ProfitCalculation\Exceptions;

use RuntimeException;

class InvalidProfitCalculationTransitionException extends RuntimeException
{
    public static function alreadyFinalized(int $id): self
    {
        return new self("Profit calculation #{$id} is already finalized.");
    }
}
