<?php

namespace App\Modules\Settings\Support;

use App\Modules\Settings\Models\Setting;
use App\Modules\Tenancy\Support\TenantContext;

/**
 * The only supported way to read the shop's settings. A brand-new
 * tenant has no settings row yet — rather than requiring the
 * provisioning flow to seed one, this creates it lazily on first
 * access, defaulting shop_name to the landlord's own record of the
 * tenant's name so the shop isn't unnamed until someone visits the
 * Settings page.
 */
class CurrentSettings
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function get(): Setting
    {
        return Setting::first() ?? Setting::create([
            'shop_name' => $this->tenantContext->get()->name,
            'currency_symbol' => 'Rs.',
        ]);
    }
}
