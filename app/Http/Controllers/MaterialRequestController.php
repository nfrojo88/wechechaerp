<?php

namespace App\Http\Controllers;

use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\PurchaseRequestItem;
use App\Models\Store;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\PrWorkflowLog;
use App\Services\ProcurementLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class MaterialRequestController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', MaterialRequest::class);
        
        $query = MaterialRequest::with(['project', 'store', 'creator'])->latest();

        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        if ($user && $user->hasRole('site_engineer') && !$user->hasAnyRole(['admin', 'global_admin', 'coordinator', 'store_manager', 'purchase_manager'])) {
            $assignedProjectIds = $user->projects()->pluck('projects.id');
            if ($user->employee?->project_id) {
                $assignedProjectIds->push($user->employee->project_id);
            }
            if ($user->store && $user->store->project_id) {
                $assignedProjectIds->push($user->store->project_id);
            }
            $query->where(function($q) use ($user, $assignedProjectIds) {
                $q->whereIn('project_id', $assignedProjectIds->unique())
                  ->orWhere('created_by', $user->id);
            });
        } elseif ($user && $user->hasRole('store_keeper') && !$user->hasAnyRole(['admin', 'global_admin', 'coordinator', 'store_manager', 'purchase_manager'])) {
            // Scoped strictly to Store Keeper's assigned store(s) / project(s)
            $assignedStoreIds = collect([$user->store_id])
                ->concat(Store::where('manager_id', $user->id)->pluck('id'))
                ->concat(Store::whereHas('users', fn($q) => $q->where('users.id', $user->id))->pluck('id'))
                ->filter()
                ->unique();

            $assignedProjectIds = Store::whereIn('id', $assignedStoreIds)->whereNotNull('project_id')->pluck('project_id')->unique();

            if ($assignedStoreIds->isNotEmpty() || $assignedProjectIds->isNotEmpty()) {
                $query->where(function($q) use ($assignedStoreIds, $assignedProjectIds) {
                    if ($assignedStoreIds->isNotEmpty()) {
                        $q->whereIn('destination_store_id', $assignedStoreIds);
                    }
                    if ($assignedProjectIds->isNotEmpty()) {
                        $q->orWhereIn('project_id', $assignedProjectIds);
                    }
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        
        $requests = $query->paginate(15);
        
        return view('procurement.requests.index', compact('requests'));
    }

    public function create(Request $request)
    {
        Gate::authorize('create', MaterialRequest::class);
        
        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        $isSiteEngineer = $user && $user->hasRole('site_engineer') && !$user->hasAnyRole(['admin', 'global_admin', 'store_manager', 'purchase_manager', 'coordinator', 'planning_manager']);

        // Thoroughly resolve all assigned project IDs for the user
        $assignedProjectIds = collect();
        if ($user) {
            if ($user->projects()->exists()) {
                $assignedProjectIds = $assignedProjectIds->concat($user->projects()->pluck('projects.id'));
            }
            if (!empty($user->project_id)) {
                $assignedProjectIds->push($user->project_id);
            }
            if ($user->employee && !empty($user->employee->project_id)) {
                $assignedProjectIds->push($user->employee->project_id);
            }
            if ($user->store && !empty($user->store->project_id)) {
                $assignedProjectIds->push($user->store->project_id);
            }
            if (!empty($user->store_id)) {
                $userStore = Store::find($user->store_id);
                if ($userStore && !empty($userStore->project_id)) {
                    $assignedProjectIds->push($userStore->project_id);
                }
            }
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('project_user')) {
                    $pu = \Illuminate\Support\Facades\DB::table('project_user')->where('user_id', $user->id)->pluck('project_id');
                    $assignedProjectIds = $assignedProjectIds->concat($pu);
                }
            } catch (\Throwable $e) {}
        }
        $assignedProjectIds = $assignedProjectIds->filter()->unique();

        $projectsQuery = Project::where('status', '!=', 'cancelled')->with('stores');
        if ($isSiteEngineer && $assignedProjectIds->isNotEmpty()) {
            $projectsQuery->whereIn('id', $assignedProjectIds);
        }
        $projects = $projectsQuery->get();
        if ($projects->isEmpty()) {
            $projects = Project::where('status', '!=', 'cancelled')->with('stores')->get();
        }

        $storesQuery = Store::where('is_active', true);
        if ($isSiteEngineer && $assignedProjectIds->isNotEmpty()) {
            $storesQuery->where(function($q) use ($user, $assignedProjectIds) {
                $q->whereIn('project_id', $assignedProjectIds);
                if ($user && $user->store_id) {
                    $q->orWhere('id', $user->store_id);
                }
            });
        } elseif ($user && $user->store_id) {
            $storesQuery->where('id', $user->store_id);
        }
        $stores = $storesQuery->get();
        if ($stores->isEmpty()) {
            $stores = Store::where('is_active', true)->get();
        }

        // Determine default auto-selected project:
        $selectedProjectId = $request->query('project_id');
        if (!$selectedProjectId && $assignedProjectIds->isNotEmpty()) {
            $selectedProjectId = $assignedProjectIds->first();
        }
        if (!$selectedProjectId && $user) {
            $selectedProjectId = $user->project_id
                ?? $user->employee?->project_id
                ?? $user->store?->project_id;
        }
        if (!$selectedProjectId && $projects->isNotEmpty()) {
            $selectedProjectId = $projects->first()->id;
        }

        // Determine default auto-selected destination store:
        $selectedStoreId = $request->query('destination_store_id');
        if (!$selectedStoreId && $user && $user->store_id) {
            $selectedStoreId = $user->store_id;
        }
        if (!$selectedStoreId && $selectedProjectId) {
            $selectedStoreId = $stores->where('project_id', $selectedProjectId)->first()?->id
                ?? $projects->where('id', $selectedProjectId)->first()?->default_store_id
                ?? $projects->where('id', $selectedProjectId)->first()?->stores->first()?->id
                ?? Store::where('project_id', $selectedProjectId)->where('is_active', true)->first()?->id;
        }
        if (!$selectedStoreId && $stores->isNotEmpty()) {
            $selectedStoreId = $stores->first()->id;
        }

        $dateNeeded = $request->query('date_needed');
        $materialName = $request->query('material_name');
        $quantity = $request->query('quantity');
        $unit = $request->query('unit');
        $rawSource = $request->query('source');
        $redirectBack = $request->query('redirect_back');
        
        if ($rawSource) {
            $source = $rawSource;
        } else {
            $source = 'Emergency';
        }

        $products = \App\Models\Product::where('is_active', true)->orderBy('name')->get();
        
        return view('procurement.requests.create', compact(
            'projects', 'stores', 'products', 'selectedProjectId', 'selectedStoreId', 'dateNeeded', 'materialName', 'quantity', 'unit', 'source', 'redirectBack'
        ));
    }

    public function store(Request $request)
    {
        Gate::authorize('create', MaterialRequest::class);
        
        $validated = $request->validate([
            'project_id'           => 'required|exists:projects,id',
            'destination_store_id' => 'required|exists:stores,id',
            'reference_number'     => 'required|string|unique:material_requests,reference_number',
            'source'               => 'nullable|string|max:255',
            'required_date'        => 'required|date',
            'notes'                => 'nullable|string',
            'items'                => 'nullable|array',
            'items.*.product_id'   => 'nullable|exists:products,id',
            'items.*.quantity'     => 'nullable|numeric|min:0.001',
            'items.*.notes'        => 'nullable|string',
        ]);
        
        $validated['source'] = $request->input('source', 'Emergency');
        $validated['created_by'] = auth()->id();
        $validated['status'] = 'pending_planning';
        $validated['planning_approval_status'] = 'pending';

        if (!\Illuminate\Support\Facades\Schema::hasColumn('material_requests', 'source')) {
            unset($validated['source']);
        }
        if (!\Illuminate\Support\Facades\Schema::hasColumn('material_requests', 'planning_approval_status')) {
            unset($validated['planning_approval_status']);
        }
        
        $mr = MaterialRequest::create($validated);

        // Process dynamic items array
        if ($request->has('items') && is_array($request->items)) {
            foreach ($request->items as $itemData) {
                if (empty($itemData['product_id']) || empty($itemData['quantity'])) {
                    continue;
                }
                $mr->items()->create([
                    'product_id'         => $itemData['product_id'],
                    'quantity_requested' => (float)$itemData['quantity'],
                    'notes'              => $itemData['notes'] ?? null,
                ]);
            }
        }

        // Single material name query fallback
        $materialName = $request->input('material_name');
        $quantity = $request->input('quantity');
        $unit = $request->input('unit');
        if (!empty($materialName) && $mr->items()->count() === 0) {
            $product = \App\Models\Product::where('name', 'like', "%{$materialName}%")->first();
            if ($product) {
                $mr->items()->create([
                    'product_id'         => $product->id,
                    'quantity_requested' => (float)($quantity ?? 1),
                    'notes'              => 'Auto-added from Demand Forecast (' . ($unit ?? '') . ')'
                ]);
            }
        }

        // Auto-create or link companion Purchase Request so this Material Request immediately enters the PR Procurement Flow
        $linkedPr = null;
        try {
            $linkedPr = $mr->createOrGetPurchaseRequest(auth()->id());
        } catch (\Throwable $e) {
            Log::error("Failed to auto-create companion PR for MR #{$mr->reference_number}: " . $e->getMessage());
        }

        if ($request->filled('redirect_back')) {
            $msg = "Material Request #{$mr->reference_number} submitted to Procurement Queue";
            if ($linkedPr) {
                $msg .= " (PR #{$linkedPr->pr_no}).";
            }
            return redirect($request->input('redirect_back'))->with('success', $msg);
        }
        
        $msg = "Material Request #{$mr->reference_number} created and entered into the Procurement Lifecycle";
        if ($linkedPr) {
            $msg .= " as Purchase Request #{$linkedPr->pr_no}.";
        }
        return redirect()->route('material-requests.show', $mr)->with('success', $msg);
    }

    public function show(MaterialRequest $materialRequest)
    {
        Gate::authorize('view', $materialRequest);
        $materialRequest->load([
            'project', 'store', 'creator', 'items.product',
            'purchaseRequests.receipt.uploadedBy', 'purchaseRequests.payment'
        ]);
        $products = \App\Models\Product::where('is_active', true)->get();
        
        return view('procurement.requests.show', compact('materialRequest', 'products'));
    }
    
    public function updateStatus(Request $request, MaterialRequest $materialRequest)
    {
        $status = $request->input('status');
        
        if ($status === 'submitted' || $status === 'pending_planning') {
            Gate::authorize('update', $materialRequest);
            $materialRequest->update(['status' => 'pending_planning']);
            return back()->with('success', 'Request sent to Planning Manager for approval.');
        }
        
        if ($status === 'approved' || $status === 'rejected') {
            Gate::authorize('approve', $materialRequest);
            $materialRequest->update([
                'status' => $status,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
            return back()->with('success', "Request {$status}.");
        }
        
        return back()->with('error', 'Invalid status transition.');
    }

    // ─── Emergency & Standard MR Approval by Planning Team ──────────────────
    public function planningApprove(MaterialRequest $materialRequest)
    {
        $user = auth()->user();
        $roles = $user ? $user->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray() : [];
        if (!in_array('planning_manager', $roles)) {
            abort(403, 'Unauthorized: Emergency material requests awaiting planning approval can only be approved by the Planning Manager.');
        }

        Gate::authorize('approvePlanning', $materialRequest);
        $materialRequest->update([
            'planning_approval_status' => 'approved',
            'planning_approved_by'      => auth()->id(),
            'planning_approved_at'      => now(),
            'status'                    => 'planning_approved', // Move to Coordinator
        ]);

        // Forward companion Purchase Request to Coordinator
        try {
            $lifecycle = app(ProcurementLifecycleService::class);
            $materialRequest->loadMissing('purchaseRequests');
            if ($materialRequest->purchaseRequests->isEmpty()) {
                $materialRequest->createOrGetPurchaseRequest(auth()->id());
                $materialRequest->load('purchaseRequests');
            }
            foreach ($materialRequest->purchaseRequests as $pr) {
                if (in_array($pr->status, [PurchaseRequest::STATUS_PENDING_PLANNING, 'pending_planning', PurchaseRequest::STATUS_DRAFT])) {
                    $targetRole = $lifecycle->resolveOwnerRole('coordinator', $pr);
                    $pr->update([
                        'status'             => PurchaseRequest::STATUS_PENDING_HR_APPROVAL,
                        'current_owner_role' => $targetRole,
                        'approved_by'        => auth()->id(),
                        'approved_at'        => now(),
                    ]);

                    try {
                        PrWorkflowLog::create([
                            'purchase_request_id' => $pr->id,
                            'from_stage'          => PurchaseRequest::STATUS_PENDING_PLANNING,
                            'to_stage'            => PurchaseRequest::STATUS_PENDING_HR_APPROVAL,
                            'action'              => 'planning_approve_to_coordinator',
                            'actor_role'          => 'planning',
                            'actor_id'            => auth()->id(),
                            'notes'               => "Planning Team approved Material Request #{$materialRequest->reference_number}. PR forwarded to Project Coordinator.",
                            'created_at'          => now(),
                        ]);
                    } catch (\Throwable $e) {}

                    try {
                        $lifecycle->notifyStageRole(
                            $pr->id,
                            $targetRole,
                            "ConstructPro: PR #{$pr->pr_no} approved by Planning for Project: " . ($pr->project?->name ?? 'General') . ". Awaiting coordinator review. Open: " . url("/purchase-requests/{$pr->id}"),
                            $pr->project_id,
                            $pr->store_id
                        );
                    } catch (\Throwable $e) {}
                }
            }
        } catch (\Throwable $e) {
            Log::error("Failed to forward companion PRs to coordinator for MR #{$materialRequest->reference_number}: " . $e->getMessage());
        }

        return back()->with('success', 'Material Request approved by Planning Manager and sent to Coordinator.');
    }

    /**
     * Emergency MR Approval by Planning Manager specifically for direct Site Petty Cash purchase.
     * Routes directly to the Store Keeper's Buy Material (Petty Cash) queue, bypassing regular PO procurement.
     */
    public function planningApprovePettyCash(MaterialRequest $materialRequest)
    {
        $user = auth()->user();
        $roles = $user ? $user->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray() : [];
        if (!in_array('planning_manager', $roles) && !$user->hasRole('Planning Manager')) {
            abort(403, 'Unauthorized: Emergency material requests awaiting planning approval can only be approved by the Planning Manager.');
        }

        Gate::authorize('approvePlanning', $materialRequest);
        $materialRequest->update([
            'planning_approval_status' => 'approved',
            'planning_approved_by'      => auth()->id(),
            'planning_approved_at'      => now(),
            'status'                    => 'approved_for_petty_cash',
            'purchase_method'           => 'petty_cash',
        ]);

        // Mark companion Purchase Requests so they don't clutter normal PR queues
        try {
            $materialRequest->loadMissing('purchaseRequests');
            foreach ($materialRequest->purchaseRequests as $pr) {
                if (in_array($pr->status, [PurchaseRequest::STATUS_PENDING_PLANNING, 'pending_planning', PurchaseRequest::STATUS_DRAFT])) {
                    $pr->update([
                        'status'      => 'approved_petty_cash',
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                        'notes'       => ($pr->notes ? $pr->notes . "\n" : "") . "Approved by Planning Manager for direct Site Petty Cash purchase by Store Keeper.",
                    ]);

                    try {
                        PrWorkflowLog::create([
                            'purchase_request_id' => $pr->id,
                            'from_stage'          => PurchaseRequest::STATUS_PENDING_PLANNING,
                            'to_stage'            => 'approved_petty_cash',
                            'action'              => 'planning_approve_petty_cash',
                            'actor_role'          => 'planning',
                            'actor_id'            => auth()->id(),
                            'notes'               => "Planning Manager approved Emergency Material Request #{$materialRequest->reference_number} for Site Petty Cash purchase by Store Keeper.",
                            'created_at'          => now(),
                        ]);
                    } catch (\Throwable $e) {}
                }
            }
        } catch (\Throwable $e) {
            Log::error("Failed to update companion PRs for MR #{$materialRequest->reference_number}: " . $e->getMessage());
        }

        return back()->with('success', "Emergency Material Request #{$materialRequest->reference_number} approved for Site Petty Cash purchase! It is now waiting in the Store Keeper's Buy Material queue.");
    }

    public function planningReject(Request $request, MaterialRequest $materialRequest)
    {
        $user = auth()->user();
        $roles = $user ? $user->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray() : [];
        if (!in_array('planning_manager', $roles)) {
            abort(403, 'Unauthorized: Emergency material requests awaiting planning approval can only be rejected by the Planning Manager.');
        }

        Gate::authorize('approvePlanning', $materialRequest);
        $request->validate(['rejection_reason' => 'required|string']);
        $materialRequest->update([
            'planning_approval_status' => 'rejected',
            'planning_approved_by'      => auth()->id(),
            'planning_approved_at'      => now(),
            'planning_rejection_reason' => $request->rejection_reason,
            'status'                    => 'rejected',
        ]);

        try {
            $materialRequest->loadMissing('purchaseRequests');
            foreach ($materialRequest->purchaseRequests as $pr) {
                if (in_array($pr->status, [PurchaseRequest::STATUS_PENDING_PLANNING, PurchaseRequest::STATUS_DRAFT])) {
                    $pr->update([
                        'status'           => PurchaseRequest::STATUS_REJECTED,
                        'rejection_reason' => $request->rejection_reason,
                    ]);

                    try {
                        PrWorkflowLog::create([
                            'purchase_request_id' => $pr->id,
                            'from_stage'          => $pr->status,
                            'to_stage'            => PurchaseRequest::STATUS_REJECTED,
                            'action'              => 'planning_reject',
                            'actor_role'          => 'planning',
                            'actor_id'            => auth()->id(),
                            'notes'               => "Planning Team rejected Material Request: " . $request->rejection_reason,
                            'created_at'          => now(),
                        ]);
                    } catch (\Throwable $e) {}
                }
            }
        } catch (\Throwable $e) {
            Log::error("Failed to reject companion PRs for MR #{$materialRequest->reference_number}: " . $e->getMessage());
        }

        return back()->with('success', 'Material Request rejected by Planning Manager.');
    }

    /**
     * Planning Manager item-level / split decision for Emergency Material Requests:
     * Allows selecting which material items to approve for standard procurement,
     * which to send directly to Site Petty Cash (Store Keeper), and which to reject.
     */
    public function itemDecisions(Request $request, MaterialRequest $materialRequest)
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        $roles = $user ? $user->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray() : [];
        if (!in_array('planning_manager', $roles) && !$user?->hasRole('Planning Manager')) {
            abort(403, 'Unauthorized: Emergency material requests awaiting planning approval can only be decided by the Planning Manager.');
        }

        Gate::authorize('approvePlanning', $materialRequest);

        $validated = $request->validate([
            'decisions'           => 'required|array|min:1',
            'decisions.*'         => 'required|in:approve_procurement,buy_petty_cash,reject',
            'rejection_reasons'   => 'nullable|array',
            'rejection_reasons.*' => 'nullable|string|max:1000',
        ]);

        $decisions = $validated['decisions'];
        $rejectionReasons = $validated['rejection_reasons'] ?? [];

        $materialRequest->loadMissing('items.product', 'purchaseRequests');

        if ($materialRequest->items->isEmpty()) {
            return back()->with('error', 'Cannot process item decisions: This Material Request has no items.');
        }

        $procurementItemIds = [];
        $pettyCashItemIds   = [];
        $rejectedItemIds    = [];

        foreach ($materialRequest->items as $item) {
            $dec = $decisions[$item->id] ?? 'approve_procurement';
            if ($dec === 'buy_petty_cash') {
                $pettyCashItemIds[] = $item->id;
            } elseif ($dec === 'reject') {
                $rejectedItemIds[] = $item->id;
            } else {
                $procurementItemIds[] = $item->id;
            }
        }

        $totalItems = $materialRequest->items->count();

        // ── 1. If ALL items are Buy with Petty Cash ───────────────────────────
        if (count($pettyCashItemIds) === $totalItems) {
            return $this->planningApprovePettyCash($materialRequest);
        }

        // ── 2. If ALL items are Approve Procurement ───────────────────────────
        if (count($procurementItemIds) === $totalItems) {
            return $this->planningApprove($materialRequest);
        }

        // ── 3. If ALL items are Rejected ──────────────────────────────────────
        if (count($rejectedItemIds) === $totalItems) {
            $combinedReason = collect($rejectedItemIds)->map(function($id) use ($rejectionReasons) {
                return $rejectionReasons[$id] ?? null;
            })->filter()->implode('; ');
            $request->merge(['rejection_reason' => $combinedReason ?: 'Rejected by Planning Manager']);
            return $this->planningReject($request, $materialRequest);
        }

        // ── 4. Split / Mixed Decisions ────────────────────────────────────────
        $redirectMsg = '';

        DB::transaction(function () use (
            $materialRequest,
            $procurementItemIds,
            $pettyCashItemIds,
            $rejectedItemIds,
            $rejectionReasons,
            $user,
            &$redirectMsg
        ) {
            $hasProcurement = count($procurementItemIds) > 0;
            $hasPettyCash   = count($pettyCashItemIds) > 0;
            $hasRejected    = count($rejectedItemIds) > 0;

            $baseRef = preg_replace('/-(PC|REJ)\d*$/', '', $materialRequest->reference_number);

            // ── Step A: Handle Rejected Items ──────────────────────────────────
            if ($hasRejected) {
                $rejNotes = [];
                foreach ($rejectedItemIds as $rId) {
                    $rItem = MaterialRequestItem::find($rId);
                    $reason = !empty($rejectionReasons[$rId]) ? trim($rejectionReasons[$rId]) : 'Rejected during planning split review';
                    if ($rItem) {
                        $rNotes = "[REJECTED by Planning: {$reason}]" . ($rItem->notes ? " " . $rItem->notes : "");
                        $itemData = ['notes' => $rNotes];
                        if (\Illuminate\Support\Facades\Schema::hasColumn('material_request_items', 'status')) {
                            $itemData['status'] = 'rejected';
                        }
                        if (\Illuminate\Support\Facades\Schema::hasColumn('material_request_items', 'rejection_reason')) {
                            $itemData['rejection_reason'] = $reason;
                        }
                        $rItem->update($itemData);
                        $rejNotes[] = ($rItem->product?->name ?? "Item #{$rId}") . " (Reason: {$reason})";
                    }
                }
                $rejSummary = implode("; ", $rejNotes);

                // Move rejected items to a dedicated child rejected MR so they don't stay in actionable queues
                if ($hasProcurement || $hasPettyCash) {
                    $rejRef = $baseRef . '-REJ';
                    $c = 1;
                    while (MaterialRequest::where('reference_number', $rejRef)->exists()) {
                        $c++;
                        $rejRef = $baseRef . '-REJ' . $c;
                    }

                    $rejectedMr = MaterialRequest::create([
                        'project_id'                => $materialRequest->project_id,
                        'destination_store_id'      => $materialRequest->destination_store_id,
                        'reference_number'          => $rejRef,
                        'source'                    => 'Emergency (Split Rejected)',
                        'status'                    => 'rejected',
                        'purchase_method'           => null,
                        'required_date'             => $materialRequest->required_date,
                        'notes'                     => "Rejected items split from Emergency Request #{$materialRequest->reference_number}. " . $rejSummary,
                        'created_by'                => $materialRequest->created_by,
                        'approved_by'               => $user->id,
                        'approved_at'               => now(),
                        'planning_approval_status'  => 'rejected',
                        'planning_approved_by'      => $user->id,
                        'planning_approved_at'      => now(),
                        'planning_rejection_reason' => $rejSummary,
                    ]);

                    foreach ($rejectedItemIds as $rId) {
                        $rItem = MaterialRequestItem::find($rId);
                        if ($rItem) {
                            $rItem->update(['material_request_id' => $rejectedMr->id]);
                        }
                    }
                }
            }

            // ── Step B: Handle Petty Cash Items ────────────────────────────────
            if ($hasPettyCash) {
                if ($hasProcurement) {
                    // Mixed Procurement + Petty Cash: Create child MR for Petty Cash items
                    $pcRef = $baseRef . '-PC';
                    $c = 1;
                    while (MaterialRequest::where('reference_number', $pcRef)->exists()) {
                        $c++;
                        $pcRef = $baseRef . '-PC' . $c;
                    }

                    $pettyCashMr = MaterialRequest::create([
                        'project_id'               => $materialRequest->project_id,
                        'destination_store_id'     => $materialRequest->destination_store_id,
                        'reference_number'         => $pcRef,
                        'source'                   => 'Emergency (Petty Cash Split)',
                        'status'                   => 'approved_for_petty_cash',
                        'purchase_method'          => 'petty_cash',
                        'required_date'            => $materialRequest->required_date,
                        'notes'                    => "Site Petty Cash purchase split from Emergency Request #{$materialRequest->reference_number}." . ($materialRequest->notes ? " [Original notes: {$materialRequest->notes}]" : ''),
                        'created_by'               => $materialRequest->created_by,
                        'approved_by'              => $user->id,
                        'approved_at'              => now(),
                        'planning_approval_status' => 'approved',
                        'planning_approved_by'     => $user->id,
                        'planning_approved_at'     => now(),
                    ]);

                    foreach ($pettyCashItemIds as $pId) {
                        $pItem = MaterialRequestItem::find($pId);
                        if ($pItem) {
                            $itemData = ['material_request_id' => $pettyCashMr->id];
                            if (\Illuminate\Support\Facades\Schema::hasColumn('material_request_items', 'status')) {
                                $itemData['status'] = 'petty_cash';
                            }
                            $pItem->update($itemData);
                        }
                    }
                } else {
                    // ONLY Petty Cash (+ optionally Rejected): Original MR becomes the Petty Cash MR
                    $materialRequest->update([
                        'status'                   => 'approved_for_petty_cash',
                        'purchase_method'          => 'petty_cash',
                        'planning_approval_status' => 'approved',
                        'planning_approved_by'     => $user->id,
                        'planning_approved_at'     => now(),
                    ]);

                    foreach ($pettyCashItemIds as $pId) {
                        $pItem = MaterialRequestItem::find($pId);
                        if ($pItem && \Illuminate\Support\Facades\Schema::hasColumn('material_request_items', 'status')) {
                            $pItem->update(['status' => 'petty_cash']);
                        }
                    }

                    // Companion PRs are marked approved_petty_cash
                    $materialRequest->loadMissing('purchaseRequests');
                    foreach ($materialRequest->purchaseRequests as $cPr) {
                        $cPr->update([
                            'status' => 'approved_petty_cash',
                            'notes'  => ($cPr->notes ? $cPr->notes . "\n" : "") . "Routed to Site Petty Cash purchase by Planning Manager during item split.",
                        ]);
                    }
                }
            }

            // ── Step C: Handle Procurement Items ────────────────────────────────
            if ($hasProcurement) {
                $materialRequest->update([
                    'status'                   => 'planning_approved',
                    'planning_approval_status' => 'approved',
                    'planning_approved_by'     => $user->id,
                    'planning_approved_at'     => now(),
                ]);

                foreach ($procurementItemIds as $prId) {
                    $prItem = MaterialRequestItem::find($prId);
                    if ($prItem && \Illuminate\Support\Facades\Schema::hasColumn('material_request_items', 'status')) {
                        $prItem->update(['status' => 'approved']);
                    }
                }

                // Companion PR: Keep ONLY the procurement items and forward to Coordinator
                $lifecycle = app(ProcurementLifecycleService::class);
                $companionPr = $materialRequest->purchaseRequests()->latest()->first();
                if (!$companionPr) {
                    $companionPr = $materialRequest->createOrGetPurchaseRequest($user->id);
                }

                if ($companionPr) {
                    $activeProcurementProductIds = MaterialRequestItem::where('material_request_id', $materialRequest->id)
                        ->whereIn('id', $procurementItemIds)
                        ->pluck('product_id')
                        ->toArray();

                    \App\Models\PurchaseRequestItem::where('purchase_request_id', $companionPr->id)
                        ->whereNotIn('product_id', $activeProcurementProductIds)
                        ->delete();

                    $targetRole = $lifecycle->resolveOwnerRole('coordinator', $companionPr);
                    $companionPr->update([
                        'status'             => PurchaseRequest::STATUS_PENDING_HR_APPROVAL,
                        'current_owner_role' => $targetRole,
                        'approved_by'        => $user->id,
                        'approved_at'        => now(),
                    ]);

                    try {
                        PrWorkflowLog::create([
                            'purchase_request_id' => $companionPr->id,
                            'from_stage'          => PurchaseRequest::STATUS_PENDING_PLANNING,
                            'to_stage'            => PurchaseRequest::STATUS_PENDING_HR_APPROVAL,
                            'action'              => 'planning_approve_split_to_coordinator',
                            'actor_role'          => 'planning',
                            'actor_id'            => $user->id,
                            'notes'               => "Planning Manager approved " . count($procurementItemIds) . " item(s) for Procurement. Other items split to Petty Cash / Rejected. PR forwarded to Coordinator.",
                            'created_at'          => now(),
                        ]);
                    } catch (\Throwable $e) {}

                    try {
                        $lifecycle->notifyStageRole(
                            $companionPr->id,
                            $targetRole,
                            "ConstructPro: Emergency PR #{$companionPr->pr_no} approved (split items) by Planning for Project: " . ($companionPr->project?->name ?? 'General') . ". Awaiting coordinator review. Open: " . url("/purchase-requests/{$companionPr->id}"),
                            $companionPr->project_id,
                            $companionPr->store_id
                        );
                    } catch (\Throwable $e) {}
                }
            }

            // Step D: Activity Log & Summary Message
            try {
                \App\Models\ActivityLog::log(
                    'planning_split_mr_decisions',
                    "Planning Manager '{$user->name}' processed split item decisions for Emergency MR #{$materialRequest->reference_number}: " .
                    count($procurementItemIds) . " procurement, " .
                    count($pettyCashItemIds) . " petty cash, " .
                    count($rejectedItemIds) . " rejected.",
                    'Procurement',
                    $materialRequest,
                    [
                        'procurement_items' => $procurementItemIds,
                        'petty_cash_items'  => $pettyCashItemIds,
                        'rejected_items'    => $rejectedItemIds,
                    ]
                );
            } catch (\Throwable $e) {}

            $parts = [];
            if ($hasProcurement) {
                $parts[] = count($procurementItemIds) . " item(s) approved for Procurement (sent to Coordinator)";
            }
            if ($hasPettyCash) {
                $parts[] = count($pettyCashItemIds) . " item(s) sent to Site Petty Cash (Store Keeper queue)";
            }
            if ($hasRejected) {
                $parts[] = count($rejectedItemIds) . " item(s) rejected";
            }
            $redirectMsg = "Emergency Request #{$materialRequest->reference_number} items processed: " . implode(', ', $parts) . ".";
        });

        return back()->with('success', $redirectMsg);
    }

    // ─── Dispatch by Coordinator to Store Manager ────────────────────────────
    public function coordinatorDispatch(MaterialRequest $materialRequest)
    {
        Gate::authorize('dispatchCoordinator', $materialRequest);
        $materialRequest->update([
            'status' => 'sent_to_store_manager',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        try {
            $lifecycle = app(ProcurementLifecycleService::class);
            $materialRequest->loadMissing('purchaseRequests');
            if ($materialRequest->purchaseRequests->isEmpty()) {
                $materialRequest->createOrGetPurchaseRequest(auth()->id());
                $materialRequest->load('purchaseRequests');
            }
            foreach ($materialRequest->purchaseRequests as $pr) {
                if (in_array($pr->status, [PurchaseRequest::STATUS_PENDING_HR_APPROVAL, PurchaseRequest::STATUS_DRAFT, PurchaseRequest::STATUS_PENDING_PLANNING])) {
                    $targetRole = $lifecycle->resolveOwnerRole('store_manager', $pr);
                    $pr->update([
                        'status'             => PurchaseRequest::STATUS_PENDING_STORE_REVIEW,
                        'current_owner_role' => $targetRole,
                    ]);

                    try {
                        PrWorkflowLog::create([
                            'purchase_request_id' => $pr->id,
                            'from_stage'          => PurchaseRequest::STATUS_PENDING_HR_APPROVAL,
                            'to_stage'            => PurchaseRequest::STATUS_PENDING_STORE_REVIEW,
                            'action'              => 'coordinator_dispatch_to_store',
                            'actor_role'          => 'coordinator',
                            'actor_id'            => auth()->id(),
                            'notes'               => "Coordinator dispatched Material Request #{$materialRequest->reference_number} to Store Manager.",
                            'created_at'          => now(),
                        ]);
                    } catch (\Throwable $e) {}

                    try {
                        $lifecycle->notifyStageRole(
                            $pr->id,
                            $targetRole,
                            "ConstructPro: PR #{$pr->pr_no} dispatched by Coordinator to Store Manager for Project: " . ($pr->project?->name ?? 'General') . ". Open: " . url("/purchase-requests/{$pr->id}"),
                            $pr->project_id,
                            $pr->store_id
                        );
                    } catch (\Throwable $e) {}
                }
            }
        } catch (\Throwable $e) {
            Log::error("Failed to dispatch companion PRs to store for MR #{$materialRequest->reference_number}: " . $e->getMessage());
        }

        return back()->with('success', 'Material Request sent to Store Manager.');
    }

    /**
     * Coordinator routes Emergency MR directly to Store Keeper for Site Petty Cash purchase.
     */
    public function coordinatorApprovePettyCash(MaterialRequest $materialRequest)
    {
        Gate::authorize('dispatchCoordinator', $materialRequest);
        $materialRequest->update([
            'status'          => 'approved_for_petty_cash',
            'purchase_method' => 'petty_cash',
            'approved_by'     => auth()->id(),
            'approved_at'     => now(),
        ]);

        try {
            $materialRequest->loadMissing('purchaseRequests');
            foreach ($materialRequest->purchaseRequests as $pr) {
                $pr->update([
                    'status' => 'approved_petty_cash',
                    'notes'  => ($pr->notes ? $pr->notes . "\n" : "") . "Routed by Coordinator to direct Site Petty Cash purchase by Store Keeper.",
                ]);

                try {
                    PrWorkflowLog::create([
                        'purchase_request_id' => $pr->id,
                        'from_stage'          => $pr->status,
                        'to_stage'            => 'approved_petty_cash',
                        'action'              => 'coordinator_approve_petty_cash',
                        'actor_role'          => 'coordinator',
                        'actor_id'            => auth()->id(),
                        'notes'               => "Coordinator routed Emergency Material Request #{$materialRequest->reference_number} to Site Petty Cash purchase by Store Keeper.",
                        'created_at'          => now(),
                    ]);
                } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {}

        return back()->with('success', "Emergency Material Request #{$materialRequest->reference_number} routed to Site Petty Cash purchase! Sent to Store Keeper.");
    }

    public function coordinatorReject(Request $request, MaterialRequest $materialRequest)
    {
        Gate::authorize('dispatchCoordinator', $materialRequest);
        $request->validate(['rejection_reason' => 'required|string|max:1000']);

        $materialRequest->update([
            'status'                    => 'rejected',
            'planning_rejection_reason' => $request->rejection_reason,
            'approved_by'               => auth()->id(),
            'approved_at'               => now(),
        ]);

        try {
            $materialRequest->loadMissing('purchaseRequests');
            foreach ($materialRequest->purchaseRequests as $pr) {
                if (in_array($pr->status, [
                    PurchaseRequest::STATUS_PENDING_HR_APPROVAL,
                    PurchaseRequest::STATUS_DRAFT,
                    PurchaseRequest::STATUS_PENDING_PLANNING
                ])) {
                    $pr->update([
                        'status'           => PurchaseRequest::STATUS_REJECTED,
                        'rejection_reason' => $request->rejection_reason,
                    ]);

                    try {
                        PrWorkflowLog::create([
                            'purchase_request_id' => $pr->id,
                            'from_stage'          => $pr->status,
                            'to_stage'            => PurchaseRequest::STATUS_REJECTED,
                            'action'              => 'coordinator_reject',
                            'actor_role'          => 'coordinator',
                            'actor_id'            => auth()->id(),
                            'notes'               => "Coordinator rejected Material Request: " . $request->rejection_reason,
                            'created_at'          => now(),
                        ]);
                    } catch (\Throwable $e) {}
                }
            }
        } catch (\Throwable $e) {
            Log::error("Failed to reject companion PRs for MR #{$materialRequest->reference_number}: " . $e->getMessage());
        }

        return back()->with('success', 'Material Request rejected by Coordinator.');
    }

    // ─── Store Manager Actions: Send to PR or Create Transfer ────────────────
    public function sendToPr(MaterialRequest $materialRequest)
    {
        Gate::authorize('actionStoreManager', $materialRequest);
        $materialRequest->update(['status' => 'sent_to_pr']);
        $pr = $materialRequest->createOrGetPurchaseRequest(auth()->id());
        if ($pr->status === PurchaseRequest::STATUS_PENDING_STORE_REVIEW) {
            app(ProcurementLifecycleService::class)->sendToProcurementManager($pr, "Converted from Material Request #{$materialRequest->reference_number}");
        }

        return redirect()->route('purchase-requests.show', $pr)
            ->with('success', "Material Request #{$materialRequest->reference_number} successfully routed to Purchase Request #{$pr->pr_no} in the Procurement Lifecycle.");
    }

    public function convertToPr(MaterialRequest $materialRequest)
    {
        $materialRequest->update(['status' => 'sent_to_pr']);
        $pr = $materialRequest->createOrGetPurchaseRequest(auth()->id());
        if ($pr->status === PurchaseRequest::STATUS_PENDING_STORE_REVIEW) {
            app(ProcurementLifecycleService::class)->sendToProcurementManager($pr, "Converted from Material Request #{$materialRequest->reference_number}");
        }

        return redirect()->route('purchase-requests.show', $pr)
            ->with('success', "Material Request #{$materialRequest->reference_number} is now active in the Procurement Lifecycle as Purchase Request #{$pr->pr_no}.");
    }

    public function createTransfer(MaterialRequest $materialRequest)
    {
        Gate::authorize('actionStoreManager', $materialRequest);
        $materialRequest->update(['status' => 'transferred']);

        return redirect()->route('transfers.create', [
            'material_request_id' => $materialRequest->id,
            'project_id' => $materialRequest->project_id,
            'destination_store_id' => $materialRequest->destination_store_id,
        ])->with('success', 'Material Request routed to Store Transfer creation.');
    }

    /**
     * Delete an existing Material Request.
     * STRICT RULE: Locked after creation. Only Global Admin is permitted to delete.
     */
    public function destroy(Request $request, MaterialRequest $materialRequest)
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        $refNo = $materialRequest->reference_number ?? ('MR #' . $materialRequest->id);

        if (!$user || !$user->isGlobalAdmin()) {
            $actorRole = $user?->roles?->first()?->name ?? 'user';
            $userName = $user?->name ?? 'Unknown User';

            try {
                \App\Models\ActivityLog::log(
                    'unauthorized_delete_mr_blocked',
                    "Security violation: User '{$userName}' ({$actorRole}) attempted to delete locked Material Request {$refNo} without Global Admin authorization.",
                    'Procurement',
                    $materialRequest,
                    ['user_id' => $user?->id, 'mr_id' => $materialRequest->id]
                );
            } catch (\Throwable $e) {}

            return back()->with('error', 'Access Denied: Records are locked after creation and cannot be deleted. Only the Global Admin role is permitted to delete existing entries.');
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function() use ($materialRequest, $user, $refNo) {
                \App\Models\ActivityLog::log(
                    'admin_deleted_material_request',
                    "Global Admin '{$user->name}' deleted Material Request {$refNo} and all associated items.",
                    'Procurement',
                    null,
                    ['deleted_mr_id' => $materialRequest->id, 'ref_no' => $refNo]
                );

                $materialRequest->items()->delete();
                $materialRequest->delete();
            });

            return redirect()->route('procurement.my-queue')
                ->with('success', "Material Request {$refNo} has been deleted by Global Admin.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to delete Material Request: ' . $e->getMessage());
        }
    }

    /**
     * Send Instant SMS notification for Material Request current stage (Disabled/Removed).
     */
    public function sendStageSms(Request $request, MaterialRequest $materialRequest)
    {
        return back()->with('info', 'SMS notifications for procurement are disabled.');
    }
}

