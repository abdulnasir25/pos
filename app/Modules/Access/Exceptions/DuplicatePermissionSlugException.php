<?php

namespace App\Modules\Access\Exceptions;

use InvalidArgumentException;

class DuplicatePermissionSlugException extends InvalidArgumentException
{
    public static function forSlug(string $slug): self
    {
        return new self("A permission with slug [{$slug}] already exists.");
    }
}
