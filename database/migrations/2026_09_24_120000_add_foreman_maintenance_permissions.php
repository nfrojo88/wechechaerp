<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        // Reset cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Register the new permission
        $perm = Permission::firstOrCreate([
            'name'       => 'asset_maintenance.report',
            'guard_name' => 'web',
        ]);

        // Assign to foreman role
        $foreman = Role::firstOrCreate(['name' => 'foreman', 'guard_name' => 'web']);
        if (!$foreman->hasPermissionTo('asset_maintenance.report')) {
            $foreman->givePermissionTo($perm);
        }

        // Admins get it too
        foreach (['admin', 'global_admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role && !$role->hasPermissionTo('asset_maintenance.report')) {
                $role->givePermissionTo($perm);
            }
        }

        // General service and store_manager can view/manage these requests
        $viewPerm = Permission::firstOrCreate([
            'name'       => 'asset_maintenance.manage',
            'guard_name' => 'web',
        ]);
        foreach (['general_service', 'store_manager', 'admin', 'global_admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role && !$role->hasPermissionTo('asset_maintenance.manage')) {
                $role->givePermissionTo($viewPerm);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::where('name', 'asset_maintenance.report')->delete();
        Permission::where('name', 'asset_maintenance.manage')->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
