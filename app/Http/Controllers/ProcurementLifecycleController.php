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
            $prQuery->where(function ($q) use ($targetRoles, $isHr, $isCoordinator, $isGm) {
                $q->whereIn('current_owner_role', $targetRoles);
                if ($isHr || $isCoordinator || $isGm) {
                    $q->orWhere('status', PurchaseRequest::STATUS_PENDING_HR_APPROVAL);
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

        $pendingOfficeCount = 0;
        $pendingStoreOfficeCount = 0;
        $pendingFinanceOfficeCount = 0;

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
