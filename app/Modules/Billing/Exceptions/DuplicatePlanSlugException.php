<?php

namespace App\Modules\Billing\Exceptions;

use InvalidArgumentException;

class DuplicatePlanSlugException extends InvalidArgumentException
{
    public static function forSlug(string $slug): self
    {
        return new self("A plan with slug [{$slug}] already exists.");
    }
}
