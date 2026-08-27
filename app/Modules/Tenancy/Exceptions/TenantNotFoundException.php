<?php

namespace App\Modules\Tenancy\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TenantNotFoundException extends NotFoundHttpException
{
    public static function forHost(string $host): self
    {
        return new self("No tenant is registered for host [{$host}].");
    }
}
