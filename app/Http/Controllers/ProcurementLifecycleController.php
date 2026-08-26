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

        // 1. Identify owner roles to query
        $targetRoles = [];
        if ($isAdmin) {
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
            if ($user->hasRole('store_manager'))      $targetRoles[] = 'store_manager';
            if ($user->hasRole('purchase_manager'))   $targetRoles[] = 'purchase_manager';
            if ($user->hasRole('purchase'))           $targetRoles[] = 'purchase';
            if ($user->hasRole('market_research'))    $targetRoles[] = 'market_research';
            if ($isGm)                                $targetRoles[] = 'gm';
            if ($user->hasRole('finance_head'))       $targetRoles[] = 'finance_head';
            if ($user->hasRole('finance'))            $targetRoles[] = 'finance';
            if ($user->hasRole('general_service'))    $targetRoles[] = 'general_service';
            if ($user->hasRole('planning'))           $targetRoles[] = 'planning';
        }

        $isStoreManager = $user->hasRole('store_manager');
        $isPurchaseManager = $user->hasRole('purchase_manager');
        $isFinanceHead = $user->hasRole('finance_head') || $user->hasRole('finance_manager') || $user->hasRole('finance') || $user->hasRole('accountant');

        // 2. Fetch PRs awaiting action by this user's role(s)
        $prQuery = PurchaseRequest::with(['project', 'requestedBy', 'materialRequest', 'items'])
            ->latest();

        if (!$isAdmin) {
            $prQuery->where(function ($q) use ($targetRoles, $isHr, $isCoordinator, $isGm, $isStoreManager, $isPurchaseManager) {
                $q->whereIn('current_owner_role', $targetRoles);
                if ($isHr || $isCoordinator || $isGm) {
                    $q->orWhere('status', PurchaseRequest::STATUS_PENDING_HR_APPROVAL)
                      ->orWhere(function ($sub) {
                          $sub->where('is_office_request', true)
                              ->where('status', PurchaseRequest::STATUS_PENDING_HR_APPROVAL);
                      });
                }
                if ($isStoreManager) {
                    $q->orWhere(function ($sub) {
                        $sub->where('is_office_request', true)
                            ->whereIn('status', [PurchaseRequest::STATUS_APPROVED, PurchaseRequest::STATUS_PENDING_STORE_REVIEW]);
                    });
                }
                if ($isPurchaseManager) {
                    $q->orWhere(function ($sub) {
                        $sub->where('is_office_request', true)
                            ->whereIn('status', [PurchaseRequest::STATUS_PENDING_PM_REVIEW, PurchaseRequest::STATUS_PENDING_PROC_TEAM]);
                    });
                }
                if ($isFinanceHead) {
                    $q->orWhere(function ($sub) {
                        $sub->where('is_office_request', true)
                            ->where('status', PurchaseRequest::STATUS_PENDING_FINANCE);
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
            $emergencyMrs = MaterialRequest::with(['project', 'creator', 'requestedBy'])
                ->where('planning_approval_status', 'pending')
                ->latest()
                ->get();
        }

        // 4. Pending Office Requests specifically for HR / Coordinator & Store Manager
        $pendingOfficeCount = 0;
        if ($isHr || $isCoordinator || $isAdmin || $isGm) {
            try {
                $pendingOfficeCount = PurchaseRequest::where(function($q) {
                    $q->where('is_office_request', true)
                      ->orWhere('status', PurchaseRequest::STATUS_PENDING_HR_APPROVAL);
                })->where('status', PurchaseRequest::STATUS_PENDING_HR_APPROVAL)->count();
            } catch (\Throwable $e) {}
        }

        $pendingStoreOfficeCount = 0;
        if ($isStoreManager || $isAdmin) {
            try {
                $pendingStoreOfficeCount = PurchaseRequest::where('is_office_request', true)
                    ->whereIn('status', [PurchaseRequest::STATUS_APPROVED, PurchaseRequest::STATUS_PENDING_STORE_REVIEW])
                    ->count();
            } catch (\Throwable $e) {}
        }

        $pendingFinanceOfficeCount = 0;
        if ($isFinanceHead || $isAdmin || $isGm) {
            try {
                $pendingFinanceOfficeCount = PurchaseRequest::where('is_office_request', true)
                    ->where('status', PurchaseRequest::STATUS_PENDING_FINANCE)
                    ->count();
            } catch (\Throwable $e) {}
        }

        // 5. Summary Counters
        $kpi = [
            'my_pending'                   => $myPrs->total(),
            'emergency_mrs'                => $emergencyMrs->count(),
            'pending_office_requests'      => $pendingOfficeCount,
            'pending_store_office_requests'=> $pendingStoreOfficeCount,
            'pending_finance_office_requests' => $pendingFinanceOfficeCount,
            'completed'                    => PurchaseRequest::where('status', PurchaseRequest::STATUS_INTAKE_COMPLETE)->count(),
        ];

        $projects = Project::whereIn('status', ['active', 'planning', 'in_progress', 'on_hold'])->orderBy('name')->get();
        if ($projects->isEmpty()) {
            $projects = Project::orderBy('name')->get();
        }

        return view('procurement.lifecycle.my-queue', compact('myPrs', 'emergencyMrs', 'kpi', 'projects', 'isHr', 'isCoordinator', 'isStoreManager', 'isPurchaseManager', 'isFinanceHead'));
    }
}
