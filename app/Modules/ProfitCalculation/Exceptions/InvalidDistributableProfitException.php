<?php

namespace App\Modules\ProfitCalculation\Exceptions;

use InvalidArgumentException;

class InvalidDistributableProfitException extends InvalidArgumentException
{
    public static function outOfRange(string $distributable, string $netProfit): self
    {
        return new self(
            "distributable_profit [{$distributable}] must be between 0 and max(net_profit, 0) ".
            "— net_profit is [{$netProfit}]."
        );
    }
}
