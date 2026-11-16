<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RenamePendingHrApprovalStatusToPendingCoordinatorApproval extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        // 1. Update purchase_requests
        if (Schema::hasTable('purchase_requests') && Schema::hasColumn('purchase_requests', 'status')) {
            DB::table('purchase_requests')
                ->where('status', 'pending_hr_approval')
                ->update(['status' => 'pending_coordinator_approval']);
        }

        // 2. Update pr_workflow_logs
        if (Schema::hasTable('pr_workflow_logs')) {
            if (Schema::hasColumn('pr_workflow_logs', 'from_stage')) {
                DB::table('pr_workflow_logs')
                    ->where('from_stage', 'pending_hr_approval')
                    ->update(['from_stage' => 'pending_coordinator_approval']);
            }
            if (Schema::hasColumn('pr_workflow_logs', 'to_stage')) {
                DB::table('pr_workflow_logs')
                    ->where('to_stage', 'pending_hr_approval')
                    ->update(['to_stage' => 'pending_coordinator_approval']);
            }
        }

        // 3. Update material_requests
        if (Schema::hasTable('material_requests') && Schema::hasColumn('material_requests', 'status')) {
            DB::table('material_requests')
                ->where('status', 'pending_hr_approval')
                ->update(['status' => 'pending_coordinator_approval']);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        // 1. Revert purchase_requests
        if (Schema::hasTable('purchase_requests') && Schema::hasColumn('purchase_requests', 'status')) {
            DB::table('purchase_requests')
                ->where('status', 'pending_coordinator_approval')
                ->update(['status' => 'pending_hr_approval']);
        }

        // 2. Revert pr_workflow_logs
        if (Schema::hasTable('pr_workflow_logs')) {
            if (Schema::hasColumn('pr_workflow_logs', 'from_stage')) {
                DB::table('pr_workflow_logs')
                    ->where('from_stage', 'pending_coordinator_approval')
                    ->update(['from_stage' => 'pending_hr_approval']);
            }
            if (Schema::hasColumn('pr_workflow_logs', 'to_stage')) {
                DB::table('pr_workflow_logs')
                    ->where('to_stage', 'pending_coordinator_approval')
                    ->update(['to_stage' => 'pending_hr_approval']);
            }
        }

        // 3. Revert material_requests
        if (Schema::hasTable('material_requests') && Schema::hasColumn('material_requests', 'status')) {
            DB::table('material_requests')
                ->where('status', 'pending_coordinator_approval')
                ->update(['status' => 'pending_hr_approval']);
        }
    }
}
