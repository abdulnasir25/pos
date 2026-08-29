<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Same gap as 0020/0021/0022: Accounting was built without
     * registering its own permission in the 0010 baseline seed.
     * Additive — inserts it, grants it to super_admin.
     */
    public function up(): void
    {
        $now = now();

        $permissionId = DB::table('permissions')->insertGetId([
            'slug' => 'accounting.view',
            'description' => 'View the chart of accounts and post journal entries',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $superAdminRoleId = DB::table('roles')->where('slug', 'super_admin')->value('id');

        if ($superAdminRoleId !== null) {
            DB::table('role_permissions')->insert([
                'role_id' => $superAdminRoleId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('slug', 'accounting.view')->value('id');

        if ($permissionId !== null) {
            DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
