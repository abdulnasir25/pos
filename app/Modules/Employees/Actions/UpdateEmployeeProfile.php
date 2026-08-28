<?php

namespace App\Modules\Employees\Actions;

use App\Modules\Employees\Models\Employee;

/**
 * Only the profile fields that carry no lifecycle meaning — name and
 * phone. Status, user linkage, and employment dates each have their own
 * dedicated Action so this one can never be used to bypass those rules.
 *
 * Both parameters are required, not optional-and-merged: an omitted
 * "not provided, don't change this" and an explicit "clear this field"
 * are different intents, and a nullable-defaults-to-skip signature can't
 * tell them apart. Callers pass the employee's current value for
 * whichever field they aren't changing.
 */
class UpdateEmployeeProfile
{
    public function handle(Employee $employee, string $name, ?string $phone): Employee
    {
        $employee->update([
            'name' => $name,
            'phone' => $phone,
        ]);

        return $employee->fresh();
    }
}
