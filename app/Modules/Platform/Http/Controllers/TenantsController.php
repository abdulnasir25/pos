<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Platform\Actions\ProvisionTenant;
use App\Modules\Platform\Exceptions\DuplicateTenantSlugException;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TenantsController extends \App\Http\Controllers\Controller
{
    public function show(): Response
    {
        $centralDomain = config('tenancy.central_domains')[0] ?? 'localhost';

        $tenants = Tenant::orderBy('name')->get(['id', 'name', 'slug', 'status', 'suspended_at'])
            ->map(fn (Tenant $tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
                'url' => "{$tenant->slug}.{$centralDomain}",
            ]);

        return Inertia::render('Landlord/Tenants/Index', [
            'tenants' => $tenants,
        ]);
    }

    public function store(Request $request, ProvisionTenant $provision): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:63', 'regex:/^[a-z0-9-]+$/'],
            'owner_name' => ['required', 'string', 'max:150'],
            'owner_email' => ['required', 'email', 'max:255'],
        ]);

        try {
            $provisioned = $provision->handle(
                $validated['name'],
                $validated['slug'] ?? null,
                $validated['owner_name'],
                $validated['owner_email'],
            );
        } catch (DuplicateTenantSlugException $e) {
            return back()->withErrors(['tenant' => $e->getMessage()])->withInput();
        } catch (Throwable $e) {
            return back()->withErrors(['tenant' => 'Could not provision the shop: '.$e->getMessage()])->withInput();
        }

        return back()->with('success', "Shop provisioned and active. Owner login — email: {$provisioned->ownerEmail}, password: {$provisioned->ownerPassword} (shown once, share it now).");
    }

    public function toggleStatus(Tenant $tenant): RedirectResponse
    {
        $tenant->isSuspended()
            ? $tenant->update(['status' => 'active', 'suspended_at' => null])
            : $tenant->update(['status' => 'suspended', 'suspended_at' => now()]);

        return back()->with('success', 'Shop status updated.');
    }
}
