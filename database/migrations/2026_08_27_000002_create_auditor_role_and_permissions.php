<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            $permissions = [
                // Audit Permissions
                'audit.view',
                'admin.audit.view',
                'finance.audit.view',

                // Reports & Analytics
                'reports.view',
                'reports.export',
                'reports.daily.view',
                'reports.weekly.view',

                // Finance & Accounting (Read-Only)
                'finance.view',
                'chart_of_accounts.view',
                'expenses.view',
                'banking.view',
                'payroll.view',
                'payments.view',

                // Procurement, Inventory & Stores
                'purchases.view',
                'material_requests.view',
                'material_transfers.view',
                'inventory.view',
                'stores.view',
                'products.view',
                'material_damage_reports.view',
                'tool_transactions.view',

                // Projects, Contracts, BOQ
                'projects.view',
                'boq.view',
                'schedule.view',
                'subcon.view',
                'bidding.view',

                // HR & Manpower
                'hr.view',
                'attendance.view',
                'users.view',

                // Equipment & Fixed Assets
                'equipment.view',
            ];

            foreach ($permissions as $permName) {
                Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
            }

            // Create auditor roles
            $auditorRole = Role::firstOrCreate(['name' => 'auditor', 'guard_name' => 'web']);
            $auditorRoleTitle = Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);
            $auditTeamRole = Role::firstOrCreate(['name' => 'audit_team', 'guard_name' => 'web']);

            $auditorRole->syncPermissions($permissions);
            $auditorRoleTitle->syncPermissions($permissions);
            $auditTeamRole->syncPermissions($permissions);

            app()[PermissionRegistrar::class]->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // Log or ignore if table not ready
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op to preserve audit history
    }
};
