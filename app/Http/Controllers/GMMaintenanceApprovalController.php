<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExpenseRequest;
use App\Models\MaterialRequest;
use App\Models\MaintenanceRequest;
use App\Models\Store;
use App\Models\Product;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GMMaintenanceApprovalController extends Controller
{
    /**
     * Ensure only General Manager or Administrator can access these approval actions.
     */
    protected function checkGmAuthorization(): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        $roleNames = strtolower(implode(' ', $user->getRoleNames()->toArray()));
        $isAuthorized = $user->hasAnyRole(['gm', 'general_manager', 'General Manager', 'GM', 'admin', 'global_admin'])
            || str_contains($roleNames, 'gm')
            || str_contains($roleNames, 'general_manager')
            || str_contains($roleNames, 'admin');

        if (!$isAuthorized) {
            abort(403, 'Unauthorized access to General Manager approvals.');
        }
    }

    /**
     * Display the Executive GM Maintenance Approvals dashboard.
     */
    public function index(Request $request)
    {
        $this->checkGmAuthorization();

        $search = trim($request->get('search', ''));
        $tab = $request->get('tab', 'pending'); // pending, expenses, materials, history

        // 0. Maintenance & Repair Tickets (Incoming & Active Reports)
        $ticketQuery = MaintenanceRequest::with([
                'employee',
                'reportedBy',
                'assignedTo',
                'fixedAssetUnit',
                'expenseRequests',
                'materialRequests.items.product'
            ])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('request_no', 'like', "%{$search}%")
                       ->orWhere('asset_name', 'like', "%{$search}%")
                       ->orWhere('asset_code', 'like', "%{$search}%")
                       ->orWhere('description', 'like', "%{$search}%")
                       ->orWhereHas('employee', function ($eq) use ($search) {
                           $eq->where('first_name', 'like', "%{$search}%")
                              ->orWhere('last_name', 'like', "%{$search}%");
                       });
                });
            });

        $maintenanceTickets = $ticketQuery->latest()->get();
        $pendingTicketsCount = $maintenanceTickets->whereIn('status', ['pending', 'in_progress'])->count();

        // 1. Pending Expense Requests (Ask Money)
        $expenseQuery = ExpenseRequest::with([
                'maintenanceRequest.employee',
                'maintenanceRequest.fixedAssetUnit',
                'user',
                'employee'
            ])
            ->where(function ($q) {
                $q->whereNotNull('maintenance_request_id')
                  ->orWhere('category', 'Maintenance');
            })
            ->whereIn('status', [ExpenseRequest::STATUS_PENDING_GM, 'Pending (GM Review)', 'pending_gm']);

        if ($search !== '') {
            $expenseQuery->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('maintenanceRequest', function ($mq) use ($search) {
                      $mq->where('request_no', 'like', "%{$search}%")
                         ->orWhere('asset_name', 'like', "%{$search}%");
                  });
            });
        }
        $pendingExpenses = $expenseQuery->latest()->get();

        // 2. Pending Material Requests (Ask Material)
        $materialQuery = MaterialRequest::with([
                'maintenanceRequest.employee',
                'items.product',
                'store',
                'creator'
            ])
            ->where(function ($q) {
                $q->whereNotNull('maintenance_request_id')
                  ->orWhere('source', 'like', 'Maintenance%');
            })
            ->whereIn('status', ['pending_gm', 'pending', 'pending_approval']);

        if ($search !== '') {
            $materialQuery->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('maintenanceRequest', function ($mq) use ($search) {
                      $mq->where('request_no', 'like', "%{$search}%")
                         ->orWhere('asset_name', 'like', "%{$search}%");
                  });
            });
        }
        $pendingMaterials = $materialQuery->latest()->get();

        // 3. Recently Decided / History
        $decidedExpenses = ExpenseRequest::with(['maintenanceRequest', 'gmReviewer', 'gmApprover', 'user'])
            ->where(function ($q) {
                $q->whereNotNull('maintenance_request_id')
                  ->orWhere('category', 'Maintenance');
            })
            ->whereIn('status', [
                ExpenseRequest::STATUS_APPROVED_ASSIGNED,
                ExpenseRequest::STATUS_ASSIGNED,
                ExpenseRequest::STATUS_SENT_TO_STORE,
                ExpenseRequest::STATUS_PAID,
                ExpenseRequest::STATUS_REJECTED,
            ])
            ->latest()
            ->take(30)
            ->get();

        $decidedMaterials = MaterialRequest::with(['maintenanceRequest', 'approver', 'store', 'items.product'])
            ->where(function ($q) {
                $q->whereNotNull('maintenance_request_id')
                  ->orWhere('source', 'like', 'Maintenance%');
            })
            ->whereIn('status', [
                'sent_to_store_manager',
                'needs_purchase',
                'sent_to_pr',
                'issued',
                'processed',
                'rejected',
            ])
            ->latest()
            ->take(30)
            ->get();

        // 4. Stores, Staff and Finance Staff for assignment in modals
        $stores = Store::where('is_active', true)->get();
        $staff = User::where('is_active', true)->orderBy('name')->get();
        $financeStaff = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['finance', 'Finance', 'finance_officer', 'finance_head', 'finance_manager', 'accountant', 'cashier']);
        })->get();

        // 5. Aggregate KPIs
        $totalPendingCount = $pendingExpenses->count() + $pendingMaterials->count() + $maintenanceTickets->where('status', 'pending')->count();
        $totalPendingExpenseAmount = (float) $pendingExpenses->sum('amount');
        $totalPendingMaterialItems = $pendingMaterials->sum(fn($mr) => $mr->items->count());
        $totalDecidedCount = $decidedExpenses->count() + $decidedMaterials->count();

        return view('gm.maintenance-approvals.index', compact(
            'maintenanceTickets',
            'pendingTicketsCount',
            'pendingExpenses',
            'pendingMaterials',
            'decidedExpenses',
            'decidedMaterials',
            'stores',
            'staff',
            'financeStaff',
            'totalPendingCount',
            'totalPendingExpenseAmount',
            'totalPendingMaterialItems',
            'totalDecidedCount',
            'tab',
            'search'
        ));
    }

    /**
     * Approve and update a Maintenance & Repair Ticket as GM.
     */
    public function approveTicket(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $this->checkGmAuthorization();

        $validated = $request->validate([
            'status'                => 'required|in:pending,in_progress,sent_to_store_manager,resolved,closed',
            'assigned_to_user_id'   => 'nullable|exists:users,id',
            'replacement_condition' => 'nullable|in:in_maintenance,unrepairable_damage',
            'gm_notes'              => 'nullable|string|max:2000',
        ]);

        $data = [
            'status'              => $validated['status'],
            'assigned_to_user_id' => $validated['assigned_to_user_id'] ?? $maintenanceRequest->assigned_to_user_id,
        ];

        if ($validated['status'] === 'sent_to_store_manager') {
            $data['replacement_action'] = 'sent_to_store_manager';
            $data['replacement_condition'] = $validated['replacement_condition'] ?? 'in_maintenance';
            $data['sent_to_store_manager_at'] = now();
        }

        if ($validated['status'] === 'resolved' && !$maintenanceRequest->resolved_at) {
            $data['resolved_at'] = now();
        }

        if (!empty($validated['gm_notes'])) {
            $data['admin_notes'] = ($maintenanceRequest->admin_notes ? ($maintenanceRequest->admin_notes . "\n") : '') . "[GM Directive " . now()->format('d M Y') . "]: " . $validated['gm_notes'];
        }

        $maintenanceRequest->update($data);

        ActivityLog::log(
            'updated',
            "GM reviewed and updated Maintenance Ticket #{$maintenanceRequest->request_no} status to '" . ucfirst(str_replace('_', ' ', $validated['status'])) . "'" . (!empty($validated['gm_notes']) ? " (Note: {$validated['gm_notes']})" : ''),
            'Maintenance Requests',
            $maintenanceRequest
        );

        return back()->with('success', "Maintenance Ticket #{$maintenanceRequest->request_no} status updated to '" . ucfirst(str_replace('_', ' ', $validated['status'])) . "' by GM!");
    }

    /**
     * Process GM Decision for an Expense Request ("Ask Money").
     * Options:
     * - approve_finance: passes to Finance section (Finance Head/Staff) for disbursement.
     * - send_to_store: GM directs to fulfill via store inventory instead of cash; creates Material Request.
     * - reject: returns with rejection reason.
     */
    public function approveExpense(Request $request, ExpenseRequest $expenseRequest)
    {
        $this->checkGmAuthorization();

        $validated = $request->validate([
            'action'                     => 'required|in:approve_finance,send_to_store,reject',
            'rejection_reason'           => 'required_if:action,reject|nullable|string|max:1000',
            'destination_store_id'       => 'nullable|exists:stores,id',
            'assigned_finance_staff_id'  => 'nullable|exists:users,id',
            'gm_notes'                   => 'nullable|string|max:2000',
        ]);

        $user = auth()->user();
        $maintReq = $expenseRequest->maintenanceRequest;

        // ── Option A: Reject ──────────────────────────────────────────────────────────
        if ($validated['action'] === 'reject') {
            $reason = !empty($validated['rejection_reason']) ? $validated['rejection_reason'] : 'Rejected by General Manager (GM)';

            $expenseRequest->update([
                'status'           => ExpenseRequest::STATUS_REJECTED,
                'gm_reviewer_id'   => $user->id,
                'gm_approver_id'   => $user->id,
                'gm_reviewed_at'   => now(),
                'gm_approved_at'   => now(),
                'rejection_reason' => $reason,
            ]);

            if ($maintReq) {
                ActivityLog::log(
                    'rejected',
                    "GM rejected Expense Request #{$expenseRequest->request_number} for Maintenance {$maintReq->request_no}: {$reason}",
                    'Maintenance Requests',
                    $maintReq
                );
            }

            ActivityLog::log(
                'rejected',
                "GM rejected Maintenance Expense Request #{$expenseRequest->request_number}: {$reason}",
                'Expense Requests',
                $expenseRequest
            );

            return back()->with('success', "Expense Request #{$expenseRequest->request_number} was rejected by GM.");
        }

        // ── Option B: Send to Store Manager (Material Fulfillment instead of Cash) ───
        if ($validated['action'] === 'send_to_store') {
            $gmDirectives = !empty($validated['gm_notes']) ? $validated['gm_notes'] : 'GM directed to fulfill materials/parts from store inventory rather than cash disbursement.';

            $targetStoreId = $validated['destination_store_id'] ?? null;
            $store = $targetStoreId ? Store::find($targetStoreId) : Store::where('is_active', true)->first();
            $storeId = $store?->id;

            $projectId = $expenseRequest->project_id ?: ($store?->project_id);
            if (!$projectId) {
                $project = \App\Models\Project::whereIn('status', ['active', 'in_progress', 'planning'])->first() ?? \App\Models\Project::first();
                $projectId = $project?->id;
            }

            // Ensure column exists
            if (Schema::hasTable('material_requests') && !Schema::hasColumn('material_requests', 'maintenance_request_id')) {
                Schema::table('material_requests', function ($table) {
                    $table->unsignedBigInteger('maintenance_request_id')->nullable()->index();
                });
            }

            $ticketPart = $maintReq ? str_replace('MNT-', '', $maintReq->request_no) : $expenseRequest->id;
            $refNumber = 'MR-MNT-' . $ticketPart . '-' . strtoupper(Str::random(3));
            while (MaterialRequest::where('reference_number', $refNumber)->exists()) {
                $refNumber = 'MR-MNT-' . $ticketPart . '-' . strtoupper(Str::random(4));
            }

            $materialRequest = MaterialRequest::create([
                'project_id'             => $projectId,
                'destination_store_id'   => $storeId,
                'maintenance_request_id' => $expenseRequest->maintenance_request_id,
                'reference_number'       => $refNumber,
                'source'                 => 'Maintenance (GM Decision) — ' . ($maintReq ? $maintReq->request_no : $expenseRequest->request_number),
                'status'                 => 'sent_to_store_manager',
                'required_date'          => now()->addDays(2),
                'notes'                  => "GM Directive: {$gmDirectives}\nExpense Request: #{$expenseRequest->request_number} (ETB " . number_format($expenseRequest->amount, 2) . ")\nPurpose: {$expenseRequest->description}",
                'created_by'             => $user->id,
                'approved_by'            => $user->id,
                'approved_at'            => now(),
            ]);

            $productName = $maintReq ? "Spare Parts / Repair Items for {$maintReq->asset_name}" : "Maintenance Supplies for {$expenseRequest->request_number}";
            $product = Product::firstOrCreate(
                ['name' => $productName],
                [
                    'sku'       => 'MNT-' . strtoupper(Str::random(6)),
                    'unit'      => 'pcs',
                    'category'  => 'Maintenance / Spare Parts',
                    'is_active' => true,
                ]
            );

            $materialRequest->items()->create([
                'product_id'         => $product->id,
                'quantity_requested' => 1,
                'notes'              => "Expense #{$expenseRequest->request_number}: " . Str::limit($expenseRequest->description, 200),
            ]);

            $expenseRequest->update([
                'status'           => ExpenseRequest::STATUS_SENT_TO_STORE,
                'gm_reviewer_id'   => $user->id,
                'gm_approver_id'   => $user->id,
                'gm_reviewed_at'   => now(),
                'gm_approved_at'   => now(),
                'rejection_reason' => null,
            ]);

            if ($maintReq) {
                $maintReq->update([
                    'status'                   => 'sent_to_store_manager',
                    'replacement_action'       => 'sent_to_store_manager',
                    'sent_to_store_manager_at' => now(),
                ]);

                ActivityLog::log(
                    'updated',
                    "GM reviewed Expense Request #{$expenseRequest->request_number} and routed to Store Manager for material fulfillment (Material Request #{$materialRequest->reference_number})",
                    'Maintenance Requests',
                    $maintReq
                );
            }

            ActivityLog::log(
                'updated',
                "GM routed Expense Request #{$expenseRequest->request_number} to Store Manager. Generated Material Request #{$materialRequest->reference_number}",
                'Expense Requests',
                $expenseRequest
            );

            return back()->with('success', "Expense Request #{$expenseRequest->request_number} rerouted to Store Manager! Material Request #{$materialRequest->reference_number} created and passed to Store section.");
        }

        // ── Option C: Approve & Pass to Finance ───────────────────────────────────────
        $desc = $expenseRequest->description;
        if (!empty($validated['gm_notes'])) {
            $desc .= "\n[GM Approval Directive: " . $validated['gm_notes'] . "]";
        }

        $assignedStaffId = $validated['assigned_finance_staff_id'] ?? null;

        $expenseRequest->update([
            'status'                    => ExpenseRequest::STATUS_APPROVED_ASSIGNED,
            'gm_reviewer_id'            => $user->id,
            'gm_approver_id'            => $user->id,
            'gm_reviewed_at'            => now(),
            'gm_approved_at'            => now(),
            'assigned_finance_staff_id' => $assignedStaffId,
            'finance_staff_id'          => $assignedStaffId,
            'description'               => $desc,
            'rejection_reason'          => null,
        ]);

        if ($maintReq) {
            ActivityLog::log(
                'approved',
                "GM approved Expense Request #{$expenseRequest->request_number} for ETB " . number_format($expenseRequest->amount, 2) . " and forwarded to Finance section for disbursement" . ($assignedStaffId ? " (Assigned to Finance Staff #{$assignedStaffId})" : ''),
                'Maintenance Requests',
                $maintReq
            );
        }

        ActivityLog::log(
            'approved',
            "GM approved Expense Request #{$expenseRequest->request_number} for ETB " . number_format($expenseRequest->amount, 2) . " and passed to Finance section for disbursement",
            'Expense Requests',
            $expenseRequest
        );

        return back()->with('success', "Expense Request #{$expenseRequest->request_number} for ETB " . number_format($expenseRequest->amount, 2) . " approved by GM and passed to Finance section for disbursement!");
    }

    /**
     * Process GM Decision for a Material Request ("Ask Material").
     * Options:
     * - send_to_store: GM approves and passes to Store Manager for inventory issuance.
     * - send_to_pr: GM approves and passes directly to Procurement (PR creation).
     * - reject: returns with rejection reason.
     */
    public function approveMaterial(Request $request, MaterialRequest $materialRequest)
    {
        $this->checkGmAuthorization();

        $validated = $request->validate([
            'action'               => 'required|in:send_to_store,send_to_pr,reject',
            'rejection_reason'     => 'required_if:action,reject|nullable|string|max:1000',
            'destination_store_id' => 'nullable|exists:stores,id',
            'gm_notes'             => 'nullable|string|max:2000',
        ]);

        $user = auth()->user();
        $maintReq = $materialRequest->maintenanceRequest;

        // ── Option A: Reject ──────────────────────────────────────────────────────────
        if ($validated['action'] === 'reject') {
            $reason = !empty($validated['rejection_reason']) ? $validated['rejection_reason'] : 'Rejected by General Manager (GM)';

            $materialRequest->update([
                'status'                    => 'rejected',
                'planning_rejection_reason' => $reason,
                'approved_by'               => $user->id,
                'approved_at'               => now(),
            ]);

            if ($maintReq) {
                ActivityLog::log(
                    'rejected',
                    "GM rejected Material Request #{$materialRequest->reference_number} for Maintenance {$maintReq->request_no}: {$reason}",
                    'Maintenance Requests',
                    $maintReq
                );
            }

            return back()->with('success', "Material Request #{$materialRequest->reference_number} was rejected by GM.");
        }

        // Append GM directives to notes
        $notes = $materialRequest->notes ?: '';
        if (!empty($validated['gm_notes'])) {
            $notes .= "\n[GM Approval Directive: " . $validated['gm_notes'] . "]";
        }

        // ── Option B: Send directly to Procurement (PR) ───────────────────────────────
        if ($validated['action'] === 'send_to_pr') {
            $materialRequest->update([
                'status'      => 'needs_purchase',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'notes'       => $notes,
            ]);

            if ($maintReq) {
                ActivityLog::log(
                    'approved',
                    "GM approved Material Request #{$materialRequest->reference_number} and routed directly to Procurement section for Purchase Request (PR)",
                    'Maintenance Requests',
                    $maintReq
                );
            }

            return back()->with('success', "Material Request #{$materialRequest->reference_number} approved by GM and passed to Procurement section!");
        }

        // ── Option C: Send to Store Manager (Default Fulfillment) ─────────────────────
        $updateData = [
            'status'      => 'sent_to_store_manager',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'notes'       => $notes,
        ];

        if (!empty($validated['destination_store_id'])) {
            $updateData['destination_store_id'] = $validated['destination_store_id'];
        }

        $materialRequest->update($updateData);

        if ($maintReq) {
            $maintReq->update([
                'status'                   => 'sent_to_store_manager',
                'replacement_action'       => 'sent_to_store_manager',
                'sent_to_store_manager_at' => now(),
            ]);

            ActivityLog::log(
                'approved',
                "GM approved Material Request #{$materialRequest->reference_number} and passed to Store Manager for stock fulfillment",
                'Maintenance Requests',
                $maintReq
            );
        }

        return back()->with('success', "Material Request #{$materialRequest->reference_number} approved by GM and passed to Store Manager for inventory fulfillment!");
    }
}
