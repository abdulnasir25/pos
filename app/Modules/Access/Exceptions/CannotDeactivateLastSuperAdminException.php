<?php

namespace App\Modules\Access\Exceptions;

use RuntimeException;

class CannotDeactivateLastSuperAdminException extends RuntimeException
{
    public static function forUser(int $userId): self
    {
        return new self(
            "Cannot deactivate user #{$userId}: they are the last active Super Admin in this tenant. ".
            'Assign super_admin to another active user first.'
        );
    }
}
