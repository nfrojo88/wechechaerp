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

        $query = Inventory::with('store', 'product');

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }
        if ($request->filled('low_stock')) {
            $query->whereColumn('quantity_on_hand', '<=', 'min_stock');
        }

        /** @var \Illuminate\Pagination\LengthAwarePaginator $inventory */
        $inventory = $query->paginate(20);
        $inventory->withQueryString();
        $stores    = Store::where('is_active', true)->orderBy('name')->get();
        $products  = Product::where('is_active', true)->orderBy('name')->get();

        return view('inventory.index', compact('inventory', 'stores', 'products'));
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
            'type'     => ['required', 'in:in,out,adjustment'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'unit_cost'=> ['nullable', 'numeric', 'min:0'],
            'remarks'  => ['nullable', 'string', 'max:500'],
        ]);

        try {
            if ($validated['type'] === 'in' || $validated['type'] === 'adjustment') {
                $this->inventoryService->stockIn(
                    $inventory->store_id,
                    $inventory->product_id,
                    $validated['quantity'],
                    $validated['unit_cost'] ?? $inventory->unit_cost ?? 0,
                    'adjustment',
                    auth()->id(),
                    null, null,
                    $validated['remarks'] ?? null
                );
            } else {
                $this->inventoryService->stockOut(
                    $inventory->store_id,
                    $inventory->product_id,
                    $validated['quantity'],
                    'adjustment',
                    auth()->id(),
                    null, null,
                    $validated['remarks'] ?? null
                );
            }

            return redirect()->back()->with('success', 'Inventory adjusted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['quantity' => $e->getMessage()]);
        }
    }

    /**
     * Show the bulk manual stock-level adjustment form.
     */
    public function showBulkAdjust(Request $request)
    {
        $user = auth()->user();
        $isStoreKeeper = $user && $user->hasRole('store_keeper');
        $assignedStore = null;

        if ($isStoreKeeper) {
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
        if (!$user->can('inventory.edit') && !$user->hasAnyRole(['admin', 'global_admin', 'store_manager', 'store_keeper'])) {
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

        if ($user->hasRole('store_keeper') && !$user->hasAnyRole(['admin', 'global_admin', 'store_manager'])) {
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

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $storeId, &$count, &$results) {
            foreach ($request->items as $row) {
                $productId = $row['product_id'];
                $newQty    = (float) $row['quantity'];
                $unitCost  = isset($row['unit_cost']) && $row['unit_cost'] !== '' ? (float) $row['unit_cost'] : null;

                $inv = Inventory::firstOrCreate(
                    ['store_id' => $storeId, 'product_id' => $productId],
                    ['quantity_on_hand' => 0, 'quantity_reserved' => 0, 'unit_cost' => 0, 'min_stock' => 0]
                );

                $currentQty = (float) $inv->quantity_on_hand;
                $diff       = $newQty - $currentQty;

                if (abs($diff) < 0.001) {
                    $results[] = ['product_id' => $productId, 'status' => 'skipped'];
                    continue;
                }

                \App\Models\InventoryMovement::create([
                    'inventory_id'   => $inv->id,
                    'type'           => 'adjustment',
                    'quantity'       => $diff,
                    'reference_type' => 'manual_bulk',
                    'reference_id'   => null,
                    'performed_by'   => auth()->id(),
                    'remarks'        => 'Manual stock count adjustment — set to ' . $newQty,
                ]);

                $inv->quantity_on_hand = $newQty;
                $inv->last_movement_at = now();
                if ($unitCost !== null) {
                    $inv->unit_cost = $unitCost;
                }
                $inv->save();

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
        if (!$user->can('inventory.edit') && !$user->hasAnyRole(['admin', 'global_admin', 'store_manager', 'store_keeper'])) {
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
        if ($user->hasRole('store_keeper') && !$user->hasAnyRole(['admin', 'global_admin', 'store_manager'])) {
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
            $result = \Illuminate\Support\Facades\DB::transaction(function () use ($storeId, $productId, $qtyInput, $mode, $unitCost, $remarks) {
                $inv = Inventory::with(['store', 'product'])->firstOrCreate(
                    ['store_id' => $storeId, 'product_id' => $productId],
                    ['quantity_on_hand' => 0, 'quantity_reserved' => 0, 'unit_cost' => 0, 'min_stock' => 0]
                );

                $oldQty = (float) $inv->quantity_on_hand;

                if ($mode === 'add') {
                    $diff   = $qtyInput;
                    $newQty = $oldQty + $diff;
                    $autoNote = "Manual stock addition (+{$qtyInput})";
                } elseif ($mode === 'deduct') {
                    if ($qtyInput > $oldQty) {
                        throw new \Exception("Cannot deduct {$qtyInput}. Only {$oldQty} currently on hand in this store.");
                    }
                    $diff   = -$qtyInput;
                    $newQty = max(0, $oldQty - $qtyInput);
                    $autoNote = "Manual stock deduction (-{$qtyInput})";
                } else { // 'set'
                    $newQty = $qtyInput;
                    $diff   = $newQty - $oldQty;
                    $autoNote = "Manual stock count adjustment — set to {$newQty}";
                }

                $finalRemarks = $remarks !== '' ? "{$autoNote}: {$remarks}" : $autoNote;

                // Record movement in ledger
                \App\Models\InventoryMovement::create([
                    'inventory_id'   => $inv->id,
                    'type'           => 'adjustment',
                    'quantity'       => $diff,
                    'reference_type' => 'manual_adjustment',
                    'reference_id'   => null,
                    'performed_by'   => auth()->id(),
                    'remarks'        => $finalRemarks,
                ]);

                $inv->quantity_on_hand = $newQty;
                $inv->last_movement_at = now();
                if ($unitCost !== null) {
                    $inv->unit_cost = $unitCost;
                }
                $inv->save();

                return [
                    'inv'          => $inv,
                    'product_name' => $inv->product?->name ?? 'Product',
                    'store_name'   => $inv->store?->name ?? 'Store',
                    'unit'         => $inv->product?->unit ?? 'pcs',
                    'old_qty'      => $oldQty,
                    'new_qty'      => $newQty,
                    'diff'         => $diff,
                    'unit_cost'    => $inv->unit_cost,
                ];
            });

            $formattedDiff = ($result['diff'] >= 0 ? '+' : '') . number_format($result['diff'], 3) . ' ' . $result['unit'];
            $successMsg = "Manual adjustment complete: {$result['product_name']} in {$result['store_name']} updated ({$formattedDiff}, new on-hand: " . number_format($result['new_qty'], 3) . " {$result['unit']}).";

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success'      => true,
                    'message'      => $successMsg,
                    'product_id'   => $productId,
                    'product_name' => $result['product_name'],
                    'store_id'     => $storeId,
                    'store_name'   => $result['store_name'],
                    'old_qty'      => $result['old_qty'],
                    'new_qty'      => $result['new_qty'],
                    'diff'         => $result['diff'],
                    'unit_cost'    => $result['unit_cost'],
                    'unit'         => $result['unit'],
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
