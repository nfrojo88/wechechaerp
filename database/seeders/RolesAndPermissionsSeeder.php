<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // -------------------------------------------------------
        // PERMISSIONS
        // -------------------------------------------------------
        $permissions = [
            // Users
            'users.view', 'users.create', 'users.edit', 'users.delete',

            // Stores
            'stores.view', 'stores.create', 'stores.edit', 'stores.delete',

            // Projects
            'projects.view', 'projects.create', 'projects.edit', 'projects.delete',

            // Products
            'products.view', 'products.create', 'products.edit', 'products.delete',

            // Inventory
            'inventory.view', 'inventory.create', 'inventory.edit', 'inventory.delete',

            // Schedules (Phase 3)
            'schedule.view', 'schedule.create', 'schedule.edit', 'schedule.delete', 'schedule.approve',

            // BOQ (Phase 3)
            'boq.view', 'boq.create', 'boq.edit', 'boq.delete', 'boq.approve',

            // Material Requests (Phase 4)
            'material_requests.view', 'material_requests.create', 'material_requests.edit',
            'material_requests.delete', 'material_requests.approve',

            // Material Transfers (Phase 4)
            'material_transfers.view', 'material_transfers.create', 'material_transfers.approve',

            // Purchases (Phase 4)
            'purchases.view', 'purchases.create', 'purchases.edit', 'purchases.delete',
            'purchases.approve', 'purchases.receive',

            // Finance (Phase 5)
            'finance.view', 'finance.create', 'finance.edit', 'finance.delete', 'finance.approve',
            'chart_of_accounts.view', 'chart_of_accounts.create', 'chart_of_accounts.edit',
            'expenses.view', 'expenses.create', 'expenses.edit', 'expenses.approve',
            'banking.view', 'banking.create', 'banking.edit',

            // HR (Phase 6)
            'hr.view', 'hr.create', 'hr.edit', 'hr.delete',
            'attendance.view', 'attendance.create', 'attendance.edit',
            'payroll.view', 'payroll.create', 'payroll.approve',

            // Subcontractors (Phase 7)
            'subcon.view', 'subcon.create', 'subcon.edit', 'subcon.delete', 'subcon.approve',

            // Bidding / Tender (Phase 8)
            'bidding.view', 'bidding.create', 'bidding.edit', 'bidding.delete',

            // Equipment (Phase 9)
            'equipment.view', 'equipment.create', 'equipment.edit', 'equipment.delete',

            // Reports (Phase 10)
            'reports.view', 'reports.export',
            'reports.daily.view', 'reports.daily.create',
            'reports.weekly.view', 'reports.weekly.create',

            // Finance IPC
            'finance.ipcs.manage',

            // Payments
            'payments.view', 'payments.create', 'payments.edit', 'payments.approve', 'payments.delete',

            // Resources
            'resources.dispatch',

            // Audit
            'audit.view',

            // Planning
            'planning.view',
            
            // Foreman Specific
            'material_damage_reports.view', 'material_damage_reports.create',
            'tool_transactions.view', 'tool_transactions.create',

            // Engineer Work Scheduling
            'eng_schedule.view',
            'eng_schedule.create',
            'eng_schedule.assign',
            'eng_schedule.manage',

            // Planning Workflow (5-stage approval chain)
            'plan_workflow.submit',            // Planning team submits plan for review
            'plan_workflow.approve_planning',  // Planning Manager approves step 1
            'plan_workflow.approve_coord',     // Coordinator approves step 2
            'plan_workflow.approve_tech',      // Technical Manager approves step 3
            'plan_workflow.approve_gm',        // GM approves step 4 + allocates budget
            'plan_workflow.reject',            // Any approver can reject
            'plan_workflow.view',              // View workflow status

            // Budget
            'budget.view',                     // Read-only budget visibility
            'budget.allocate',                 // GM-only: allocate/supplement budget

            // Procurement Lifecycle 14-Stage Permissions
            'procurement.mr.plan_approve',
            'procurement.pr.store_manager',
            'procurement.pr.procurement_manager',
            'procurement.pr.procurement_team',
            'procurement.pr.marketing_variance',
            'procurement.pr.gm_decide',
            'procurement.pr.finance_head',
            'procurement.pr.finance_staff',
            'procurement.pr.driver_booking',
            'procurement.pr.store_intake',
            'procurement.lifecycle.view',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // -------------------------------------------------------
        // ROLES
        // -------------------------------------------------------

        $roles = [
            'global_admin' => $permissions, // all permissions

            'admin' => [
                'users.view', 'users.create', 'users.edit',
                'stores.view', 'stores.create', 'stores.edit',
                'projects.view', 'projects.create', 'projects.edit',
                'products.view', 'products.create', 'products.edit',
                'inventory.view', 'inventory.create', 'inventory.edit',
                'reports.view', 'reports.export',
                'audit.view',
            ],

            'gm' => [
                'projects.view', 'projects.create', 'projects.edit', 'projects.delete',
                'finance.view', 'finance.approve',
                'purchases.view', 'purchases.approve',
                'payroll.view', 'payroll.approve',
                'subcon.view', 'subcon.approve',
                'reports.view', 'reports.export',
                'bidding.view', 'bidding.edit',
                // Workflow & Budget
                'plan_workflow.view', 'plan_workflow.approve_gm', 'plan_workflow.reject',
                'budget.view', 'budget.allocate',
            ],

            'planning_manager' => [
                'projects.view', 'schedule.view', 'schedule.create', 'schedule.edit', 'schedule.approve',
                'boq.view', 'boq.create', 'boq.edit', 'boq.approve',
                'material_requests.view', 'material_requests.approve',
                'reports.view', 'planning.view',
                // Engineer Work Scheduling
                'eng_schedule.view', 'eng_schedule.create', 'eng_schedule.assign', 'eng_schedule.manage',
                // Workflow & Budget
                'plan_workflow.view', 'plan_workflow.approve_planning', 'plan_workflow.reject',
                'budget.view',
            ],

            'planning' => [
                'projects.view', 'schedule.view', 'schedule.create', 'schedule.edit',
                'boq.view', 'boq.create', 'boq.edit',
                'material_requests.view', 'planning.view',
                // Engineer Work Scheduling
                'eng_schedule.view', 'eng_schedule.create', 'eng_schedule.assign',
                // Workflow & Budget
                'plan_workflow.view', 'plan_workflow.submit',
                'budget.view',
            ],

            'technical_manager' => [
                'projects.view', 'schedule.view', 'schedule.approve',
                'boq.view', 'boq.approve',
                'material_requests.view', 'material_requests.approve',
                'reports.view', 'planning.view',
                // Engineer Work Scheduling
                'eng_schedule.view', 'eng_schedule.assign',
                // Workflow
                'plan_workflow.view', 'plan_workflow.approve_tech', 'plan_workflow.reject',
            ],

            'site_engineer' => [
                'projects.view',
                'schedule.view',
                'material_requests.view', 'material_requests.create',
                'inventory.view',
                'attendance.view', 'attendance.create',
                'reports.daily.view', 'reports.daily.create',
                'reports.weekly.view', 'reports.weekly.create', 'reports.view',
                'resources.dispatch',
                'boq.view',
                'payments.view', 'finance.ipcs.manage', 'subcon.view',
                // Engineer Work Scheduling — view own tasks only
                'eng_schedule.view',
            ],

            'foreman' => [
                'projects.view',
                'material_requests.view', 'material_requests.create',
                'inventory.view',
                'attendance.view', 'attendance.create',
                'material_damage_reports.view', 'material_damage_reports.create',
                'tool_transactions.view', 'tool_transactions.create',
                // Engineer Work Scheduling — view own tasks only
                'eng_schedule.view',
            ],

            'store_manager' => [
                'stores.view', 'stores.edit',
                'products.view', 'products.create', 'products.edit',
                'inventory.view', 'inventory.create', 'inventory.edit',
                'material_requests.view', 'material_requests.approve',
                'material_transfers.view', 'material_transfers.create', 'material_transfers.approve',
                'purchases.view', 'purchases.receive',
                'reports.view',
                'material_damage_reports.view',
                'tool_transactions.view',
            ],

            'store_keeper' => [
                'stores.view',
                'products.view',
                'inventory.view', 'inventory.edit',
                'material_requests.view',
                'material_transfers.view', 'material_transfers.create',
                'purchases.view', 'purchases.receive',
                'material_damage_reports.view',
                'tool_transactions.view', 'tool_transactions.create',
            ],

            'finance_head' => [
                'finance.view', 'finance.create', 'finance.edit', 'finance.approve',
                'chart_of_accounts.view', 'chart_of_accounts.create', 'chart_of_accounts.edit',
                'expenses.view', 'expenses.create', 'expenses.edit', 'expenses.approve',
                'banking.view', 'banking.create', 'banking.edit',
                'payroll.view', 'payroll.approve',
                'reports.view', 'reports.export',
            ],

            'finance' => [
                'finance.view', 'finance.create', 'finance.edit',
                'chart_of_accounts.view',
                'expenses.view', 'expenses.create', 'expenses.edit',
                'banking.view', 'banking.create',
                'payroll.view',
                'reports.view',
            ],

            'purchase_manager' => [
                'purchases.view', 'purchases.create', 'purchases.edit', 'purchases.approve',
                'material_requests.view', 'material_requests.approve',
                'products.view', 'products.create', 'products.edit',
                'reports.view',
                // Budget visibility
                'budget.view',
            ],

            'purchase' => [
                'purchases.view', 'purchases.create', 'purchases.edit',
                'material_requests.view',
                'products.view',
            ],

            'market_research' => [
                'purchases.view', 'products.view', 'products.create', 'products.edit',
                'reports.view',
            ],

            'hr' => [
                'hr.view', 'hr.create', 'hr.edit',
                'attendance.view', 'attendance.create', 'attendance.edit',
                'payroll.view', 'payroll.create',
                'reports.view',
            ],

            'hr_officer' => [
                'hr.view', 'hr.create',
                'attendance.view', 'attendance.create',
                'payroll.view',
            ],

            'coordinator' => [
                'projects.view', 'schedule.view', 'inventory.view',
                'material_requests.view', 'material_requests.create',
                'reports.view',
                // Workflow & Budget
                'plan_workflow.view', 'plan_workflow.approve_coord', 'plan_workflow.reject',
                'budget.view',
            ],

            'contract_admin' => [
                'subcon.view', 'subcon.create', 'subcon.edit', 'subcon.approve',
                'purchases.view',
                'reports.view',
            ],

            'bid_team' => [
                'bidding.view', 'bidding.create', 'bidding.edit',
                'boq.view', 'boq.create',
                'reports.view',
            ],

            'law' => [
                'subcon.view',
                'bidding.view',
                'reports.view',
            ],

            'marketing' => [
                'bidding.view', 'bidding.create', 'bidding.edit',
                'projects.view',
            ],

            'audit_team' => [
                'audit.view',
                'finance.view',
                'chart_of_accounts.view',
                'expenses.view',
                'banking.view',
                'payroll.view',
                'payments.view',
                'reports.view', 'reports.export',
                'reports.daily.view', 'reports.weekly.view',
                'inventory.view', 'stores.view', 'products.view',
                'purchases.view', 'material_requests.view', 'material_transfers.view',
                'projects.view', 'boq.view', 'schedule.view', 'subcon.view',
                'hr.view', 'attendance.view', 'users.view', 'equipment.view',
            ],

            'auditor' => [
                'audit.view',
                'finance.view',
                'chart_of_accounts.view',
                'expenses.view',
                'banking.view',
                'payroll.view',
                'payments.view',
                'reports.view', 'reports.export',
                'reports.daily.view', 'reports.weekly.view',
                'inventory.view', 'stores.view', 'products.view',
                'purchases.view', 'material_requests.view', 'material_transfers.view',
                'projects.view', 'boq.view', 'schedule.view', 'subcon.view',
                'hr.view', 'attendance.view', 'users.view', 'equipment.view',
            ],

            'Auditor' => [
                'audit.view',
                'finance.view',
                'chart_of_accounts.view',
                'expenses.view',
                'banking.view',
                'payroll.view',
                'payments.view',
                'reports.view', 'reports.export',
                'reports.daily.view', 'reports.weekly.view',
                'inventory.view', 'stores.view', 'products.view',
                'purchases.view', 'material_requests.view', 'material_transfers.view',
                'projects.view', 'boq.view', 'schedule.view', 'subcon.view',
                'hr.view', 'attendance.view', 'users.view', 'equipment.view',
            ],


            'secretary' => [
                'projects.view', 'schedule.view',
                'hr.view',
            ],

            'general_service' => [
                'stores.view',
                'inventory.view',
                'equipment.view',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($rolePermissions);
        }
    }
}
