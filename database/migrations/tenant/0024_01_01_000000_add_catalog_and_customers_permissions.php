<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Same gap as 0020-0023: Products, Warehouses, and Customers UIs
     * were built without registering their own permission slugs in the
     * 0010 baseline seed. Additive — inserts them, grants to super_admin.
     */
    public function up(): void
    {
        $now = now();

        $slugs = [
            ['slug' => 'products.manage', 'description' => 'Manage the product catalog and units'],
            ['slug' => 'warehouses.manage', 'description' => 'Manage warehouses'],
            ['slug' => 'customers.manage', 'description' => 'Manage customers and record customer payments'],
        ];

        $superAdminRoleId = DB::table('roles')->where('slug', 'super_admin')->value('id');

        foreach ($slugs as $slug) {
            $permissionId = DB::table('permissions')->insertGetId([
                'slug' => $slug['slug'],
                'description' => $slug['description'],
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
        $slugs = ['products.manage', 'warehouses.manage', 'customers.manage'];

        $permissionIds = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
