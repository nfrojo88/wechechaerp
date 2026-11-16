<?php

namespace Tests\Feature;

use App\Models\PrWorkflowLog;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlanningApprovalStatusRenameTest extends TestCase
{
    use DatabaseTransactions;

    protected User $planningUser;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=']);

        $this->planningUser = User::firstOrCreate(
            ['email' => 'planning_mgr_test@wechecha.local'],
            ['name' => 'Planning Manager Tester', 'password' => bcrypt('password'), 'is_active' => true]
        );
        $this->planningUser->update(['is_active' => true]);

        $role = Role::firstOrCreate(['name' => 'planning_manager', 'guard_name' => 'web']);
        $this->planningUser->assignRole($role);

        $this->project = Project::firstOrCreate(
            ['name' => 'Planning Test Project'],
            ['code' => 'PLN-TEST', 'status' => 'active']
        );

        $coordUser = User::firstOrCreate(
            ['email' => 'coord_tester@wechecha.local'],
            ['name' => 'Coordinator Tester', 'password' => bcrypt('password'), 'is_active' => true]
        );
        $coordUser->update(['is_active' => true]);
        $coordRole = Role::firstOrCreate(['name' => 'coordinator', 'guard_name' => 'web']);
        $coordUser->assignRole($coordRole);
        $this->project->users()->syncWithoutDetaching([$coordUser->id]);
    }

    /**
     * Test: Planning approval sets status 'pending_coordinator_approval',
     * current_owner_role 'coordinator', and logs action 'planning_approve_to_coordinator'.
     */
    public function test_planning_approval_sets_pending_coordinator_approval_and_logs_action(): void
    {
        $pr = PurchaseRequest::create([
            'pr_no'              => 'PR-PLN-' . rand(1000, 9999),
            'project_id'         => $this->project->id,
            'requested_by'       => $this->planningUser->id,
            'status'             => PurchaseRequest::STATUS_PENDING_PLANNING,
            'current_owner_role' => 'planning',
            'justification'      => 'Test materials for planning approval',
        ]);

        $this->actingAs($this->planningUser);

        $response = $this->post(route('purchase-requests.planning-approve', $pr), [
            'notes' => 'Approved by Planning Manager for testing',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $pr->refresh();

        // 1. Verify PR status is pending_coordinator_approval
        $this->assertEquals(
            PurchaseRequest::STATUS_PENDING_COORDINATOR_APPROVAL,
            $pr->status,
            "PR status must be 'pending_coordinator_approval' after Planning approval."
        );
        $this->assertEquals('pending_coordinator_approval', $pr->status);

        // 2. Verify current owner role is coordinator
        $this->assertEquals(
            'coordinator',
            $pr->current_owner_role,
            "current_owner_role must be 'coordinator' after Planning approval."
        );

        // 3. Verify workflow log is recorded with 'planning_approve_to_coordinator'
        $log = PrWorkflowLog::where('purchase_request_id', $pr->id)
            ->where('action', 'planning_approve_to_coordinator')
            ->latest('id')
            ->first();

        $this->assertNotNull($log, "A PrWorkflowLog entry with action 'planning_approve_to_coordinator' must be created.");
        $this->assertEquals(PurchaseRequest::STATUS_PENDING_PLANNING, $log->from_stage);
        $this->assertEquals(PurchaseRequest::STATUS_PENDING_COORDINATOR_APPROVAL, $log->to_stage);
        $this->assertEquals('planning_approve_to_coordinator', $log->action);
        $this->assertEquals('planning', $log->actor_role);
        $this->assertEquals($this->planningUser->id, $log->actor_id);
    }
}
