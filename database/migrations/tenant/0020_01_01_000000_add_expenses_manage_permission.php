<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The Expenses module (built earlier) never registered its own
     * permission in the 0010 baseline seed — a genuine gap, caught
     * while building the Expenses UI. Additive, matching how the
     * baseline seed itself works: insert the permission, grant it to
     * super_admin (which the 0010 seed grants every permission that
     * exists at seed time) so existing tenants get the same starting
     * point a fresh tenant would.
     */
    public function up(): void
    {
        $now = now();

        $permissionId = DB::table('permissions')->insertGetId([
            'slug' => 'expenses.manage',
            'description' => 'Record and categorize business expenses',
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
        $permissionId = DB::table('permissions')->where('slug', 'expenses.manage')->value('id');

        if ($permissionId !== null) {
            DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
