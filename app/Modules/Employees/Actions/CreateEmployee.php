<?php

namespace App\Modules\Employees\Actions;

use App\Modules\Employees\Enums\EmployeeStatus;
use App\Modules\Employees\Models\Employee;

/**
 * The only supported way to create an Employee. user_id is optional —
 * an employee can exist with no login at all, per the confirmed
 * Employee/User distinction.
 */
class CreateEmployee
{
    public function handle(string $name, string $hiredAt, ?string $phone = null, ?int $userId = null): Employee
    {
        return Employee::create([
            'user_id' => $userId,
            'name' => $name,
            'phone' => $phone,
            'hired_at' => $hiredAt,
            'status' => EmployeeStatus::Active,
        ]);
    }
}
