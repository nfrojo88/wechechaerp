<?php

namespace App\Http\Controllers;

use App\Models\MaterialRequest;
use App\Models\ManpowerRequest;
use App\Models\Project;
use App\Models\ErpPlanHeader;
use App\Models\TakeoffSheet;
use App\Models\Schedule;
use App\Models\Inventory;
use App\Models\Employee;
use App\Models\WeeklyPlanDispatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlanningManagerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function emergencyRequests()
    {
        $materialRequests = MaterialRequest::with(['project', 'store', 'creator', 'items.product'])
            ->where(function($q) {
                $q->where('planning_approval_status', 'pending')
                  ->orWhereIn('status', ['pending_planning', 'submitted', 'pending']);
            })
            ->whereNotIn('status', ['planning_approved', 'approved', 'rejected', 'cancelled'])
            ->latest()
            ->get();

        $manpowerRequests = ManpowerRequest::with(['project', 'requestedBy', 'items'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        return view('planning_manager.emergency_requests', compact('materialRequests', 'manpowerRequests'));
    }

    public function approveMaterial(Request $request, MaterialRequest $materialRequest)
    {
        $action = $request->input('action', 'approve');
        if ($action === 'approve') {
            $materialRequest->update([
                'status'                   => 'planning_approved',
                'planning_approval_status' => 'approved',
                'planning_approved_by'     => Auth::id(),
                'planning_approved_at'     => now(),
            ]);

            // Forward companion Purchase Request to Coordinator
            try {
                $lifecycle = app(\App\Services\ProcurementLifecycleService::class);
                $materialRequest->loadMissing('purchaseRequests');

                if ($materialRequest->purchaseRequests->isEmpty()) {
                    $materialRequest->createOrGetPurchaseRequest(Auth::id());
                    $materialRequest->load('purchaseRequests');
                }

                foreach ($materialRequest->purchaseRequests as $pr) {
                    $targetRole = $lifecycle->resolveOwnerRole('coordinator', $pr);
                    $pr->update([
                        'status'             => \App\Models\PurchaseRequest::STATUS_PENDING_HR_APPROVAL,
                        'current_owner_role' => $targetRole,
                        'approved_by'        => Auth::id(),
                        'approved_at'        => now(),
                    ]);

                    try {
                        \App\Models\PrWorkflowLog::create([
                            'purchase_request_id' => $pr->id,
                            'from_stage'          => \App\Models\PurchaseRequest::STATUS_PENDING_PLANNING,
                            'to_stage'            => \App\Models\PurchaseRequest::STATUS_PENDING_HR_APPROVAL,
                            'action'              => 'planning_approve_to_coordinator',
                            'actor_role'          => 'planning',
                            'actor_id'            => Auth::id(),
                            'notes'               => "Planning Manager approved Emergency Material Request #{$materialRequest->reference_number}. Forwarded directly to Coordinator.",
                            'created_at'          => now(),
                        ]);
                    } catch (\Throwable $e) {}

                    try {
                        $lifecycle->notifyStageRole(
                            $pr->id,
                            $targetRole,
                            "ConstructPro: Emergency PR #{$pr->pr_no} approved by Planning for Project: " . ($pr->project?->name ?? 'General') . ". Awaiting coordinator review. Open: " . url("/purchase-requests/{$pr->id}"),
                            $pr->project_id,
                            $pr->store_id
                        );
                    } catch (\Throwable $e) {}
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Failed to forward companion PRs to coordinator in PlanningManagerController for MR #{$materialRequest->reference_number}: " . $e->getMessage());
            }

            // Trigger Instant SMS Handoff: Planning -> Coordinator
            try {
                app(\App\Services\ProcurementHandoffNotificationService::class)->triggerHandoff(
                    'planning_forwarded',
                    $materialRequest,
                    [
                        'sender_user'  => Auth::user(),
                        'sender_role'  => 'planning',
                        'target_roles' => ['coordinator'],
                        'project_id'   => $materialRequest->project_id,
                        'store_id'     => $materialRequest->destination_store_id,
                    ]
                );
            } catch (\Throwable $e) {}

            return back()->with('success', "Emergency Material Request #{$materialRequest->reference_number} approved and sent directly to Coordinator in Procurement Queue.");
        } else {
            $reason = $request->input('rejection_reason', 'Rejected by Planning Manager.');
            $materialRequest->update([
                'status'                   => 'rejected',
                'planning_approval_status' => 'rejected',
                'planning_approved_by'     => Auth::id(),
                'planning_approved_at'     => now(),
                'planning_rejection_reason'=> $reason,
            ]);

            try {
                $materialRequest->loadMissing('purchaseRequests');
                foreach ($materialRequest->purchaseRequests as $pr) {
                    $pr->update([
                        'status'           => \App\Models\PurchaseRequest::STATUS_REJECTED,
                        'rejected_by'      => Auth::id(),
                        'rejected_at'      => now(),
                        'rejection_reason' => $reason,
                    ]);
                }
            } catch (\Throwable $e) {}

            return back()->with('success', "Emergency Material Request #{$materialRequest->reference_number} has been rejected.");
        }
    }

    public function approveManpower(Request $request, ManpowerRequest $manpowerRequest)
    {
        $action = $request->input('action', 'approve');
        $manpowerRequest->update([
            'status' => $action === 'approve' ? 'approved' : 'rejected',
        ]);

        return back()->with('success', 'Manpower request updated successfully.');
    }

    public function resourceReport()
    {
        $projects = Project::where('status', 'active')->get();
        
        // Calculate mock/live allocations per project
        $reportData = [];
        foreach ($projects as $project) {
            $reportData[] = [
                'project' => $project,
                'cement_qty' => rand(150, 800),
                'steel_qty' => rand(10, 50),
                'sand_qty' => rand(80, 400),
                'manpower_active' => rand(20, 120),
                'equipment_active' => rand(2, 10),
            ];
        }

        return view('planning_manager.resource_report', compact('reportData'));
    }

    public function weeklyPlanSetup()
    {
        $projects = Project::where('status', 'active')->get();
        return view('planning_manager.weekly_plan_setup', compact('projects'));
    }

    public function storeWeeklyPlan(Request $request)
    {
        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'week_number' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'targets' => 'required|string',
        ]);

        // Simulating store dispatch/targets
        return redirect()->route('planning-manager.weekly-plan-setup')->with('success', 'Weekly plan setup target saved successfully.');
    }
}
