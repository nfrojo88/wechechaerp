<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectBudgetAllocation;
use App\Models\ProjectPlanWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanWorkflowController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // ── Show workflow for a project ───────────────────────────────────────

    public function show(Project $project)
    {
        $this->authorize('plan_workflow.view');
        $workflow = $project->activeWorkflow()->with([
            'creator', 'planningManager', 'coordinator', 'techManager', 'gm', 'rejector'
        ])->first();

        $associatedCostSummary = app(\App\Services\ProjectAssociatedCostService::class)->getSummary($project);

        return view('plan-workflow.show', compact('project', 'workflow', 'associatedCostSummary'));
    }

    // ── Budget check API (JSON for JS live bar) ───────────────────────────

    public function budgetCheck(Request $request, Project $project)
    {
        $amount = (float) $request->input('amount', 0);
        $result = app(\App\Services\BudgetGuardService::class)->check($project, $amount);
        return response()->json([
            'budget_allocated'    => (float) $project->budget_allocated,
            'budget_consumed'     => (float) $project->budget_consumed,
            'budget_remaining'    => $project->budgetRemaining(),
            'utilization_current' => $project->budgetUtilizationPercent(),
            'utilization_after'   => $result['utilization_after'],
            'status'              => $result['status'],
            'message'             => $result['message'],
            'guarded'             => $result['guarded'],
        ]);
    }

    // ── Step 0: Planning team submits ────────────────────────────────────

    public function submit(Request $request, Project $project)
    {
        $this->authorize('plan_workflow.submit');

        // Only one active workflow at a time
        $existing = $project->planWorkflows()
            ->whereNotIn('status', ['gm_approved', 'rejected'])
            ->first();

        if ($existing) {
            return back()->withErrors(['workflow' => 'There is already an active workflow for this project.']);
        }

        $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($project, $request) {
            $workflow = ProjectPlanWorkflow::create([
                'project_id'   => $project->id,
                'plan_type'    => $project->planWorkflows()->exists() ? 'revision' : 'initial',
                'status'       => 'submitted',
                'submitted_at' => now(),
                'created_by'   => auth()->id(),
            ]);

            $project->update(['planning_phase_status' => 'submitted']);
        });

        // status stays 'planning' — sync ensures consistency
        $project->refresh()->syncStatusFromWorkflow();

        return back()->with('success', 'Plan submitted for Planning Manager review.');
    }

    // ── Step 1: Planning Manager approves ────────────────────────────────

    public function approvePlanning(Request $request, ProjectPlanWorkflow $workflow)
    {
        $this->authorize('plan_workflow.approve_planning');
        $this->requireStep($workflow, 'submitted');

        $request->validate(['note' => 'nullable|string|max:1000']);

        DB::transaction(function () use ($workflow, $request) {
            $workflow->update([
                'status'               => 'planning_manager_approved',
                'planning_manager_id'  => auth()->id(),
                'planning_manager_at'  => now(),
                'planning_manager_note'=> $request->note,
            ]);
            $workflow->project->update(['planning_phase_status' => 'planning_manager_approved']);
        });

        $workflow->project->refresh()->syncStatusFromWorkflow();

        return back()->with('success', 'Plan approved. Forwarded to Coordinator.');
    }

    // ── Step 2: Coordinator approves ─────────────────────────────────────

    public function approveCoordinator(Request $request, ProjectPlanWorkflow $workflow)
    {
        $this->authorize('plan_workflow.approve_coord');
        $this->requireStep($workflow, 'planning_manager_approved');

        $request->validate(['note' => 'nullable|string|max:1000']);

        DB::transaction(function () use ($workflow, $request) {
            $workflow->update([
                'status'          => 'coordinator_approved',
                'coordinator_id'  => auth()->id(),
                'coordinator_at'  => now(),
                'coordinator_note'=> $request->note,
            ]);
            $workflow->project->update(['planning_phase_status' => 'coordinator_approved']);
        });

        $workflow->project->refresh()->syncStatusFromWorkflow();

        return back()->with('success', 'Plan approved. Forwarded to Technical Manager.');
    }

    // ── Step 3: Technical Manager approves ───────────────────────────────

    public function approveTechnical(Request $request, ProjectPlanWorkflow $workflow)
    {
        $this->authorize('plan_workflow.approve_tech');
        $this->requireStep($workflow, 'coordinator_approved');

        $request->validate(['note' => 'nullable|string|max:1000']);

        DB::transaction(function () use ($workflow, $request) {
            $workflow->update([
                'status'           => 'technical_manager_approved',
                'tech_manager_id'  => auth()->id(),
                'tech_manager_at'  => now(),
                'tech_manager_note'=> $request->note,
            ]);
            $workflow->project->update(['planning_phase_status' => 'technical_manager_approved']);
        });

        $workflow->project->refresh()->syncStatusFromWorkflow();

        return back()->with('success', 'Plan approved. Forwarded to General Manager for final approval and budget allocation.');
    }

    // ── Step 4: GM approves & allocates budget ───────────────────────────

    public function approveGm(Request $request, ProjectPlanWorkflow $workflow)
    {
        $this->authorize('plan_workflow.approve_gm');
        $this->requireStep($workflow, 'technical_manager_approved');

        $request->validate([
            'budget_allocated' => 'required|numeric|min:1',
            'note'             => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($workflow, $request) {
            $budget = (float) $request->budget_allocated;

            $workflow->update([
                'status'           => 'gm_approved',
                'gm_id'            => auth()->id(),
                'gm_at'            => now(),
                'gm_note'          => $request->note,
                'budget_allocated' => $budget,
            ]);

            // Update project budget + planning_phase_status
            $workflow->project->update([
                'planning_phase_status' => 'gm_approved',
                'budget_allocated'      => $budget,
            ]);

            // Audit trail
            ProjectBudgetAllocation::create([
                'project_id'      => $workflow->project_id,
                'workflow_id'     => $workflow->id,
                'amount'          => $budget,
                'allocation_type' => 'initial',
                'reason'          => $request->note ?? 'GM initial budget allocation',
                'allocated_by'    => auth()->id(),
                'allocated_at'    => now(),
            ]);
        });

        // ✔ This is the key step: GM approval → project status becomes 'active'
        $workflow->project->refresh()->syncStatusFromWorkflow();

        return back()->with('success', 'Project plan fully approved. Budget of ' . number_format($request->budget_allocated, 2) . ' ETB allocated. Project is now ACTIVE.');
    }

    // ── GM: Supplement budget ─────────────────────────────────────────────

    public function supplementBudget(Request $request, Project $project)
    {
        $this->authorize('budget.allocate');

        $request->validate([
            'amount' => 'required|numeric|min:1',
            'reason' => 'required|string|max:1000',
        ]);

        DB::transaction(function () use ($project, $request) {
            $additional = (float) $request->amount;

            $project->increment('budget_allocated', $additional);

            ProjectBudgetAllocation::create([
                'project_id'      => $project->id,
                'workflow_id'     => null,
                'amount'          => $additional,
                'allocation_type' => 'supplement',
                'reason'          => $request->reason,
                'allocated_by'    => auth()->id(),
                'allocated_at'    => now(),
            ]);
        });

        return back()->with('success', 'Supplemental budget of ' . number_format($request->amount, 2) . ' ETB added.');
    }

    // ── Reject (any approver in chain) ────────────────────────────────────

    public function reject(Request $request, ProjectPlanWorkflow $workflow)
    {
        $this->authorize('plan_workflow.reject');

        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        DB::transaction(function () use ($workflow, $request) {
            $workflow->update([
                'status'           => 'rejected',
                'rejected_by'      => auth()->id(),
                'rejected_at'      => now(),
                'rejection_reason' => $request->rejection_reason,
                'rejected_at_step' => $workflow->status, // capture which step it was at
            ]);
            $workflow->project->update(['planning_phase_status' => 'rejected']);
        });

        // Rejection keeps project in 'planning' status
        $workflow->project->refresh()->syncStatusFromWorkflow();

        return back()->with('success', 'Plan rejected. The planning team has been notified.');
    }

    // ── Helper ─────────────────────────────────────────────────────────────

    private function requireStep(ProjectPlanWorkflow $workflow, string $expectedStatus): void
    {
        if ($workflow->status !== $expectedStatus) {
            abort(403, 'This action cannot be performed at the current workflow step (' . $workflow->status . ').');
        }
    }
}
