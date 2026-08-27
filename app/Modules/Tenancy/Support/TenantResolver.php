<?php

namespace App\Modules\Tenancy\Support;

use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Exceptions\TenantNotFoundException;
use Illuminate\Http\Request;

/**
 * The only place tenant-identification logic lives. Given a request, works
 * out which tenant it belongs to. Today that's "<slug>.<central-domain>";
 * swapping to a path prefix or a custom-domain lookup table later means
 * changing this one class, not every place that currently reads a
 * subdomain out of the host.
 */
class TenantResolver
{
    public function resolve(Request $request): ?Tenant
    {
        $slug = $this->slugFromHost($request->getHost());

        if ($slug === null) {
            return null;
        }

        return Tenant::where('slug', $slug)->first();
    }

    public function resolveOrFail(Request $request): Tenant
    {
        return $this->resolve($request) ?? throw TenantNotFoundException::forHost($request->getHost());
    }

    private function slugFromHost(string $host): ?string
    {
        foreach (config('tenancy.central_domains', []) as $central) {
            $central = trim($central);

            if ($central === '') {
                continue;
            }

            if ($host === $central) {
                // Bare central domain — no tenant, this is a landlord request.
                return null;
            }

            $suffix = '.'.$central;

            if (str_ends_with($host, $suffix)) {
                return substr($host, 0, -strlen($suffix));
            }
        }

        return null;
    }
}
