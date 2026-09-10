<?php

namespace App\Modules\Platform\Console\Commands;

use App\Modules\Platform\Actions\ProvisionTenant;
use App\Modules\Platform\Exceptions\DuplicateTenantSlugException;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Thin CLI wrapper around ProvisionTenant — the same action the
 * landlord "add a shop" form uses, so both paths provision a tenant
 * (schema, migrations, and the shop's first login) identically and
 * can never drift apart.
 */
class TenantCreateCommand extends Command
{
    protected $signature = 'tenants:create
        {name : Display name of the business, e.g. "Al-Fateh Cloth House"}
        {--slug= : Subdomain slug; derived from the name if omitted}
        {--owner-name= : Name of the shop\'s first user; prompted if omitted}
        {--owner-email= : Email of the shop\'s first user; prompted if omitted}';

    protected $description = 'Provision a new tenant: landlord record, database, schema, and its first (Super Admin) login.';

    public function handle(ProvisionTenant $provision): int
    {
        $name = $this->argument('name');
        $slug = $this->option('slug') ?: Str::slug($name);

        // Only prompt when a human is actually at a terminal —
        // $input->isInteractive() is true even for Artisan::call()
        // (used by tests and other automation), which has no real
        // stdin to answer a prompt with and would abort; checking the
        // stream itself is the only reliable signal. Neither option
        // given and nobody to ask: provision a bare tenant with no
        // login, same as this command behaved before owner-seeding
        // existed.
        $interactive = $this->input->isInteractive() && stream_isatty(STDIN);
        $ownerName = $this->option('owner-name') ?: ($interactive ? $this->ask('Owner name') : null);
        $ownerEmail = $this->option('owner-email') ?: ($interactive ? $this->ask('Owner email') : null);

        try {
            $provisioned = $provision->handle($name, $slug, $ownerName, $ownerEmail);
        } catch (DuplicateTenantSlugException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error("Provisioning failed, rolling back: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Tenant [{$provisioned->tenant->slug}] provisioned and active.");

        if ($provisioned->ownerPassword !== null) {
            $this->line("Owner login — email: {$provisioned->ownerEmail}, password: {$provisioned->ownerPassword}");
            $this->warn('This password is shown once and is not stored anywhere in plain text — save it now.');
        } else {
            $this->warn('No owner was created — pass --owner-name and --owner-email to seed the shop\'s first login.');
        }

        return self::SUCCESS;
    }
}
