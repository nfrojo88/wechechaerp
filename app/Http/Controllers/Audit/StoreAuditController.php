<?php

namespace App\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Models\AuditFinding;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\MaterialUsage;
use App\Models\MaterialUsageItem;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\DeliveryReceipt;
use App\Models\DeliveryReceiptItem;
use App\Models\ProcurementPayment;
use App\Models\Store;
use App\Models\Project;
use App\Models\Product;
use App\Models\User;
use App\Models\NotificationLog;
use App\Services\FileUploadService;
use App\Services\EthiopianDate;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StoreAuditController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Authorize auditor or administrator.
     */
    protected function authorizeAuditorOrAdmin(): User
    {
        $user = Auth::user();
        if (!$user) {
            abort(403, 'Unauthorized. Please login.');
        }

        $isAuditorOrAdmin = $user->hasAnyRole([
            'auditor', 'audit', 'internal_auditor', 'audit_team', 'Auditor',
            'admin', 'global_admin', 'super_admin', 'general_admin'
        ]) || (method_exists($user, 'isGlobalAdmin') && $user->isGlobalAdmin());

        if (!$isAuditorOrAdmin) {
            abort(403, 'Unauthorized access. Only Auditors and Administrators can access the Store Audit Module.');
        }

        return $user;
    }

    /**
     * 3.1 Inventory Overview (/audit/inventory)
     * All stores and items: on-hand qty, unit, unit cost, valuation, last movement, store, project.
     * Flags: negative stock, dormant stock (>30, >60, >90 days), low stock, out of stock.
     */
    public function inventoryOverview(Request $request)
    {
        $this->authorizeAuditorOrAdmin();
        AuditFinding::ensureSchema();

        $storeId   = $request->input('store_id');
        $projectId = $request->input('project_id');
        $category  = $request->input('category');
        $status    = $request->input('status'); // negative, dormant_30, dormant_60, dormant_90, low_stock, out_of_stock, in_stock
        $search    = $request->input('search');

        $query = Inventory::with(['store.project', 'product']);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        if ($projectId) {
            $query->whereHas('store', function ($q) use ($projectId) {
                $q->where('project_id', $projectId);
            });
        }

        if ($category) {
            $query->whereHas('product', function ($q) use ($category) {
                $q->where('category', $category);
            });
        }

        if ($search) {
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($status === 'negative') {
            $query->where('quantity_on_hand', '<', 0);
        } elseif ($status === 'dormant_30') {
            $query->where(function ($q) {
                $q->where('last_movement_at', '<', now()->subDays(30))
                  ->orWhereNull('last_movement_at');
            });
        } elseif ($status === 'dormant_60') {
            $query->where(function ($q) {
                $q->where('last_movement_at', '<', now()->subDays(60))
                  ->orWhereNull('last_movement_at');
            });
        } elseif ($status === 'dormant_90') {
            $query->where(function ($q) {
                $q->where('last_movement_at', '<', now()->subDays(90))
                  ->orWhereNull('last_movement_at');
            });
        } elseif ($status === 'low_stock') {
            $query->whereRaw('quantity_on_hand > 0 AND quantity_on_hand <= COALESCE(min_stock, 0)');
        } elseif ($status === 'out_of_stock') {
            $query->where('quantity_on_hand', '<=', 0);
        } elseif ($status === 'in_stock') {
            $query->where('quantity_on_hand', '>', 0);
        }

        // CSV / Excel Export
        if ($request->input('export') === 'csv' || $request->input('export') === 'excel') {
            $items = $query->orderBy('store_id')->orderBy('product_id')->get();
            return $this->exportInventoryCsv($items);
        }

        // Aggregate KPIs
        $totalItemsCount   = (clone $query)->count();
        $totalValuation    = (clone $query)->select(DB::raw('SUM(quantity_on_hand * COALESCE(unit_cost, 0)) as total_val'))->value('total_val') ?? 0;
        $negativeCount     = (clone $query)->where('quantity_on_hand', '<', 0)->count();
        $dormantCount      = (clone $query)->where(function ($q) {
            $q->where('last_movement_at', '<', now()->subDays(30))->orWhereNull('last_movement_at');
        })->count();
        $lowStockCount     = (clone $query)->whereRaw('quantity_on_hand > 0 AND quantity_on_hand <= COALESCE(min_stock, 0)')->count();

        $inventories = $query->orderByDesc('quantity_on_hand')->paginate(25)->withQueryString();

        $stores     = Store::orderBy('name')->get();
        $projects   = Project::orderBy('name')->get();
        $categories = Product::distinct()->whereNotNull('category')->pluck('category')->filter();
        $assignees  = User::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('audit.inventory', compact(
            'inventories',
            'stores',
            'projects',
            'categories',
            'assignees',
            'totalItemsCount',
            'totalValuation',
            'negativeCount',
            'dormantCount',
            'lowStockCount'
        ));
    }

    /**
     * 3.2 Stock Movement Ledger (/audit/stock-movements)
     * Every intake, issue, transfer out, transfer in, adjustment, return.
     * Running balance check: verify balance_before + delta == balance_after.
     */
    public function stockMovements(Request $request)
    {
        $this->authorizeAuditorOrAdmin();
        AuditFinding::ensureSchema();

        $storeId         = $request->input('store_id');
        $type            = $request->input('type');
        $search          = $request->input('search');
        $hasDiscrepancy  = $request->boolean('has_discrepancy');
        $dateFrom        = $request->input('date_from');
        $dateTo          = $request->input('date_to');

        $query = InventoryMovement::with(['inventory.product', 'inventory.store.project']);

        if ($storeId) {
            $query->whereHas('inventory', function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            });
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', Carbon::parse($dateFrom));
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', Carbon::parse($dateTo));
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('remarks', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhere('reference_type', 'like', "%{$search}%")
                  ->orWhere('reference_id', 'like', "%{$search}%")
                  ->orWhereHas('inventory.product', function ($p) use ($search) {
                      $p->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                  });
            });
        }

        // Export
        if ($request->input('export') === 'csv' || $request->input('export') === 'excel') {
            $items = $query->latest('id')->limit(3000)->get();
            return $this->exportMovementsCsv($items);
        }

        // KPIs
        $totalMovements = (clone $query)->count();
        $inboundCount   = (clone $query)->whereIn('type', ['intake', 'in', 'transfer_in', 'return'])->count();
        $outboundCount  = (clone $query)->whereIn('type', ['issue', 'out', 'transfer_out'])->count();
        $adjustCount    = (clone $query)->where('type', 'adjustment')->count();

        $movements = $query->latest('id')->paginate(30)->withQueryString();

        $stores    = Store::orderBy('name')->get();
        $assignees = User::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('audit.stock-movements', compact(
            'movements',
            'stores',
            'assignees',
            'totalMovements',
            'inboundCount',
            'outboundCount',
            'adjustCount'
        ));
    }

    /**
     * 3.3 Daily Usage Audit (/audit/daily-usage)
     * All daily material usage/issue records by site, project, date, and storekeeper.
     * Flag: unusually large entries, duplicate entries on same store/product/date.
     */
    public function dailyUsage(Request $request)
    {
        $this->authorizeAuditorOrAdmin();
        AuditFinding::ensureSchema();

        $storeId       = $request->input('store_id');
        $projectId     = $request->input('project_id');
        $storekeeperId = $request->input('storekeeper_id');
        $dateFrom      = $request->input('date_from');
        $dateTo        = $request->input('date_to');
        $flag          = $request->input('flag'); // large_usage, duplicate, all

        $query = MaterialUsage::with(['store', 'project', 'createdBy', 'verifiedBy', 'task', 'items.product']);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        if ($storekeeperId) {
            $query->where('created_by', $storekeeperId);
        }

        if ($dateFrom) {
            $query->whereDate('usage_date', '>=', Carbon::parse($dateFrom));
        }

        if ($dateTo) {
            $query->whereDate('usage_date', '<=', Carbon::parse($dateTo));
        }

        // Export
        if ($request->input('export') === 'csv' || $request->input('export') === 'excel') {
            $records = $query->latest('usage_date')->latest('id')->limit(2000)->get();
            return $this->exportDailyUsageCsv($records);
        }

        $totalRecords   = (clone $query)->count();
        $totalUsageCost = 0.0;
        try {
            $usageIds = (clone $query)->pluck('id');
            $totalUsageCost = (float) MaterialUsageItem::whereIn('material_usage_id', $usageIds)->sum('total_cost');
        } catch (\Throwable $e) {}

        $usages = $query->latest('usage_date')->latest('id')->paginate(25)->withQueryString();

        $stores       = Store::orderBy('name')->get();
        $projects     = Project::orderBy('name')->get();
        $storekeepers = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['store_keeper', 'storekeeper', 'Store Keeper', 'Storekeeper']);
        })->orWhere('is_active', true)->orderBy('name')->get(['id', 'name']);
        $assignees    = User::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('audit.daily-usage', compact(
            'usages',
            'stores',
            'projects',
            'storekeepers',
            'assignees',
            'totalRecords',
            'totalUsageCost'
        ));
    }

    /**
     * 3.4 Material Transfer Audit (/audit/transfers)
     * Origin, destination, driver/vehicle, dispatched qty, received qty, status.
     * Flags: dispatched but not received, shortages/excess, in transit > 48h, sync mismatches.
     */
    public function transferAudit(Request $request)
    {
        $this->authorizeAuditorOrAdmin();
        AuditFinding::ensureSchema();

        $fromStoreId = $request->input('from_store_id');
        $toStoreId   = $request->input('to_store_id');
        $status      = $request->input('status');
        $flag        = $request->input('flag'); // in_transit_over_48h, shortage, unreceived, all
        $slipFilter  = $request->input('slip_filter'); // has_outgoing, missing_outgoing, has_receiving, missing_receiving, has_file, missing_file
        $search      = trim((string) $request->input('search'));
        $dateFrom    = $request->input('date_from');
        $dateTo      = $request->input('date_to');

        $query = Transfer::with(['fromStore', 'toStore', 'requestedBy', 'dispatchedBy', 'receivedBy', 'items.product']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('transfer_no', 'like', "%{$search}%")
                  ->orWhere('outgoing_slip_no', 'like', "%{$search}%")
                  ->orWhere('receiving_slip_no', 'like', "%{$search}%")
                  ->orWhere('physical_slip_no', 'like', "%{$search}%")
                  ->orWhere('vehicle_plate_no', 'like', "%{$search}%")
                  ->orWhere('driver_employee_id', 'like', "%{$search}%");
            });
        }

        if ($fromStoreId) {
            $query->where('from_store_id', $fromStoreId);
        }

        if ($toStoreId) {
            $query->where('to_store_id', $toStoreId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', Carbon::parse($dateFrom));
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', Carbon::parse($dateTo));
        }

        if ($flag === 'in_transit_over_48h') {
            $query->where('status', Transfer::STATUS_IN_TRANSIT)
                  ->whereNotNull('dispatched_at')
                  ->where('dispatched_at', '<=', now()->subHours(48));
        } elseif ($flag === 'unreceived') {
            $query->whereNotNull('dispatched_at')
                  ->whereNull('received_at')
                  ->whereNotIn('status', [Transfer::STATUS_CANCELLED, Transfer::STATUS_REJECTED]);
        }

        // Slip status filters
        if ($slipFilter === 'has_outgoing') {
            $query->where(function ($q) {
                $q->whereNotNull('outgoing_slip_no')->where('outgoing_slip_no', '!=', '')
                  ->orWhere(function ($sq) {
                      $sq->whereNotNull('physical_slip_no')->where('physical_slip_no', '!=', '');
                  });
            });
        } elseif ($slipFilter === 'missing_outgoing') {
            $query->where(function ($q) {
                $q->whereNull('outgoing_slip_no')->orWhere('outgoing_slip_no', '');
            })->where(function ($q) {
                $q->whereNull('physical_slip_no')->orWhere('physical_slip_no', '');
            });
        } elseif ($slipFilter === 'has_receiving') {
            $query->whereNotNull('receiving_slip_no')->where('receiving_slip_no', '!=', '');
        } elseif ($slipFilter === 'missing_receiving') {
            $query->where('status', Transfer::STATUS_COMPLETED)
                  ->where(function ($q) {
                      $q->whereNull('receiving_slip_no')->orWhere('receiving_slip_no', '');
                  });
        } elseif ($slipFilter === 'has_file') {
            $query->where(function ($q) {
                $q->whereNotNull('outgoing_slip_file')->where('outgoing_slip_file', '!=', '')
                  ->orWhereNotNull('receiving_slip_file')->where('receiving_slip_file', '!=', '');
            });
        } elseif ($slipFilter === 'missing_file') {
            $query->where(function ($q) {
                $q->whereNull('outgoing_slip_file')->orWhere('outgoing_slip_file', '');
            });
        }

        // Export
        if ($request->input('export') === 'csv' || $request->input('export') === 'excel') {
            $transfers = $query->latest('id')->get();
            return $this->exportTransfersCsv($transfers);
        }

        // KPIs
        $totalTransfers   = (clone $query)->count();
        $inTransitCount   = (clone $query)->where('status', Transfer::STATUS_IN_TRANSIT)->count();
        $over48HoursCount = (clone $query)->where('status', Transfer::STATUS_IN_TRANSIT)
            ->whereNotNull('dispatched_at')
            ->where('dispatched_at', '<=', now()->subHours(48))
            ->count();
        $completedCount   = (clone $query)->where('status', Transfer::STATUS_COMPLETED)->count();

        // Slip KPI Stats across current filtered set
        $withSlipsCount = (clone $query)->where(function ($q) {
            $q->whereNotNull('outgoing_slip_no')->where('outgoing_slip_no', '!=', '')
              ->orWhereNotNull('receiving_slip_no')->where('receiving_slip_no', '!=', '')
              ->orWhereNotNull('physical_slip_no')->where('physical_slip_no', '!=', '');
        })->count();

        $withAttachedFilesCount = (clone $query)->where(function ($q) {
            $q->whereNotNull('outgoing_slip_file')->where('outgoing_slip_file', '!=', '')
              ->orWhereNotNull('receiving_slip_file')->where('receiving_slip_file', '!=', '');
        })->count();

        $transfers = $query->latest('id')->paginate(25)->withQueryString();

        $stores    = Store::orderBy('name')->get();
        $assignees = User::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('audit.transfers', compact(
            'transfers',
            'stores',
            'assignees',
            'totalTransfers',
            'inTransitCount',
            'over48HoursCount',
            'completedCount',
            'withSlipsCount',
            'withAttachedFilesCount'
        ));
    }

    /**
     * 3.5 Purchase → Intake Fulfillment Check (/audit/fulfillment) [MOST IMPORTANT]
     * Line by line: Requested qty → Approved qty → Purchased qty → Received at store qty → Remaining qty.
     * Status per line: Fully Fulfilled, Partially Fulfilled, Not Received, Over-received.
     * Tabs: Unfulfilled Materials (remaining > 0), Paid But Not Received (RED ALERT), All.
     */
    public function fulfillmentCheck(Request $request)
    {
        $this->authorizeAuditorOrAdmin();
        AuditFinding::ensureSchema();

        $tab         = $request->input('tab', 'unfulfilled'); // unfulfilled, paid_not_received, all
        $storeId     = $request->input('store_id');
        $projectId   = $request->input('project_id');
        $supplierId  = $request->input('supplier_id');
        $status      = $request->input('status'); // fully_fulfilled, partially_fulfilled, not_received, over_received
        $search      = $request->input('search');

        // Query Purchase Request Items with related PR, Product, Payments, and Delivery Receipts
        $query = PurchaseRequestItem::with([
            'purchaseRequest.project',
            'purchaseRequest.store',
            'purchaseRequest.supplier',
            'purchaseRequest.payments',
            'purchaseRequest.payment',
            'purchaseRequest.deliveryReceipts.items',
            'product',
            'supplier',
        ])->whereHas('purchaseRequest', function ($q) {
            $q->whereNotIn('status', [PurchaseRequest::STATUS_CANCELLED, PurchaseRequest::STATUS_REJECTED]);
        });

        if ($storeId) {
            $query->whereHas('purchaseRequest', function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            });
        }

        if ($projectId) {
            $query->whereHas('purchaseRequest', function ($q) use ($projectId) {
                $q->where('project_id', $projectId);
            });
        }

        if ($supplierId) {
            $query->where(function ($q) use ($supplierId) {
                $q->where('supplier_id', $supplierId)
                  ->orWhereHas('purchaseRequest', function ($sq) use ($supplierId) {
                      $sq->where('supplier_id', $supplierId);
                  });
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('purchaseRequest', function ($sq) use ($search) {
                    $sq->where(function ($subPr) use ($search) {
                        $subPr->where('pr_no', 'like', "%{$search}%");
                        if (Schema::hasColumn('purchase_requests', 'request_number')) {
                            $subPr->orWhere('request_number', 'like', "%{$search}%");
                        }
                        if (Schema::hasColumn('purchase_requests', 'title')) {
                            $subPr->orWhere('title', 'like', "%{$search}%");
                        }
                    });
                })->orWhereHas('product', function ($pq) use ($search) {
                    $pq->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%");
                });
            });
        }

        // Fetch collection to compute fulfillment metrics
        $allItems = $query->latest('id')->get();

        // Process line items with accurate metrics
        $processed = $allItems->map(function ($item) {
            $pr = $item->purchaseRequest;

            $requestedQty = (float) $item->quantity;
            $approvedQty  = (float) $item->quantity;

            // Purchased Qty
            $purchasedQty = (float) $item->purchased_quantity;
            if ($purchasedQty <= 0) {
                // If PR advanced past procurement/marketing, default to quantity
                if ($pr && in_array($pr->status, [
                    PurchaseRequest::STATUS_PENDING_PAYMENT,
                    PurchaseRequest::STATUS_PENDING_RECEIPT_UPLOAD,
                    PurchaseRequest::STATUS_INTAKE_COMPLETE,
                    PurchaseRequest::STATUS_COMPLETED
                ])) {
                    $purchasedQty = $requestedQty;
                }
            }

            // Received Qty calculation from Delivery Receipts
            $receivedFromReceipts = 0.0;
            if ($pr && $pr->deliveryReceipts) {
                foreach ($pr->deliveryReceipts as $dr) {
                    if ($dr->is_void) continue;
                    foreach ($dr->items as $drItem) {
                        if ($drItem->product_id == $item->product_id) {
                            $receivedFromReceipts += (float) ($drItem->accepted_quantity ?? $drItem->quantity_received ?? 0);
                        }
                    }
                }
            }
            $receivedQty = max((float)$item->received_quantity, $receivedFromReceipts);

            // Remaining Qty
            $targetQty = $purchasedQty > 0 ? $purchasedQty : $approvedQty;
            $remainingQty = max(0.0, $targetQty - $receivedQty);

            // Fulfillment Status
            if ($receivedQty >= $targetQty && $targetQty > 0) {
                $fulfillmentStatus = 'fully_fulfilled';
            } elseif ($receivedQty > 0 && $remainingQty > 0) {
                $fulfillmentStatus = 'partially_fulfilled';
            } elseif ($receivedQty == 0 && $targetQty > 0) {
                $fulfillmentStatus = 'not_received';
            } elseif ($receivedQty > $targetQty && $targetQty > 0) {
                $fulfillmentStatus = 'over_received';
            } else {
                $fulfillmentStatus = 'pending';
            }

            // Payment check
            $isPaid = false;
            $paidAmount = 0.0;
            if ($pr) {
                if ($pr->payments && $pr->payments->isNotEmpty()) {
                    foreach ($pr->payments as $p) {
                        if ($p->status === 'paid' || $p->paid_at !== null) {
                            $isPaid = true;
                            $paidAmount += (float)$p->amount;
                        }
                    }
                }
                if (!$isPaid && $pr->payment && ($pr->payment->status === 'paid' || $pr->payment->paid_at !== null)) {
                    $isPaid = true;
                    $paidAmount = (float)$pr->payment->amount;
                }
            }

            // Critical Audit Flag: "Paid But Not Received"
            $isPaidButNotReceived = $isPaid && ($remainingQty > 0);

            // Unit cost & Outstanding monetary value
            $unitCost = (float) ($item->estimated_unit_cost ?? 0);
            $outstandingValue = round($remainingQty * $unitCost, 2);

            // Days since purchase/creation
            $dateBasis = $pr?->created_at;
            $daysSincePurchase = $dateBasis ? now()->diffInDays($dateBasis) : 0;

            return (object) [
                'item'                   => $item,
                'pr'                     => $pr,
                'product'                => $item->product,
                'requested_qty'          => $requestedQty,
                'approved_qty'           => $approvedQty,
                'purchased_qty'          => $purchasedQty,
                'received_qty'           => $receivedQty,
                'remaining_qty'          => $remainingQty,
                'fulfillment_status'     => $fulfillmentStatus,
                'is_paid'                => $isPaid,
                'paid_amount'            => $paidAmount,
                'is_paid_but_not_received'=> $isPaidButNotReceived,
                'unit_cost'              => $unitCost,
                'outstanding_value'      => $outstandingValue,
                'days_since_purchase'    => $daysSincePurchase,
            ];
        });

        // Compute KPIs across entire filtered set
        $kpiTotalLines         = $processed->count();
        $kpiUnfulfilledCount   = $processed->where('remaining_qty', '>', 0)->count();
        $kpiUnfulfilledValue   = $processed->where('remaining_qty', '>', 0)->sum('outstanding_value');
        $kpiPaidNotReceivedCount = $processed->where('is_paid_but_not_received', true)->count();
        $kpiPaidNotReceivedValue = $processed->where('is_paid_but_not_received', true)->sum('outstanding_value');
        $kpiFulfilledCount     = $processed->where('fulfillment_status', 'fully_fulfilled')->count();

        // Apply Tab Filter
        if ($tab === 'unfulfilled') {
            $processed = $processed->where('remaining_qty', '>', 0);
        } elseif ($tab === 'paid_not_received') {
            $processed = $processed->where('is_paid_but_not_received', true);
        }

        // Apply Status Filter
        if ($status) {
            $processed = $processed->where('fulfillment_status', $status);
        }

        // Export
        if ($request->input('export') === 'csv' || $request->input('export') === 'excel') {
            return $this->exportFulfillmentCsv($processed);
        }

        // Manual Pagination
        $page = (int) $request->input('page', 1);
        $perPage = 25;
        $paginatedItems = new LengthAwarePaginator(
            $processed->forPage($page, $perPage)->values(),
            $processed->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $stores    = Store::orderBy('name')->get();
        $projects  = Project::orderBy('name')->get();
        $assignees = User::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('audit.fulfillment', compact(
            'paginatedItems',
            'stores',
            'projects',
            'assignees',
            'tab',
            'kpiTotalLines',
            'kpiUnfulfilledCount',
            'kpiUnfulfilledValue',
            'kpiPaidNotReceivedCount',
            'kpiPaidNotReceivedValue',
            'kpiFulfilledCount'
        ));
    }

    /**
     * Detail drawer data for Purchase Request fulfillment trail.
     * Returns:
     * - PR and project/store details
     * - Physical delivery slips added by storekeeper (Model 19 / GRN) with scan photo URLs & store-only flags
     * - Inventory stock movements (intake ledger, before/after balances, performer)
     * - Finance payments disbursed
     */
    public function fulfillmentDetails(int $id)
    {
        $this->authorizeAuditorOrAdmin();

        $pr = PurchaseRequest::with([
            'project',
            'store',
            'supplier',
            'requestedBy',
            'payments.assignedStaff',
            'payments.paidByUser',
            'payment',
            'deliveryReceipts.store',
            'deliveryReceipts.items.product',
            'deliveryReceipts.receivedBy',
            'deliveryReceipts.voidedBy',
        ])->findOrFail($id);

        // Analyze intake slips for quality/compliance flags
        $intakeSlips = $pr->deliveryReceipts->map(function ($dr) use ($pr) {
            $flags = [];

            // Flag 0: Voided slip
            if ($dr->is_void) {
                $flags[] = [
                    'type'    => 'danger',
                    'title'   => 'VOID SLIP',
                    'message' => 'This intake slip has been VOIDED' . ($dr->void_reason ? ': ' . $dr->void_reason : '.') . ($dr->voidedBy ? ' (Voided by ' . $dr->voidedBy->name . ')' : ''),
                ];
            }

            // Flag 1: Missing slip photo
            if (empty($dr->slip_receipt_path)) {
                $flags[] = [
                    'type'    => 'danger',
                    'title'   => 'Missing Physical Slip Photo',
                    'message' => 'No scan or photo of the physical delivery slip (Model 19 / GRN) is attached by store keeper.',
                ];
            }

            // Flag 2: Missing sequence number
            if (empty($dr->slip_no)) {
                $flags[] = [
                    'type'    => 'warning',
                    'title'   => 'Missing Sequence Number',
                    'message' => 'Delivery slip has no registered sequential slip number.',
                ];
            }

            // Flag 3: Store-Only physical slip
            if ($dr->is_physical_store_only) {
                $flags[] = [
                    'type'    => 'info',
                    'title'   => 'Store-Only Physical Slip',
                    'message' => 'Physical slip recorded for store internal stock intake only (excluded from VAT declarations).',
                ];
            }

            // Flag 4: Intake date before PR date
            if ($dr->received_date && $pr->created_at && $dr->received_date->lt($pr->created_at->startOfDay())) {
                $flags[] = [
                    'type'    => 'danger',
                    'title'   => 'Date Inconsistency',
                    'message' => 'Intake receipt date (' . $dr->received_date->format('Y-m-d') . ') is before the Purchase Request creation date (' . $pr->created_at->format('Y-m-d') . ').',
                ];
            }

            $isImage = !empty($dr->slip_receipt_path) && (bool) preg_match('/\.(jpe?g|png|webp|gif|bmp)$/i', $dr->slip_receipt_path);

            return [
                'id'                  => $dr->id,
                'dr_no'               => $dr->dr_no,
                'slip_no'             => $dr->slip_no,
                'store_name'          => $dr->store?->name ?? $pr->store?->name ?? 'Store',
                'received_date'       => $dr->received_date ? $dr->received_date->format('d M Y') : ($dr->created_at ? $dr->created_at->format('d M Y') : 'N/A'),
                'received_by'         => $dr->receivedBy?->name ?? 'Storekeeper',
                'slip_receipt_url'    => $dr->slip_receipt_url,
                'slip_receipt_path'   => $dr->slip_receipt_path,
                'is_image'            => $isImage,
                'is_store_only'       => (bool) $dr->is_physical_store_only,
                'physical_slip_notes' => $dr->physical_slip_notes,
                'notes'               => $dr->notes,
                'is_void'             => (bool) $dr->is_void,
                'void_reason'         => $dr->void_reason,
                'voided_by'           => $dr->voidedBy?->name,
                'voided_at'           => $dr->voided_at ? $dr->voided_at->format('d M Y H:i') : null,
                'status'              => $dr->status ?? 'verified',
                'flags'               => $flags,
                'items'               => $dr->items->map(fn($it) => [
                    'product_name'      => $it->product?->name ?? 'N/A',
                    'product_sku'       => $it->product?->sku ?? $it->product?->item_code ?? '—',
                    'quantity_received' => (float)$it->quantity_received,
                    'accepted_quantity' => (float)$it->accepted_quantity,
                    'rejected_quantity' => (float)($it->rejected_quantity ?? 0),
                    'unit'              => $it->unit ?? $it->product?->unit ?? 'pcs',
                    'rejection_reason'  => $it->rejection_reason,
                ]),
            ];
        });

        // Query Inventory Movements associated with this PR and its delivery intake slips
        $deliveryReceipts = $pr->deliveryReceipts;
        $drIds = $deliveryReceipts->pluck('id')->filter()->all();
        $slipNos = $deliveryReceipts->pluck('slip_no')->filter()->all();
        $drNos = $deliveryReceipts->pluck('dr_no')->filter()->all();
        $prNo = $pr->pr_no ?? $pr->request_number;

        $movements = InventoryMovement::with([
            'inventory.product',
            'inventory.store',
            'performer',
        ])->where(function ($query) use ($pr, $drIds, $slipNos, $drNos, $prNo) {
            // Direct PR reference
            $query->where(function ($sub) use ($pr) {
                $sub->whereIn('reference_type', [
                    PurchaseRequest::class,
                    'App\Models\PurchaseRequest',
                    'PurchaseRequest',
                    'purchase_request',
                ])->where('reference_id', $pr->id);
            });

            // Delivery Receipt reference
            if (!empty($drIds)) {
                $query->orWhere(function ($sub) use ($drIds) {
                    $sub->whereIn('reference_type', [
                        DeliveryReceipt::class,
                        'App\Models\DeliveryReceipt',
                        'DeliveryReceipt',
                        'delivery_receipt',
                    ])->whereIn('reference_id', $drIds);
                });
            }

            // Remarks matching PR number
            if (!empty($prNo)) {
                $query->orWhere('remarks', 'like', "%PR #{$prNo}%")
                      ->orWhere('remarks', 'like', "%PR-{$prNo}%")
                      ->orWhere('remarks', 'like', "%{$prNo}%");
            }

            // Remarks matching Delivery Receipt slip numbers
            foreach ($slipNos as $sNo) {
                if (!empty($sNo)) {
                    $query->orWhere('remarks', 'like', "%Slip #{$sNo}%")
                          ->orWhere('remarks', 'like', "%{$sNo}%");
                }
            }
            foreach ($drNos as $dNo) {
                if (!empty($dNo)) {
                    $query->orWhere('remarks', 'like', "%DR #{$dNo}%")
                          ->orWhere('remarks', 'like', "%{$dNo}%");
                }
            }
        })
        ->orderBy('id', 'desc')
        ->get();

        $formattedMovements = $movements->map(function ($mov) {
            return [
                'id'             => $mov->id,
                'date'           => $mov->created_at ? $mov->created_at->format('d M Y, H:i') : 'N/A',
                'type'           => $mov->type,
                'type_label'     => ucfirst(str_replace('_', ' ', $mov->type ?? 'in')),
                'quantity'       => (float) $mov->quantity,
                'balance_before' => $mov->balance_before !== null ? (float) $mov->balance_before : null,
                'balance_after'  => $mov->balance_after !== null ? (float) $mov->balance_after : null,
                'product_name'   => $mov->inventory?->product?->name ?? 'Unknown Product',
                'product_sku'    => $mov->inventory?->product?->sku ?? $mov->inventory?->product?->item_code ?? '—',
                'product_unit'   => $mov->inventory?->product?->unit ?? 'pcs',
                'store_name'     => $mov->inventory?->store?->name ?? 'Store',
                'performed_by'   => $mov->performer?->name ?? 'Storekeeper / System',
                'remarks'        => $mov->remarks ?: ($mov->notes ?: 'Stock movement recorded'),
                'reference_type' => class_basename($mov->reference_type ?? ''),
                'reference_id'   => $mov->reference_id,
            ];
        });

        // Payments
        $paymentsList = collect();
        if ($pr->payments && $pr->payments->isNotEmpty()) {
            $paymentsList = $pr->payments;
        } elseif ($pr->payment) {
            $paymentsList = collect([$pr->payment]);
        }

        $payments = $paymentsList->map(fn($p) => [
            'amount'      => (float)$p->amount,
            'method'      => $p->method ?? 'Bank',
            'status'      => $p->status ?? 'paid',
            'paid_at'     => $p->paid_at ? $p->paid_at->format('d M Y') : 'Pending',
            'paid_by'     => $p->paidByUser?->name ?? $p->assignedStaff?->name ?? 'Finance Staff',
            'gross'       => (float)($p->gross_amount ?? $p->amount),
            'vat'         => (float)($p->vat_amount ?? 0),
            'withholding' => (float)($p->withholding_amount ?? 0),
        ]);

        return response()->json([
            'success'         => true,
            'pr'              => [
                'id'             => $pr->id,
                'request_number' => $pr->pr_no ?? $pr->request_number ?? ('PR-' . $pr->id),
                'pr_no'          => $pr->pr_no ?? $pr->request_number ?? ('PR-' . $pr->id),
                'title'          => $pr->title ?? $pr->justification ?? ('PR #' . ($pr->pr_no ?? $pr->id)),
                'status'         => $pr->status_label ?? $pr->status,
                'project_name'   => $pr->project?->name ?? 'Head Office',
                'store_name'     => $pr->store?->name ?? 'Main Store',
                'supplier_name'  => $pr->supplier?->name ?? 'N/A',
                'created_at'     => $pr->created_at ? $pr->created_at->format('d M Y') : 'N/A',
                'requested_by'   => $pr->requestedBy?->name ?? 'N/A',
            ],
            'payments'        => $payments,
            'intake_slips'    => $intakeSlips,
            'movements'       => $formattedMovements,
            'total_slips'     => $intakeSlips->count(),
            'total_movements' => $formattedMovements->count(),
        ]);
    }

    /**
     * 3.6 Audit Findings & Actions (/audit/findings)
     * List of findings, status tabs (open, responded, overdue, resolved, all),
     * response workflow, and resolution. Overdue findings escalate to GM.
     */
    public function findingsIndex(Request $request)
    {
        $this->authorizeAuditorOrAdmin();
        AuditFinding::ensureSchema();

        // Run automatic escalations for any findings past due date
        $this->performEscalations();

        $tab        = $request->input('tab', 'open'); // open, responded, overdue, resolved, all
        $severity   = $request->input('severity');
        $storeId    = $request->input('store_id');
        $projectId  = $request->input('project_id');
        $assignedTo = $request->input('assigned_to');
        $search     = $request->input('search');

        $query = AuditFinding::with(['store', 'project', 'assignedTo', 'createdBy', 'respondedBy', 'resolvedBy']);

        if ($tab === 'open') {
            $query->open();
        } elseif ($tab === 'responded') {
            $query->where('status', AuditFinding::STATUS_RESPONDED);
        } elseif ($tab === 'overdue') {
            $query->overdue();
        } elseif ($tab === 'resolved') {
            $query->resolved();
        }

        if ($severity) {
            $query->where('severity', $severity);
        }

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        if ($assignedTo) {
            $query->where('assigned_to', $assignedTo);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('finding_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('observation', 'like', "%{$search}%");
            });
        }

        // Export
        if ($request->input('export') === 'csv' || $request->input('export') === 'excel') {
            $findings = $query->latest('id')->get();
            return $this->exportFindingsCsv($findings);
        }

        // KPIs
        $kpiOpen      = AuditFinding::open()->count();
        $kpiResponded = AuditFinding::where('status', AuditFinding::STATUS_RESPONDED)->count();
        $kpiOverdue   = AuditFinding::overdue()->count();
        $kpiResolved  = AuditFinding::resolved()->count();
        $kpiTotal     = AuditFinding::count();

        $findings = $query->latest('id')->paginate(20)->withQueryString();

        $stores    = Store::orderBy('name')->get();
        $projects  = Project::orderBy('name')->get();
        $assignees = User::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('audit.findings.index', compact(
            'findings',
            'stores',
            'projects',
            'assignees',
            'tab',
            'kpiOpen',
            'kpiResponded',
            'kpiOverdue',
            'kpiResolved',
            'kpiTotal'
        ));
    }

    /**
     * Store new Audit Observation / Query.
     */
    public function storeFinding(Request $request)
    {
        $this->authorizeAuditorOrAdmin();
        AuditFinding::ensureSchema();

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'observation' => 'required|string',
            'source_type' => 'required|string|max:50',
            'source_id'   => 'nullable|integer',
            'store_id'    => 'nullable|integer|exists:stores,id',
            'project_id'  => 'nullable|integer|exists:projects,id',
            'severity'    => 'required|in:low,medium,high,critical',
            'assigned_to' => 'nullable|integer|exists:users,id',
            'due_date'    => 'nullable|date',
            'attachment'  => 'nullable|file|max:10240',
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = FileUploadService::upload($request->file('attachment'), 'audit_findings');
        }

        $finding = AuditFinding::create([
            'finding_number'  => AuditFinding::generateFindingNumber(),
            'source_type'     => $validated['source_type'],
            'source_id'       => $validated['source_id'] ?? null,
            'store_id'        => $validated['store_id'] ?? null,
            'project_id'      => $validated['project_id'] ?? null,
            'title'           => $validated['title'],
            'observation'     => $validated['observation'],
            'severity'        => $validated['severity'],
            'status'          => AuditFinding::STATUS_OPEN,
            'assigned_to'     => $validated['assigned_to'] ?? null,
            'created_by'      => Auth::id(),
            'due_date'        => $validated['due_date'] ?? null,
            'attachment_path' => $attachmentPath,
        ]);

        // Notify Assignee (Storekeeper / Procurement Staff)
        if ($finding->assigned_to) {
            $this->logInAppNotification(
                recipientId: $finding->assigned_to,
                action: 'audit_finding_raised',
                message: "New Audit Query Raised: #{$finding->finding_number} - {$finding->title}. Please review and respond by " . ($finding->due_date ? $finding->due_date->format('Y-m-d') : 'ASAP'),
                findingId: $finding->id
            );
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Audit Finding #{$finding->finding_number} created successfully.",
                'finding' => $finding,
            ]);
        }

        return redirect()->back()->with('success', "Audit Finding #{$finding->finding_number} created successfully.");
    }

    /**
     * Show Audit Finding detail page.
     */
    public function showFinding(int $id)
    {
        $user = Auth::user();
        AuditFinding::ensureSchema();

        $finding = AuditFinding::with(['store', 'project', 'assignedTo', 'createdBy', 'respondedBy', 'resolvedBy'])->findOrFail($id);

        return view('audit.findings.show', compact('finding', 'user'));
    }

    /**
     * Assignee responds with notes and optional attachment.
     */
    public function respondFinding(Request $request, int $id)
    {
        AuditFinding::ensureSchema();
        $finding = AuditFinding::findOrFail($id);

        $user = Auth::user();
        $isAssignee = ($finding->assigned_to && $finding->assigned_to == $user->id);
        $isAuditorOrAdmin = $user->hasAnyRole([
            'auditor', 'audit', 'internal_auditor', 'audit_team', 'Auditor',
            'admin', 'global_admin', 'super_admin'
        ]);

        if (!$isAssignee && !$isAuditorOrAdmin) {
            abort(403, 'Unauthorized. Only the assigned staff or administrators can submit a response.');
        }

        $validated = $request->validate([
            'response_notes'      => 'required|string',
            'response_attachment' => 'nullable|file|max:10240',
        ]);

        $responseAttachment = $finding->response_attachment;
        if ($request->hasFile('response_attachment')) {
            $responseAttachment = FileUploadService::upload($request->file('response_attachment'), 'audit_finding_responses');
        }

        $finding->update([
            'response_notes'      => $validated['response_notes'],
            'response_attachment' => $responseAttachment,
            'responded_by'        => $user->id,
            'responded_at'        => now(),
            'status'              => AuditFinding::STATUS_RESPONDED,
        ]);

        // Notify Auditor that a response was submitted
        if ($finding->created_by) {
            $this->logInAppNotification(
                recipientId: $finding->created_by,
                action: 'audit_finding_responded',
                message: "Response submitted for Audit Query #{$finding->finding_number} by {$user->name}.",
                findingId: $finding->id
            );
        }

        return redirect()->route('audit.findings.show', $finding->id)
            ->with('success', 'Response recorded successfully. The auditor will review your submission.');
    }

    /**
     * Auditor closes / resolves the finding.
     */
    public function resolveFinding(Request $request, int $id)
    {
        $this->authorizeAuditorOrAdmin();
        AuditFinding::ensureSchema();

        $finding = AuditFinding::findOrFail($id);

        $validated = $request->validate([
            'resolution_notes' => 'required|string',
        ]);

        $finding->update([
            'resolution_notes' => $validated['resolution_notes'],
            'resolved_by'      => Auth::id(),
            'resolved_at'      => now(),
            'status'           => AuditFinding::STATUS_RESOLVED,
        ]);

        // Notify Assignee that query is closed
        if ($finding->assigned_to) {
            $this->logInAppNotification(
                recipientId: $finding->assigned_to,
                action: 'audit_finding_resolved',
                message: "Audit Query #{$finding->finding_number} has been reviewed and marked as RESOLVED.",
                findingId: $finding->id
            );
        }

        return redirect()->route('audit.findings.show', $finding->id)
            ->with('success', "Audit Finding #{$finding->finding_number} has been resolved.");
    }

    /**
     * Check and trigger overdue escalations to GM.
     */
    public function checkEscalations(Request $request)
    {
        $this->authorizeAuditorOrAdmin();
        $count = $this->performEscalations();

        return response()->json([
            'success'          => true,
            'escalated_count'  => $count,
            'message'          => "Checked escalations: {$count} findings marked as escalated to GM.",
        ]);
    }

    /**
     * Perform overdue escalations to General Manager.
     */
    protected function performEscalations(): int
    {
        AuditFinding::ensureSchema();

        $overdueFindings = AuditFinding::where('status', '!=', AuditFinding::STATUS_RESOLVED)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->where('is_escalated', false)
            ->get();

        if ($overdueFindings->isEmpty()) {
            return 0;
        }

        // Find GM users
        $gmUsers = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['gm', 'general_manager', 'General Manager', 'GM', 'super_admin']);
        })->get();

        foreach ($overdueFindings as $f) {
            $f->update([
                'is_escalated' => true,
                'escalated_at' => now(),
                'status'       => AuditFinding::STATUS_ESCALATED,
            ]);

            // Notify GM users
            foreach ($gmUsers as $gm) {
                $this->logInAppNotification(
                    recipientId: $gm->id,
                    action: 'audit_finding_escalated',
                    message: "🚨 ESCALATION: Audit Query #{$f->finding_number} ({$f->title}) is OVERDUE and requires GM attention.",
                    findingId: $f->id
                );
            }
        }

        return $overdueFindings->count();
    }

    /**
     * Helper to log notifications defensively.
     */
    protected function logInAppNotification(int $recipientId, string $action, string $message, int $findingId): void
    {
        try {
            if (Schema::hasTable('notification_log')) {
                NotificationLog::create([
                    'request_type'      => 'audit_finding',
                    'request_id'        => $findingId,
                    'action'            => $action,
                    'sender_user_id'    => Auth::id() ?? 1,
                    'recipient_user_id' => $recipientId,
                    'message'           => $message,
                    'status'            => 'sent',
                ]);
            }
        } catch (\Throwable $e) {}
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // CSV / EXCEL EXPORTERS
    // ─────────────────────────────────────────────────────────────────────────────

    protected function exportInventoryCsv($items): StreamedResponse
    {
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="inventory_audit_overview_' . date('Ymd_His') . '.csv"',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($items) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Store',
                'Project / Location',
                'Product Name',
                'SKU',
                'Category',
                'Unit',
                'Quantity On Hand',
                'Unit Cost (ETB)',
                'Total Valuation (ETB)',
                'Min Stock',
                'Last Movement Date',
                'Status Flag',
            ]);

            foreach ($items as $row) {
                $statusFlag = 'Normal';
                if ($row->quantity_on_hand < 0) {
                    $statusFlag = 'NEGATIVE STOCK';
                } elseif ($row->quantity_on_hand == 0) {
                    $statusFlag = 'Out of Stock';
                } elseif ($row->quantity_on_hand <= ($row->min_stock ?? 0)) {
                    $statusFlag = 'Low Stock';
                }

                $lastMove = $row->last_movement_at ? $row->last_movement_at->format('Y-m-d H:i') : 'No Movement Recorded';

                fputcsv($handle, [
                    $row->store?->name ?? 'N/A',
                    $row->store?->project?->name ?? 'N/A',
                    $row->product?->name ?? 'N/A',
                    $row->product?->sku ?? 'N/A',
                    $row->product?->category ?? 'N/A',
                    $row->product?->unit ?? 'pcs',
                    number_format((float)$row->quantity_on_hand, 2, '.', ''),
                    number_format((float)($row->unit_cost ?? 0), 2, '.', ''),
                    number_format((float)($row->quantity_on_hand * ($row->unit_cost ?? 0)), 2, '.', ''),
                    $row->min_stock ?? 0,
                    $lastMove,
                    $statusFlag,
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    protected function exportMovementsCsv($items): StreamedResponse
    {
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="stock_movement_ledger_' . date('Ymd_His') . '.csv"',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($items) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Date & Time',
                'Store',
                'Product Name',
                'SKU',
                'Movement Type',
                'Quantity In / Out',
                'Balance Before',
                'Balance After',
                'Running Reconciliation',
                'Reference Doc',
                'Remarks',
            ]);

            foreach ($items as $mov) {
                $isIn = in_array($mov->type, ['intake', 'in', 'transfer_in', 'return']);
                $isOut = in_array($mov->type, ['issue', 'out', 'transfer_out']);
                $expected = $isIn ? ($mov->balance_before + $mov->quantity) : ($isOut ? ($mov->balance_before - $mov->quantity) : $mov->balance_after);
                $discrepancy = abs((float)$expected - (float)$mov->balance_after) > 0.001;

                fputcsv($handle, [
                    $mov->created_at ? $mov->created_at->format('Y-m-d H:i') : 'N/A',
                    $mov->inventory?->store?->name ?? 'N/A',
                    $mov->inventory?->product?->name ?? 'N/A',
                    $mov->inventory?->product?->sku ?? 'N/A',
                    strtoupper($mov->type),
                    number_format((float)$mov->quantity, 2, '.', ''),
                    number_format((float)$mov->balance_before, 2, '.', ''),
                    number_format((float)$mov->balance_after, 2, '.', ''),
                    $discrepancy ? 'DISCREPANCY (Expected ' . number_format($expected, 2) . ')' : 'Balanced',
                    ($mov->reference_type ? class_basename($mov->reference_type) : '') . ' #' . ($mov->reference_id ?? 'N/A'),
                    $mov->remarks ?? ($mov->notes ?? ''),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    protected function exportDailyUsageCsv($records): StreamedResponse
    {
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="daily_usage_audit_' . date('Ymd_His') . '.csv"',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($records) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Usage Date',
                'Project / Site',
                'Store',
                'Slip # / Ref',
                'Item Name',
                'Used Quantity',
                'Unit Cost (ETB)',
                'Total Cost (ETB)',
                'Storekeeper',
                'Verified By',
            ]);

            foreach ($records as $u) {
                foreach ($u->items as $it) {
                    fputcsv($handle, [
                        $u->usage_date ? $u->usage_date->format('Y-m-d') : 'N/A',
                        $u->project?->name ?? 'N/A',
                        $u->store?->name ?? 'N/A',
                        $u->slip_number ?? ('USG-' . $u->id),
                        $it->product?->name ?? 'N/A',
                        number_format((float)($it->used_quantity ?? $it->quantity ?? 0), 2, '.', ''),
                        number_format((float)($it->unit_cost ?? 0), 2, '.', ''),
                        number_format((float)($it->total_cost ?? 0), 2, '.', ''),
                        $u->createdBy?->name ?? 'N/A',
                        $u->verifiedBy?->name ?? 'N/A',
                    ]);
                }
            }

            fclose($handle);
        }, 200, $headers);
    }

    protected function exportTransfersCsv($transfers): StreamedResponse
    {
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="material_transfers_audit_' . date('Ymd_His') . '.csv"',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($transfers) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Transfer #',
                'Outgoing Physical Slip #',
                'Outgoing Document Attached',
                'Receiving Physical Slip #',
                'Receiving Document Attached',
                'Physical Slip # (Legacy)',
                'Origin Store (From)',
                'Destination Store (To)',
                'Dispatched Date',
                'Received Date',
                'Vehicle Plate / Driver',
                'Dispatched Qty',
                'Received Qty',
                'Discrepancy (Shortage/Excess)',
                'Status',
                'Audit Flag',
            ]);

            foreach ($transfers as $tr) {
                $sent = (float) $tr->items->sum('sent_quantity');
                $recv = (float) $tr->items->sum('received_quantity');
                $diff = $recv - $sent;

                $auditFlag = 'Normal';
                if ($tr->status === Transfer::STATUS_IN_TRANSIT && $tr->dispatched_at && $tr->dispatched_at->lte(now()->subHours(48))) {
                    $auditFlag = 'IN TRANSIT > 48 HOURS';
                } elseif ($tr->status === Transfer::STATUS_COMPLETED && $diff < 0) {
                    $auditFlag = 'SHORTAGE: ' . abs($diff);
                } elseif ($tr->status === Transfer::STATUS_COMPLETED && $diff > 0) {
                    $auditFlag = 'EXCESS: +' . $diff;
                }

                $outgoingSlip = $tr->outgoing_slip_no ?: $tr->physical_slip_no;

                fputcsv($handle, [
                    $tr->transfer_no ?? ('TR-' . $tr->id),
                    $outgoingSlip ?: 'None',
                    $tr->outgoing_slip_url ? 'Yes (' . $tr->outgoing_slip_url . ')' : 'No',
                    $tr->receiving_slip_no ?: 'None',
                    $tr->receiving_slip_url ? 'Yes (' . $tr->receiving_slip_url . ')' : 'No',
                    $tr->physical_slip_no ?: 'None',
                    $tr->fromStore?->name ?? 'N/A',
                    $tr->toStore?->name ?? 'N/A',
                    $tr->dispatched_at ? $tr->dispatched_at->format('Y-m-d H:i') : 'Pending',
                    $tr->received_at ? $tr->received_at->format('Y-m-d H:i') : 'Pending',
                    ($tr->vehicle_plate_no ?? '') . ' / ' . ($tr->driver_employee_id ?? 'N/A'),
                    number_format($sent, 2, '.', ''),
                    number_format($recv, 2, '.', ''),
                    number_format($diff, 2, '.', ''),
                    strtoupper($tr->status),
                    $auditFlag,
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    protected function exportFulfillmentCsv($collection): StreamedResponse
    {
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="fulfillment_check_audit_' . date('Ymd_His') . '.csv"',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($collection) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'PR #',
                'Project',
                'Store',
                'Supplier',
                'Product Name',
                'SKU',
                'Unit',
                'Requested Qty',
                'Approved Qty',
                'Purchased Qty',
                'Received at Store Qty',
                'Remaining Qty',
                'Fulfillment Status',
                'Payment Status',
                'Paid Amount (ETB)',
                'Outstanding Value (ETB)',
                'Audit Risk Alert',
            ]);

            foreach ($collection as $row) {
                fputcsv($handle, [
                    $row->pr?->request_number ?? ('PR-' . $row->pr?->id),
                    $row->pr?->project?->name ?? 'N/A',
                    $row->pr?->store?->name ?? 'N/A',
                    $row->pr?->supplier?->name ?? 'N/A',
                    $row->product?->name ?? 'N/A',
                    $row->product?->sku ?? 'N/A',
                    $row->product?->unit ?? 'pcs',
                    number_format((float)$row->requested_qty, 2, '.', ''),
                    number_format((float)$row->approved_qty, 2, '.', ''),
                    number_format((float)$row->purchased_qty, 2, '.', ''),
                    number_format((float)$row->received_qty, 2, '.', ''),
                    number_format((float)$row->remaining_qty, 2, '.', ''),
                    strtoupper(str_replace('_', ' ', $row->fulfillment_status)),
                    $row->is_paid ? 'PAID' : 'PENDING PAYMENT',
                    number_format((float)$row->paid_amount, 2, '.', ''),
                    number_format((float)$row->outstanding_value, 2, '.', ''),
                    $row->is_paid_but_not_received ? 'PAID BUT NOT RECEIVED (CRITICAL RISK)' : 'OK',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    protected function exportFindingsCsv($findings): StreamedResponse
    {
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="audit_findings_report_' . date('Ymd_His') . '.csv"',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($findings) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Finding #',
                'Title',
                'Severity',
                'Status',
                'Source Type',
                'Store',
                'Project',
                'Assigned To',
                'Due Date',
                'Created By',
                'Created At',
                'Is Escalated',
            ]);

            foreach ($findings as $f) {
                fputcsv($handle, [
                    $f->finding_number,
                    $f->title,
                    strtoupper($f->severity),
                    strtoupper($f->status),
                    $f->source_type,
                    $f->store?->name ?? 'N/A',
                    $f->project?->name ?? 'N/A',
                    $f->assignedTo?->name ?? 'N/A',
                    $f->due_date ? $f->due_date->format('Y-m-d') : 'N/A',
                    $f->createdBy?->name ?? 'N/A',
                    $f->created_at ? $f->created_at->format('Y-m-d H:i') : 'N/A',
                    $f->is_escalated ? 'YES' : 'NO',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
