<?php

namespace App\Modules\Billing\Exceptions;

use RuntimeException;

class TenantAlreadyHasActiveSubscriptionException extends RuntimeException
{
    public static function forTenant(int $tenantId): self
    {
        return new self("Tenant #{$tenantId} already has an active subscription — cancel it first.");
    }
}
