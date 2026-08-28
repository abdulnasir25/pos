<?php

namespace App\Modules\Employees\Actions;

use App\Modules\Employees\Enums\EmployeeStatus;
use App\Modules\Employees\Models\Employee;

/**
 * The only supported way to change an Employee's status — never a
 * direct $employee->update(['status' => ...]). No transition graph is
 * enforced (unlike Financial Period's strictly forward lifecycle): a
 * terminated employee can be rehired, an inactive one reactivated. What
 * this Action does guard is terminated_at staying consistent with
 * status, since that timestamp is what future correction/eligibility
 * logic will need to trust.
 */
class ChangeEmployeeStatus
{
    public function handle(Employee $employee, EmployeeStatus $to, ?string $terminatedAt = null): Employee
    {
        $employee->update([
            'status' => $to,
            'terminated_at' => $to === EmployeeStatus::Terminated
                ? ($terminatedAt ?? now()->toDateString())
                : null,
        ]);

        return $employee->fresh();
    }
}
