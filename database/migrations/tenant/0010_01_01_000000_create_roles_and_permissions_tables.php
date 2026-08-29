<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roles/permissions are tenant-scoped by virtue of living in the
     * tenant's own database — no tenant_id column is needed. The
     * `super_admin` role is seeded as protected: RemoveRoleFromUser
     * refuses to strip it from the last user who holds it, so a tenant
     * can never lock itself out of its own account.
     *
     * Every tenant gets the same baseline roles and permission slugs
     * out of the box; super_admin is granted every permission that
     * exists at seed time. Other roles start with no permissions —
     * assigning them is left to whoever administers the tenant.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->boolean('is_protected')->default(false);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('description', 255);
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'role_id']);
        });

        $this->seedBaselineRolesAndPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }

    private function seedBaselineRolesAndPermissions(): void
    {
        $now = now();

        $roles = [
            ['name' => 'Super Admin', 'slug' => 'super_admin', 'is_protected' => true],
            ['name' => 'Partner', 'slug' => 'partner', 'is_protected' => false],
            ['name' => 'Manager', 'slug' => 'manager', 'is_protected' => false],
            ['name' => 'Cashier', 'slug' => 'cashier', 'is_protected' => false],
            ['name' => 'Employee', 'slug' => 'employee', 'is_protected' => false],
        ];

        foreach ($roles as &$role) {
            $role['created_at'] = $now;
            $role['updated_at'] = $now;
        }
        unset($role);

        DB::table('roles')->insert($roles);

        $permissions = [
            ['slug' => 'sales.create', 'description' => 'Confirm new sales at the POS'],
            ['slug' => 'sales.view', 'description' => 'View sales history'],
            ['slug' => 'sales.return', 'description' => 'Process sale returns'],
            ['slug' => 'sales.cancel', 'description' => 'Cancel a sale'],
            ['slug' => 'inventory.view', 'description' => 'View stock levels and movements'],
            ['slug' => 'inventory.adjust', 'description' => 'Record stock adjustments'],
            ['slug' => 'employees.manage', 'description' => 'Create and update employee records'],
            ['slug' => 'employees.view', 'description' => 'View employee records'],
            ['slug' => 'salaries.manage', 'description' => 'Change salaries and record salary payments'],
            ['slug' => 'financial_periods.manage', 'description' => 'Open, review, and close financial periods'],
            ['slug' => 'partners.manage', 'description' => 'Manage partner ownership and capital'],
            ['slug' => 'commission.manage', 'description' => 'Configure and review employee commission'],
            ['slug' => 'reports.view', 'description' => 'View business reports'],
            ['slug' => 'roles.manage', 'description' => 'Manage roles, permissions, and user access'],
            ['slug' => 'settings.manage', 'description' => 'Manage tenant settings'],
        ];

        foreach ($permissions as &$permission) {
            $permission['created_at'] = $now;
            $permission['updated_at'] = $now;
        }
        unset($permission);

        DB::table('permissions')->insert($permissions);

        $superAdminRoleId = DB::table('roles')->where('slug', 'super_admin')->value('id');
        $allPermissionIds = DB::table('permissions')->pluck('id');

        $grants = $allPermissionIds->map(fn ($permissionId) => [
            'role_id' => $superAdminRoleId,
            'permission_id' => $permissionId,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('role_permissions')->insert($grants);
    }
};
