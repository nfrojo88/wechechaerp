<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProcurementStatusRenameMigrationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../../database/migrations/2026_11_16_000001_rename_pending_hr_approval_status_to_pending_coordinator_approval.php';

        $this->user = User::firstOrCreate(
            ['email' => 'mig_tester@wechecha.local'],
            ['name' => 'Migration Tester', 'password' => bcrypt('password')]
        );

        $this->project = Project::firstOrCreate(
            ['name' => 'Migration Test Project'],
            ['code' => 'MIG-PRJ', 'status' => 'active']
        );
    }

    /**
     * Test that the migration converts existing rows from pending_hr_approval
     * to pending_coordinator_approval, and reverses cleanly in down().
     */
    public function test_migration_converts_old_rows_and_rolls_back_correctly(): void
    {
        $migration = new \RenamePendingHrApprovalStatusToPendingCoordinatorApproval();

        // 1. Insert test records with legacy status 'pending_hr_approval'
        $prId = DB::table('purchase_requests')->insertGetId([
            'pr_no'               => 'PR-MIG-' . rand(1000, 9999),
            'project_id'          => $this->project->id,
            'requested_by'        => $this->user->id,
            'status'              => 'pending_hr_approval',
            'current_owner_role' => 'coordinator',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $logId = null;
        if (Schema::hasTable('pr_workflow_logs')) {
            $logId = DB::table('pr_workflow_logs')->insertGetId([
                'purchase_request_id' => $prId,
                'from_stage'          => 'pending_planning_approval',
                'to_stage'            => 'pending_hr_approval',
                'action'              => 'planning_approve_to_coordinator',
                'actor_role'          => 'planning',
                'actor_id'            => $this->user->id,
                'created_at'          => now(),
            ]);
        }

        // Verify initial state
        $this->assertEquals('pending_hr_approval', DB::table('purchase_requests')->where('id', $prId)->value('status'));
        if ($logId) {
            $this->assertEquals('pending_hr_approval', DB::table('pr_workflow_logs')->where('id', $logId)->value('to_stage'));
        }

        // 2. Run up() -> must convert to pending_coordinator_approval
        $migration->up();

        $this->assertEquals('pending_coordinator_approval', DB::table('purchase_requests')->where('id', $prId)->value('status'));
        if ($logId) {
            $this->assertEquals('pending_coordinator_approval', DB::table('pr_workflow_logs')->where('id', $logId)->value('to_stage'));
        }

        // 3. Run down() -> must roll back to pending_hr_approval
        $migration->down();

        $this->assertEquals('pending_hr_approval', DB::table('purchase_requests')->where('id', $prId)->value('status'));
        if ($logId) {
            $this->assertEquals('pending_hr_approval', DB::table('pr_workflow_logs')->where('id', $logId)->value('to_stage'));
        }

        // 4. Run up() again -> idempotent and leaves rows in pending_coordinator_approval
        $migration->up();

        $this->assertEquals('pending_coordinator_approval', DB::table('purchase_requests')->where('id', $prId)->value('status'));
        if ($logId) {
            $this->assertEquals('pending_coordinator_approval', DB::table('pr_workflow_logs')->where('id', $logId)->value('to_stage'));
        }
    }
}
