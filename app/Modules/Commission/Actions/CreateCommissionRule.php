<?php

namespace App\Modules\Commission\Actions;

use App\Modules\Commission\Enums\CommissionRuleStatus;
use App\Modules\Commission\Models\CommissionRule;
use App\Modules\Employees\Models\Employee;

class CreateCommissionRule
{
    public function handle(?Employee $employee, string $rate, string $effectiveFrom, string $basis = 'gross_profit'): CommissionRule
    {
        return CommissionRule::create([
            'employee_id' => $employee?->id,
            'basis' => $basis,
            'rate' => $rate,
            'effective_from' => $effectiveFrom,
            'status' => CommissionRuleStatus::Active,
        ]);
    }
}
