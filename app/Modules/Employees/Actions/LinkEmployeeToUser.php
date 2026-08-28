<?php

namespace App\Modules\Employees\Actions;

use App\Modules\Employees\Models\Employee;

/**
 * An employee can gain a login, lose it, and later gain a different one
 * — none of that touches employment history. This Action only ever
 * changes employees.user_id; it never touches the users table.
 */
class LinkEmployeeToUser
{
    public function handle(Employee $employee, int $userId): Employee
    {
        $employee->update(['user_id' => $userId]);

        return $employee->fresh();
    }
}
