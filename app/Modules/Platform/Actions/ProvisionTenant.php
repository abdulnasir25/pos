<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Access\Actions\AssignRoleToUser;
use App\Modules\Access\Models\Role;
use App\Modules\Platform\DTOs\ProvisionedTenant;
use App\Modules\Platform\Exceptions\DuplicateTenantSlugException;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Tenancy\Support\TenantConnectionFactory;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use Throwable;

/**
 * The only supported way to provision a tenant, shared by the
 * `tenants:create` console command and the landlord "add a shop" form:
 * create the landlord row, create its database (a SQLite file, or a
 * MySQL schema — whichever TENANT_DB_DRIVER selects), run every tenant
 * migration against it, then seed the shop's own first login (a Super
 * Admin user) so the tenant is actually usable the moment this
 * returns, not just an empty schema nobody can sign into. Runs as one
 * logical unit — if any step fails, the half-created tenant is torn
 * back down rather than left in a broken "provisioning" state.
 */
class ProvisionTenant
{
    public function __construct(
        private readonly TenantConnectionFactory $connections,
        private readonly TenantContext $context,
    ) {}

    /**
     * $ownerName/$ownerEmail are optional — omit both to provision a
     * bare tenant with no login (e.g. tooling that just needs an
     * isolated database to test against). Supply both to also seed
     * the shop's first (Super Admin) user, which is what every
     * real-world caller — the landlord form, a human running the CLI
     * — actually wants.
     */
    public function handle(string $name, ?string $slug, ?string $ownerName = null, ?string $ownerEmail = null): ProvisionedTenant
    {
        $slug = $slug ?: Str::slug($name);

        if (Tenant::where('slug', $slug)->exists()) {
            throw DuplicateTenantSlugException::forSlug($slug);
        }

        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'database' => $slug,
            'status' => 'provisioning',
        ]);

        $ownerPassword = $ownerEmail !== null ? Str::password(12, symbols: false) : null;

        try {
            $this->connections->usesMysql()
                ? $this->createMysqlDatabase($slug)
                : $this->createSqliteFile($this->connections->databasePathFor($tenant));

            $connectionName = $this->connections->useConnectionFor($tenant);

            Artisan::call('migrate', [
                '--database' => $connectionName,
                '--path' => 'database/migrations/tenant',
                '--realpath' => false,
                '--force' => true,
            ]);

            if ($ownerName !== null && $ownerEmail !== null) {
                $this->createOwner($tenant, $connectionName, $ownerName, $ownerEmail, $ownerPassword);
            }

            $tenant->update(['status' => 'active']);
        } catch (Throwable $e) {
            $this->connections->usesMysql()
                ? $this->dropMysqlDatabase($slug)
                : File::delete($this->connections->databasePathFor($tenant));

            $tenant->delete();

            throw $e;
        }

        return new ProvisionedTenant($tenant, $ownerEmail, $ownerPassword);
    }

    /**
     * User/Role are tenant-scoped models (HasTenantScopedQueries) that
     * refuse to run without a resolved TenantContext and the default
     * connection pointed at this tenant — exactly what IdentifyTenant
     * sets up per-request. Provisioning runs outside any request for
     * this tenant, so it has to set up and tear down that same state
     * by hand, restoring the landlord connection no matter what
     * happens in between.
     */
    private function createOwner(Tenant $tenant, string $connectionName, string $ownerName, string $ownerEmail, string $ownerPassword): void
    {
        $previousDefault = config('database.default');
        Config::set('database.default', $connectionName);
        $this->context->set($tenant);

        try {
            $owner = User::create([
                'name' => $ownerName,
                'email' => $ownerEmail,
                'password' => $ownerPassword,
            ]);

            $superAdmin = Role::where('slug', 'super_admin')->firstOrFail();

            app(AssignRoleToUser::class)->handle($owner, $superAdmin);
        } finally {
            $this->context->clear();
            Config::set('database.default', $previousDefault);
        }
    }

    private function createSqliteFile(string $path): void
    {
        File::ensureDirectoryExists(dirname($path));

        if (! File::exists($path)) {
            File::put($path, '');
        }
    }

    /**
     * A tenant's schema doesn't exist yet, so this can't go through the
     * 'tenant' Laravel connection (which needs a database name to
     * connect at all) — a bare administrative PDO connection to the
     * server itself is the only way to run CREATE/DROP DATABASE.
     */
    private function administrativePdo(): PDO
    {
        $config = config('database.connections.tenant');

        return new PDO(
            "mysql:host={$config['host']};port={$config['port']}",
            $config['username'],
            $config['password'],
        );
    }

    private function createMysqlDatabase(string $name): void
    {
        $this->administrativePdo()->exec(
            "CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
    }

    private function dropMysqlDatabase(string $name): void
    {
        $this->administrativePdo()->exec("DROP DATABASE IF EXISTS `{$name}`");
    }
}
