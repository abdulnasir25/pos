<?php

namespace App\Modules\Employees\Actions;

use App\Modules\Employees\Models\Employee;

/**
 * Removes login access without terminating employment or touching the
 * User row itself — the two lifecycles stay independent.
 */
class UnlinkEmployeeFromUser
{
    public function handle(Employee $employee): Employee
    {
        $employee->update(['user_id' => null]);

        return $employee->fresh();
    }
}
