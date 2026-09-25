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
        // Ensure schema columns dynamically if migration hasn't been run
        if (Schema::hasTable('maintenance_requests')) {
            Schema::table('maintenance_requests', function ($table) {
                if (!Schema::hasColumn('maintenance_requests', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('admin_notes');
                }
                if (!Schema::hasColumn('maintenance_requests', 'gm_approved_at')) {
                    $table->timestamp('gm_approved_at')->nullable()->after('admin_notes');
                }
                if (!Schema::hasColumn('maintenance_requests', 'gm_approver_id')) {
                    $table->unsignedBigInteger('gm_approver_id')->nullable()->after('gm_approved_at');
                }
                if (!Schema::hasColumn('maintenance_requests', 'gm_decision_locked')) {
                    $table->boolean('gm_decision_locked')->default(false)->after('gm_approver_id');
                }
                if (!Schema::hasColumn('maintenance_requests', 'gm_decision_summary')) {
                    $table->text('gm_decision_summary')->nullable()->after('gm_decision_locked');
                }
            });
        }

        // 0. Maintenance & Repair Tickets (Incoming & Active Reports)
        $ticketQuery = MaintenanceRequest::with([
                'employee',
                'reportedBy',
                'assignedTo',
                'gmApprover',
                'fixedAssetUnit.parentAsset',
                'fixedAssetUnit.assignedEmployee',
                'expenseRequests',
                'materialRequests.items.product',
                'materialRequests.store'
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

        // Pending / Incoming tickets awaiting GM approval:
        $maintenanceTickets = (clone $ticketQuery)
            ->where(function ($q) {
                $q->whereNull('gm_approved_at')
                  ->where(function ($sq) {
                      $sq->whereNull('gm_decision_locked')
                         ->orWhere('gm_decision_locked', false);
                  });
            })
            ->whereNotIn('status', ['resolved', 'closed', 'rejected'])
            ->latest()
            ->get();

        $pendingTicketsCount = $maintenanceTickets->count();

        // Decided / Locked Maintenance Tickets for Decision History:
        $decidedTickets = (clone $ticketQuery)
            ->where(function ($q) {
                $q->whereNotNull('gm_approved_at')
                  ->orWhere('gm_decision_locked', true)
                  ->orWhereIn('status', ['resolved', 'closed', 'rejected']);
            })
            ->latest()
            ->take(50)
            ->get();

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
        $totalPendingCount = $pendingExpenses->count() + $pendingMaterials->count() + $pendingTicketsCount;
        $totalPendingExpenseAmount = (float) $pendingExpenses->sum('amount');
        $totalPendingMaterialItems = $pendingMaterials->sum(fn($mr) => $mr->items->count());
        $totalDecidedCount = $decidedTickets->count() + $decidedExpenses->count() + $decidedMaterials->count();

        return view('gm.maintenance-approvals.index', compact(
            'maintenanceTickets',
            'pendingTicketsCount',
            'decidedTickets',
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
            'status'                       => 'required|in:pending,in_progress,sent_to_store_manager,resolved,closed,rejected',
            'rejection_reason'             => 'required_if:status,rejected|nullable|string|max:1000',
            'assigned_to_user_id'          => 'nullable|exists:users,id',
            'replacement_condition'        => 'nullable|in:in_maintenance,unrepairable_damage',
            'gm_notes'                     => 'nullable|string|max:2000',
            'route_money_to_finance'       => 'nullable|boolean',
            'route_money_to_coordinator'   => 'nullable|boolean',
            'assigned_finance_staff_id'    => 'nullable|exists:users,id',
            'route_material_to_store'      => 'nullable|boolean',
            'create_expense_amount'        => 'nullable|numeric|min:0.01',
            'create_expense_notes'         => 'nullable|string|max:1000',
            'create_material_store_id'     => 'nullable|exists:stores,id',
            'create_material_notes'        => 'nullable|string|max:1000',
        ]);

        $user = auth()->user();

        // ── Handle Rejection ────────────────────────────────────────────────
        if ($validated['status'] === 'rejected') {
            $reason = trim($request->input('rejection_reason') ?? $validated['gm_notes'] ?? 'Rejected by Executive GM');

            $updateData = [
                'status'              => 'rejected',
                'admin_notes'         => ($maintenanceRequest->admin_notes ? ($maintenanceRequest->admin_notes . "\n") : '') . "[GM Rejection " . now()->format('d M Y') . "]: " . $reason,
                'rejection_reason'    => $reason,
                'gm_approved_at'      => now(),
                'gm_approver_id'      => $user->id,
                'gm_decision_locked'  => true,
                'gm_decision_summary' => "Rejected by GM: " . $reason,
            ];
            $maintenanceRequest->update($updateData);

            // Cancel/Reject linked pending expense requests
            foreach ($maintenanceRequest->expenseRequests as $exp) {
                if (in_array($exp->status, [ExpenseRequest::STATUS_PENDING_GM, 'Pending (GM Review)', 'pending_gm', ExpenseRequest::STATUS_PENDING_HR])) {
                    $exp->update([
                        'status'           => ExpenseRequest::STATUS_REJECTED,
                        'gm_reviewer_id'   => $user->id,
                        'gm_approver_id'   => $user->id,
                        'gm_reviewed_at'   => now(),
                        'gm_approved_at'   => now(),
                        'rejection_reason' => $reason,
                        'description'      => $exp->description . "\n[GM Rejection: Linked maintenance ticket rejected - {$reason}]",
                    ]);
                }
            }

            // Cancel/Reject linked pending material requests
            foreach ($maintenanceRequest->materialRequests as $mr) {
                if (in_array($mr->status, ['pending_gm', 'pending', 'pending_approval'])) {
                    $mr->update([
                        'status' => 'rejected',
                        'notes'  => ($mr->notes ? ($mr->notes . "\n") : '') . "\n[GM Rejection: Linked maintenance ticket rejected - {$reason}]",
                    ]);
                }
            }

            ActivityLog::log(
                'rejected',
                "GM rejected Maintenance Ticket #{$maintenanceRequest->request_no} and locked decision in history. Reason: {$reason}",
                'Maintenance',
                $maintenanceRequest
            );

            return redirect()->route('gm.maintenance-approvals.index', ['tab' => 'history'])
                ->with('warning', "Maintenance Ticket #{$maintenanceRequest->request_no} has been rejected and locked into Decision History. Reason: {$reason}");
        }

        // ── Handle Approval ────────────────────────────────────────────────
        $finalStatus = ($validated['status'] === 'pending') ? 'in_progress' : $validated['status'];
        $data = [
            'status'              => $finalStatus,
            'assigned_to_user_id' => $validated['assigned_to_user_id'] ?? $maintenanceRequest->assigned_to_user_id,
            'gm_approved_at'      => now(),
            'gm_approver_id'      => $user->id,
            'gm_decision_locked'  => true,
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

        $activityDetails = ["GM approved Ticket #{$maintenanceRequest->request_no} (Status: " . ucfirst(str_replace('_', ' ', $finalStatus)) . ")"];

        // ── 1. Route Money to Finance for Payment / Disbursement ─────────────
        $shouldRouteMoney = $request->boolean('route_money_to_finance', true) || $request->boolean('route_money_to_coordinator', false);
        $financeStaffId = $validated['assigned_finance_staff_id'] ?? null;

        if ($shouldRouteMoney) {
            $linkedExpenses = $maintenanceRequest->expenseRequests;
            if ($linkedExpenses->isNotEmpty()) {
                foreach ($linkedExpenses as $lkExp) {
                    if ($lkExp->status !== ExpenseRequest::STATUS_PAID) {
                        $lkExp->update([
                            'status'                    => ExpenseRequest::STATUS_APPROVED_ASSIGNED,
                            'gm_reviewer_id'            => $user->id,
                            'gm_approver_id'            => $user->id,
                            'gm_reviewed_at'            => now(),
                            'gm_approved_at'            => now(),
                            'assigned_finance_staff_id' => $financeStaffId ?? $lkExp->assigned_finance_staff_id,
                            'finance_staff_id'          => $financeStaffId ?? $lkExp->finance_staff_id,
                            'finance_assigned_at'       => now(),
                            'description'               => $lkExp->description . "\n[GM Approval: Approved & routed directly to Finance for Payment / Payout]",
                        ]);

                        ActivityLog::log(
                            'approved',
                            "GM approved Maintenance #{$maintenanceRequest->request_no} and routed Expense Request #{$lkExp->request_number} directly to Finance for Payment",
                            'Expense Requests',
                            $lkExp
                        );
                    }
                }
                $activityDetails[] = "routed " . $linkedExpenses->count() . " expense request(s) to Finance for Payment";
            } elseif (!empty($validated['create_expense_amount'])) {
                // Auto-create new Expense Request sent directly to Finance for Payment
                $reqNo = 'EXP-MNT-' . str_replace('MNT-', '', $maintenanceRequest->request_no) . '-' . strtoupper(Str::random(3));
                while (ExpenseRequest::where('request_number', $reqNo)->exists()) {
                    $reqNo = 'EXP-MNT-' . str_replace('MNT-', '', $maintenanceRequest->request_no) . '-' . strtoupper(Str::random(4));
                }

                $newExp = ExpenseRequest::create([
                    'request_number'            => $reqNo,
                    'user_id'                   => $user->id,
                    'employee_id'               => $maintenanceRequest->employee_id,
                    'maintenance_request_id'    => $maintenanceRequest->id,
                    'category'                  => ExpenseRequest::CATEGORY_MAINTENANCE,
                    'amount'                    => $validated['create_expense_amount'],
                    'gross_amount'              => $validated['create_expense_amount'],
                    'net_amount'                => $validated['create_expense_amount'],
                    'description'               => "Maintenance repair budget for {$maintenanceRequest->asset_name} (#{$maintenanceRequest->request_no}). GM Directive: " . ($validated['create_expense_notes'] ?? 'Approved by GM for Finance payment & disbursement.'),
                    'status'                    => ExpenseRequest::STATUS_APPROVED_ASSIGNED,
                    'gm_reviewer_id'            => $user->id,
                    'gm_approver_id'            => $user->id,
                    'gm_reviewed_at'            => now(),
                    'gm_approved_at'            => now(),
                    'assigned_finance_staff_id' => $financeStaffId,
                    'finance_staff_id'          => $financeStaffId,
                    'finance_assigned_at'       => now(),
                ]);

                ActivityLog::log(
                    'created',
                    "GM created Expense Request #{$newExp->request_number} (ETB {$newExp->amount}) and forwarded directly to Finance for Payment",
                    'Expense Requests',
                    $newExp
                );
                $activityDetails[] = "created Expense Request #{$newExp->request_number} sent to Finance for payment";
            }
        }

        // ── 2. Route Materials to Store Manager (Add to PR Cycle) ────────────────
        $shouldRouteMaterial = $request->boolean('route_material_to_store', true) || $validated['status'] === 'sent_to_store_manager';
        if ($shouldRouteMaterial) {
            $linkedMaterials = $maintenanceRequest->materialRequests;
            if ($linkedMaterials->isNotEmpty()) {
                foreach ($linkedMaterials as $lkMat) {
                    $lkMat->update([
                        'status'      => 'sent_to_store_manager',
                        'approved_by' => $user->id,
                        'approved_at' => now(),
                        'notes'       => ($lkMat->notes ?: '') . "\n[GM Approval: Forwarded to Store Manager for fulfillment & PR purchase cycle]",
                    ]);

                    ActivityLog::log(
                        'approved',
                        "GM approved Maintenance #{$maintenanceRequest->request_no} and routed Material Request #{$lkMat->reference_number} to Store Manager for PR cycle",
                        'Material Requests',
                        $lkMat
                    );
                }
                $activityDetails[] = "routed " . $linkedMaterials->count() . " material request(s) to Store Manager for PR cycle";
            } elseif ($validated['status'] === 'sent_to_store_manager' || !empty($validated['create_material_notes'])) {
                // Auto-create new Material Request for Store Manager & PR cycle
                $targetStoreId = $validated['create_material_store_id'] ?? Store::where('is_active', true)->first()?->id;
                $project = \App\Models\Project::whereIn('status', ['active', 'in_progress'])->first() ?? \App\Models\Project::first();

                if (Schema::hasTable('material_requests') && !Schema::hasColumn('material_requests', 'maintenance_request_id')) {
                    Schema::table('material_requests', function ($table) {
                        $table->unsignedBigInteger('maintenance_request_id')->nullable()->index();
                    });
                }

                $ticketPart = str_replace('MNT-', '', $maintenanceRequest->request_no);
                $refNo = 'MR-MNT-' . $ticketPart . '-' . strtoupper(Str::random(3));
                while (MaterialRequest::where('reference_number', $refNo)->exists()) {
                    $refNo = 'MR-MNT-' . $ticketPart . '-' . strtoupper(Str::random(4));
                }

                $repCondition = $validated['replacement_condition'] ?? 'in_maintenance';
                $condLabel = $repCondition === 'unrepairable_damage' ? 'Permanent Replacement' : 'Temporary Maintenance Unit';

                $newMat = MaterialRequest::create([
                    'project_id'             => $project?->id,
                    'destination_store_id'   => $targetStoreId,
                    'maintenance_request_id' => $maintenanceRequest->id,
                    'reference_number'       => $refNo,
                    'source'                 => "Maintenance ({$condLabel}) — {$maintenanceRequest->request_no}",
                    'status'                 => 'sent_to_store_manager',
                    'required_date'          => now()->addDays(2),
                    'notes'                  => "GM Directive: Replacement Unit / Spare Parts for {$maintenanceRequest->asset_name} ({$maintenanceRequest->asset_code}). Condition: {$condLabel}. " . ($validated['create_material_notes'] ?? 'Store Manager: Fulfill from stock or route directly into PR purchase cycle.'),
                    'created_by'             => $user->id,
                    'approved_by'            => $user->id,
                    'approved_at'            => now(),
                ]);

                $product = Product::firstOrCreate(
                    ['name' => "Replacement Unit / Parts for {$maintenanceRequest->asset_name}"],
                    [
                        'sku'       => 'MNT-' . strtoupper(Str::random(6)),
                        'unit'      => 'pcs',
                        'category'  => 'Maintenance / Replacement',
                        'is_active' => true,
                    ]
                );

                $newMat->items()->create([
                    'product_id'         => $product->id,
                    'quantity_requested' => 1,
                    'notes'              => "Maintenance Ticket #{$maintenanceRequest->request_no}: {$maintenanceRequest->asset_name} ({$condLabel})",
                ]);

                ActivityLog::log(
                    'created',
                    "GM created Material Request #{$newMat->reference_number} and forwarded to Store Manager for PR purchase cycle",
                    'Material Requests',
                    $newMat
                );
                $activityDetails[] = "created Material Request #{$newMat->reference_number} sent to Store Manager for PR cycle";
            }
        }

        // Summary on ticket
        $summary = "GM Approved (" . ucfirst(str_replace('_', ' ', $finalStatus)) . ")";
        if (count($activityDetails) > 1) {
            $summary .= " — " . implode(', ', array_slice($activityDetails, 1));
        }
        $maintenanceRequest->update(['gm_decision_summary' => $summary]);

        ActivityLog::log(
            'updated',
            implode('; ', $activityDetails) . " — Decision Locked in History",
            'Maintenance Requests',
            $maintenanceRequest
        );

        return redirect()->route('gm.maintenance-approvals.index', ['tab' => 'history'])
            ->with('success', "Ticket #{$maintenanceRequest->request_no} approved, routed (Money → Finance to Pay, Materials → Store PR), and locked into Decision History!");
    }

    /**
     * Unlock a locked ticket decision if needed.
     */
    public function unlockTicket(MaintenanceRequest $maintenanceRequest)
    {
        $this->checkGmAuthorization();
        $maintenanceRequest->update([
            'gm_decision_locked' => false,
            'status'             => 'in_progress',
        ]);

        ActivityLog::log(
            'updated',
            "GM unlocked Maintenance Ticket #{$maintenanceRequest->request_no} from Decision History",
            'Maintenance Requests',
            $maintenanceRequest
        );

        return redirect()->route('gm.maintenance-approvals.index', ['tab' => 'tickets'])
            ->with('info', "Decision for Ticket #{$maintenanceRequest->request_no} unlocked and returned to incoming queue.");
    }

    /**
     * Process GM Decision for an Expense Request ("Ask Money").
     * Options:
     * - approve_coordinator: GM approves and sends to Coordinator Expenses Approval section for budget review.
     * - approve_finance: passes to Finance section (Finance Head/Staff) for disbursement.
     * - send_to_store: GM directs to fulfill via store inventory instead of cash; creates Material Request.
     * - reject: returns with rejection reason.
     */
    public function approveExpense(Request $request, ExpenseRequest $expenseRequest)
    {
        $this->checkGmAuthorization();

        $validated = $request->validate([
            'action'                     => 'required|in:approve_coordinator,approve_finance,send_to_store,reject',
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

        // ── Option C: Approve & Send to Coordinator Expenses Approval ───────────────
        if ($validated['action'] === 'approve_coordinator') {
            $desc = $expenseRequest->description;
            if (!empty($validated['gm_notes'])) {
                $desc .= "\n[GM Directive for Coordinator: " . $validated['gm_notes'] . "]";
            } else {
                $desc .= "\n[GM Approved: Sent to Coordinator Expenses Approval section]";
            }

            $expenseRequest->update([
                'status'           => ExpenseRequest::STATUS_PENDING_HR,
                'gm_reviewer_id'   => $user->id,
                'gm_approver_id'   => $user->id,
                'gm_reviewed_at'   => now(),
                'gm_approved_at'   => now(),
                'description'      => $desc,
                'rejection_reason' => null,
            ]);

            if ($maintReq) {
                ActivityLog::log(
                    'approved',
                    "GM approved Expense Request #{$expenseRequest->request_number} for ETB " . number_format($expenseRequest->amount, 2) . " and sent to Coordinator Expenses Approval section",
                    'Maintenance Requests',
                    $maintReq
                );
            }

            ActivityLog::log(
                'approved',
                "GM approved Expense Request #{$expenseRequest->request_number} for ETB " . number_format($expenseRequest->amount, 2) . " and sent to Coordinator Expenses Approval section",
                'Expense Requests',
                $expenseRequest
            );

            return back()->with('success', "Expense Request #{$expenseRequest->request_number} (ETB " . number_format($expenseRequest->amount, 2) . ") approved by GM and sent to Coordinator Expenses Approval section!");
        }

        // ── Option D: Approve & Pass to Finance ───────────────────────────────────────
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
