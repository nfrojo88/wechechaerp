<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Store;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventoryService)
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Inventory::class);

        $user = auth()->user();
        $rawUserRoles = $user ? $user->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray() : [];
        $isGlobalAdmin = $user && ($user->isGlobalAdmin() || in_array('global_admin', $rawUserRoles) || in_array('admin', $rawUserRoles));
        $isSiteEngineer = in_array('site_engineer', $rawUserRoles) && !$isGlobalAdmin;

        $query = Inventory::with(['store', 'product'])
            ->join('products', 'inventory.product_id', '=', 'products.id')
            ->whereNull('products.deleted_at')
            ->select('inventory.*');

        $assignedStores = collect();
        if ($isSiteEngineer) {
            $assignedStoreIds = $user->getAssignedStoreIds();
            $query->whereIn('inventory.store_id', $assignedStoreIds);
            $stores = Store::whereIn('id', $assignedStoreIds)->where('is_active', true)->orderBy('name')->get();
            $assignedStores = $stores;

            if ($request->filled('store_id')) {
                if ($assignedStoreIds->contains((int)$request->store_id)) {
                    $query->where('inventory.store_id', $request->store_id);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
        } else {
            $stores = Store::where('is_active', true)->orderBy('name')->get();
            if ($request->filled('store_id')) {
                $query->where('inventory.store_id', $request->store_id);
            }
        }

        if ($request->filled('product_id')) {
            $query->where('inventory.product_id', $request->product_id);
        }
        if ($request->filled('low_stock')) {
            $query->whereColumn('inventory.quantity_on_hand', '<=', 'inventory.min_stock');
        }
        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('products.name', 'like', "%{$s}%")
                  ->orWhere('products.sku', 'like', "%{$s}%")
                  ->orWhere('products.code', 'like', "%{$s}%")
                  ->orWhere('products.category', 'like', "%{$s}%");
            });
        }

        // Alphabetical sorting by product name (A-Z) by default
        $sort = $request->query('sort', 'name');
        $direction = strtolower($request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        if ($sort === 'sku') {
            $query->orderBy('products.sku', $direction);
        } elseif ($sort === 'category') {
            $query->orderBy('products.category', $direction);
        } elseif ($sort === 'quantity') {
            $query->orderBy('inventory.quantity_on_hand', $direction);
        } else {
            $query->orderBy('products.name', $direction);
        }

        /** @var \Illuminate\Pagination\LengthAwarePaginator $inventory */
        $inventory = $query->paginate(25);
        $inventory->withQueryString();
        $products  = Product::where('is_active', true)->orderBy('name')->get();

        return view('inventory.index', compact('inventory', 'stores', 'products', 'sort', 'direction', 'isSiteEngineer', 'assignedStores'));
    }

    /**
     * View all stock movements across products (filtered to assigned site store for Site Engineer).
     */
    public function allMovements(Request $request)
    {
        $this->authorize('viewAny', Inventory::class);

        $user = auth()->user();
        $rawUserRoles = $user ? $user->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray() : [];
        $isGlobalAdmin = $user && ($user->isGlobalAdmin() || in_array('global_admin', $rawUserRoles) || in_array('admin', $rawUserRoles));
        $isSiteEngineer = in_array('site_engineer', $rawUserRoles) && !$isGlobalAdmin;

        $query = \App\Models\InventoryMovement::with(['inventory.store', 'inventory.product', 'performer'])
            ->latest('id');

        $assignedStores = collect();
        if ($isSiteEngineer) {
            $assignedStoreIds = $user->getAssignedStoreIds();
            $query->whereHas('inventory', fn($iq) => $iq->whereIn('store_id', $assignedStoreIds));
            $stores = Store::whereIn('id', $assignedStoreIds)->where('is_active', true)->orderBy('name')->get();
            $assignedStores = $stores;

            if ($request->filled('store_id')) {
                if ($assignedStoreIds->contains((int)$request->store_id)) {
                    $query->whereHas('inventory', fn($iq) => $iq->where('store_id', $request->store_id));
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
        } else {
            $stores = Store::where('is_active', true)->orderBy('name')->get();
            if ($request->filled('store_id')) {
                $query->whereHas('inventory', fn($iq) => $iq->where('store_id', $request->store_id));
            }
        }

        if ($request->filled('product_id')) {
            $query->whereHas('inventory', fn($iq) => $iq->where('product_id', $request->product_id));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->whereHas('inventory.product', function ($pq) use ($s) {
                    $pq->where('name', 'like', "%{$s}%")
                       ->orWhere('sku', 'like', "%{$s}%")
                       ->orWhere('code', 'like', "%{$s}%");
                })->orWhere('remarks', 'like', "%{$s}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $movements = $query->paginate(25)->withQueryString();
        $products = Product::where('is_active', true)->orderBy('name')->get();

        return view('inventory.all-movements', compact('movements', 'stores', 'products', 'isSiteEngineer', 'assignedStores'));
    }

    public function show(Inventory $inventory)
    {
        $this->authorize('view', $inventory);
        $inventory->load('store', 'product', 'movements.performer');
        return view('inventory.show', compact('inventory'));
    }

    public function adjust(Request $request, Inventory $inventory)
    {
        $this->authorize('update', $inventory);

        $validated = $request->validate([
            'type'      => ['required', 'in:in,out,adjustment'],
            'quantity'  => ['required', 'numeric', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'remarks'   => ['nullable', 'string', 'max:500'],
        ]);

        $user = auth()->user();
        if (!$user->isGlobalAdmin() && !$user->hasAnyRole(['admin', 'global_admin', 'super_admin', 'store_manager', 'Store Manager']) && $user->hasAnyRole(['store_keeper', 'Store Keeper'])) {
            $assignedStoreId = $user->store_id ?? $user->store?->id ?? Store::where('manager_id', $user->id)->value('id');
            if ($assignedStoreId && (int)$assignedStoreId !== (int)$inventory->store_id) {
                return redirect()->back()->withInput()->withErrors(['quantity' => 'You are only authorized to adjust stock for your assigned store.']);
            }
        }

        try {
            $qtyInput = (float) $validated['quantity'];
            $type = $validated['type'];

            if ($type === 'in') {
                $delta = $qtyInput;
                $remarks = $validated['remarks'] ?? "Manual stock addition (+{$qtyInput})";
            } elseif ($type === 'out') {
                $delta = -$qtyInput;
                $remarks = $validated['remarks'] ?? "Manual stock deduction (-{$qtyInput})";
            } else { // 'adjustment' (Set to target quantity)
                $currentQty = (float) $inventory->quantity_on_hand;
                $delta = $qtyInput - $currentQty;
                $remarks = $validated['remarks'] ?? "Stock count adjustment — set to {$qtyInput}";
            }

            $this->inventoryService->applyMovement(
                $inventory->store_id,
                $inventory->product_id,
                $delta,
                'adjustment',
                referenceType: 'manual_adjustment',
                remarks: $remarks,
                performedBy: auth()->id(),
                unitCost: isset($validated['unit_cost']) && $validated['unit_cost'] !== '' ? (float) $validated['unit_cost'] : null
            );

            return redirect()->back()->with('success', 'Inventory adjusted successfully.');
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->withErrors(['quantity' => $e->getMessage()]);
        }
    }

    /**
     * Show the bulk manual stock-level adjustment form.
     */
    public function showBulkAdjust(Request $request)
    {
        $user = auth()->user();
        $isStoreKeeperOnly = $user && $user->hasAnyRole(['store_keeper', 'Store Keeper']) && !$user->isGlobalAdmin() && !$user->hasAnyRole(['admin', 'global_admin', 'super_admin', 'store_manager', 'Store Manager']);
        $assignedStore = null;

        if ($isStoreKeeperOnly) {
            $assignedStore = $user->store ?? Store::where('manager_id', $user->id)->first();
            $stores = $assignedStore ? collect([$assignedStore]) : Store::where('is_active', true)->orderBy('name')->get();
            $storeId = $assignedStore ? $assignedStore->id : ($request->store_id ?? ($stores->first()->id ?? null));
        } else {
            $stores   = Store::where('is_active', true)->orderBy('name')->get();
            $storeId  = $request->store_id ?? ($stores->first()->id ?? null);
        }

        $products = Product::where('is_active', true)->orderBy('name')->get();

        // Load existing inventory for the chosen store (keyed by product_id)
        $existingStock = Inventory::where('store_id', $storeId)
            ->get()
            ->keyBy('product_id');

        $selectedStore = $storeId ? Store::find($storeId) : $stores->first();

        return view('inventory.bulk-adjust', compact('stores', 'products', 'existingStock', 'selectedStore', 'storeId'));
    }

    /**
     * Process bulk manual stock-level adjustment (set absolute levels).
     * Supports AJAX (returns JSON) and regular form POST (redirects).
     */
    public function bulkAdjust(Request $request)
    {
        $user = auth()->user();
        $isAuthorized = $user && (
            $user->isGlobalAdmin()
            || $user->can('inventory.edit')
            || $user->hasAnyRole(['admin', 'global_admin', 'super_admin', 'store_manager', 'store_keeper', 'Store Manager', 'Store Keeper'])
        );
        if (!$isAuthorized) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized to adjust inventory.'], 403);
            }
            abort(403, 'Unauthorized to adjust inventory.');
        }

        $request->validate([
            'store_id'              => ['required', 'exists:stores,id'],
            'items'                 => ['required', 'array'],
            'items.*.product_id'    => ['required', 'exists:products,id'],
            'items.*.quantity'      => ['required', 'numeric', 'min:0'],
            'items.*.unit_cost'     => ['nullable', 'numeric', 'min:0'],
        ]);

        $storeId = (int) $request->store_id;

        if (!$user->isGlobalAdmin() && !$user->hasAnyRole(['admin', 'global_admin', 'super_admin', 'store_manager', 'Store Manager']) && $user->hasAnyRole(['store_keeper', 'Store Keeper'])) {
            $assignedStoreId = $user->store_id ?? $user->store?->id ?? Store::where('manager_id', $user->id)->value('id');
            if ($assignedStoreId && (int)$assignedStoreId !== $storeId) {
                $msg = 'You are only authorized to adjust stock for your assigned store.';
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json(['success' => false, 'message' => $msg], 403);
                }
                return redirect()->back()->withInput()->withErrors(['store_id' => $msg]);
            }
        }

        $count   = 0;
        $results = [];

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($request, $storeId, &$count, &$results) {
                foreach ($request->items as $row) {
                    $productId = (int) $row['product_id'];
                    $newQty    = (float) $row['quantity'];
                    $unitCost  = isset($row['unit_cost']) && $row['unit_cost'] !== '' ? (float) $row['unit_cost'] : null;

                    $inv = Inventory::where('store_id', $storeId)->where('product_id', $productId)->first();
                    $currentQty = $inv ? (float) $inv->quantity_on_hand : 0.0;
                    $diff       = $newQty - $currentQty;

                    if (abs($diff) < 0.001) {
                        $results[] = ['product_id' => $productId, 'status' => 'skipped'];
                        continue;
                    }

                    $this->inventoryService->applyMovement(
                        $storeId,
                        $productId,
                        $diff,
                        'adjustment',
                        referenceType: 'manual_bulk',
                        remarks: 'Manual stock count adjustment — set to ' . $newQty,
                        performedBy: auth()->id(),
                        unitCost: $unitCost
                    );

                    $results[] = ['product_id' => $productId, 'status' => 'saved', 'new_qty' => $newQty];
                    $count++;
                }
            });

            // AJAX request → return JSON
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'count'   => $count,
                    'results' => $results,
                    'message' => $count > 0
                        ? "$count item(s) updated."
                        : 'No changes detected.',
                ]);
            }

            // Regular form POST → redirect
            return redirect()->back()
                ->with('success', "Manual adjustment complete — $count product(s) updated.");
        } catch (\Throwable $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return redirect()->back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Get live stock info for a product at a given store (AJAX).
     */
    public function getStock(Request $request)
    {
        $storeId   = $request->query('store_id');
        $productId = $request->query('product_id');

        if (!$storeId || !$productId) {
            return response()->json(['success' => false, 'message' => 'Missing store or product ID.'], 400);
        }

        $inv = Inventory::where('store_id', $storeId)
            ->where('product_id', $productId)
            ->first();

        $product = Product::find($productId);
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        $onHand    = (float) ($inv->quantity_on_hand ?? 0);
        $reserved  = (float) ($inv->quantity_reserved ?? 0);
        $available = max(0, $onHand - $reserved);
        $unitCost  = (float) ($inv->unit_cost ?? $product->unit_price ?? 0);

        return response()->json([
            'success'      => true,
            'store_id'     => (int) $storeId,
            'product_id'   => (int) $productId,
            'product_name' => $product->name,
            'product_code' => $product->code,
            'unit'         => $product->unit ?? 'pcs',
            'on_hand'      => $onHand,
            'reserved'     => $reserved,
            'available'    => $available,
            'unit_cost'    => $unitCost,
        ]);
    }

    /**
     * Save a single product's stock adjustment.
     * Supports both AJAX (returns JSON) and standard form POST (redirects with flash message).
     */
    public function saveSingle(Request $request)
    {
        $user = auth()->user();
        $isAuthorized = $user && (
            $user->isGlobalAdmin()
            || $user->can('inventory.edit')
            || $user->hasAnyRole(['admin', 'global_admin', 'super_admin', 'store_manager', 'store_keeper', 'Store Manager', 'Store Keeper'])
        );
        if (!$isAuthorized) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized to adjust inventory.'], 403);
            }
            abort(403, 'Unauthorized to adjust inventory.');
        }

        $validated = $request->validate([
            'store_id'   => ['required', 'integer', 'exists:stores,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity'   => ['required', 'numeric', 'min:0'],
            'unit_cost'  => ['nullable', 'numeric', 'min:0'],
            'mode'       => ['nullable', 'string', 'in:set,add,deduct'],
            'remarks'    => ['nullable', 'string', 'max:500'],
        ]);

        $storeId   = (int) $validated['store_id'];
        $productId = (int) $validated['product_id'];
        $qtyInput  = (float) $validated['quantity'];
        $mode      = $validated['mode'] ?? 'set';
        $unitCost  = isset($validated['unit_cost']) && $validated['unit_cost'] !== '' ? (float) $validated['unit_cost'] : null;
        $remarks   = trim($validated['remarks'] ?? '');

        // Store keeper constraint check
        if (!$user->isGlobalAdmin() && !$user->hasAnyRole(['admin', 'global_admin', 'super_admin', 'store_manager', 'Store Manager']) && $user->hasAnyRole(['store_keeper', 'Store Keeper'])) {
            $assignedStoreId = $user->store_id ?? $user->store?->id ?? Store::where('manager_id', $user->id)->value('id');
            if ($assignedStoreId && (int)$assignedStoreId !== $storeId) {
                $msg = 'You are only authorized to adjust stock for your assigned store.';
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json(['success' => false, 'message' => $msg], 403);
                }
                return redirect()->back()->withInput()->withErrors(['store_id' => $msg]);
            }
        }

        try {
            $inv = Inventory::where('store_id', $storeId)->where('product_id', $productId)->first();
            $oldQty = $inv ? (float) $inv->quantity_on_hand : 0.0;

            if ($mode === 'add') {
                $diff   = $qtyInput;
                $autoNote = "Manual stock addition (+{$qtyInput})";
            } elseif ($mode === 'deduct') {
                $diff   = -$qtyInput;
                $autoNote = "Manual stock deduction (-{$qtyInput})";
            } else { // 'set'
                $diff   = $qtyInput - $oldQty;
                $autoNote = "Manual stock count adjustment — set to {$qtyInput}";
            }

            $finalRemarks = $remarks !== '' ? "{$autoNote}: {$remarks}" : $autoNote;

            $applyRes = $this->inventoryService->applyMovement(
                $storeId,
                $productId,
                $diff,
                'adjustment',
                referenceType: 'manual_adjustment',
                remarks: $finalRemarks,
                performedBy: auth()->id(),
                unitCost: $unitCost
            );

            $inv = $applyRes['inventory']->load(['store', 'product']);
            $productName = $inv->product?->name ?? 'Product';
            $storeName = $inv->store?->name ?? 'Store';
            $unit = $inv->product?->unit ?? 'pcs';
            $newQty = (float) $inv->quantity_on_hand;

            $formattedDiff = ($diff >= 0 ? '+' : '') . number_format($diff, 3) . ' ' . $unit;
            $successMsg = "Manual adjustment complete: {$productName} in {$storeName} updated ({$formattedDiff}, new on-hand: " . number_format($newQty, 3) . " {$unit}).";

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success'      => true,
                    'message'      => $successMsg,
                    'product_id'   => $productId,
                    'product_name' => $productName,
                    'store_id'     => $storeId,
                    'store_name'   => $storeName,
                    'old_qty'      => $oldQty,
                    'new_qty'      => $newQty,
                    'diff'         => $diff,
                    'unit_cost'    => $inv->unit_cost,
                    'unit'         => $unit,
                ]);
            }

            return redirect()->back()->with('success', $successMsg);

        } catch (\Throwable $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function movements(Inventory $inventory)
    {
        $this->authorize('view', $inventory);
        $movements = $inventory->movements()
            ->with('performer')
            ->latest()
            ->paginate(20);
        return view('inventory.movements', compact('inventory', 'movements'));
    }
}
