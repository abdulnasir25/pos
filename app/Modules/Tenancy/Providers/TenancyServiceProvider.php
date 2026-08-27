<?php

namespace App\Modules\Tenancy\Providers;

use App\Modules\Tenancy\Authorization\DenyCrossTenantAuthorization;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    public function boot(): void
    {
        Gate::before(new DenyCrossTenantAuthorization);
    }
}
