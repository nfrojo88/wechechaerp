<?php

namespace App\Http\Controllers;

use App\Models\PurchaseRequest;
use App\Models\MaterialRequest;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProcurementLifecycleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Role-based Queue Dashboard ("My Queue")
     * Displays actionable items depending on the logged-in user's role.
     */
    public function myQueue(Request $request)
    {
        $user = Auth::user();
        $roles = $user->getRoleNames()->toArray();

        $isHr = $user->hasRole('hr_manager') || $user->hasRole('hr_officer') || $user->hasRole('hr');
        $isCoordinator = $user->hasRole('coordinator');
        $isAdmin = $user->hasRole('global_admin') || $user->hasRole('admin');
        $isGm = $user->hasRole('gm') || $user->hasRole('general_manager');
        $isAuditor = $user->hasAnyRole(['auditor', 'audit', 'internal_auditor', 'Auditor', 'Audit']) || in_array('auditor', $roles) || in_array('audit', $roles);

        $isStoreKeeper = $user->hasAnyRole(['store_keeper', 'Store Keeper', 'storekeeper', 'Storekeeper']) || in_array('store_keeper', $roles) || in_array('storekeeper', $roles);
        $isStoreManager = $user->hasRole('store_manager');
        $isPurchaseManager = $user->hasRole('purchase_manager');
        $isFinanceHead = $user->hasRole('finance_head') || $user->hasRole('finance_manager') || $user->hasRole('finance') || $user->hasRole('accountant');

        // 1. Identify owner roles to query
        $targetRoles = [];
        if ($isAdmin || $isAuditor) {
            $targetRoles = PurchaseRequest::allStatuses();
        } else {
            if ($isCoordinator) {
                $targetRoles[] = 'coordinator';
                $targetRoles[] = 'hr_manager';
            }
            if ($isHr) {
                $targetRoles[] = 'hr_manager';
                $targetRoles[] = 'hr';
                $targetRoles[] = 'coordinator';
            }
            if ($isStoreKeeper)                       $targetRoles[] = 'store_keeper';
            if ($isStoreManager)                      $targetRoles[] = 'store_manager';
            if ($user->hasRole('purchase_manager'))   $targetRoles[] = 'purchase_manager';
            if ($user->hasRole('purchase'))           $targetRoles[] = 'purchase';
            if ($user->hasRole('market_research'))    $targetRoles[] = 'market_research';
            if ($isGm)                                $targetRoles[] = 'gm';
            if ($user->hasRole('finance_head'))       $targetRoles[] = 'finance_head';
            if ($user->hasRole('finance'))            $targetRoles[] = 'finance';
            if ($user->hasRole('general_service'))    $targetRoles[] = 'general_service';
            if ($user->hasRole('planning'))           $targetRoles[] = 'planning';
        }

        // 2. Fetch PRs awaiting action by this user's role(s)
        $prQuery = PurchaseRequest::with(['project', 'requestedBy', 'materialRequest', 'items'])
            ->latest();

        if (!$isAdmin && !$isAuditor) {
            $prQuery->where(function ($q) use ($targetRoles, $isHr, $isCoordinator, $isGm, $isStoreKeeper, $isStoreManager, $user) {
                $q->where(function ($roleQ) use ($targetRoles, $isStoreManager, $isStoreKeeper) {
                    if ($isStoreManager && !$isStoreKeeper) {
                        // Store Manager must NOT see or fulfill final intake! Only initial Stage 2 smart split
                        $roleQ->where(function ($smQ) {
                            $smQ->where('current_owner_role', 'store_manager')
                                ->where(function ($notFinalQ) {
                                    $notFinalQ->where('status', '!=', PurchaseRequest::STATUS_PENDING_STORE_REVIEW)
                                              ->orWhere(function ($stage2Only) {
                                                  $stage2Only->where('status', PurchaseRequest::STATUS_PENDING_STORE_REVIEW)
                                                             ->whereDoesntHave('payment')
                                                             ->whereDoesntHave('creditLedger')
                                                             ->whereDoesntHave('driverBooking')
                                                             ->whereDoesntHave('receipt')
                                                             ->whereDoesntHave('workflowLogs', function ($wlQ) {
                                                                 $wlQ->whereIn('action', [
                                                                     'gm_approve_buy_by_credit',
                                                                     'gm_approve_credit_direct_store',
                                                                     'finance_credit_approved',
                                                                     'finance_credit_approved_direct_intake',
                                                                     'driver_booked',
                                                                     'receipt_verified',
                                                                     'partial_store_intake',
                                                                 ]);
                                                             });
                                              });
                                });
                        });
                        $otherRoles = array_diff($targetRoles, ['store_manager']);
                        if (!empty($otherRoles)) {
                            $roleQ->orWhereIn('current_owner_role', $otherRoles);
                        }
                    } else {
                        $roleQ->whereIn('current_owner_role', $targetRoles);
                    }
                })->orWhere('requested_by', $user->id);

                if ($isHr || $isCoordinator || $isGm) {
                    $q->orWhere('status', PurchaseRequest::STATUS_PENDING_HR_APPROVAL);
                }

                if ($isStoreKeeper) {
                    // Store Keeper sees all PRs awaiting final material intake & receiving
                    $q->orWhere(function ($skQ) {
                        $skQ->where('status', PurchaseRequest::STATUS_PENDING_STORE_REVIEW)
                            ->where(function ($finalQ) {
                                $finalQ->where('current_owner_role', 'store_keeper')
                                       ->orWhereHas('payment')
                                       ->orWhereHas('creditLedger')
                                       ->orWhereHas('driverBooking')
                                       ->orWhereHas('receipt')
                                       ->orWhereHas('workflowLogs', function ($lq) {
                                           $lq->whereIn('action', [
                                               'gm_approve_buy_by_credit',
                                               'gm_approve_credit_direct_store',
                                               'finance_credit_approved',
                                               'finance_credit_approved_direct_intake',
                                               'driver_booked',
                                               'receipt_verified',
                                               'partial_store_intake',
                                           ]);
                                       });
                            });
                    });
                }
            });
        }

        if ($request->filled('project_id')) {
            $prQuery->where('project_id', $request->project_id);
        }
        if ($request->filled('status')) {
            $prQuery->where('status', $request->status);
        }

        $myPrs = $prQuery->paginate(15, ['*'], 'pr_page')->withQueryString();

        // 3. Emergency / Pending MR approval queue for Planning Team
        $emergencyMrs = collect();
        if ($user->hasRole('planning') || $user->hasRole('planning_manager') || $isAdmin) {
            $emergencyMrs = MaterialRequest::with(['project', 'creator', 'requestedBy', 'maintenanceRequest', 'items.product'])
                ->where(function($q) {
                    $q->where('planning_approval_status', 'pending')
                      ->orWhereIn('status', ['pending_planning', 'submitted', 'pending']);
                })
                ->whereNotIn('status', ['planning_approved', 'approved', 'rejected', 'cancelled'])
                ->latest()
                ->get();
        }

        // 4. Material & Maintenance Requisitions (Store & Coordinator Procurement Phase)
        $prMrIds = $myPrs->pluck('material_request_id')->filter()->toArray();

        $mrQuery = MaterialRequest::with(['project', 'store', 'creator', 'requestedBy', 'maintenanceRequest', 'items.product', 'purchaseRequests'])
            ->whereNotIn('id', $prMrIds)
            ->latest();

        if ($request->filled('project_id')) {
            $mrQuery->where('project_id', $request->project_id);
        }

        // Filter MRs for specific roles (Coordinator sees planning_approved directly)
        if (!$isAdmin && !$isAuditor) {
            if ($isCoordinator) {
                $mrQuery->whereIn('status', ['planning_approved', 'sent_to_store_manager', 'needs_purchase', 'sent_to_pr', 'pending']);
            } elseif ($isStoreManager) {
                $mrQuery->whereIn('status', ['sent_to_store_manager', 'planning_approved', 'needs_purchase', 'sent_to_pr', 'pending']);
            } else {
                $mrQuery->whereIn('status', ['sent_to_store_manager', 'needs_purchase', 'sent_to_pr', 'planning_approved', 'pending']);
            }
        }

        $materialRequestsQueue = $mrQuery->take(25)->get();

        $pendingOfficeCount = 0;
        $pendingStoreOfficeCount = 0;
        $pendingFinanceOfficeCount = 0;

        // 5. User's Created Requisitions & Purchase Requests History ("they created question history")
        $createdPrQuery = PurchaseRequest::with(['project', 'requestedBy', 'store', 'materialRequest', 'items.product', 'deliveryReceipts'])
            ->latest();

        $createdMrQuery = MaterialRequest::with(['project', 'store', 'creator', 'requestedBy', 'items.product', 'purchaseRequests'])
            ->latest();

        if (!$isAdmin && !$isAuditor) {
            $createdPrQuery->where('requested_by', $user->id);
            $createdMrQuery->where('created_by', $user->id);
        }

        if ($request->filled('project_id')) {
            $createdPrQuery->where('project_id', $request->project_id);
            $createdMrQuery->where('project_id', $request->project_id);
        }
        if ($request->filled('status')) {
            $createdPrQuery->where('status', $request->status);
            $createdMrQuery->where('status', $request->status);
        }

        $myCreatedPrs = $createdPrQuery->paginate(15, ['*'], 'created_pr_page')->withQueryString();
        $myCreatedMrs = $createdMrQuery->take(25)->get();

        $myCreatedCount = ($isAdmin || $isAuditor)
            ? (PurchaseRequest::count() + MaterialRequest::count())
            : (PurchaseRequest::where('requested_by', $user->id)->count() + MaterialRequest::where('created_by', $user->id)->count());

        // 6. Completed History
        $completedPrQuery = PurchaseRequest::with(['project', 'requestedBy', 'store', 'items.product', 'deliveryReceipts'])
            ->where('status', PurchaseRequest::STATUS_INTAKE_COMPLETE)
            ->latest();

        if ($request->filled('project_id')) {
            $completedPrQuery->where('project_id', $request->project_id);
        }
        $completedPrs = $completedPrQuery->paginate(15, ['*'], 'completed_page')->withQueryString();

        // 7. All Company PR History (Oversight)
        $allPrQuery = PurchaseRequest::with(['project', 'requestedBy', 'store', 'items.product'])
            ->latest();
        if ($request->filled('project_id')) {
            $allPrQuery->where('project_id', $request->project_id);
        }
        if ($request->filled('status')) {
            $allPrQuery->where('status', $request->status);
        }
        $allPrs = $allPrQuery->paginate(15, ['*'], 'all_pr_page')->withQueryString();

        // 8. Summary Counters
        $pendingCount = $isAuditor ? PurchaseRequest::where('status', '!=', PurchaseRequest::STATUS_INTAKE_COMPLETE)->count() : ($myPrs->total() + $materialRequestsQueue->count());

        $kpi = [
            'my_pending'                     => $pendingCount,
            'my_created'                     => $myCreatedCount,
            'emergency_mrs'                  => $emergencyMrs->count(),
            'material_requests_queue'        => $materialRequestsQueue->count(),
            'pending_office_requests'        => $pendingOfficeCount,
            'pending_store_office_requests'  => $pendingStoreOfficeCount,
            'pending_finance_office_requests'=> $pendingFinanceOfficeCount,
            'completed'                      => PurchaseRequest::where('status', PurchaseRequest::STATUS_INTAKE_COMPLETE)->count(),
            'all_prs'                        => PurchaseRequest::count(),
        ];

        // Determine active tab: if no pending actions and user has created requests, show their created history tab
        $activeTab = $request->input('tab');
        if (!$activeTab) {
            if ($pendingCount == 0 && $myCreatedCount > 0) {
                $activeTab = 'my_created';
            } else {
                $activeTab = 'pending';
            }
        }

        $projects = Project::whereIn('status', ['active', 'planning', 'in_progress', 'on_hold'])->orderBy('name')->get();
        if ($projects->isEmpty()) {
            $projects = Project::orderBy('name')->get();
        }

        return view('procurement.lifecycle.my-queue', compact(
            'myPrs', 'emergencyMrs', 'materialRequestsQueue', 'kpi', 'projects',
            'myCreatedPrs', 'myCreatedMrs', 'completedPrs', 'allPrs', 'activeTab',
            'isHr', 'isCoordinator', 'isStoreManager', 'isStoreKeeper', 'isPurchaseManager', 'isFinanceHead', 'isAuditor', 'isAdmin', 'isGm'
        ));
    }
}
