<?php

namespace App\Modules\Tenancy\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class TenantSuspendedException extends HttpException
{
    public static function forSlug(string $slug): self
    {
        return new self(403, "Tenant [{$slug}] is suspended.");
    }
}
