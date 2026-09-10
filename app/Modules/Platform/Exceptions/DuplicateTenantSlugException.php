<?php

namespace App\Modules\Platform\Exceptions;

use InvalidArgumentException;

class DuplicateTenantSlugException extends InvalidArgumentException
{
    public static function forSlug(string $slug): self
    {
        return new self("A shop with subdomain [{$slug}] already exists.");
    }
}
