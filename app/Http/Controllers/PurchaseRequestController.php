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

        return view('procurement.purchase-requests.show', compact(
            'purchaseRequest', 'stockAvailability', 'coaAccounts',
            'financeStaff', 'drivers', 'suppliers', 'stores'
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
        $this->lifecycle->submitProformas($purchaseRequest, $request->notes);
        return back()->with('success', 'Proformas submitted to Procurement Manager.');
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
        $request->validate([
            'decision'       => 'required|in:approve,reject,send_back',
            'payment_method' => 'required_if:decision,approve|in:pay_and_buy,buy_by_credit',
            'notes'          => 'nullable|string',
        ]);
        $this->lifecycle->gmDecide(
            $purchaseRequest,
            $request->decision,
            $request->payment_method,
            $request->notes
        );
        return back()->with('success', 'GM decision recorded.');
    }

    // ─── STAGE 7a: Finance Head — Credit Path ───────────────────────────────
    public function financeCreditApprove(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeStageRole($purchaseRequest, ['finance_head', 'finance_manager']);
        $request->validate([
            'coa_account_id' => 'required|exists:chart_of_accounts,id',
            'amount'         => 'required|numeric|min:0.01',
            'notes'          => 'nullable|string',
        ]);
        $this->lifecycle->financeCreditApprove(
            $purchaseRequest,
            (int)$request->coa_account_id,
            (float)$request->amount,
            $request->notes
        );
        return back()->with('success', 'Credit authorized. Driver booking notified.');
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
        $this->authorizeStageRole($purchaseRequest, ['store_manager']);
        $this->lifecycle->storeIntake($purchaseRequest, $request->notes);
        return back()->with('success', 'Intake complete. Procurement lifecycle closed.');
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
