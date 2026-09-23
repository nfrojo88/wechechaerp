<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AddReceiptPermissions extends Migration
{
    public function up()
    {
        // Reset cached roles/permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        // Create permissions if they don't exist
        $manage = Permission::firstOrCreate(['name' => 'manage-receipts', 'guard_name' => $guard]);
        $view   = Permission::firstOrCreate(['name' => 'view-receipts',   'guard_name' => $guard]);

        // Roles that get full management access
        $managerRoles = ['admin', 'global_admin', 'finance'];
        foreach ($managerRoles as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();
            if ($role) {
                $role->givePermissionTo($manage);
                $role->givePermissionTo($view);
            }
        }

        // Roles that only get view access
        $viewerRoles = ['auditor'];
        foreach ($viewerRoles as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();
            if ($role) {
                $role->givePermissionTo($view);
            }
        }
    }

    public function down()
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('name', 'manage-receipts')->delete();
        Permission::where('name', 'view-receipts')->delete();
    }
}
