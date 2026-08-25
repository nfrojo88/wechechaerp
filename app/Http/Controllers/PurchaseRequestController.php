<?php

namespace App\Http\Controllers;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\PrWorkflowLog;
use App\Models\Project;
use App\Models\Store;
use App\Models\Product;
use App\Models\MaterialRequest;
use App\Models\Supplier;
use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\User;
use App\Models\Inventory;
use App\Models\ProformaInvoice;
use App\Services\ProcurementLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PurchaseRequestController extends Controller
{
    public function __construct(private ProcurementLifecycleService $lifecycle) {}

    // ─── Index / List ────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $query = PurchaseRequest::with(['project', 'requestedBy'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->filled('source')) {
            $query->whereHas('materialRequest', fn($q) => $q->where('source', $request->source));
        }
        if ($request->filled('search')) {
            $query->where('pr_no', 'like', '%' . $request->search . '%');
        }

        $prs      = $query->paginate(20)->withQueryString();
        $projects = Project::whereIn('status', ['active', 'planning', 'in_progress', 'on_hold'])->orderBy('name')->get();
        if ($projects->isEmpty()) {
            $projects = Project::orderBy('name')->get();
        }
        $statuses = PurchaseRequest::statusLabels();

        return view('procurement.purchase-requests.index', compact('prs', 'projects', 'statuses'));
    }

    // ─── Create / Store ──────────────────────────────────────────────────────
    public function create()
    {
        $projects = Project::whereIn('status', ['active', 'planning', 'in_progress', 'on_hold'])->orderBy('name')->get();
        if ($projects->isEmpty()) {
            $projects = Project::orderBy('name')->get();
        }
        $stores           = Store::where('is_active', true)->get();
        $products = Product::orderBy('name')->get()->map(function($product) {
            $latestPriceRecord = \App\Models\MaterialPrice::where('product_id', $product->id)
                ->orderBy('effective_date', 'desc')
                ->first();
            $unitCost = $latestPriceRecord ? (float)$latestPriceRecord->price : (float)($product->unit_price ?? $product->selling_price ?? 0);
            $product->latest_marketing_price = $unitCost;
            return $product;
        });
        $materialRequests = MaterialRequest::where('status', 'approved')->get();
        return view('procurement.purchase-requests.create', compact('projects', 'stores', 'products', 'materialRequests'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'project_id'          => 'required|exists:projects,id',
            'store_id'            => 'nullable|exists:stores,id',
            'material_request_id' => 'nullable|exists:material_requests,id',
            'priority'            => 'required|in:normal,high,urgent',
            'type'                => 'required|in:normal,emergency,direct',
            'required_date'       => 'nullable|date',
            'justification'       => 'nullable|string',
            'items'               => 'required|array|min:1',
            'items.*.product_id'  => 'required|exists:products,id',
            'items.*.quantity'    => 'required|numeric|min:0.001',
            'items.*.unit'        => 'required|string|max:20',
        ]);

        DB::transaction(function () use ($request) {
            $no = 'PR-' . date('Ymd') . '-' . str_pad(PurchaseRequest::withTrashed()->count() + 1, 4, '0', STR_PAD_LEFT);

            $pr = PurchaseRequest::create([
                'pr_no'               => $no,
                'project_id'          => $request->project_id,
                'store_id'            => $request->store_id,
                'requested_by'        => Auth::id(),
                'material_request_id' => $request->material_request_id,
                'priority'            => $request->priority,
                'type'                => $request->type,
                'required_date'       => $request->required_date,
                'justification'       => $request->justification,
                'status'              => PurchaseRequest::STATUS_DRAFT,
                'current_owner_role'  => 'coordinator',
            ]);

            foreach ($request->items as $item) {
                $prod = Product::find($item['product_id']);
                $unit = !empty($item['unit']) ? $item['unit'] : ($prod?->unit ?? 'pcs');
                
                $latestPriceRecord = \App\Models\MaterialPrice::where('product_id', $item['product_id'])
                    ->orderBy('effective_date', 'desc')
                    ->first();
                $estCost = $latestPriceRecord ? (float)$latestPriceRecord->price : (float)($prod?->unit_price ?? $prod?->selling_price ?? 0);
                if (isset($item['estimated_unit_cost']) && $item['estimated_unit_cost'] !== '' && $item['estimated_unit_cost'] > 0) {
                    $estCost = (float) $item['estimated_unit_cost'];
                }

                $pr->items()->create([
                    'product_id'          => $item['product_id'],
                    'quantity'            => $item['quantity'],
                    'unit'                => $unit,
                    'specifications'      => $item['specifications'] ?? null,
                    'estimated_unit_cost' => $estCost,
                ]);
            }
        });

        return redirect()->route('purchase-requests.index')->with('success', 'Purchase Request created.');
    }

    // ─── Show ────────────────────────────────────────────────────────────────
    public function show(PurchaseRequest $purchaseRequest)
    {
        $purchaseRequest->load([
            'project', 'store', 'requestedBy', 'items.product',
            'marketResearch.supplier', 'proformaInvoices.supplier',
            'gmDecisions.decidedBy', 'marketingVariance.addedBy',
            'payment.coaAccount', 'payment.assignedStaff', 'payment.paidByUser',
            'receipt.uploadedBy', 'receipt.verifiedBy',
            'driverBooking.driver', 'driverBooking.bookedBy',
            'workflowLogs.actor',
        ]);

        // Cross-store stock availability for all items
        $stockAvailability = [];
        foreach ($purchaseRequest->items as $item) {
            if ($item->product_id) {
                $stockAvailability[$item->product_id] = Inventory::with('store')
                    ->where('product_id', $item->product_id)
                    ->where('quantity_on_hand', '>', 0)
                    ->get();
            } else {
                $stockAvailability[$item->id] = collect();
            }
        }

        // Data for action forms
        try {
            $coaAccounts = ChartOfAccount::where('is_active', true)
                ->whereIn('type', ['asset', 'expense'])
                ->orderBy('code')
                ->get();
        } catch (\Throwable $e) {
            $coaAccounts = collect();
        }

        try {
            $financeStaff = User::role(['finance', 'finance_head'])->get();
        } catch (\Throwable $e) {
            $financeStaff = User::all();
        }

        try {
            $drivers = Employee::where('status', 'active')
                ->where(function ($q) {
                    $q->whereIn('role_title', ['Driver', 'driver', 'General Service', 'general_service'])
                      ->orWhere('department', 'General Service');
                })
                ->orderBy('full_name')
                ->get();
        } catch (\Throwable $e) {
            $drivers = collect();
        }

        try {
            $suppliers = Supplier::where('status', 'active')
                ->orWhereNull('status')
                ->orderBy('name')
                ->get();
        } catch (\Throwable $e) {
            $suppliers = collect();
        }

        $stores = Store::where('is_active', true)->orderBy('name')->get();

        // Material Pricing Benchmarks (Monthly Marketing Survey vs Last Purchase Price)
        $pricingBenchmarks = [
            'items'                  => [],
            'total_monthly_market'   => 0,
            'total_last_purchase'    => 0,
            'total_latest_benchmark' => 0,
            'has_monthly_data'       => false,
            'has_purchase_data'      => false,
        ];

        foreach ($purchaseRequest->items as $item) {
            $monthlyPriceRec = null;
            $lastPurchaseRec = null;

            if ($item->product_id) {
                // 1. Monthly Market Price (Marketing Team Price List)
                try {
                    $monthlyPriceRec = \App\Models\MaterialPrice::where('product_id', $item->product_id)
                        ->orderBy('effective_date', 'desc')
                        ->orderBy('id', 'desc')
                        ->first();
                } catch (\Throwable $e) {}

                // 2. Last Purchase Price (from Purchase Orders, past PR items, or Product catalog)
                try {
                    $lastPoItem = \App\Models\PurchaseOrderItem::where('product_id', $item->product_id)
                        ->where('unit_price', '>', 0)
                        ->with('purchaseOrder')
                        ->orderBy('created_at', 'desc')
                        ->first();

                    if ($lastPoItem) {
                        $lastPurchaseRec = [
                            'price'  => (float)$lastPoItem->unit_price,
                            'date'   => $lastPoItem->created_at,
                            'source' => 'PO #' . ($lastPoItem->purchaseOrder?->po_number ?? $lastPoItem->purchase_order_id),
                        ];
                    } else {
                        $pastPrItem = \App\Models\PurchaseRequestItem::where('product_id', $item->product_id)
                            ->where('purchase_request_id', '!=', $purchaseRequest->id)
                            ->where('estimated_unit_cost', '>', 0)
                            ->with('purchaseRequest')
                            ->orderBy('created_at', 'desc')
                            ->first();

                        if ($pastPrItem) {
                            $lastPurchaseRec = [
                                'price'  => (float)$pastPrItem->estimated_unit_cost,
                                'date'   => $pastPrItem->created_at,
                                'source' => 'PR #' . ($pastPrItem->purchaseRequest?->pr_no ?? $pastPrItem->purchase_request_id),
                            ];
                        } elseif ($item->product && (float)$item->product->unit_price > 0) {
                            $lastPurchaseRec = [
                                'price'  => (float)$item->product->unit_price,
                                'date'   => $item->product->updated_at ?? $item->product->created_at,
                                'source' => 'Product Catalog Price',
                            ];
                        }
                    }
                } catch (\Throwable $e) {}
            }

            $monthlyPrice = $monthlyPriceRec ? (float)$monthlyPriceRec->price : null;
            $monthlyDate = $monthlyPriceRec ? $monthlyPriceRec->effective_date : null;
            $monthlySource = $monthlyPriceRec ? ($monthlyPriceRec->source ?: 'Marketing Monthly Price') : null;
            if ($monthlyPrice !== null) {
                $pricingBenchmarks['has_monthly_data'] = true;
            }

            $purchasePrice = $lastPurchaseRec ? (float)$lastPurchaseRec['price'] : null;
            $purchaseDate = $lastPurchaseRec ? $lastPurchaseRec['date'] : null;
            $purchaseSource = $lastPurchaseRec ? $lastPurchaseRec['source'] : null;
            if ($purchasePrice !== null) {
                $pricingBenchmarks['has_purchase_data'] = true;
            }

            // Determine latest benchmark
            $chosenPrice = null;
            $chosenSource = null;
            $chosenType = null;
            $chosenDate = null;

            if ($monthlyPrice !== null && $purchasePrice !== null) {
                $mTime = $monthlyDate ? \Carbon\Carbon::parse($monthlyDate)->timestamp : 0;
                $pTime = $purchaseDate ? \Carbon\Carbon::parse($purchaseDate)->timestamp : 0;

                if ($mTime >= $pTime) {
                    $chosenPrice = $monthlyPrice;
                    $chosenSource = $monthlySource ?: 'Monthly Marketing Survey';
                    $chosenType = 'monthly_market';
                    $chosenDate = $monthlyDate;
                } else {
                    $chosenPrice = $purchasePrice;
                    $chosenSource = $purchaseSource ?: 'Last Purchase Order';
                    $chosenType = 'last_purchase';
                    $chosenDate = $purchaseDate;
                }
            } elseif ($monthlyPrice !== null) {
                $chosenPrice = $monthlyPrice;
                $chosenSource = $monthlySource ?: 'Monthly Marketing Survey';
                $chosenType = 'monthly_market';
                $chosenDate = $monthlyDate;
            } elseif ($purchasePrice !== null) {
                $chosenPrice = $purchasePrice;
                $chosenSource = $purchaseSource ?: 'Last Purchase Price';
                $chosenType = 'last_purchase';
                $chosenDate = $purchaseDate;
            } else {
                $chosenPrice = (float)($item->estimated_unit_cost ?? 0);
                $chosenSource = 'Requested Unit Cost';
                $chosenType = 'estimated';
            }

            $qty = (float)$item->quantity;
            $monthlyTotal = ($monthlyPrice !== null) ? round($monthlyPrice * $qty, 2) : 0;
            $purchaseTotal = ($purchasePrice !== null) ? round($purchasePrice * $qty, 2) : 0;
            $chosenTotal = ($chosenPrice !== null) ? round($chosenPrice * $qty, 2) : 0;

            $pricingBenchmarks['total_monthly_market'] += $monthlyTotal;
            $pricingBenchmarks['total_last_purchase'] += $purchaseTotal;
            $pricingBenchmarks['total_latest_benchmark'] += $chosenTotal;

            $pricingBenchmarks['items'][$item->id] = [
                'item_id'          => $item->id,
                'product_id'       => $item->product_id,
                'product_name'     => $item->product?->name ?? ('Item #' . $item->product_id),
                'quantity'         => $qty,
                'unit'             => $item->unit,
                'direct_unit_cost' => (float)$item->estimated_unit_cost,
                'direct_total'     => round((float)$item->estimated_unit_cost * $qty, 2),
                'monthly_price'    => $monthlyPrice,
                'monthly_date'     => $monthlyDate,
                'monthly_source'   => $monthlySource,
                'monthly_total'    => $monthlyTotal,
                'purchase_price'   => $purchasePrice,
                'purchase_date'    => $purchaseDate,
                'purchase_source'  => $purchaseSource,
                'purchase_total'   => $purchaseTotal,
                'chosen_price'     => $chosenPrice,
                'chosen_source'    => $chosenSource,
                'chosen_type'      => $chosenType,
                'chosen_date'      => $chosenDate,
                'chosen_total'     => $chosenTotal,
            ];
        }

        // Determine if PR is at Final Store Intake stage (after credit approval, payment, or driver booking)
        $isFinalIntake = ($purchaseRequest->status === PurchaseRequest::STATUS_PENDING_STORE_REVIEW) && (
            $purchaseRequest->payment !== null || 
            $purchaseRequest->creditLedger !== null || 
            $purchaseRequest->driverBooking !== null || 
            $purchaseRequest->receipt !== null || 
            $purchaseRequest->gmDecisions()->where('decision', 'approve')->exists() ||
            $purchaseRequest->workflowLogs()->whereIn('action', [
                'gm_approve_buy_by_credit',
                'gm_approve_credit_direct_store',
                'finance_credit_approved',
                'finance_credit_approved_direct_intake',
                'driver_booked',
                'receipt_verified'
            ])->exists()
        );

        $targetStoreId = $purchaseRequest->store_id ?? ($stores->first()?->id ?? null);
        $receiveSlipSequence = null;
        $nextReceiveSlipNo = null;
        if ($targetStoreId) {
            $receiveSlipSequence = \App\Models\SlipSequence::where('store_id', $targetStoreId)
                ->where('slip_type', 'receive')
                ->where('status', 'active')
                ->first();
            $nextReceiveSlipNo = $receiveSlipSequence ? $receiveSlipSequence->formatSlipNumber($receiveSlipSequence->current_slip_no) : null;
        }

        return view('procurement.purchase-requests.show', compact(
            'purchaseRequest', 'stockAvailability', 'coaAccounts',
            'financeStaff', 'drivers', 'suppliers', 'stores', 'pricingBenchmarks',
            'isFinalIntake', 'receiveSlipSequence', 'nextReceiveSlipNo'
        ));
    }

    /**
     * Check whether the authenticated user has permission to act on the given PR stage.
     */
    private function authorizeStageRole(PurchaseRequest $pr, array $allowedRoles): void
    {
        $user = Auth::user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        $userRoles = $user->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray();
        if (in_array('global_admin', $userRoles) || in_array('admin', $userRoles)) {
            return;
        }

        if ($pr->requested_by && $pr->requested_by === $user->id && in_array('requester', $allowedRoles)) {
            return;
        }

        foreach ($allowedRoles as $role) {
            $normalized = strtolower(str_replace([' ', '-'], '_', trim($role)));
            if (in_array($normalized, $userRoles)) {
                return;
            }
        }

        abort(403, 'Unauthorized: Your role does not own the current stage of this Purchase Request.');
    }

    // ─── Submit (Coordinator submits draft to Store Manager) ────────────────
    public function submit(PurchaseRequest $purchaseRequest)
    {
        $this->authorizeStageRole($purchaseRequest, ['coordinator', 'site_engineer', 'requester', 'store_manager']);
        $purchaseRequest->update([
            'status'             => PurchaseRequest::STATUS_PENDING_STORE_REVIEW,
            'current_owner_role' => 'store_manager',
        ]);
        return back()->with('success', 'Purchase Request submitted to Store Manager.');
    }

    // ─── STAGE 2 / STORE REVIEW: Selective Store Transfer ───────────────────
    public function selectiveTransfer(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeStageRole($purchaseRequest, ['store_manager']);
        $request->validate([
            'from_store_id'     => 'required|exists:stores,id',
            'to_store_id'       => 'required|exists:stores,id|different:from_store_id',
            'items'             => 'required|array|min:1',
            'items.*.item_id'   => 'required|exists:purchase_request_items,id',
            'items.*.quantity'  => 'required|numeric|min:0.001',
            'reason'            => 'nullable|string',
            'required_date'     => 'nullable|date',
        ]);

        $createdTransfer = null;
        $transferredCount = 0;

        DB::transaction(function () use ($request, $purchaseRequest, &$createdTransfer, &$transferredCount) {
            $no = 'TR-' . date('Ymd') . '-' . str_pad(Transfer::count() + 1, 4, '0', STR_PAD_LEFT);

            $transfer = Transfer::create([
                'transfer_no'   => $no,
                'from_store_id' => $request->from_store_id,
                'to_store_id'   => $request->to_store_id,
                'requested_by'  => Auth::id(),
                'required_date' => $request->required_date ?? $purchaseRequest->required_date ?? now(),
                'reason'        => $request->reason ?: ("Transferred from PR #{$purchaseRequest->pr_no} (" . ($purchaseRequest->project?->name ?? 'Project') . ")"),
                'status'        => 'draft',
            ]);

            foreach ($request->items as $itemData) {
                $prItem = PurchaseRequestItem::where('purchase_request_id', $purchaseRequest->id)
                    ->where('id', $itemData['item_id'])
                    ->first();

                if (!$prItem) continue;

                $transferQty = min((float)$itemData['quantity'], (float)$prItem->quantity);
                if ($transferQty <= 0) continue;

                $transfer->items()->create([
                    'product_id'         => $prItem->product_id,
                    'requested_quantity' => $transferQty,
                    'unit'               => $prItem->unit ?? 'pcs',
                ]);

                $transferredCount++;

                // If full quantity transferred, delete PR item
                if ($transferQty >= (float)$prItem->quantity) {
                    $prItem->delete();
                } else {
                    // Partial transfer: reduce remaining quantity on PR item
                    $prItem->decrement('quantity', $transferQty);
                }
            }

            $createdTransfer = $transfer;

            // Check if any items remain on the PR
            $remainingCount = $purchaseRequest->items()->count();

            if ($remainingCount === 0) {
                $from = $purchaseRequest->status;
                $purchaseRequest->update([
                    'status'             => PurchaseRequest::STATUS_TRANSFERRED,
                    'current_owner_role' => null,
                ]);
                PrWorkflowLog::create([
                    'purchase_request_id' => $purchaseRequest->id,
                    'from_stage'          => $from,
                    'to_stage'            => PurchaseRequest::STATUS_TRANSFERRED,
                    'action'              => 'full_store_transfer_created',
                    'actor_role'          => 'store_manager',
                    'notes'               => "All items transferred via Store Transfer #{$transfer->transfer_no}",
                    'actor_id'            => Auth::id(),
                    'created_at'          => now(),
                ]);
            } else {
                PrWorkflowLog::create([
                    'purchase_request_id' => $purchaseRequest->id,
                    'from_stage'          => $purchaseRequest->status,
                    'to_stage'            => $purchaseRequest->status,
                    'action'              => 'partial_store_transfer_created',
                    'actor_role'          => 'store_manager',
                    'notes'               => "Transferred {$transferredCount} item(s) to Store Transfer #{$transfer->transfer_no}. {$remainingCount} item(s) remaining for purchase.",
                    'actor_id'            => Auth::id(),
                    'created_at'          => now(),
                ]);
            }
        });

        if (!$createdTransfer || $transferredCount === 0) {
            return back()->with('error', 'No valid items were selected for transfer.');
        }

        $remaining = $purchaseRequest->fresh()->items()->count();
        $msg = "Store Transfer #{$createdTransfer->transfer_no} created with {$transferredCount} item(s).";
        if ($remaining > 0) {
            $msg .= " Remaining {$remaining} item(s) are in PR #{$purchaseRequest->pr_no} ready to be sent to Purchase Team.";
        }

        return back()->with('success', $msg);
    }

    // ─── STAGE 2 / STORE REVIEW: Selective Send to Procurement Manager ──────
    public function selectiveSendToPm(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeStageRole($purchaseRequest, ['store_manager']);
        $request->validate([
            'item_ids'   => 'nullable|array',
            'item_ids.*' => 'exists:purchase_request_items,id',
            'notes'      => 'nullable|string',
        ]);

        $itemIds = $request->input('item_ids', []);
        $allItemsCount = $purchaseRequest->items()->count();

        if (empty($itemIds) || count($itemIds) >= $allItemsCount) {
            // Send entire PR to Procurement Manager
            $this->lifecycle->sendToProcurementManager($purchaseRequest, $request->notes);
            return back()->with('success', "All items on PR #{$purchaseRequest->pr_no} sent to Procurement Manager.");
        }

        // Partial selection: split selected items into a new PR for purchase
        $newPr = null;
        DB::transaction(function () use ($purchaseRequest, $itemIds, $request, &$newPr) {
            $newPrNo = 'PR-' . date('Ymd') . '-' . str_pad(PurchaseRequest::withTrashed()->count() + 1, 4, '0', STR_PAD_LEFT);

            $newPr = PurchaseRequest::create([
                'pr_no'               => $newPrNo,
                'project_id'          => $purchaseRequest->project_id,
                'store_id'            => $purchaseRequest->store_id,
                'requested_by'        => $purchaseRequest->requested_by ?? Auth::id(),
                'material_request_id' => $purchaseRequest->material_request_id,
                'priority'            => $purchaseRequest->priority,
                'type'                => $purchaseRequest->type,
                'required_date'       => $purchaseRequest->required_date,
                'justification'       => "Split from PR #{$purchaseRequest->pr_no}: " . ($purchaseRequest->justification ?? ''),
                'status'              => PurchaseRequest::STATUS_PENDING_STORE_REVIEW,
                'current_owner_role'  => 'store_manager',
            ]);

            // Move selected items to new PR
            PurchaseRequestItem::where('purchase_request_id', $purchaseRequest->id)
                ->whereIn('id', $itemIds)
                ->update(['purchase_request_id' => $newPr->id]);

            // Route new PR to PM
            $this->lifecycle->sendToProcurementManager($newPr, $request->notes ?: "Split from PR #{$purchaseRequest->pr_no}");

            PrWorkflowLog::create([
                'purchase_request_id' => $purchaseRequest->id,
                'from_stage'          => $purchaseRequest->status,
                'to_stage'            => $purchaseRequest->status,
                'action'              => 'split_items_to_new_pr',
                'actor_role'          => 'store_manager',
                'notes'               => "Split " . count($itemIds) . " item(s) into PR #{$newPr->pr_no} and routed to Procurement Manager.",
                'actor_id'            => Auth::id(),
                'created_at'          => now(),
            ]);
        });

        return back()->with('success', "Selected items (" . count($itemIds) . ") split into PR #{$newPr->pr_no} and routed to Procurement Manager!");
    }

    // ─── STAGE 2 / STORE REVIEW: Unified Split & Process (Transfer + Buy) ───
    public function splitAndProcess(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeStageRole($purchaseRequest, ['store_manager']);
        $request->validate([
            'allocations'               => 'required|array|min:1',
            'allocations.*.item_id'     => 'required|exists:purchase_request_items,id',
            'allocations.*.action'      => 'required|in:transfer,purchase,keep',
            'allocations.*.transfer_qty'=> 'nullable|numeric|min:0',
            'allocations.*.from_store_id'=>'nullable|exists:stores,id',
            'to_store_id'               => 'nullable|exists:stores,id',
            'notes'                     => 'nullable|string',
        ]);

        $destinationStoreId = $request->to_store_id ?: ($purchaseRequest->store_id ?: Store::where('is_active', true)->value('id'));
        $createdTransfers = [];
        $transferItemsCount = 0;
        $purchaseItemsCount = 0;

        DB::transaction(function () use ($request, $purchaseRequest, $destinationStoreId, &$createdTransfers, &$transferItemsCount, &$purchaseItemsCount) {
            $transferItemsByStore = [];
            $purchaseItemIds = [];

            foreach ($request->allocations as $alloc) {
                $prItem = PurchaseRequestItem::where('purchase_request_id', $purchaseRequest->id)
                    ->where('id', $alloc['item_id'])
                    ->first();
                if (!$prItem) continue;

                $action = $alloc['action'];
                $fromStoreId = $alloc['from_store_id'] ?? null;
                $transferQty = (float)($alloc['transfer_qty'] ?? 0);

                if ($action === 'transfer' && $fromStoreId) {
                    $transferItemsByStore[$fromStoreId][] = [
                        'pr_item' => $prItem,
                        'qty'     => $transferQty > 0 ? $transferQty : (float)$prItem->quantity,
                    ];
                } elseif ($action === 'purchase') {
                    $purchaseItemIds[] = $prItem->id;
                }
            }

            // 1. Process Transfers
            foreach ($transferItemsByStore as $fromStoreId => $items) {
                $fallbackTo = ($fromStoreId == $destinationStoreId)
                    ? (Store::where('id', '!=', $fromStoreId)->where('is_active', true)->value('id') ?? $destinationStoreId)
                    : $destinationStoreId;

                $no = 'TR-' . date('Ymd') . '-' . str_pad(Transfer::count() + 1, 4, '0', STR_PAD_LEFT);
                $transfer = Transfer::create([
                    'transfer_no'   => $no,
                    'from_store_id' => $fromStoreId,
                    'to_store_id'   => $fallbackTo,
                    'requested_by'  => Auth::id(),
                    'required_date' => $purchaseRequest->required_date ?? now(),
                    'reason'        => "Stock transfer for PR #{$purchaseRequest->pr_no} (" . ($purchaseRequest->project?->name ?? 'Project') . ")",
                    'status'        => 'draft',
                ]);

                foreach ($items as $itm) {
                    $prItem = $itm['pr_item'];
                    $qty = min((float)$itm['qty'], (float)$prItem->quantity);

                    $transfer->items()->create([
                        'product_id'         => $prItem->product_id,
                        'requested_quantity' => $qty,
                        'unit'               => $prItem->unit ?? 'pcs',
                    ]);
                    $transferItemsCount++;

                    if ($qty >= (float)$prItem->quantity && !in_array($prItem->id, $purchaseItemIds)) {
                        $prItem->delete();
                    } else if ($qty < (float)$prItem->quantity) {
                        $prItem->decrement('quantity', $qty);
                    }
                }

                $createdTransfers[] = $transfer;
            }

            // 2. Process Purchase Items (Route PR to Procurement Manager)
            $remainingPrItemsCount = $purchaseRequest->fresh()->items()->count();
            if ($remainingPrItemsCount > 0 && !empty($purchaseItemIds)) {
                $this->lifecycle->sendToProcurementManager($purchaseRequest, $request->notes ?: "Routed to Procurement for {$remainingPrItemsCount} items.");
                $purchaseItemsCount = $remainingPrItemsCount;
            } elseif ($remainingPrItemsCount === 0) {
                $purchaseRequest->update([
                    'status'             => PurchaseRequest::STATUS_TRANSFERRED,
                    'current_owner_role' => null,
                ]);
                PrWorkflowLog::create([
                    'purchase_request_id' => $purchaseRequest->id,
                    'from_stage'          => PurchaseRequest::STATUS_PENDING_STORE_REVIEW,
                    'to_stage'            => PurchaseRequest::STATUS_TRANSFERRED,
                    'action'              => 'full_store_transfer_created',
                    'actor_role'          => 'store_manager',
                    'notes'               => "All items fulfilled via Store Transfer(s).",
                    'actor_id'            => Auth::id(),
                    'created_at'          => now(),
                ]);
            }
        });

        $transferNos = collect($createdTransfers)->pluck('transfer_no')->implode(', ');
        $msg = "Processed successfully!";
        if (!empty($transferNos)) {
            $msg .= " Created Store Transfer(s): [{$transferNos}] ({$transferItemsCount} item(s)).";
        }
        if ($purchaseItemsCount > 0) {
            $msg .= " Sent PR #{$purchaseRequest->pr_no} ({$purchaseItemsCount} item(s)) to Procurement Manager for purchase.";
        }

        return back()->with('success', $msg);
    }

    // ─── STAGE 3: Procurement Manager Triage ────────────────────────────────
    public function sendToProcurementManager(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeStageRole($purchaseRequest, ['store_manager', 'purchase_manager', 'procurement_manager']);
        $this->lifecycle->sendToProcurementManager($purchaseRequest, $request->notes);
        return back()->with('success', 'Routed to Procurement Manager.');
    }

    public function sendBackToStoreManager(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeStageRole($purchaseRequest, ['purchase_manager', 'procurement_manager']);
        $request->validate(['reason' => 'required|string']);
        $this->lifecycle->sendBackToStoreManager($purchaseRequest, $request->reason);
        return back()->with('success', 'Sent back to Store Manager.');
    }

    public function selectiveSendBackToStoreManager(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeStageRole($purchaseRequest, ['purchase_manager', 'procurement_manager']);
        $request->validate([
            'item_ids'   => 'nullable|array',
            'item_ids.*' => 'exists:purchase_request_items,id',
            'reason'     => 'required|string',
        ]);

        $itemIds = $request->input('item_ids', []);
        $allItemsCount = $purchaseRequest->items()->count();

        if (empty($itemIds) || count($itemIds) >= $allItemsCount) {
            // Send entire PR back to Store Manager
            $this->lifecycle->sendBackToStoreManager($purchaseRequest, $request->reason);
            return back()->with('success', "All items on PR #{$purchaseRequest->pr_no} returned to Store Manager for stock review / fulfillment decision.");
        }

        // Partial selection: split selected items into a new PR returned to Store Manager
        $newPr = null;
        DB::transaction(function () use ($purchaseRequest, $itemIds, $request, &$newPr) {
            $newPrNo = 'PR-' . date('Ymd') . '-' . str_pad(PurchaseRequest::withTrashed()->count() + 1, 4, '0', STR_PAD_LEFT);

            $newPr = PurchaseRequest::create([
                'pr_no'               => $newPrNo,
                'project_id'          => $purchaseRequest->project_id,
                'store_id'            => $purchaseRequest->store_id,
                'requested_by'        => $purchaseRequest->requested_by ?? Auth::id(),
                'material_request_id' => $purchaseRequest->material_request_id,
                'priority'            => $purchaseRequest->priority,
                'type'                => $purchaseRequest->type,
                'required_date'       => $purchaseRequest->required_date,
                'justification'       => "Returned to Store Manager from PR #{$purchaseRequest->pr_no}: " . ($request->reason ?: $purchaseRequest->justification),
                'pm_sendback_reason'  => $request->reason,
                'status'              => PurchaseRequest::STATUS_PENDING_STORE_REVIEW,
                'current_owner_role'  => 'store_manager',
            ]);

            // Move selected items to new PR
            PurchaseRequestItem::where('purchase_request_id', $purchaseRequest->id)
                ->whereIn('id', $itemIds)
                ->update(['purchase_request_id' => $newPr->id]);

            // Recalculate totals
            $newPrTotal = $newPr->items()->sum('estimated_total');
            $newPr->update(['estimated_total' => $newPrTotal]);

            $remainingPrTotal = $purchaseRequest->items()->sum('estimated_total');
            $purchaseRequest->update(['estimated_total' => $remainingPrTotal]);

            PrWorkflowLog::create([
                'purchase_request_id' => $purchaseRequest->id,
                'from_stage'          => $purchaseRequest->status,
                'to_stage'            => $purchaseRequest->status,
                'action'              => 'selective_items_returned_to_store_manager',
                'actor_role'          => 'purchase_manager',
                'notes'               => "Returned " . count($itemIds) . " item(s) to Store Manager in new PR #{$newPr->pr_no}. Reason: " . $request->reason,
                'actor_id'            => Auth::id(),
                'created_at'          => now(),
            ]);

            PrWorkflowLog::create([
                'purchase_request_id' => $newPr->id,
                'from_stage'          => PurchaseRequest::STATUS_PENDING_PROC_MANAGER,
                'to_stage'            => PurchaseRequest::STATUS_PENDING_STORE_REVIEW,
                'action'              => 'created_from_pm_sendback',
                'actor_role'          => 'purchase_manager',
                'notes'               => "Created with " . count($itemIds) . " item(s) returned by Procurement Manager from PR #{$purchaseRequest->pr_no}. Reason: " . $request->reason,
                'actor_id'            => Auth::id(),
                'created_at'          => now(),
            ]);
        });

        return back()->with('success', "Selected " . count($itemIds) . " item(s) split into PR #{$newPr->pr_no} and returned to Store Manager to decide fulfillment/purchase! Remaining items stay on PR #{$purchaseRequest->pr_no}.");
    }

    public function sendToProcurementTeam(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeStageRole($purchaseRequest, ['purchase_manager', 'procurement_manager']);
        $request->validate([
            'sourcing_method' => 'nullable|in:direct_buy,proforma',
            'notes'           => 'nullable|string',
        ]);
        $method = $request->input('sourcing_method', 'proforma');
        $this->lifecycle->sendToProcurementTeam($purchaseRequest, $method, $request->notes);
        return back()->with('success', 'Routed to Procurement Team for ' . ($method === 'direct_buy' ? 'Direct Buy material pricing.' : 'Proforma quotes collection.'));
    }

    public function selectiveSendToProcTeam(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeStageRole($purchaseRequest, ['purchase_manager', 'procurement_manager']);
        $request->validate([
            'item_ids'        => 'nullable|array',
            'item_ids.*'      => 'exists:purchase_request_items,id',
            'sourcing_method' => 'nullable|in:direct_buy,proforma',
            'notes'           => 'nullable|string',
        ]);

        $method = $request->input('sourcing_method', 'proforma');
        $itemIds = $request->input('item_ids', []);
        $allItemsCount = $purchaseRequest->items()->count();

        if (empty($itemIds) || count($itemIds) >= $allItemsCount) {
            // Send entire PR to Procurement Team
            $this->lifecycle->sendToProcurementTeam($purchaseRequest, $method, $request->notes);
            return back()->with('success', 'Routed to Procurement Team for ' . ($method === 'direct_buy' ? 'Direct Buy material pricing.' : 'Proforma quotes collection.'));
        }

        // Partial selection: split selected items into a new PR sent to Procurement Team
        $newPr = null;
        DB::transaction(function () use ($purchaseRequest, $itemIds, $method, $request, &$newPr) {
            $newPrNo = 'PR-' . date('Ymd') . '-' . str_pad(PurchaseRequest::withTrashed()->count() + 1, 4, '0', STR_PAD_LEFT);

            $newPr = PurchaseRequest::create([
                'pr_no'               => $newPrNo,
                'project_id'          => $purchaseRequest->project_id,
                'store_id'            => $purchaseRequest->store_id,
                'requested_by'        => $purchaseRequest->requested_by ?? Auth::id(),
                'material_request_id' => $purchaseRequest->material_request_id,
                'priority'            => $purchaseRequest->priority,
                'type'                => $purchaseRequest->type,
                'required_date'       => $purchaseRequest->required_date,
                'justification'       => "Split for {$method} from PR #{$purchaseRequest->pr_no}: " . ($purchaseRequest->justification ?? ''),
                'sourcing_method'     => $method,
                'status'              => PurchaseRequest::STATUS_PENDING_PROC_TEAM,
                'current_owner_role'  => 'purchase',
            ]);

            // Move selected items to new PR
            PurchaseRequestItem::where('purchase_request_id', $purchaseRequest->id)
                ->whereIn('id', $itemIds)
                ->update(['purchase_request_id' => $newPr->id]);

            // Recalculate totals
            $newPrTotal = $newPr->items()->sum('estimated_total');
            $newPr->update(['estimated_total' => $newPrTotal]);

            $remainingPrTotal = $purchaseRequest->items()->sum('estimated_total');
            $purchaseRequest->update(['estimated_total' => $remainingPrTotal]);

            PrWorkflowLog::create([
                'purchase_request_id' => $purchaseRequest->id,
                'from_stage'          => $purchaseRequest->status,
                'to_stage'            => $purchaseRequest->status,
                'action'              => 'selective_items_sent_to_proc_team',
                'actor_role'          => 'purchase_manager',
                'notes'               => "Split " . count($itemIds) . " item(s) into PR #{$newPr->pr_no} for {$method}.",
                'actor_id'            => Auth::id(),
                'created_at'          => now(),
            ]);

            PrWorkflowLog::create([
                'purchase_request_id' => $newPr->id,
                'from_stage'          => PurchaseRequest::STATUS_PENDING_PROC_MANAGER,
                'to_stage'            => PurchaseRequest::STATUS_PENDING_PROC_TEAM,
                'action'              => $method === 'direct_buy' ? 'send_to_proc_team_direct_buy' : 'send_to_proc_team_proforma',
                'actor_role'          => 'purchase_manager',
                'notes'               => "Created with " . count($itemIds) . " item(s) from PR #{$purchaseRequest->pr_no}. " . ($request->notes ?? ''),
                'actor_id'            => Auth::id(),
                'created_at'          => now(),
            ]);
        });

        return back()->with('success', "Selected " . count($itemIds) . " item(s) split into PR #{$newPr->pr_no} and routed to Purchase Team ({$method})! Remaining items stay on PR #{$purchaseRequest->pr_no}.");
    }

    // ─── STAGE 4: Procurement Team Submits Direct Buy Material Pricing ───────
    public function submitDirectBuy(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeStageRole($purchaseRequest, ['purchase', 'procurement_team', 'purchaser', 'buyer']);
        $request->validate([
            'item_prices'   => 'nullable|array',
            'item_prices.*' => 'nullable|numeric|min:0',
            'amount'        => 'nullable|numeric|min:0',
            'notes'         => 'nullable|string',
        ]);

        $itemPrices = $request->input('item_prices', []);
        $amount = (float)($request->input('amount', 0));

        $this->lifecycle->submitDirectBuy($purchaseRequest, $amount, $request->notes, $itemPrices);
        return back()->with('success', 'Direct buy material pricing submitted. Awaiting marketing review.');
    }

    public function submitProformas(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeStageRole($purchaseRequest, ['purchase', 'procurement_team', 'purchaser', 'buyer']);
        if ($purchaseRequest->proformaInvoices()->count() === 0) {
            return back()->with('error', 'Please attach at least one supplier proforma quote before submitting.');
        }
        $this->lifecycle->submitProformas($purchaseRequest, $request->notes);
        return back()->with('success', 'Proformas submitted to Procurement Manager for review and selection.');
    }

    public function attachProforma(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeStageRole($purchaseRequest, ['purchase', 'procurement_team', 'purchaser', 'buyer', 'purchase_manager', 'procurement_manager']);

        $request->validate([
            'supplier_id'   => 'required|exists:suppliers,id',
            'proforma_no'   => 'nullable|string|max:50',
            'proforma_date' => 'required|date',
            'valid_until'   => 'nullable|date',
            'item_prices'   => 'nullable|array',
            'item_prices.*' => 'nullable|numeric|min:0',
            'subtotal'      => 'nullable|numeric|min:0',
            'tax_amount'    => 'nullable|numeric|min:0',
            'grand_total'   => 'required|numeric|min:0.01',
            'notes'         => 'nullable|string',
            'proforma_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $filePath = null;
        if ($request->hasFile('proforma_file')) {
            $filePath = \App\Services\FileUploadService::upload($request->file('proforma_file'), 'proformas');
        }

        $pNo = $request->proforma_no ?: ('PROF-' . date('Ymd') . '-' . str_pad(ProformaInvoice::count() + 1, 4, '0', STR_PAD_LEFT));

        // Process item prices breakdown
        $itemPricesInput = $request->input('item_prices', []);
        $structuredItemPrices = [];
        $calculatedSubtotal = 0;
        if (is_array($itemPricesInput) && !empty($itemPricesInput)) {
            foreach ($purchaseRequest->items as $prItm) {
                if (isset($itemPricesInput[$prItm->id])) {
                    $unitCost = (float)$itemPricesInput[$prItm->id];
                    $lineTotal = round($unitCost * (float)$prItm->quantity, 2);
                    $calculatedSubtotal += $lineTotal;
                    $structuredItemPrices[$prItm->id] = [
                        'product_id'   => $prItm->product_id,
                        'product_name' => $prItm->product?->name ?? ('Item #' . $prItm->product_id),
                        'product_code' => $prItm->product?->code,
                        'quantity'     => (float)$prItm->quantity,
                        'unit'         => $prItm->unit,
                        'unit_price'   => $unitCost,
                        'total'        => $lineTotal,
                    ];
                }
            }
        }

        // Auto-ensure schema column if not migrated yet
        if (!\Illuminate\Support\Facades\Schema::hasColumn('proforma_invoices', 'item_prices')) {
            try {
                \Illuminate\Support\Facades\Schema::table('proforma_invoices', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->json('item_prices')->nullable()->after('grand_total');
                });
            } catch (\Throwable $e) {}
        }

        $subtotal = $request->subtotal ?: ($calculatedSubtotal > 0 ? $calculatedSubtotal : $request->grand_total);
        $taxAmount = (float)($request->tax_amount ?? 0);
        $grandTotal = (float)$request->grand_total;

        $proforma = ProformaInvoice::create([
            'proforma_no'         => $pNo,
            'purchase_request_id' => $purchaseRequest->id,
            'supplier_id'         => $request->supplier_id,
            'proforma_date'       => $request->proforma_date,
            'valid_until'         => $request->valid_until,
            'subtotal'            => $subtotal,
            'tax_amount'          => $taxAmount,
            'grand_total'         => $grandTotal,
            'item_prices'         => !empty($structuredItemPrices) ? $structuredItemPrices : null,
            'notes'               => $request->notes,
            'file_path'           => $filePath,
            'status'              => 'pending',
            'gm_selected'         => false,
        ]);

        return back()->with('success', "Proforma #{$proforma->proforma_no} attached successfully with itemized pricing.");
    }

    public function deleteProforma(PurchaseRequest $purchaseRequest, ProformaInvoice $proforma)
    {
        $this->authorizeStageRole($purchaseRequest, ['purchase', 'procurement_team', 'purchaser', 'buyer', 'purchase_manager', 'procurement_manager']);

        if ($proforma->purchase_request_id !== $purchaseRequest->id) {
            abort(404);
        }

        if ($proforma->file_path) {
            \App\Services\FileUploadService::delete($proforma->file_path);
        }

        $proforma->delete();
        return back()->with('success', 'Proforma invoice removed.');
    }

    // ─── STAGE 5a: Marketing Variance ───────────────────────────────────────
    public function addMarketingVariance(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeStageRole($purchaseRequest, ['marketing', 'market_research']);
        $request->validate([
            'market_price'       => 'required|numeric|min:0',
            'variance_notes'     => 'nullable|string',
        ]);
        $directBuy       = (float)$purchaseRequest->direct_buy_amount;
        $marketPrice     = (float)$request->market_price;
        $varianceAmount  = $marketPrice - $directBuy;
        $variancePct     = $directBuy > 0 ? round(($varianceAmount / $directBuy) * 100, 2) : 0;

        $this->lifecycle->addMarketingVariance($purchaseRequest, [
            'market_price'        => $marketPrice,
            'variance_amount'     => $varianceAmount,
            'variance_percentage' => $variancePct,
            'variance_notes'      => $request->variance_notes,
        ]);
        return back()->with('success', 'Price variance recorded. Sent to GM.');
    }

    // ─── STAGE 5b: Select Proformas and send to GM ──────────────────────────
    public function selectProformas(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeStageRole($purchaseRequest, ['purchase_manager', 'procurement_manager']);
        $request->validate(['proforma_ids' => 'required|array|min:1']);
        $this->lifecycle->sendProformasToGm($purchaseRequest, $request->proforma_ids, $request->notes);
        return back()->with('success', 'Selected proformas sent to GM.');
    }

    // ─── STAGE 6: GM Decision ────────────────────────────────────────────────
    public function gmDecide(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeStageRole($purchaseRequest, ['gm', 'general_manager']);

        $decisionInput = $request->input('decision');
        $paymentMethodInput = $request->input('payment_method');

        // Normalize composite decisions
        if (in_array($decisionInput, ['buy_by_credit', 'approve_credit', 'credit'])) {
            $decision = 'approve';
            $paymentMethod = 'buy_by_credit';
        } elseif (in_array($decisionInput, ['pay_and_buy', 'approve_pay', 'cash', 'bank'])) {
            $decision = 'approve';
            $paymentMethod = 'pay_and_buy';
        } else {
            $decision = $decisionInput;
            $paymentMethod = $paymentMethodInput ?: ($decision === 'approve' ? 'pay_and_buy' : null);
        }

        if (!in_array($decision, ['approve', 'reject', 'send_back'])) {
            return back()->withErrors(['decision' => 'Invalid GM decision selected.']);
        }

        if ($decision === 'approve' && !in_array($paymentMethod, ['pay_and_buy', 'buy_by_credit'])) {
            $paymentMethod = 'pay_and_buy';
        }

        $this->lifecycle->gmDecide(
            $purchaseRequest,
            $decision,
            $paymentMethod,
            $request->notes
        );

        $decisionLabel = ($decision === 'approve') 
            ? ('Approved with ' . ($paymentMethod === 'buy_by_credit' ? 'Buy with Credit' : 'Pay & Buy')) 
            : ucfirst(str_replace('_', ' ', $decision));

        return back()->with('success', "GM decision recorded: {$decisionLabel}.");
    }

    // ─── STAGE 7a: Finance Head — Credit Path ───────────────────────────────
    public function financeCreditApprove(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeStageRole($purchaseRequest, ['finance_head', 'finance_manager', 'admin', 'global_admin']);
        $request->validate([
            'coa_account_id' => 'nullable|exists:chart_of_accounts,id',
            'amount'         => 'nullable|numeric|min:0.01',
            'notes'          => 'nullable|string',
        ]);
        $this->lifecycle->financeCreditApprove(
            $purchaseRequest,
            $request->filled('coa_account_id') ? (int)$request->coa_account_id : null,
            $request->filled('amount') ? (float)$request->amount : null,
            $request->notes
        );
        return back()->with('success', 'Credit authorized (COA 5110). Sent directly to Store Manager for material intake.');
    }

    // ─── STAGE 7b: Finance Head — Cash Path, Assign Staff ───────────────────
    public function assignPayment(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeStageRole($purchaseRequest, ['finance_head', 'finance_manager']);
        $request->validate([
            'coa_account_id'  => 'required|exists:chart_of_accounts,id',
            'amount'          => 'required|numeric|min:0.01',
            'staff_user_id'   => 'required|exists:users,id',
            'notes'           => 'nullable|string',
        ]);
        $this->lifecycle->financeHeadAssignPayment(
            $purchaseRequest,
            (int)$request->coa_account_id,
            (float)$request->amount,
            (int)$request->staff_user_id,
            $request->notes
        );
        return back()->with('success', 'Payment assigned to finance staff.');
    }

    // ─── STAGE 7b: Finance Staff — Execute Payment ──────────────────────────
    public function executePayment(Request $request, PurchaseRequest $purchaseRequest)
    {
        $user = Auth::user();
        $userRoles = $user ? $user->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray() : [];
        $isGlobalAdmin = in_array('global_admin', $userRoles) || in_array('admin', $userRoles);
        $isFinanceHead = in_array('finance_head', $userRoles) || in_array('finance_manager', $userRoles);
        $isAssigned = $purchaseRequest->payment && $purchaseRequest->payment->assigned_finance_staff_id === $user->id;

        if (!$isGlobalAdmin && !$isFinanceHead && !$isAssigned) {
            abort(403, 'Unauthorized: You are not assigned to execute this payment.');
        }

        $this->lifecycle->financeStaffPay($purchaseRequest, $request->notes);
        return back()->with('success', 'Payment executed. COA balance updated. Procurement Team notified to upload receipt.');
    }

    // ─── STAGE 8: Upload Receipt ─────────────────────────────────────────────
    public function uploadReceipt(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeStageRole($purchaseRequest, ['purchase', 'procurement_team', 'purchaser', 'buyer']);
        $request->validate(['receipt_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120']);

        $file     = $request->file('receipt_file');
        $path     = \App\Services\FileUploadService::upload($file, 'procurement_receipts');
        $original = $file->getClientOriginalName();

        $this->lifecycle->uploadReceipt($purchaseRequest, $path, $original, $request->notes);
        return back()->with('success', 'Receipt uploaded. Finance Staff notified to verify.');
    }

    // ─── STAGE 8: Verify Receipt ─────────────────────────────────────────────
    public function verifyReceipt(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeStageRole($purchaseRequest, ['finance_head', 'finance_manager', 'finance']);
        $request->validate([
            'verification_status' => 'required|in:verified,rejected',
            'verification_notes'  => 'nullable|string',
        ]);
        $this->lifecycle->verifyReceipt($purchaseRequest, $request->verification_status, $request->verification_notes);
        return back()->with('success', 'Receipt verification recorded.');
    }

    // ─── STAGE 9: Book Driver ────────────────────────────────────────────────
    public function bookDriver(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeStageRole($purchaseRequest, ['general_service', 'general_services']);
        $request->validate([
            'driver_employee_id'  => 'required|exists:employees,id',
            'vehicle_number'      => 'nullable|string|max:50',
            'vehicle_description' => 'nullable|string|max:255',
            'scheduled_at'        => 'nullable|date',
            'booking_notes'       => 'nullable|string',
        ]);
        $this->lifecycle->bookDriver(
            $purchaseRequest,
            (int)$request->driver_employee_id,
            $request->vehicle_number,
            $request->vehicle_description,
            $request->scheduled_at,
            $request->booking_notes
        );
        return back()->with('success', 'Driver booked. Store Manager notified for final intake.');
    }

    // ─── STAGE 9 Final: Store Intake ─────────────────────────────────────────
    public function storeIntake(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeStageRole($purchaseRequest, ['store_manager', 'store_keeper', 'store', 'admin', 'global_admin']);
        
        $request->validate([
            'store_id'      => 'nullable|exists:stores,id',
            'slip_no'       => 'nullable|string|max:100',
            'received_date' => 'nullable|date',
            'items'         => 'nullable|array',
            'notes'         => 'nullable|string',
        ]);

        $this->lifecycle->storeIntake(
            $purchaseRequest,
            $request->filled('store_id') ? (int)$request->store_id : null,
            $request->input('slip_no'),
            $request->input('received_date'),
            $request->input('items', []),
            $request->input('notes')
        );

        return back()->with('success', 'Material intake complete! Items received into store inventory and slip sequence updated.');
    }

    // ─── Legacy: approve/reject (kept for backward compat) ──────────────────
    public function approve(PurchaseRequest $purchaseRequest)
    {
        $purchaseRequest->update([
            'status'      => PurchaseRequest::STATUS_PENDING_PROC_MANAGER,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);
        return back()->with('success', 'Purchase Request approved.');
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest)
    {
        $request->validate(['rejection_reason' => 'required|string']);
        $purchaseRequest->update([
            'status'           => PurchaseRequest::STATUS_REJECTED,
            'rejection_reason' => $request->rejection_reason,
        ]);
        return back()->with('success', 'Purchase Request rejected.');
    }
}
