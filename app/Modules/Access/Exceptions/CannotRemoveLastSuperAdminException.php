<?php

namespace App\Modules\Access\Exceptions;

use RuntimeException;

class CannotRemoveLastSuperAdminException extends RuntimeException
{
    public static function forUser(int $userId): self
    {
        return new self(
            "Cannot remove the super_admin role from user #{$userId}: they are the last Super Admin ".
            'in this tenant. Assign super_admin to another user first.'
        );
    }
}
