<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\DeliveryReceipt;
use App\Models\SlipSequence;
use App\Models\User;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StoreManagerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Store Manager Dashboard
     */
    public function dashboard()
    {
        // ── Safe defaults (used if ANY query fails) ───────────────────────
        $kpi = [
            'total_items'       => 0,
            'total_value'       => 0,
            'low_stock_items'   => 0,
            'pending_transfers' => 0,
            'received_today'    => 0,
            'pending_requests'  => 0,
        ];
        $inventoryValueByStore  = collect();
        $todayAdjustmentValue   = 0;
        $monthlyReceiptsValue   = 0;
        $lastMonthReceiptsValue = 0;
        $topValueItems          = collect();
        $allInventory           = collect();
        $lowStock               = collect();
        $lowStockItems          = collect();
        $transfersToGeneralService = collect();
        $materialRequests       = collect();
        $stores                 = collect();

        try {
            $user    = Auth::user();
            $storeId = $user->store_id ?? null;

            // ── KPI Cards ────────────────────────────────────────────────
            $kpi['total_items']       = $this->safe(fn() => Inventory::count(), 0);
            $kpi['total_value']       = $this->safe(fn() => DB::table('inventory')
                ->join('products', 'inventory.product_id', '=', 'products.id')
                ->whereNull('products.deleted_at')
                ->sum(DB::raw('inventory.quantity_on_hand * COALESCE(
                    inventory.unit_cost,
                    (SELECT price FROM material_prices WHERE product_id = products.id ORDER BY effective_date DESC, id DESC LIMIT 1),
                    (SELECT unit_price FROM purchase_order_items WHERE product_id = products.id ORDER BY id DESC LIMIT 1),
                    products.unit_price,
                    0
                )')), 0);
            $kpi['low_stock_items']   = $this->safe(fn() => Inventory::whereColumn('quantity_on_hand', '<=', 'min_stock')->count(), 0);
            $kpi['pending_transfers'] = $this->safe(fn() => Transfer::where('status', 'draft')->count(), 0);
            $kpi['received_today']    = $this->safe(fn() => DeliveryReceipt::where(function($q) {
                $q->whereDate('received_date', today())
                  ->orWhereDate('created_at', today());
            })->count(), 0);
            $kpi['pending_requests']  = $this->safe(fn() => MaterialRequest::where('status', 'pending')->count(), 0);

            // ── Financial Metrics ────────────────────────────────────────
            $inventoryValueByStore = $this->safe(fn() => DB::table('inventory')
                ->join('stores', 'inventory.store_id', '=', 'stores.id')
                ->join('products', 'inventory.product_id', '=', 'products.id')
                ->where('stores.is_active', true)
                ->selectRaw('stores.name as store_name, SUM(inventory.quantity_on_hand * COALESCE(
                    inventory.unit_cost,
                    (SELECT price FROM material_prices WHERE product_id = products.id ORDER BY effective_date DESC, id DESC LIMIT 1),
                    (SELECT unit_price FROM purchase_order_items WHERE product_id = products.id ORDER BY id DESC LIMIT 1),
                    products.unit_price,
                    0
                )) as total_value, COUNT(*) as product_count')
                ->groupBy('stores.id', 'stores.name')
                ->orderByDesc('total_value')
                ->get(), collect());

            $todayAdjustmentValue = $this->safe(fn() => (float) DB::table('inventory_movements')
                ->join('inventory', 'inventory_movements.inventory_id', '=', 'inventory.id')
                ->whereDate('inventory_movements.created_at', today())
                ->where('inventory_movements.type', 'adjustment')
                ->selectRaw('SUM(inventory_movements.quantity * inventory.unit_cost) as delta_value')
                ->value('delta_value'), 0);

            $monthlyReceiptsValue = $this->safe(fn() => (float) DB::table('inventory_movements')
                ->join('inventory', 'inventory_movements.inventory_id', '=', 'inventory.id')
                ->whereMonth('inventory_movements.created_at', now()->month)
                ->whereYear('inventory_movements.created_at', now()->year)
                ->where('inventory_movements.type', 'in')
                ->selectRaw('SUM(ABS(inventory_movements.quantity) * inventory.unit_cost) as total')
                ->value('total'), 0);

            $lastMonthReceiptsValue = $this->safe(fn() => (float) DB::table('inventory_movements')
                ->join('inventory', 'inventory_movements.inventory_id', '=', 'inventory.id')
                ->whereMonth('inventory_movements.created_at', now()->subMonth()->month)
                ->whereYear('inventory_movements.created_at', now()->subMonth()->year)
                ->where('inventory_movements.type', 'in')
                ->selectRaw('SUM(ABS(inventory_movements.quantity) * inventory.unit_cost) as total')
                ->value('total'), 0);

            $topValueItems = $this->safe(fn() => DB::table('inventory')
                ->join('products', 'inventory.product_id', '=', 'products.id')
                ->join('stores', 'inventory.store_id', '=', 'stores.id')
                ->where('stores.is_active', true)
                ->selectRaw('products.name as product_name, products.sku, products.unit, stores.name as store_name,
                             inventory.quantity_on_hand,
                             COALESCE(
                                inventory.unit_cost,
                                (SELECT price FROM material_prices WHERE product_id = products.id ORDER BY effective_date DESC, id DESC LIMIT 1),
                                (SELECT unit_price FROM purchase_order_items WHERE product_id = products.id ORDER BY id DESC LIMIT 1),
                                products.unit_price,
                                0
                             ) as unit_cost,
                             (inventory.quantity_on_hand * COALESCE(
                                inventory.unit_cost,
                                (SELECT price FROM material_prices WHERE product_id = products.id ORDER BY effective_date DESC, id DESC LIMIT 1),
                                (SELECT unit_price FROM purchase_order_items WHERE product_id = products.id ORDER BY id DESC LIMIT 1),
                                products.unit_price,
                                0
                             )) as line_value')
                ->orderByDesc('line_value')
                ->limit(10)
                ->get(), collect());

            $allInventory = $this->safe(fn() => Inventory::with('product', 'store')
                ->whereHas('store', fn($q) => $q->where('is_active', true))
                ->orderBy('quantity_on_hand', 'desc')
                ->take(15)
                ->get(), collect());

            $lowStock = $this->safe(fn() => Inventory::with('product', 'store')
                ->whereColumn('quantity_on_hand', '<=', 'min_stock')
                ->whereHas('store', fn($q) => $q->where('is_active', true))
                ->get(), collect());

            $lowStockItems = $lowStock;

            $transfersToGeneralService = $this->safe(fn() => Transfer::with(['fromStore', 'toStore', 'requestedBy', 'items.product'])
                ->where('status', 'approved')
                ->latest()
                ->take(10)
                ->get(), collect());

            $materialRequests = $this->safe(fn() => MaterialRequest::with(['project', 'requestedBy', 'items.product'])
                ->where('status', 'pending')
                ->latest()
                ->take(10)
                ->get(), collect());

            $stores = $this->safe(fn() => Store::where('is_active', true)->orderBy('name')->get(), collect());

        } catch (\Throwable $e) {
            // Log the error but don't crash — show dashboard with zeros
            \Log::error('StoreManager dashboard error: ' . $e->getMessage());
        }

        return view('store-manager.dashboard', compact(
            'kpi', 'allInventory', 'lowStock', 'lowStockItems',
            'transfersToGeneralService', 'materialRequests', 'stores',
            'inventoryValueByStore', 'todayAdjustmentValue',
            'monthlyReceiptsValue', 'lastMonthReceiptsValue', 'topValueItems'
        ));
    }


    /**
     * All Inventory from all stores (Grouped by Product when All Stores is selected)
     */
    public function allInventory(Request $request)
    {
        $selectedStoreId = $request->input('store_id');
        $search = $request->input('search');
        $lowStockOnly = $request->boolean('low_stock');

        $stores = Store::where('is_active', true)->orderBy('name')->get();
        $products = Product::where('is_active', true)->orderBy('name')->get();

        $user = Auth::user();
        $isStoreKeeper = $user && $user->hasRole('store_keeper');
        $assignedStore = null;

        if ($isStoreKeeper) {
            $assignedStore = $user->store ?? Store::where('manager_id', $user->id)->first();
            if ($assignedStore) {
                $selectedStoreId = $assignedStore->id;
            }
        }

        if ($selectedStoreId) {
            // Single Store View
            $query = Inventory::with('product', 'store')
                ->where('store_id', $selectedStoreId)
                ->whereHas('store', fn($q) => $q->where('is_active', true));

            if ($search) {
                $query->whereHas('product', fn($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            }

            if ($lowStockOnly) {
                $query->whereColumn('quantity_on_hand', '<=', 'min_stock');
            }

            $inventory = $query->orderBy('quantity_on_hand', 'desc')->paginate(25)->withQueryString();
            $isGrouped = false;
        } else {
            // All Stores Grouped View (1 row per product with total on hand & store breakdown)
            $query = Inventory::with(['product', 'store'])
                ->whereHas('store', fn($q) => $q->where('is_active', true));

            if ($search) {
                $query->whereHas('product', fn($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            }

            $allRecords = $query->get();

            // Group inventory items by product_id
            $grouped = $allRecords->groupBy('product_id')->map(function ($items) {
                $first = $items->first();
                $totalOnHand = (float) $items->sum('quantity_on_hand');
                $totalReserved = (float) $items->sum('quantity_reserved');
                $totalMinStock = (float) $items->sum('min_stock');
                $product = $first->product;

                $effectiveCost = (float) (
                    $first->unit_cost ?: (
                        DB::table('material_prices')->where('product_id', $first->product_id)->orderByDesc('effective_date')->orderByDesc('id')->value('price') ?: (
                            DB::table('purchase_order_items')->where('product_id', $first->product_id)->orderByDesc('id')->value('unit_price') ?: ($product->unit_price ?? 0)
                        )
                    )
                );

                $storesBreakdown = $items->map(function ($item) use ($effectiveCost) {
                    $itemReserved = (float) ($item->quantity_reserved ?? 0);
                    return [
                        'store_id'     => $item->store_id,
                        'store_name'   => $item->store->name ?? 'N/A',
                        'store_type'   => $item->store->type ?? 'Site Store',
                        'on_hand'      => (float) $item->quantity_on_hand,
                        'reserved'     => $itemReserved,
                        'available'    => max(0, (float) $item->quantity_on_hand - $itemReserved),
                        'min_stock'    => (float) $item->min_stock,
                        'unit_cost'    => $effectiveCost,
                        'total_val'    => (float) $item->quantity_on_hand * $effectiveCost,
                    ];
                })->values();

                return [
                    'product_id'       => $first->product_id,
                    'product_name'     => $product->name ?? 'N/A',
                    'product_code'     => $product->code ?? '',
                    'product_category' => $product->category ?? 'General Material',
                    'product_unit'     => $product->unit ?? 'pcs',
                    'product_desc'     => $product->description ?? 'No additional specification provided.',
                    'total_on_hand'    => $totalOnHand,
                    'total_reserved'   => $totalReserved,
                    'total_available'  => max(0, $totalOnHand - $totalReserved),
                    'total_min_stock'  => $totalMinStock,
                    'effective_cost'   => $effectiveCost,
                    'total_value'      => $totalOnHand * $effectiveCost,
                    'store_count'      => $items->count(),
                    'stores_breakdown' => $storesBreakdown,
                ];
            });

            if ($lowStockOnly) {
                $grouped = $grouped->filter(fn($item) => $item['total_on_hand'] <= $item['total_min_stock']);
            }

            $sorted = $grouped->sortByDesc('total_on_hand')->values();

            // Paginator for grouped items
            $page = Paginator::resolveCurrentPage() ?: 1;
            $perPage = 25;
            $paginatedItems = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

            $inventory = new LengthAwarePaginator(
                $paginatedItems,
                $sorted->count(),
                $perPage,
                $page,
                ['path' => Paginator::resolveCurrentPath(), 'query' => $request->query()]
            );

            $isGrouped = true;
        }

        return view('store-manager.inventory.all', compact('inventory', 'stores', 'products', 'isGrouped', 'isStoreKeeper', 'assignedStore'));
    }

    /**
     * Create Transfer
     */
    public function createTransfer()
    {
        $user = Auth::user();
        $rawUserRoles = $user ? $user->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray() : [];
        if (in_array('store_keeper', $rawUserRoles) && !in_array('store_manager', $rawUserRoles) && !in_array('admin', $rawUserRoles) && !in_array('global_admin', $rawUserRoles)) {
            return redirect()->route('store-manager.transfers.index')
                ->with('error', 'Store Keepers cannot create inter-store transfers. Transfers must be initiated by Store Managers or Coordinators. Store Keepers handle Outgoing Dispatch and Incoming Receiving.');
        }

        $stores = Store::where('is_active', true)->orderBy('name')->get();
        $products = Product::where('is_active', true)->orderBy('name')->get();

        return view('store-manager.transfers.create', compact('stores', 'products'));
    }

    /**
     * Store Transfer
     */
    public function storeTransfer(Request $request)
    {
        $user = Auth::user();
        $rawUserRoles = $user ? $user->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray() : [];
        if (in_array('store_keeper', $rawUserRoles) && !in_array('store_manager', $rawUserRoles) && !in_array('admin', $rawUserRoles) && !in_array('global_admin', $rawUserRoles)) {
            return redirect()->route('store-manager.transfers.index')
                ->with('error', 'Store Keepers cannot create inter-store transfers. Transfers must be initiated by Store Managers or Coordinators. Store Keepers handle Outgoing Dispatch and Incoming Receiving.');
        }

        $request->validate([
            'from_store_id'       => 'required|exists:stores,id',
            'to_store_id'         => 'required|exists:stores,id|different:from_store_id',
            'required_date'       => 'nullable|date',
            'reason'              => 'nullable|string|max:500',
            'items'               => 'required|array|min:1',
            'items.*.product_id'  => 'required|exists:products,id',
            'items.*.quantity'    => 'required|numeric|min:0.001',
            'items.*.unit'        => 'nullable|string|max:20',
        ]);

        DB::transaction(function () use ($request) {
            $no = 'TR-' . date('Ymd') . '-' . str_pad(Transfer::count() + 1, 4, '0', STR_PAD_LEFT);

            $transfer = Transfer::create([
                'transfer_no'   => $no,
                'from_store_id' => $request->from_store_id,
                'to_store_id'   => $request->to_store_id,
                'requested_by'  => Auth::id(),
                'required_date' => $request->required_date,
                'reason'        => $request->reason,
                'status'        => 'draft',
            ]);

            foreach ($request->items as $item) {
                $transfer->items()->create([
                    'product_id'         => $item['product_id'],
                    'requested_quantity' => $item['quantity'],
                    'unit'               => $item['unit'] ?? 'pcs',
                ]);
            }
        });

        return redirect()->route('store-manager.transfers.index')->with('success', 'Transfer created and sent to General Service for scheduling.');
    }

    /**
     * List Transfers
     */
    /**
     * List Transfers with filter tabs and storekeeper metrics
     */
    public function transfersIndex(Request $request)
    {
        $user = Auth::user();
        $rawUserRoles = $user ? $user->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray() : [];
        $isStoreKeeper = in_array('store_keeper', $rawUserRoles);
        $assignedStore = $user->store ?? Store::where('manager_id', $user->id)->first();
        $storeId = $assignedStore?->id;

        $tab = $request->input('tab');
        if ($isStoreKeeper && empty($tab)) {
            $tab = 'incoming';
        } elseif (empty($tab)) {
            $tab = 'all';
        }
        $search = $request->input('search');
        $filterStoreId = $request->input('store_id');
        $status = $request->input('status');

        $query = Transfer::with(['fromStore', 'toStore', 'requestedBy', 'approvedBy', 'dispatchedBy', 'receivedBy', 'driver', 'items.product']);

        if ($isStoreKeeper) {
            if (!$storeId) {
                // If storekeeper has no assigned store, do not show any other store's data
                $query->whereRaw('1 = 0');
            } elseif ($tab === 'outgoing') {
                $query->where('from_store_id', $storeId);
            } else {
                // Default to incoming transfers assigned to their store
                $query->where('to_store_id', $storeId);
            }
        } elseif (!empty($filterStoreId)) {
            if ($tab === 'outgoing') {
                $query->where('from_store_id', $filterStoreId);
            } elseif ($tab === 'incoming') {
                $query->where('to_store_id', $filterStoreId);
            } else {
                $query->where(function ($q) use ($filterStoreId) {
                    $q->where('from_store_id', $filterStoreId)
                      ->orWhere('to_store_id', $filterStoreId);
                });
            }
        }

        // Tab Filters (for manager / logistics)
        if ($tab === 'pending_driver') {
            $query->where(function($q) {
                $q->whereIn('status', ['draft', 'pending_approval'])
                  ->orWhereNull('driver_employee_id');
            });
        } elseif ($tab === 'assigned_drivers' || $tab === 'driver_status') {
            $query->whereNotNull('driver_employee_id');
        } elseif ($tab === 'pending_dispatch') {
            $query->where('status', 'approved');
        } elseif ($tab === 'in_transit') {
            $query->where('status', 'in_transit');
        } elseif ($tab === 'completed') {
            $query->where('status', 'completed');
        } elseif (!empty($status)) {
            $query->where('status', $status);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('transfer_no', 'like', "%{$search}%")
                  ->orWhere('physical_slip_no', 'like', "%{$search}%")
                  ->orWhere('outgoing_slip_no', 'like', "%{$search}%")
                  ->orWhere('receiving_slip_no', 'like', "%{$search}%")
                  ->orWhere('vehicle_plate_no', 'like', "%{$search}%")
                  ->orWhereHas('fromStore', fn($sq) => $sq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('toStore', fn($sq) => $sq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('driver', fn($sq) => $sq->where('full_name', 'like', "%{$search}%"));
            });
        }

        // KPI Counts for Store Keeper / Store Manager
        $baseStatQuery = Transfer::query();
        if ($isStoreKeeper) {
            if ($storeId) {
                $baseStatQuery->where(function ($q) use ($storeId) {
                    $q->where('from_store_id', $storeId)
                      ->orWhere('to_store_id', $storeId);
                });
            } else {
                $baseStatQuery->whereRaw('1 = 0');
            }
        }
        $totalCount           = (clone $baseStatQuery)->count();
        $pendingDriverCount   = (clone $baseStatQuery)->where(function($q) {
            $q->whereIn('status', ['draft', 'pending_approval'])
              ->orWhereNull('driver_employee_id');
        })->count();
        $assignedDriverCount  = (clone $baseStatQuery)->whereNotNull('driver_employee_id')->count();
        $readyToDispatchCount = (clone $baseStatQuery)->where('status', 'approved')->count();
        $inTransitCount       = (clone $baseStatQuery)->where('status', 'in_transit')->count();
        $completedCount       = (clone $baseStatQuery)->where('status', 'completed')->count();

        // Storekeeper specific counts (Incoming vs Outgoing strictly for assigned store)
        $incomingCount        = $storeId ? Transfer::where('to_store_id', $storeId)->count() : 0;
        $outgoingCount        = $storeId ? Transfer::where('from_store_id', $storeId)->count() : 0;
        $pendingIncomingCount = $storeId ? Transfer::where('to_store_id', $storeId)->whereIn('status', ['in_transit', 'approved'])->count() : 0;
        $completedIncomingCount = $storeId ? Transfer::where('to_store_id', $storeId)->where('status', 'completed')->count() : 0;
        $pendingOutgoingCount = $storeId ? Transfer::where('from_store_id', $storeId)->whereIn('status', ['draft', 'approved'])->count() : 0;
        $completedOutgoingCount = $storeId ? Transfer::where('from_store_id', $storeId)->where('status', 'completed')->count() : 0;

        $transfers = $query->latest()->paginate(20)->withQueryString();
        $stores = Store::where('is_active', true)->orderBy('name')->get();

        return view('store-manager.transfers.index', compact(
            'transfers', 'stores', 'isStoreKeeper', 'assignedStore', 'tab',
            'totalCount', 'pendingDriverCount', 'assignedDriverCount', 'readyToDispatchCount', 'inTransitCount', 'completedCount',
            'incomingCount', 'outgoingCount', 'pendingIncomingCount', 'completedIncomingCount', 'pendingOutgoingCount', 'completedOutgoingCount'
        ));
    }

    /**
     * Show Transfer Details & Interactive Workflow Steps
     */
    public function showTransfer(Transfer $transfer)
    {
        $user = Auth::user();
        $rawUserRoles = $user ? $user->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray() : [];
        $isStoreKeeper = in_array('store_keeper', $rawUserRoles);
        $assignedStore = $user->store ?? Store::where('manager_id', $user->id)->first();
        $storeId = $assignedStore?->id;

        // Strict authorization check for Storekeeper: only view transfers belonging to assigned store
        if ($isStoreKeeper) {
            if (!$storeId || ($transfer->from_store_id != $storeId && $transfer->to_store_id != $storeId)) {
                abort(403, 'Unauthorized. You can only view incoming or outgoing transfers assigned to your store (' . ($assignedStore->name ?? 'None') . ').');
            }
        }

        $transfer->load([
            'fromStore', 'toStore', 'requestedBy', 'approvedBy',
            'dispatchedBy', 'receivedBy', 'driver', 'items.product'
        ]);

        // Fetch Drivers for assignment
        $drivers = \App\Models\Employee::where('status', 'active')
            ->where(function ($q) {
                $q->where('department', 'like', '%driver%')
                  ->orWhere('role_title', 'like', '%driver%');
            })->orderBy('full_name')->get();

        if ($drivers->isEmpty()) {
            $drivers = \App\Models\Employee::where('status', 'active')->orderBy('full_name')->get();
        }

        return view('store-manager.transfers.show', compact('transfer', 'isStoreKeeper', 'assignedStore', 'drivers'));
    }

    /**
     * Assign Driver & Approve Transfer (General Service / Store Manager / Admin)
     */
    public function assignDriver(Request $request, Transfer $transfer)
    {
        $request->validate([
            'driver_employee_id' => 'required|exists:employees,id',
            'vehicle_plate_no'   => 'nullable|string|max:100',
            'dispatch_notes'     => 'nullable|string|max:500',
        ]);

        $transfer->update([
            'driver_employee_id' => $request->driver_employee_id,
            'vehicle_plate_no'   => $request->vehicle_plate_no,
            'dispatch_notes'     => $request->dispatch_notes,
            'approved_by'        => Auth::id(),
            'approved_at'        => now(),
            'status'             => 'approved',
        ]);

        // Send SMS to Driver
        $driver = \App\Models\Employee::find($request->driver_employee_id);
        if ($driver && !empty($driver->phone)) {
            try {
                $fromStoreName = $transfer->fromStore->name ?? 'Main Store';
                $toStoreName   = $transfer->toStore->name ?? 'Destination Store';
                $itemsList     = $transfer->items->map(function($i) {
                    return ($i->product->name ?? 'Item') . ' (' . number_format($i->requested_quantity, 2) . ' ' . $i->unit . ')';
                })->implode(', ');

                $smsMessage = "ConstructPro: Transfer #{$transfer->transfer_no} has been assigned to you.\nPickup: {$fromStoreName}\nDelivery To: {$toStoreName}\nItems: {$itemsList}";
                $smsService = app(\App\Services\SmsEthiopiaService::class);
                $smsService->sendMessage($driver->phone, $smsMessage);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Transfer Driver SMS error: ' . $e->getMessage());
            }
        }

        return back()->with('success', 'Driver assigned successfully. Transfer is now ready for outgoing store keeper to dispatch.');
    }

    /**
     * Dispatch Transfer from Outgoing Store:
     * - Uploads physical outgoing slip / waybill
     * - Records sent quantities
     * - Deducts sent quantities from Origin Store Inventory
     * - Changes status to 'in_transit'
     */
    public function dispatchTransfer(Request $request, Transfer $transfer)
    {
        $request->validate([
            'outgoing_slip_no'   => 'required|string|max:100',
            'outgoing_slip_file' => 'nullable|file|mimes:jpeg,png,jpg,pdf,webp|max:10240',
            'vehicle_plate_no'   => 'nullable|string|max:100',
            'items'              => 'required|array',
            'items.*.sent_qty'   => 'required|numeric|min:0.001',
        ]);

        $outgoingSlipUrl = $transfer->outgoing_slip_file;
        if ($request->hasFile('outgoing_slip_file')) {
            try {
                $cloudinary = app(\App\Services\CloudinaryService::class);
                $outgoingSlipUrl = $cloudinary->upload($request->file('outgoing_slip_file'), 'transfer_slips');
            } catch (\Throwable $e) {
                $outgoingSlipUrl = $request->file('outgoing_slip_file')->store('transfer_slips', 'public');
            }
        }

        DB::transaction(function () use ($request, $transfer, $outgoingSlipUrl) {
            $transfer->load('items');

            foreach ($transfer->items as $item) {
                $sentQty = (float)($request->input("items.{$item->id}.sent_qty", $item->requested_quantity));
                $item->update(['sent_quantity' => $sentQty]);

                // Deduct from Origin Store Inventory
                $inv = Inventory::where('store_id', $transfer->from_store_id)
                    ->where('product_id', $item->product_id)
                    ->first();

                if ($inv) {
                    $inv->decrement('quantity_on_hand', $sentQty);

                    DB::table('inventory_movements')->insert([
                        'inventory_id' => $inv->id,
                        'type'         => 'transfer_out',
                        'quantity'     => -$sentQty,
                        'reference_id' => $transfer->id,
                        'notes'        => 'Transfer #' . $transfer->transfer_no . ' dispatched to driver (Slip: ' . $request->outgoing_slip_no . ')',
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }
            }

            $transfer->update([
                'outgoing_slip_no'   => $request->outgoing_slip_no,
                'physical_slip_no'   => $request->outgoing_slip_no,
                'outgoing_slip_file' => $outgoingSlipUrl,
                'vehicle_plate_no'   => $request->vehicle_plate_no ?? $transfer->vehicle_plate_no,
                'dispatched_by'      => Auth::id(),
                'dispatched_at'      => now(),
                'status'             => 'in_transit',
            ]);
        });

        return back()->with('success', 'Transfer dispatched successfully! Outgoing slip recorded and inventory deducted from origin store.');
    }

    /**
     * Receive Transfer at Destination Store:
     * - Verifies outgoing slip and inspects incoming quantities
     * - Records received quantities
     * - Uploads signed receiving slip (optional)
     * - Adds received quantities to Destination Store Inventory
     * - Changes status to 'completed'
     */
    public function receiveTransfer(Request $request, Transfer $transfer)
    {
        $request->validate([
            'receiving_slip_no'   => 'nullable|string|max:100',
            'receiving_slip_file' => 'nullable|file|mimes:jpeg,png,jpg,pdf,webp|max:10240',
            'receiving_notes'     => 'nullable|string|max:500',
            'items'               => 'required|array',
            'items.*.received_qty'=> 'required|numeric|min:0',
        ]);

        $receivingSlipUrl = $transfer->receiving_slip_file;
        if ($request->hasFile('receiving_slip_file')) {
            try {
                $cloudinary = app(\App\Services\CloudinaryService::class);
                $receivingSlipUrl = $cloudinary->upload($request->file('receiving_slip_file'), 'transfer_slips');
            } catch (\Throwable $e) {
                $receivingSlipUrl = $request->file('receiving_slip_file')->store('transfer_slips', 'public');
            }
        }

        DB::transaction(function () use ($request, $transfer, $receivingSlipUrl) {
            $transfer->load('items');

            foreach ($transfer->items as $item) {
                $receivedQty = (float)($request->input("items.{$item->id}.received_qty", $item->sent_quantity > 0 ? $item->sent_quantity : $item->requested_quantity));
                $item->update(['received_quantity' => $receivedQty]);

                if ($receivedQty > 0) {
                    // Add or update inventory in destination store
                    $inv = Inventory::firstOrCreate(
                        ['store_id' => $transfer->to_store_id, 'product_id' => $item->product_id],
                        ['quantity_on_hand' => 0, 'min_stock' => 5, 'unit_cost' => 0]
                    );
                    $inv->increment('quantity_on_hand', $receivedQty);

                    DB::table('inventory_movements')->insert([
                        'inventory_id' => $inv->id,
                        'type'         => 'transfer_in',
                        'quantity'     => $receivedQty,
                        'reference_id' => $transfer->id,
                        'notes'        => 'Transfer #' . $transfer->transfer_no . ' received from ' . ($transfer->fromStore->name ?? 'Source Store') . ' (Slip: ' . ($request->receiving_slip_no ?? $transfer->outgoing_slip_no ?? 'N/A') . ')',
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }
            }

            $transfer->update([
                'receiving_slip_no'   => $request->receiving_slip_no,
                'receiving_slip_file' => $receivingSlipUrl,
                'receiving_notes'     => $request->receiving_notes,
                'received_by'         => Auth::id(),
                'received_at'         => now(),
                'status'              => 'completed',
            ]);
        });

        return back()->with('success', 'Transfer confirmed and received! Materials have been added to destination store inventory.');
    }

    /**
     * Reject or Cancel Transfer
     */
    public function rejectTransfer(Request $request, Transfer $transfer)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $transfer->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->rejection_reason,
        ]);

        return back()->with('success', 'Transfer has been marked as rejected.');
    }

    /**
     * Update Physical Slip # for a Transfer
     */
    public function updatePhysicalSlip(Request $request, Transfer $transfer)
    {
        $request->validate([
            'physical_slip_no' => 'required|string|max:100',
        ]);

        $transfer->update([
            'physical_slip_no' => $request->physical_slip_no,
            'outgoing_slip_no' => $request->physical_slip_no,
        ]);

        return back()->with('success', 'Physical Slip #' . $request->physical_slip_no . ' saved successfully.');
    }


    /**
     * Material Requests from Site Engineers / Coordinator
     */
    public function materialRequests(Request $request)
    {
        $user = Auth::user();
        $isStoreKeeper = $user && $user->hasRole('store_keeper') && !$user->hasAnyRole(['admin', 'global_admin', 'store_manager', 'coordinator', 'planning_manager']);
        $assignedStore = null;

        $query = MaterialRequest::with(['project', 'requestedBy', 'items.product', 'maintenanceRequest']);

        if ($isStoreKeeper) {
            $assignedStore = $user->store
                ?? Store::where('manager_id', $user->id)->first()
                ?? Store::whereHas('users', fn($q) => $q->where('users.id', $user->id))->first();

            $assignedStoreIds = collect([$user->store_id])
                ->concat(Store::where('manager_id', $user->id)->pluck('id'))
                ->concat(Store::whereHas('users', fn($q) => $q->where('users.id', $user->id))->pluck('id'))
                ->filter()
                ->unique();

            $assignedProjectIds = Store::whereIn('id', $assignedStoreIds)->whereNotNull('project_id')->pluck('project_id')->unique();

            if ($assignedStoreIds->isNotEmpty() || $assignedProjectIds->isNotEmpty()) {
                $query->where(function ($q) use ($assignedStoreIds, $assignedProjectIds) {
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
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->latest()->paginate(20)->withQueryString();

        // Calculate on-hand stock for request items in this store
        $storeStock = [];
        if ($assignedStore) {
            $storeStock = Inventory::where('store_id', $assignedStore->id)->pluck('quantity_on_hand', 'product_id')->toArray();
        }

        return view('store-manager.material-requests.index', compact('requests', 'isStoreKeeper', 'assignedStore', 'storeStock'));
    }

    /**
     * Issue Material Request to Site Engineer
     */
    public function issueMaterialRequest(Request $request, MaterialRequest $materialRequest)
    {
        $user = Auth::user();
        $assignedStore = $user->store ?? Store::where('manager_id', $user->id)->first();
        $storeId = $materialRequest->destination_store_id ?? $assignedStore?->id;

        if (!$storeId) {
            return back()->with('error', 'No store assigned to issue materials from.');
        }

        DB::transaction(function () use ($materialRequest, $storeId) {
            $materialRequest->load('items.product');

            foreach ($materialRequest->items as $item) {
                $inv = Inventory::where('store_id', $storeId)
                    ->where('product_id', $item->product_id)
                    ->first();

                if ($inv) {
                    $inv->decrement('quantity_on_hand', $item->quantity);

                    DB::table('inventory_movements')->insert([
                        'inventory_id' => $inv->id,
                        'type'         => 'issue',
                        'quantity'     => -$item->quantity,
                        'reference_id' => $materialRequest->id,
                        'notes'        => 'Issued to Site Engineer for Material Request #' . ($materialRequest->reference_number ?? $materialRequest->id),
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }
            }

            $materialRequest->update([
                'status'      => 'issued',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);
        });

        return back()->with('success', 'Material successfully issued and handed over to the Site Engineer.');
    }

    /**
     * Weekly Material Demand Schedule (Sent by Planning team to Site)
     */
    public function weeklyMaterialDemand(Request $request)
    {
        $user = Auth::user();
        $assignedStore = $user->store ?? Store::where('manager_id', $user->id)->first();
        $storeId = $assignedStore?->id;
        $project = $assignedStore?->project;

        $weeklyDispatches = collect();

        if ($project) {
            $weeklyDispatches = \App\Models\WeeklyPlanDispatch::with(['tasks', 'dispatchedTo'])
                ->where('project_id', $project->id)
                ->latest()
                ->take(10)
                ->get();
        } else {
            $weeklyDispatches = \App\Models\WeeklyPlanDispatch::with(['tasks', 'project', 'dispatchedTo'])
                ->latest()
                ->take(10)
                ->get();
        }

        // Fetch products and correlate with store inventory
        $storeInventoryMap = $storeId
            ? Inventory::where('store_id', $storeId)->pluck('quantity_on_hand', 'product_id')->toArray()
            : [];

        // Build list of weekly material demands from active material plans or dispatches
        $materialPlans = \App\Models\MaterialPlan::with(['items.product', 'project'])
            ->when($project, fn($q) => $q->where('project_id', $project->id))
            ->latest()
            ->take(10)
            ->get();

        return view('store-keeper.weekly-material-demand', compact(
            'assignedStore', 'project', 'weeklyDispatches', 'materialPlans', 'storeInventoryMap'
        ));
    }

    /**
     * Process Material Request - Create transfer if available, or send to purchase
     */
    public function processMaterialRequest(MaterialRequest $materialRequest)
    {
        $materialRequest->load('items.product');

        $allAvailable = true;
        $unavailableItems = [];

        foreach ($materialRequest->items as $item) {
            $inventory = Inventory::where('product_id', $item->product_id)
                ->whereHas('store', fn($q) => $q->where('is_active', true))
                ->sum('quantity_on_hand');

            if ($inventory < $item->quantity) {
                $allAvailable = false;
                $unavailableItems[] = $item->product->name ?? 'Product #' . $item->product_id;
            }
        }

        if ($allAvailable) {
            // Create transfer
            DB::transaction(function () use ($materialRequest) {
                $no = 'TR-' . date('Ymd') . '-' . str_pad(Transfer::count() + 1, 4, '0', STR_PAD_LEFT);

                // Find source store with inventory
                $firstItem = $materialRequest->items->first();
                $sourceStore = Inventory::where('product_id', $firstItem->product_id)
                    ->where('quantity_on_hand', '>=', $firstItem->quantity)
                    ->first();

                $transfer = Transfer::create([
                    'transfer_no'   => $no,
                    'from_store_id' => $sourceStore->store_id ?? $materialRequest->requestedBy->store_id,
                    'to_store_id'   => $materialRequest->project->store_id ?? $materialRequest->requestedBy->store_id,
                    'requested_by'  => Auth::id(),
                    'required_date' => now(),
                    'reason'        => 'Material Request #' . $materialRequest->id,
                    'status'        => 'draft',
                ]);

                foreach ($materialRequest->items as $item) {
                    $transfer->items()->create([
                        'product_id'         => $item->product_id,
                        'requested_quantity' => $item->quantity,
                        'unit'               => $item->unit ?? 'pcs',
                    ]);
                }

                $materialRequest->update(['status' => 'processed']);
            });

            return back()->with('success', 'Transfer created successfully for the material request.');
        } else {
            // Send to Purchase Manager
            $materialRequest->update(['status' => 'needs_purchase']);
            
            return back()->with('warning', 'Materials not available (' . implode(', ', $unavailableItems) . '). Request sent to Purchase Manager.');
        }
    }

    public function productsIndex(Request $request)
    {
        return redirect()->route('products.index', $request->query());
    }

    /**
     * Create Product
     */
    public function createProduct()
    {
        return redirect()->route('products.create');
    }



    /**
     * Store Product
     */
    public function storeProduct(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'code'           => 'nullable|string|max:100',
            'category'       => 'nullable|string|max:100',
            'unit'           => 'required|string|max:20',
            'description'    => 'nullable|string',
            'specification'  => 'nullable|string',
            'min_stock_level'=> 'nullable|numeric|min:0',
            'standard_cost'  => 'nullable|numeric|min:0',
            'is_active'      => 'nullable|boolean',
        ]);

        $name = $validated['name'];
        $baseCode = !empty($validated['code']) 
            ? strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', $validated['code']))
            : strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 4));

        if (empty($baseCode)) {
            $baseCode = 'PRD';
        }

        $code = $baseCode;
        $counter = 1;
        while (Product::where('code', $code)->orWhere('sku', $code)->exists()) {
            $code = $baseCode . '-' . $counter;
            $counter++;
        }

        $validated['code'] = $code;
        $validated['sku']  = $code;
        $validated['unit_price'] = $validated['standard_cost'] ?? 0;
        $validated['is_active']  = $request->has('is_active') ? $request->boolean('is_active') : true;

        Product::create($validated);

        return redirect()->route('store-manager.products.index')->with('success', "Product \"{$name}\" (Code: {$code}) added to catalog.");
    }

    /**
     * List Slips with Unified View and Sequence Validation
     */
    public function slipsIndex(Request $request)
    {
        $query = DeliveryReceipt::with('store', 'items.product', 'createdBy');

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        if ($request->filled('slip_type')) {
            $query->where('slip_type', $request->slip_type);
        }

        if ($request->filled('status')) {
            if ($request->status === 'void') {
                $query->where('is_void', true);
            } else {
                $query->where('status', $request->status)->where('is_void', false);
            }
        }

        if ($request->filled('slip_search')) {
            $query->where('dr_no', 'like', '%' . $request->slip_search . '%');
        }

        // Add sequence validation status for each slip
        $slips = $query->latest('received_date')->paginate(20)->withQueryString();
        
        // Check sequence for each slip
        foreach ($slips as $slip) {
            $slip->sequence_status = $this->validateSlipSequence($slip);
        }

        // Calculate statistics
        $stats = [
            'receive_total' => DeliveryReceipt::where('slip_type', 'receive')->count(),
            'send_total'    => DeliveryReceipt::where('slip_type', 'send')->count(),
            'gaps'          => $this->countSequenceGaps(),
            'void'          => DeliveryReceipt::where('is_void', true)->count(),
        ];

        $stores = Store::where('is_active', true)->orderBy('name')->get();

        return view('store-manager.slips.index', compact('slips', 'stores', 'stats'));
    }

    /**
     * Validate Slip Sequence
     */
    private function validateSlipSequence(DeliveryReceipt $slip)
    {
        if ($slip->is_void) {
            return 'void';
        }

        $lastSlip = DeliveryReceipt::where('store_id', $slip->store_id)
            ->where('slip_type', $slip->slip_type)
            ->where('id', '<', $slip->id)
            ->where('is_void', false)
            ->latest('id')
            ->first();

        if (!$lastSlip) {
            return 'valid'; // First slip
        }

        // Extract sequence numbers
        $currentSeq = intval(substr($slip->dr_no, -4));
        $lastSeq = intval(substr($lastSlip->dr_no, -4));

        if ($currentSeq === $lastSeq + 1) {
            return 'valid';
        } else {
            return 'gap';
        }
    }

    /**
     * Count Sequence Gaps
     */
    private function countSequenceGaps()
    {
        $slips = DeliveryReceipt::where('is_void', false)
            ->orderBy('store_id')
            ->orderBy('slip_type')
            ->orderBy('dr_no')
            ->get();

        $gaps = 0;
        $lastByStoreType = [];

        foreach ($slips as $slip) {
            $key = $slip->store_id . '-' . $slip->slip_type;
            
            if (isset($lastByStoreType[$key])) {
                $lastSeq = intval(substr($lastByStoreType[$key], -4));
                $currentSeq = intval(substr($slip->dr_no, -4));
                if ($currentSeq !== $lastSeq + 1) {
                    $gaps++;
                }
            }
            
            $lastByStoreType[$key] = $slip->dr_no;
        }

        return $gaps;
    }

    /**
     * Mark Slip as Void
     */
    public function voidSlip(DeliveryReceipt $slip)
    {
        $slip->update([
            'is_void' => true,
            'status' => 'void',
        ]);

        return back()->with('success', 'Slip marked as void and flagged for audit.');
    }

    /**
     * Create Slip (Unified - Receive or Send)
     */
    public function createSlip()
    {
        $stores = Store::where('is_active', true)->orderBy('name')->get();
        $products = Product::where('is_active', true)->orderBy('name')->get();

        return view('store-manager.slips.create', compact('stores', 'products'));
    }

    /**
     * Store Slip (Unified - Receive or Send)
     */
    public function storeSlip(Request $request)
    {
        $request->validate([
            'slip_type'           => 'required|in:receive,send',
            'store_id'            => 'required|exists:stores,id',
            'slip_no'             => 'nullable|string|max:50',
            'slip_date'           => 'required|date',
            'supplier_name'       => 'nullable|string|max:255',
            'reference_no'        => 'nullable|string|max:100',
            'items'               => 'required|array|min:1',
            'items.*.product_id'  => 'required|exists:products,id',
            'items.*.quantity'    => 'required|numeric|min:0.001',
            'items.*.unit_cost'   => 'nullable|numeric|min:0',
        ]);

        if ($request->slip_type === 'send') {
            $request->validate([
                'to_store_id'    => 'required|exists:stores,id|different:store_id',
            ]);
        }

        DB::transaction(function () use ($request) {
            $slipType = $request->slip_type;
            $storeId = $request->store_id;
            
            // Get slip number from sequence or manual entry
            $slipNo = null;
            
            if (!empty($request->slip_no)) {
                // Manual slip number provided
                $slipNo = $request->slip_no;
            } else {
                // Auto-generate from slip sequence
                $sequence = SlipSequence::where('store_id', $storeId)
                    ->where('slip_type', $slipType)
                    ->where('status', 'active')
                    ->first();

                if (!$sequence) {
                    throw new \Exception("No active slip sequence configured for this store and type. Please configure a sequence first.");
                }

                // Generate next slip number
                $slipNo = $sequence->generateSlipNumber();
            }

            // Create dummy PO if not exists (required by table structure)
            $dummyPo = \App\Models\PurchaseOrder::firstOrCreate(
                ['supplier_id' => 1],
                ['po_no' => 'SYSTEM-' . time(), 'status' => 'delivered']
            );

            $receipt = DeliveryReceipt::create([
                'dr_no'          => $slipNo,
                'slip_type'      => $slipType,
                'store_id'       => $storeId,
                'to_store_id'    => $request->to_store_id ?? null,
                'received_date'  => $request->slip_date,
                'receipt_date'   => $request->slip_date,
                'supplier_name'  => $request->supplier_name,
                'reference_no'   => $request->reference_no,
                'purchase_order_id' => $dummyPo->id,
                'received_by'    => Auth::id(),
                'created_by'     => Auth::id(),
                'status'         => 'draft',
                'is_void'        => false,
                'sequence_status' => 'pending',
            ]);

            foreach ($request->items as $item) {
                $receipt->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity_received' => $item['quantity'],
                    'accepted_quantity' => $item['quantity'],
                    'unit' => $item['unit'] ?? 'pcs',
                ]);
            }

            // Only update inventory if it's a receive slip
            if ($slipType === 'receive') {
                foreach ($request->items as $item) {
                    $inventory = Inventory::firstOrCreate(
                        ['store_id' => $storeId, 'product_id' => $item['product_id']],
                        ['quantity_on_hand' => 0, 'unit_cost' => $item['unit_cost'] ?? 0]
                    );
                    $inventory->increment('quantity_on_hand', $item['quantity']);
                    if (!empty($item['unit_cost'])) {
                        $inventory->update(['unit_cost' => $item['unit_cost']]);
                    }
                }
            }
        });

        $type = $request->slip_type === 'receive' ? 'Receive' : 'Send';
        return redirect()->route('store-manager.slips.index')->with('success', "$type slip created successfully with auto-generated sequence number.");
    }

    /**
     * Issued Materials
     */
    public function issuedMaterials(Request $request)
    {
        $query = DeliveryReceipt::with('store', 'items.product')
            ->where('status', 'issued');

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        $issued = $query->latest()->paginate(20)->withQueryString();
        $stores = Store::where('is_active', true)->orderBy('name')->get();

        return view('store-manager.issued.index', compact('issued', 'stores'));
    }

    /**
     * Store Keeper Assignment Hub for Store Manager
     */
    public function assignStoreKeepersIndex(Request $request)
    {
        $search = $request->input('search');
        $type = $request->input('type');
        $projectId = $request->input('project_id');
        $assignmentStatus = $request->input('status'); // 'assigned', 'unassigned', 'all'

        $storesQuery = Store::with(['project', 'manager.roles', 'users.roles'])
            ->withCount(['inventory', 'users'])
            ->latest();

        if ($search) {
            $storesQuery->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhereHas('manager', function($mq) use ($search) {
                      $mq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($type) {
            $storesQuery->where('type', $type);
        }

        if ($projectId) {
            $storesQuery->where('project_id', $projectId);
        }

        if ($assignmentStatus === 'assigned') {
            $storesQuery->where(function($q) {
                $q->whereNotNull('manager_id')->orWhereHas('users');
            });
        } elseif ($assignmentStatus === 'unassigned') {
            $storesQuery->whereNull('manager_id')->whereDoesntHave('users');
        }

        $stores = $storesQuery->get();

        // Projects for filters & create modal
        $projects = Project::where('status', 'active')->orderBy('name')->get();

        // Candidates for Store Keepers (strictly users with store_keeper role)
        $storeKeepers = User::with(['store', 'roles'])
            ->where('is_active', true)
            ->whereHas('roles', function($rq) {
                $rq->whereIn('name', ['store_keeper', 'storekeeper', 'Store Keeper']);
            })
            ->orderBy('name')
            ->get();

        // Metrics
        $totalStoresCount = Store::count();
        $assignedStoresCount = Store::where(function($q) {
            $q->whereNotNull('manager_id')->orWhereHas('users');
        })->count();
        $unassignedStoresCount = $totalStoresCount - $assignedStoresCount;
        $totalStoreKeepersCount = $storeKeepers->count();

        return view('store-manager.store-keepers.index', compact(
            'stores',
            'projects',
            'storeKeepers',
            'totalStoresCount',
            'assignedStoresCount',
            'unassignedStoresCount',
            'totalStoreKeepersCount'
        ));
    }

    /**
     * Assign or reassign store keeper(s) to a store
     */
    public function updateStoreKeeperAssignment(Request $request)
    {
        $validated = $request->validate([
            'store_id'            => 'required|exists:stores,id',
            'primary_keeper_id'   => 'nullable|exists:users,id',
            'additional_keeper_ids' => 'nullable|array',
            'additional_keeper_ids.*' => 'exists:users,id',
            'assignment_notes'    => 'nullable|string|max:500',
        ]);

        $store = Store::findOrFail($validated['store_id']);
        $primaryKeeperId = $validated['primary_keeper_id'] ?? null;
        $additionalKeeperIds = $validated['additional_keeper_ids'] ?? [];

        // All selected keeper IDs for this store
        $allKeeperIds = collect([$primaryKeeperId])->concat($additionalKeeperIds)->filter()->unique()->values()->all();

        DB::transaction(function() use ($store, $primaryKeeperId, $allKeeperIds) {
            // 1. Update store's primary manager/keeper
            $store->update([
                'manager_id' => $primaryKeeperId,
            ]);

            // 2. Clear store_id for users previously assigned to this store who are not in new list
            User::where('store_id', $store->id)
                ->whereNotIn('id', $allKeeperIds)
                ->update(['store_id' => null]);

            // 3. Assign store_id to all selected users and ensure role
            if (!empty($allKeeperIds)) {
                User::whereIn('id', $allKeeperIds)->update(['store_id' => $store->id]);

                // Ensure they have store_keeper role
                $users = User::whereIn('id', $allKeeperIds)->get();
                foreach ($users as $user) {
                    if (!$user->hasRole('store_keeper')) {
                        try {
                            $user->assignRole('store_keeper');
                        } catch (\Throwable $e) {}
                    }
                }
            }

            // Log activity
            \App\Models\ActivityLog::log(
                'store_keeper_assigned',
                "Store Keepers updated for [{$store->code}] {$store->name}. Primary Keeper: " . ($store->fresh()->manager->name ?? 'Unassigned'),
                'Store & Warehouse Management',
                $store,
                [
                    'store_id' => $store->id,
                    'store_code' => $store->code,
                    'primary_keeper_id' => $primaryKeeperId,
                    'total_assigned_keepers' => count($allKeeperIds),
                ]
            );
        });

        return redirect()->back()->with('success', "Store Keepers assigned to [{$store->code}] {$store->name} successfully!");
    }

    /**
     * Unassign a store keeper from a store
     */
    public function unassignStoreKeeper(Request $request, Store $store, User $user)
    {
        DB::transaction(function() use ($store, $user) {
            if ($store->manager_id == $user->id) {
                $store->update(['manager_id' => null]);
            }
            if ($user->store_id == $store->id) {
                $user->update(['store_id' => null]);
            }

            \App\Models\ActivityLog::log(
                'store_keeper_unassigned',
                "Store Keeper {$user->name} unassigned from [{$store->code}] {$store->name}",
                'Store & Warehouse Management',
                $store
            );
        });

        return redirect()->back()->with('success', "Store Keeper {$user->name} unassigned from {$store->name}.");
    }

    /**
     * Quick Create Store & assign keeper
     */
    public function quickCreateStore(Request $request)
    {
        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'code'                => 'required|string|max:50|unique:stores,code',
            'type'                => 'required|in:site,warehouse,yard',
            'project_id'          => 'nullable|exists:projects,id',
            'address'             => 'nullable|string|max:255',
            'primary_keeper_id'   => 'nullable|exists:users,id',
            'notes'               => 'nullable|string|max:500',
        ]);

        $primaryKeeperId = $validated['primary_keeper_id'] ?? null;

        $store = Store::create([
            'name'       => $validated['name'],
            'code'       => strtoupper(trim($validated['code'])),
            'type'       => $validated['type'],
            'project_id' => $validated['project_id'] ?? null,
            'address'    => $validated['address'] ?? null,
            'manager_id' => $primaryKeeperId,
            'notes'      => $validated['notes'] ?? null,
            'is_active'  => true,
        ]);

        if ($primaryKeeperId) {
            $user = User::find($primaryKeeperId);
            if ($user) {
                $user->update(['store_id' => $store->id]);
                if (!$user->hasRole('store_keeper')) {
                    try {
                        $user->assignRole('store_keeper');
                    } catch (\Throwable $e) {}
                }
            }
        }

        return redirect()->back()->with('success', "New Store [{$store->code}] {$store->name} created successfully!");
    }

    /**
     * Quick update store details
     */
    public function quickUpdateStore(Request $request, Store $store)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'code'       => 'required|string|max:50|unique:stores,code,' . $store->id,
            'type'       => 'required|in:site,warehouse,yard',
            'project_id' => 'nullable|exists:projects,id',
            'address'    => 'nullable|string|max:255',
            'notes'      => 'nullable|string|max:500',
            'is_active'  => 'nullable|boolean',
        ]);

        $store->update([
            'name'       => $validated['name'],
            'code'       => strtoupper(trim($validated['code'])),
            'type'       => $validated['type'],
            'project_id' => $validated['project_id'] ?? null,
            'address'    => $validated['address'] ?? null,
            'notes'      => $validated['notes'] ?? null,
            'is_active'  => $request->has('is_active') ? (bool)$request->is_active : true,
        ]);

        return redirect()->back()->with('success', "Store [{$store->code}] {$store->name} updated successfully!");
    }

    /**
     * Helper method for safe execution
     */
    private function safe(callable $fn, $default = null)
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
