<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Same gap as 0020's expenses.manage: Cash Register and Audit Log
     * were built without registering their own permissions in the
     * 0010 baseline seed. Additive — inserts both, grants them to
     * super_admin, same as every permission that existed at 0010's
     * seed time.
     */
    public function up(): void
    {
        $now = now();

        $permissions = [
            ['slug' => 'cash_register.manage', 'description' => 'Open/close cash register sessions and manage financial accounts'],
            ['slug' => 'audit_logs.view', 'description' => 'View the audit trail'],
        ];

        $superAdminRoleId = DB::table('roles')->where('slug', 'super_admin')->value('id');

        foreach ($permissions as $permission) {
            $permissionId = DB::table('permissions')->insertGetId([
                ...$permission,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($superAdminRoleId !== null) {
                DB::table('role_permissions')->insert([
                    'role_id' => $superAdminRoleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('slug', ['cash_register.manage', 'audit_logs.view'])->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
