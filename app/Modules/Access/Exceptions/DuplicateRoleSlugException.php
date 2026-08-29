<?php

namespace App\Modules\Access\Exceptions;

use InvalidArgumentException;

class DuplicateRoleSlugException extends InvalidArgumentException
{
    public static function forSlug(string $slug): self
    {
        return new self("A role with slug [{$slug}] already exists.");
    }
}
