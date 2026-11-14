<?php

namespace App\Http\Controllers;

use App\Models\FixedAsset;
use App\Models\FixedAssetUnit;
use App\Models\FixedAssetAssignment;
use App\Models\Employee;
use App\Models\Store;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class FixedAssetController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of Fixed Assets & Unit records.
     */
    public function index(Request $request)
    {
        $search   = $request->input('search');
        $category = $request->input('category');
        $status   = $request->input('status'); // in_store, assigned, maintenance, disposed
        $storeId  = $request->input('store_id');
        $tab      = $request->input('tab', 'all');

        // Auto-sync from Store Inventory & Catalog (two-way automatic synchronization)
        try {
            FixedAsset::syncFromInventory(null, $search);

            $faProductCount = Product::where('category', 'Fixed Asset')->count();
            $faCount = FixedAsset::count();
            if ($faProductCount < $faCount) {
                FixedAsset::with(['store', 'units'])->chunk(50, function($assets) {
                    foreach ($assets as $asset) {
                        $asset->syncWithCatalogAndInventory();
                    }
                });
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("FixedAssetController auto-sync failed: " . $e->getMessage());
        }

        // Fast Single-Query KPI Aggregation (Store-scoped when a store filter is active)
        $unitStatsQuery = DB::table('fixed_asset_units')
            ->join('fixed_assets', 'fixed_assets.id', '=', 'fixed_asset_units.fixed_asset_id')
            ->whereNull('fixed_asset_units.deleted_at')
            ->whereNull('fixed_assets.deleted_at');

        $assetCountQuery = FixedAsset::query();

        if ($storeId) {
            $unitStatsQuery->where('fixed_assets.store_id', $storeId);
            $assetCountQuery->where('store_id', $storeId);
        }

        if ($category) {
            $unitStatsQuery->where('fixed_assets.category', $category);
            $assetCountQuery->where('category', $category);
        }

        $unitStats = $unitStatsQuery->selectRaw("
            COUNT(fixed_asset_units.id) as total_units,
            SUM(CASE WHEN fixed_asset_units.status = 'in_store' THEN 1 ELSE 0 END) as in_store_units,
            SUM(CASE WHEN fixed_asset_units.status = 'assigned' THEN 1 ELSE 0 END) as assigned_units,
            SUM(CASE WHEN fixed_asset_units.status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_units,
            SUM(CASE WHEN fixed_asset_units.status = 'disposed' THEN 1 ELSE 0 END) as disposed_units,
            SUM(fixed_asset_units.purchase_price) as total_valuation
        ")->first();

        $kpi = [
            'total_assets'      => $assetCountQuery->count(),
            'total_units'       => (int) ($unitStats->total_units ?? 0),
            'in_store_units'    => (int) ($unitStats->in_store_units ?? 0),
            'assigned_units'    => (int) ($unitStats->assigned_units ?? 0),
            'maintenance_units' => (int) ($unitStats->maintenance_units ?? 0),
            'disposed_units'    => (int) ($unitStats->disposed_units ?? 0),
            'total_valuation'   => (float) ($unitStats->total_valuation ?? 0),
        ];

        $effectiveStatus = $status ?: ($tab !== 'all' ? $tab : null);

        // Query for parent assets with eager-loaded units matching selected status
        $query = FixedAsset::with(['store', 'units' => function($q) use ($effectiveStatus, $search) {
            if ($effectiveStatus) {
                $q->where('status', $effectiveStatus);
            }
            if ($search) {
                $q->where(function($sq) use ($search) {
                    $sq->where('unit_code', 'like', "%{$search}%")
                       ->orWhere('serial_number', 'like', "%{$search}%")
                       ->orWhere('plate_number', 'like', "%{$search}%")
                       ->orWhere('brand', 'like', "%{$search}%")
                       ->orWhere('model', 'like', "%{$search}%");
                });
            }
            $q->with('assignedEmployee:id,full_name,department,employee_code')->orderBy('sequence_number');
        }]);

        if ($category) {
            $query->where('category', $category);
        }

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code_prefix', 'like', "%{$search}%")
                  ->orWhere('supplier', 'like', "%{$search}%")
                  ->orWhereHas('units', function($uq) use ($search) {
                      $uq->where('unit_code', 'like', "%{$search}%")
                         ->orWhere('serial_number', 'like', "%{$search}%")
                         ->orWhere('plate_number', 'like', "%{$search}%")
                         ->orWhere('brand', 'like', "%{$search}%")
                         ->orWhere('model', 'like', "%{$search}%");
                  });
            });
        }

        if ($effectiveStatus) {
            $query->whereHas('units', fn($q) => $q->where('status', $effectiveStatus));
        }

        $fixedAssets = $query->latest()->paginate(12)->withQueryString();
        $stores = Store::where('is_active', true)->orderBy('name')->get();
        $categories = ['Computer & IT', 'Vehicle', 'Heavy Machinery', 'Furniture', 'Tools & Equipment', 'Electronics', 'Other'];
        $employees = Employee::where('status', 'active')->select('id', 'full_name', 'department', 'employee_code')->orderBy('full_name')->get();

        return view('store-manager.fixed-assets.index', compact(
            'fixedAssets',
            'kpi',
            'stores',
            'categories',
            'employees',
            'tab'
        ));
    }

    /**
     * Store a newly created Fixed Asset and auto-generate its unit records.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'category'       => 'required|string|max:100',
            'code_prefix'    => 'required|string|max:20|regex:/^[A-Za-z0-9_-]+$/',
            'total_quantity' => 'required|integer|min:1|max:1000',
            'unit_cost'      => 'nullable|numeric|min:0',
            'purchase_date'  => 'nullable|date',
            'supplier'       => 'nullable|string|max:255',
            'store_id'       => 'nullable|exists:stores,id',
            'description'    => 'nullable|string',
            'brand'          => 'nullable|string|max:100',
            'model'          => 'nullable|string|max:100',
            'year'           => 'nullable|integer|min:1950|max:2099',
            'specifications' => 'nullable|string',
        ]);

        $prefix = strtoupper(trim($validated['code_prefix']));

        try {
            DB::beginTransaction();

            $fixedAsset = FixedAsset::create([
                'name'           => $validated['name'],
                'category'       => $validated['category'],
                'code_prefix'    => $prefix,
                'total_quantity' => $validated['total_quantity'],
                'unit_cost'      => $validated['unit_cost'] ?? 0.00,
                'purchase_date'  => $validated['purchase_date'] ?? null,
                'supplier'       => $validated['supplier'] ?? null,
                'store_id'       => $validated['store_id'] ?? null,
                'description'    => $validated['description'] ?? null,
                'created_by'     => auth()->id(),
            ]);

            // Auto-generate exactly $total_quantity units: [PREFIX]-1 to [PREFIX]-N
            $storeName = $fixedAsset->store ? $fixedAsset->store->name : 'Main Store';
            $qty = (int) $validated['total_quantity'];

            for ($i = 1; $i <= $qty; $i++) {
                $unitCode = "{$prefix}-{$i}";

                // Ensure unique unit code if prefix had old records
                $seq = $i;
                while (FixedAssetUnit::where('unit_code', $unitCode)->exists()) {
                    $seq++;
                    $unitCode = "{$prefix}-{$seq}";
                }

                FixedAssetUnit::create([
                    'fixed_asset_id'  => $fixedAsset->id,
                    'unit_code'       => $unitCode,
                    'sequence_number' => $seq,
                    'status'          => FixedAssetUnit::STATUS_IN_STORE,
                    'condition'       => 'good',
                    'brand'           => $validated['brand'] ?? null,
                    'model'           => $validated['model'] ?? null,
                    'year'            => $validated['year'] ?? null,
                    'specifications'  => $validated['specifications'] ?? null,
                    'purchase_price'  => $validated['unit_cost'] ?? 0.00,
                    'current_location'=> $storeName,
                    'created_by'      => auth()->id(),
                ]);
            }

            $product = $fixedAsset->syncWithCatalogAndInventory();
            $storeId = $fixedAsset->store_id ?: (Store::where('is_active', true)->first()?->id ?? 1);

            if ($product && $storeId) {
                app(\App\Services\InventoryService::class)->applyMovement(
                    storeId: $storeId,
                    productId: $product->id,
                    quantityDelta: (float) $qty,
                    type: 'fa_register',
                    referenceType: FixedAsset::class,
                    referenceId: $fixedAsset->id,
                    remarks: "Initial registration of {$qty} unit(s) for Fixed Asset '{$fixedAsset->name}'",
                    performedBy: auth()->id() ?: 1,
                    unitCost: (float) ($validated['unit_cost'] ?? 0.00)
                );
            }

            DB::commit();

            return redirect()->route('store-manager.fixed-assets.index')
                ->with('success', "Fixed Asset \"{$fixedAsset->name}\" created successfully with {$qty} unit codes ({$prefix}-1 to {$prefix}-{$qty}), and synced to Material Catalog & Store Inventory!");

        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->withErrors(['error' => 'Failed to create fixed asset: ' . $e->getMessage()]);
        }
    }

    /**
     * Display a specific Fixed Asset with all its unit details and history.
     */
    public function show(FixedAsset $fixedAsset)
    {
        $fixedAsset->load([
            'store',
            'units.assignedEmployee',
            'units.assignments.employee',
            'units.assignments.assigner',
            'units.assignments.receiver',
            'creator'
        ]);

        $employees = Employee::where('status', 'active')->orderBy('full_name')->get();
        $stores = Store::where('is_active', true)->orderBy('name')->get();

        return view('store-manager.fixed-assets.show', compact('fixedAsset', 'employees', 'stores'));
    }

    /**
     * Update parent Fixed Asset details and manage quantity adjustment (Strict Quantity Lock).
     */
    public function update(Request $request, FixedAsset $fixedAsset)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'category'       => 'required|string|max:100',
            'code_prefix'    => 'required|string|max:20|regex:/^[A-Za-z0-9_-]+$/',
            'total_quantity' => 'required|integer|min:1|max:1000',
            'unit_cost'      => 'nullable|numeric|min:0',
            'purchase_date'  => 'nullable|date',
            'supplier'       => 'nullable|string|max:255',
            'store_id'       => 'nullable|exists:stores,id',
            'description'    => 'nullable|string',
            'remove_unit_ids'=> 'nullable|array',
            'remove_unit_ids.*' => 'exists:fixed_asset_units,id',
        ]);

        $oldQty = $fixedAsset->total_quantity;
        $newQty = (int) $validated['total_quantity'];
        $currentUnitsCount = $fixedAsset->units()->count();

        try {
            DB::beginTransaction();

            // Scenario 1: Quantity Increased (e.g. 10 -> 12)
            if ($newQty > $currentUnitsCount) {
                $needed = $newQty - $currentUnitsCount;
                $prefix = strtoupper(trim($validated['code_prefix']));
                $maxSeq = (int) $fixedAsset->units()->max('sequence_number');
                $storeName = $fixedAsset->store ? $fixedAsset->store->name : 'Main Store';

                for ($i = 1; $i <= $needed; $i++) {
                    $maxSeq++;
                    $unitCode = "{$prefix}-{$maxSeq}";
                    while (FixedAssetUnit::where('unit_code', $unitCode)->exists()) {
                        $maxSeq++;
                        $unitCode = "{$prefix}-{$maxSeq}";
                    }

                    FixedAssetUnit::create([
                        'fixed_asset_id'  => $fixedAsset->id,
                        'unit_code'       => $unitCode,
                        'sequence_number' => $maxSeq,
                        'status'          => FixedAssetUnit::STATUS_IN_STORE,
                        'condition'       => 'good',
                        'purchase_price'  => $validated['unit_cost'] ?? $fixedAsset->unit_cost,
                        'current_location'=> $storeName,
                        'created_by'      => auth()->id(),
                    ]);
                }

                $product = $fixedAsset->syncWithCatalogAndInventory();
                $storeId = $fixedAsset->store_id ?: (Store::where('is_active', true)->first()?->id ?? 1);
                if ($product && $storeId) {
                    app(\App\Services\InventoryService::class)->applyMovement(
                        storeId: $storeId,
                        productId: $product->id,
                        quantityDelta: (float) $needed,
                        type: 'fa_register',
                        referenceType: FixedAsset::class,
                        referenceId: $fixedAsset->id,
                        remarks: "Asset quantity increased: added {$needed} unit(s)",
                        performedBy: auth()->id() ?: 1,
                        unitCost: (float) ($validated['unit_cost'] ?? $fixedAsset->unit_cost ?? 0.00)
                    );
                }
            }
            // Scenario 2: Quantity Decreased (e.g. 10 -> 8)
            elseif ($newQty < $currentUnitsCount) {
                $unitsToRemoveCount = $currentUnitsCount - $newQty;
                $removeIds = $request->input('remove_unit_ids', []);

                if (count($removeIds) < $unitsToRemoveCount) {
                    $availableUnitIds = $fixedAsset->units()
                        ->where('status', FixedAssetUnit::STATUS_IN_STORE)
                        ->limit($unitsToRemoveCount)
                        ->pluck('id')
                        ->toArray();

                    if (count($availableUnitIds) < $unitsToRemoveCount) {
                        return back()->withInput()->withErrors([
                            'total_quantity' => "To decrease quantity from {$currentUnitsCount} to {$newQty}, there must be {$unitsToRemoveCount} unassigned unit(s) available in store."
                        ]);
                    }
                    $removeIds = $availableUnitIds;
                }

                // Verify that none of the selected units are currently assigned
                $assignedCheck = FixedAssetUnit::whereIn('id', $removeIds)
                    ->where('fixed_asset_id', $fixedAsset->id)
                    ->where('status', FixedAssetUnit::STATUS_ASSIGNED)
                    ->first();

                if ($assignedCheck) {
                    return back()->withInput()->withErrors([
                        'total_quantity' => "Cannot dispose unit {$assignedCheck->unit_code} because it is currently assigned to an employee. Return it first."
                    ]);
                }

                $targetIds = array_slice($removeIds, 0, $unitsToRemoveCount);

                // DO NOT DELETE: mark as disposed, keep records!
                foreach ($targetIds as $idToDispose) {
                    $u = FixedAssetUnit::find($idToDispose);
                    if ($u) {
                        $historyNote = "[" . now()->format('Y-m-d H:i') . "] Marked as disposed due to asset quantity reduction from {$currentUnitsCount} to {$newQty}.";
                        $u->update([
                            'status' => FixedAssetUnit::STATUS_DISPOSED,
                            'notes'  => $u->notes ? $u->notes . "\n" . $historyNote : $historyNote,
                        ]);
                    }
                }

                $product = $fixedAsset->syncWithCatalogAndInventory();
                $storeId = $fixedAsset->store_id ?: (Store::where('is_active', true)->first()?->id ?? 1);
                if ($product && $storeId) {
                    app(\App\Services\InventoryService::class)->applyMovement(
                        storeId: $storeId,
                        productId: $product->id,
                        quantityDelta: -(float) $unitsToRemoveCount,
                        type: 'fa_disposed',
                        referenceType: FixedAsset::class,
                        referenceId: $fixedAsset->id,
                        remarks: "Asset quantity decreased: disposed {$unitsToRemoveCount} unit(s)",
                        performedBy: auth()->id() ?: 1
                    );
                }
            }

            $fixedAsset->update([
                'name'           => $validated['name'],
                'category'       => $validated['category'],
                'code_prefix'    => strtoupper(trim($validated['code_prefix'])),
                'total_quantity' => $newQty,
                'unit_cost'      => $validated['unit_cost'] ?? 0.00,
                'purchase_date'  => $validated['purchase_date'] ?? null,
                'supplier'       => $validated['supplier'] ?? null,
                'store_id'       => $validated['store_id'] ?? null,
                'description'    => $validated['description'] ?? null,
            ]);

            DB::commit();

            $fixedAsset->syncWithCatalogAndInventory();

            return redirect()->route('store-manager.fixed-assets.show', $fixedAsset->id)
                ->with('success', "Fixed Asset \"{$fixedAsset->name}\" updated successfully! Quantity synced to {$newQty}.");

        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->withErrors(['error' => 'Update failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Add a single extra unit — registers new unit into Store Inventory.
     */
    public function storeExtraUnit(Request $request, FixedAsset $fixedAsset)
    {
        $validated = $request->validate([
            'unit_code'      => 'nullable|string|max:50|unique:fixed_asset_units,unit_code',
            'brand'          => 'nullable|string|max:100',
            'model'          => 'nullable|string|max:100',
            'serial_number'  => 'nullable|string|max:100',
            'plate_number'   => 'nullable|string|max:50',
            'chassis_number' => 'nullable|string|max:100',
            'engine_number'  => 'nullable|string|max:100',
            'year'           => 'nullable|integer|min:1950|max:2099',
            'specifications' => 'nullable|string',
            'condition'      => 'required|in:new,good,fair,needs_repair,damaged',
            'notes'          => 'nullable|string',
        ]);

        try {
            return DB::transaction(function() use ($request, $fixedAsset, $validated) {
                $maxSeq = (int) $fixedAsset->units()->max('sequence_number') + 1;
                $unitCode = $validated['unit_code'] ?? $fixedAsset->generateUnitCode($maxSeq);

                while (FixedAssetUnit::where('unit_code', $unitCode)->exists()) {
                    $maxSeq++;
                    $unitCode = $fixedAsset->generateUnitCode($maxSeq);
                }

                $specs = $validated['specifications'] ?? '';
                $specParts = [];
                if ($request->filled('cpu')) $specParts[] = 'CPU: ' . $request->input('cpu');
                if ($request->filled('ram')) $specParts[] = 'RAM: ' . $request->input('ram');
                if ($request->filled('storage')) $specParts[] = 'Storage: ' . $request->input('storage');
                if ($request->filled('os')) $specParts[] = 'OS: ' . $request->input('os');
                if (!empty($specParts)) {
                    $specs = implode(', ', $specParts) . ($specs ? " | {$specs}" : '');
                }

                $unit = FixedAssetUnit::create([
                    'fixed_asset_id'  => $fixedAsset->id,
                    'unit_code'       => $unitCode,
                    'sequence_number' => $maxSeq,
                    'status'          => FixedAssetUnit::STATUS_IN_STORE,
                    'condition'       => $validated['condition'],
                    'brand'           => $validated['brand'] ?? null,
                    'model'           => $validated['model'] ?? null,
                    'serial_number'   => $validated['serial_number'] ?? null,
                    'plate_number'    => $validated['plate_number'] ?? null,
                    'chassis_number'  => $validated['chassis_number'] ?? null,
                    'engine_number'   => $validated['engine_number'] ?? null,
                    'year'            => $validated['year'] ?? null,
                    'specifications'  => $specs ?: null,
                    'current_location'=> $fixedAsset->store ? $fixedAsset->store->name : 'Main Store',
                    'purchase_price'  => $fixedAsset->unit_cost,
                    'notes'           => $validated['notes'] ?? null,
                    'created_by'      => auth()->id(),
                ]);

                $fixedAsset->increment('total_quantity');

                $product = $fixedAsset->syncWithCatalogAndInventory();
                $storeId = $fixedAsset->store_id ?: (Store::where('is_active', true)->first()?->id ?? 1);
                if ($product && $storeId) {
                    app(\App\Services\InventoryService::class)->applyMovement(
                        storeId: $storeId,
                        productId: $product->id,
                        quantityDelta: 1.0,
                        type: 'fa_register',
                        referenceType: FixedAssetUnit::class,
                        referenceId: $unit->id,
                        remarks: "Registered unit {$unitCode} for Fixed Asset '{$fixedAsset->name}'",
                        performedBy: auth()->id() ?: 1,
                        unitCost: (float) ($fixedAsset->unit_cost ?? 0.00)
                    );
                }

                return back()->with('success', "Unit {$unitCode} created and registered to Store Inventory successfully!");
            });
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['error' => 'Failed to add unit: ' . $e->getMessage()]);
        }
    }

    /**
     * Update individual unit specifications (Serial No, Plate No, Condition, etc.)
     */
    public function updateUnit(Request $request, FixedAssetUnit $unit)
    {
        $validated = $request->validate([
            'unit_code'       => 'required|string|max:50|unique:fixed_asset_units,unit_code,' . $unit->id,
            'brand'           => 'nullable|string|max:100',
            'model'           => 'nullable|string|max:100',
            'serial_number'   => 'nullable|string|max:100',
            'plate_number'    => 'nullable|string|max:50',
            'chassis_number'  => 'nullable|string|max:100',
            'engine_number'   => 'nullable|string|max:100',
            'year'            => 'nullable|integer|min:1950|max:2099',
            'condition'       => 'required|in:new,good,fair,needs_repair,damaged',
            'status'          => 'required|in:in_store,assigned,maintenance,disposed',
            'specifications'  => 'nullable|string',
            'current_location'=> 'nullable|string|max:255',
            'purchase_price'  => 'nullable|numeric|min:0',
            'warranty_expiry' => 'nullable|date',
            'notes'           => 'nullable|string',
        ]);

        $oldStatus = $unit->status;
        $newStatus = $validated['status'];

        try {
            DB::transaction(function() use ($unit, $validated, $oldStatus, $newStatus) {
                // If status changed away from assigned, return to store first
                if ($newStatus !== FixedAssetUnit::STATUS_ASSIGNED && $unit->assigned_to_employee_id) {
                    $unit->returnToStore(auth()->id(), 'Status changed to ' . $newStatus, $validated['condition']);
                }

                // If in_store changed to maintenance or disposed, decrease available stock
                if ($oldStatus === FixedAssetUnit::STATUS_IN_STORE && in_array($newStatus, [FixedAssetUnit::STATUS_DISPOSED, FixedAssetUnit::STATUS_MAINTENANCE])) {
                    $parent = $unit->parentAsset;
                    if ($parent) {
                        $product = $parent->syncWithCatalogAndInventory();
                        $storeId = $parent->store_id ?: (Store::where('is_active', true)->first()?->id ?? 1);
                        if ($product && $storeId) {
                            app(\App\Services\InventoryService::class)->applyMovement(
                                storeId: $storeId,
                                productId: $product->id,
                                quantityDelta: -1.0,
                                type: $newStatus === FixedAssetUnit::STATUS_DISPOSED ? 'fa_disposed' : 'fa_damaged',
                                referenceType: FixedAssetUnit::class,
                                referenceId: $unit->id,
                                remarks: "Unit {$unit->unit_code} status updated to {$newStatus}",
                                performedBy: auth()->id() ?: 1
                            );
                        }
                    }
                }
                // If previously disposed or maintenance changed back to in_store, return to available stock
                elseif (in_array($oldStatus, [FixedAssetUnit::STATUS_DISPOSED, FixedAssetUnit::STATUS_MAINTENANCE]) && $newStatus === FixedAssetUnit::STATUS_IN_STORE) {
                    $parent = $unit->parentAsset;
                    if ($parent) {
                        $product = $parent->syncWithCatalogAndInventory();
                        $storeId = $parent->store_id ?: (Store::where('is_active', true)->first()?->id ?? 1);
                        if ($product && $storeId) {
                            app(\App\Services\InventoryService::class)->applyMovement(
                                storeId: $storeId,
                                productId: $product->id,
                                quantityDelta: 1.0,
                                type: 'fa_repaired',
                                referenceType: FixedAssetUnit::class,
                                referenceId: $unit->id,
                                remarks: "Unit {$unit->unit_code} returned to service (in store)",
                                performedBy: auth()->id() ?: 1
                            );
                        }
                    }
                }

                $unit->update($validated);
            });

            return back()->with('success', "Unit \"{$unit->unit_code}\" specifications updated successfully!");
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['error' => 'Update failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Mark an individual unit as disposed (Never delete records).
     */
    public function destroyUnit(FixedAssetUnit $unit)
    {
        if ($unit->status === FixedAssetUnit::STATUS_ASSIGNED) {
            return back()->withErrors([
                'error' => "Cannot dispose unit {$unit->unit_code} because it is currently assigned to an employee. Please return the unit to store first."
            ]);
        }

        $parent = $unit->parentAsset;
        $unitCode = $unit->unit_code;

        try {
            DB::transaction(function() use ($unit, $parent, $unitCode) {
                $historyNote = "[" . now()->format('Y-m-d H:i') . "] Marked as disposed.";
                $unit->update([
                    'status' => FixedAssetUnit::STATUS_DISPOSED,
                    'notes'  => $unit->notes ? $unit->notes . "\n" . $historyNote : $historyNote,
                ]);

                if ($parent) {
                    $product = $parent->syncWithCatalogAndInventory();
                    $storeId = $parent->store_id ?: (Store::where('is_active', true)->first()?->id ?? 1);
                    if ($product && $storeId) {
                        app(\App\Services\InventoryService::class)->applyMovement(
                            storeId: $storeId,
                            productId: $product->id,
                            quantityDelta: -1.0,
                            type: 'fa_disposed',
                            referenceType: FixedAssetUnit::class,
                            referenceId: $unit->id,
                            remarks: "Unit {$unitCode} marked as disposed",
                            performedBy: auth()->id() ?: 1
                        );
                    }
                }
            });

            return back()->with('success', "Unit {$unitCode} marked as disposed and store stock updated.");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Cannot dispose unit: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete a whole parent Fixed Asset and all its units (only if no units assigned).
     */
    public function destroy(FixedAsset $fixedAsset)
    {
        $assignedCount = $fixedAsset->units()->where('status', FixedAssetUnit::STATUS_ASSIGNED)->count();

        if ($assignedCount > 0) {
            return back()->withErrors([
                'error' => "Cannot delete Fixed Asset \"{$fixedAsset->name}\" because {$assignedCount} unit(s) are currently assigned to employees."
            ]);
        }

        DB::transaction(function() use ($fixedAsset) {
            $fixedAsset->units()->delete();
            $fixedAsset->delete();
        });

        return redirect()->route('store-manager.fixed-assets.index')
            ->with('success', "Fixed Asset \"{$fixedAsset->name}\" and its unit records deleted successfully.");
    }

    /**
     * API: Get list of available In Store Fixed Asset units for HR dropdown & Transfer Unit selection.
     */
    public function availableUnitsAjax(Request $request)
    {
        $search = $request->input('q');
        $storeId = $request->input('store_id');
        $productId = $request->input('product_id');

        // 1. Ensure inventory assets and coded units are synchronized on-the-fly
        try {
            if ($productId) {
                FixedAsset::syncFromInventory((int)$productId);
            } elseif ($storeId) {
                FixedAsset::syncFromInventory(null, $search);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("availableUnitsAjax auto-sync error: " . $e->getMessage());
        }

        $prod = $productId ? Product::find($productId) : null;
        $store = $storeId ? Store::find($storeId) : null;
        $storeName = $store?->name;

        // 2. If a specific product & store are queried, auto-generate units if inventory has stock
        if ($prod && $storeId) {
            $invQty = (int) round(Inventory::where('product_id', $prod->id)->where('store_id', $storeId)->value('quantity_on_hand') ?? 0);
            if ($invQty > 0) {
                $fa = FixedAsset::where('store_id', $storeId)
                    ->where(function($q) use ($prod) {
                        $q->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($prod->name))])
                          ->orWhere('description', 'like', "%(Product ID: {$prod->id})%")
                          ->orWhere('description', 'like', "%SKU: " . ($prod->sku ?? '') . "%");
                    })->first();

                if (!$fa) {
                    $orphan = FixedAsset::where(function($q) use ($prod) {
                        $q->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($prod->name))])
                          ->orWhere('description', 'like', "%(Product ID: {$prod->id})%");
                    })->first();

                    $cat = FixedAsset::resolveCategory($prod->category ?? '', $prod->name);
                    $prefix = FixedAsset::generateCleanPrefix($prod->name, $prod->sku);

                    $fa = FixedAsset::create([
                        'name'           => $prod->name,
                        'category'       => $orphan?->category ?? $cat,
                        'code_prefix'    => $orphan?->code_prefix ?? $prefix,
                        'total_quantity' => $invQty,
                        'unit_cost'      => $prod->unit_price ?? 0,
                        'store_id'       => $storeId,
                        'created_by'     => auth()->id(),
                    ]);
                }

                if ($fa) {
                    $availUnitsCount = $fa->units()->where('status', FixedAssetUnit::STATUS_IN_STORE)->count();
                    if ($availUnitsCount < $invQty) {
                        $fa->total_quantity = max((int)$fa->total_quantity, $invQty);
                        $fa->saveQuietly();
                        $fa->generateUnitsToMatchQuantity([
                            'purchase_price'   => $prod->unit_price ?? 0,
                            'current_location' => $storeName ?: ('Store #' . $storeId),
                            'condition'        => $prod->equipment_condition ?: 'good',
                        ]);
                    }
                }
            }
        }

        $query = FixedAssetUnit::with(['parentAsset.store'])
            ->where('status', FixedAssetUnit::STATUS_IN_STORE);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('unit_code', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%")
                  ->orWhere('plate_number', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('model', 'like', "%{$search}%")
                  ->orWhereHas('parentAsset', fn($pq) => $pq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($storeId) {
            $query->where(function($q) use ($storeId, $storeName) {
                $q->whereHas('parentAsset', fn($pq) => $pq->where('store_id', $storeId));
                if ($storeName) {
                    $q->orWhere('current_location', $storeName);
                }
                $q->orWhere(function($sub) {
                    $sub->whereNull('current_location')
                        ->whereHas('parentAsset', fn($pq) => $pq->whereNull('store_id'));
                });
            });
        }

        if ($prod) {
            $prodNameLower = strtolower(trim($prod->name));
            $prodSku = strtoupper(trim($prod->sku ?? ''));
            $cleanPrefix = str_replace(['FA-', 'AST-'], '', $prodSku);

            $query->whereHas('parentAsset', function($pq) use ($prod, $prodNameLower, $cleanPrefix) {
                $pq->whereRaw('LOWER(TRIM(name)) = ?', [$prodNameLower])
                   ->orWhere('description', 'like', "%(Product ID: {$prod->id})%")
                   ->orWhere('description', 'like', "%SKU: " . ($prod->sku ?? '') . "%");
                if (!empty($cleanPrefix)) {
                    $pq->orWhere('code_prefix', $cleanPrefix);
                }
            });
        }

        $limit = $productId ? 250 : 500;
        $units = $query->orderBy('unit_code')->limit($limit)->get()->map(function($u) {
            return [
                'id'            => $u->id,
                'unit_code'     => $u->unit_code,
                'asset_name'    => $u->parentAsset->name ?? 'Asset',
                'category'      => $u->parentAsset->category ?? 'General',
                'brand'         => $u->brand,
                'model'         => $u->model,
                'serial_number' => $u->serial_number,
                'plate_number'  => $u->plate_number,
                'condition'     => $u->condition,
                'current_store' => $u->current_location ?: ($u->parentAsset->store->name ?? 'Main Store'),
                'display'       => $u->display_title,
            ];
        });

        return response()->json(['units' => $units]);
    }

    /**
     * API: Get active compatible transfers between origin and destination store for merging.
     */
    public function compatibleTransfersAjax(Request $request)
    {
        $fromStoreId = (int)$request->input('from_store_id');
        $toStoreId = (int)$request->input('to_store_id');

        if (!$fromStoreId || !$toStoreId || $fromStoreId === $toStoreId) {
            return response()->json(['transfers' => []]);
        }

        $transfers = Transfer::with(['items.product', 'requestedBy', 'fromStore', 'toStore'])
            ->where('from_store_id', $fromStoreId)
            ->where('to_store_id', $toStoreId)
            ->whereIn('status', ['draft', 'pending_approval', 'approved', 'in_transit'])
            ->latest()
            ->limit(25)
            ->get()
            ->map(function($t) {
                $itemsCount = $t->items->count();
                $totalQty = (float)$t->items->sum('requested_quantity');
                $slip = $t->outgoing_slip_no ?: $t->physical_slip_no;
                return [
                    'id'            => $t->id,
                    'transfer_no'   => $t->transfer_no,
                    'physical_slip' => $slip,
                    'status'        => ucfirst(str_replace('_', ' ', $t->status)),
                    'date'          => $t->required_date ? \Carbon\Carbon::parse($t->required_date)->format('M d, Y') : $t->created_at->format('M d, Y'),
                    'items_summary' => "{$itemsCount} item(s) (" . round($totalQty, 2) . " total)",
                    'driver'        => $t->vehicle_plate_no ? "Vehicle: {$t->vehicle_plate_no}" : '',
                    'reason'        => \Illuminate\Support\Str::limit($t->reason, 45),
                ];
            });

        return response()->json(['transfers' => $transfers]);
    }

    /**
     * Transfer one or more Fixed Asset Units to another Store / Construction Site.
     * Transferred by Individual Unit Code(s).
     */
    public function transferUnits(Request $request)
    {
        $validated = $request->validate([
            'unit_ids'           => 'required|array|min:1',
            'unit_ids.*'         => 'required|exists:fixed_asset_units,id',
            'to_store_id'        => 'required|exists:stores,id',
            'transfer_mode'      => 'nullable|in:new,merge',
            'target_transfer_id' => 'nullable|exists:transfers,id',
            'transfer_reason'    => 'nullable|string|max:500',
            'waybill_no'         => 'nullable|string|max:100',
            'driver_name'        => 'nullable|string|max:150',
            'vehicle_plate'      => 'nullable|string|max:50',
            'create_slip'        => 'nullable|boolean',
        ]);

        $toStore = Store::findOrFail($validated['to_store_id']);
        $units = FixedAssetUnit::with(['parentAsset.store'])->whereIn('id', $validated['unit_ids'])->get();

        if ($units->isEmpty()) {
            return back()->withErrors(['error' => 'No valid units selected for transfer.']);
        }

        // Verify none of the units are currently assigned or disposed
        $invalidUnits = $units->filter(fn($u) => $u->status === FixedAssetUnit::STATUS_ASSIGNED || $u->status === FixedAssetUnit::STATUS_DISPOSED);
        if ($invalidUnits->isNotEmpty()) {
            $codes = $invalidUnits->pluck('unit_code')->implode(', ');
            return back()->withErrors(['error' => "Cannot transfer units ({$codes}) because they are currently assigned or disposed. Please return them to store first."]);
        }

        // Verify that destination store is different from origin store
        $firstUnit = $units->first();
        $fromStoreId = $firstUnit->parentAsset?->store_id ?? $request->input('from_store_id') ?? Store::where('name', $firstUnit->current_location)->value('id') ?? 1;
        if ((int)$fromStoreId === (int)$toStore->id) {
            return back()->withErrors(['error' => 'Destination store cannot be the same as the origin store.']);
        }

        // Resolve Target Transfer if merge mode is selected or if waybill_no matches an existing transfer
        $targetTransfer = null;
        if (!empty($validated['target_transfer_id'])) {
            $targetTransfer = Transfer::find($validated['target_transfer_id']);
        } elseif (!empty($validated['waybill_no'])) {
            $searchNo = trim($validated['waybill_no']);
            $targetTransfer = Transfer::where('from_store_id', $fromStoreId)
                ->where('to_store_id', $toStore->id)
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->where(function($q) use ($searchNo) {
                    $q->where('transfer_no', $searchNo)
                      ->orWhere('physical_slip_no', $searchNo)
                      ->orWhere('outgoing_slip_no', $searchNo);
                })
                ->first();
        }

        if ($targetTransfer) {
            if ((int)$targetTransfer->from_store_id !== (int)$fromStoreId || (int)$targetTransfer->to_store_id !== (int)$toStore->id) {
                return back()->withErrors(['error' => "Target Transfer #{$targetTransfer->transfer_no} route does not match origin and destination store."]);
            }
            if (in_array($targetTransfer->status, ['cancelled', 'rejected'])) {
                return back()->withErrors(['error' => "Target Transfer #{$targetTransfer->transfer_no} is {$targetTransfer->status} and cannot be merged."]);
            }
        }

        FixedAssetAssignment::ensureSchema();

        $transferredCount = 0;
        $transferredCodes = [];

        DB::transaction(function() use ($units, $toStore, $validated, $targetTransfer, $fromStoreId, &$transferredCount, &$transferredCodes) {
            // Group units by their parent asset
            $groupedByAsset = $units->groupBy('fixed_asset_id');

            foreach ($groupedByAsset as $originAssetId => $assetUnits) {
                $originAsset = $assetUnits->first()->parentAsset;
                if (!$originAsset) continue;

                $unitCountToMove = $assetUnits->count();
                $fromStoreName = $originAsset->store?->name ?? 'Origin Store';

                // 1. Find or create matching FixedAsset at the destination store
                $destAsset = FixedAsset::where('store_id', $toStore->id)
                    ->where('name', $originAsset->name)
                    ->where('category', $originAsset->category)
                    ->first();

                if (!$destAsset) {
                    $destAsset = FixedAsset::create([
                        'name'           => $originAsset->name,
                        'category'       => $originAsset->category,
                        'code_prefix'    => $originAsset->code_prefix,
                        'total_quantity' => $unitCountToMove,
                        'unit_cost'      => $originAsset->unit_cost,
                        'purchase_date'  => $originAsset->purchase_date,
                        'supplier'       => $originAsset->supplier,
                        'store_id'       => $toStore->id,
                        'description'    => $originAsset->description,
                        'created_by'     => auth()->id(),
                    ]);
                } else {
                    $destAsset->increment('total_quantity', $unitCountToMove);
                }

                // 2. Decrement origin asset quantity
                $originAsset->decrement('total_quantity', $unitCountToMove);

                // 3. Move each individual unit by code
                foreach ($assetUnits as $unit) {
                    $oldLocation = $unit->current_location ?: $fromStoreName;
                    $historyNote = "[" . now()->format('Y-m-d H:i') . "] Transferred by code {$unit->unit_code} from {$fromStoreName} to {$toStore->name} (Reason: " . ($validated['transfer_reason'] ?? 'Store Transfer') . ")";

                    $unit->update([
                        'fixed_asset_id'   => $destAsset->id,
                        'current_location' => $toStore->name,
                        'status'           => FixedAssetUnit::STATUS_IN_STORE,
                        'notes'            => $unit->notes ? $unit->notes . "\n" . $historyNote : $historyNote,
                    ]);

                    // Record assignment/movement history entry
                    FixedAssetAssignment::createSafely([
                        'fixed_asset_unit_id'    => $unit->id,
                        'employee_id'            => null,
                        'action'                 => 'transferred',
                        'assigned_date'          => now(),
                        'condition_on_assignment'=> $unit->condition ?: 'good',
                        'assigned_by'            => auth()->id(),
                        'notes'                  => $historyNote,
                    ]);

                    $transferredCodes[] = $unit->unit_code;
                    $transferredCount++;
                }

                // 4. Two-way inventory synchronization for both stores
                $originAsset->syncWithCatalogAndInventory();
                $destAsset->syncWithCatalogAndInventory();
            }

            // Merge into existing Inventory Transfer OR create new Transfer record
            if ($targetTransfer) {
                // Add items to existing transfer
                foreach ($groupedByAsset as $originAssetId => $assetUnits) {
                    $originAsset = $assetUnits->first()->parentAsset;
                    $prod = Product::where('name', $originAsset->name)
                        ->orWhere('sku', 'FA-' . strtoupper(trim($originAsset->code_prefix)))
                        ->orWhere('sku', 'AST-' . strtoupper(trim($originAsset->code_prefix)))
                        ->orWhereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($originAsset->name))])
                        ->first();

                    if (!$prod) {
                        $prod = $originAsset->syncWithCatalogAndInventory();
                    }

                    if ($prod) {
                        $count = $assetUnits->count();
                        $unitCodes = $assetUnits->pluck('unit_code')->toArray();
                        $existingItem = $targetTransfer->items()->where('product_id', $prod->id)->first();

                        if ($existingItem) {
                            $currentCodes = !empty($existingItem->unit_codes)
                                ? (is_array($existingItem->unit_codes) ? $existingItem->unit_codes : (json_decode($existingItem->unit_codes, true) ?: explode(',', $existingItem->unit_codes)))
                                : [];
                            $mergedCodes = array_values(array_unique(array_merge($currentCodes, $unitCodes)));

                            $itemUpdate = [
                                'requested_quantity' => (float)$existingItem->requested_quantity + $count,
                                'approved_quantity'  => (float)$existingItem->approved_quantity + $count,
                                'unit_codes'         => $mergedCodes,
                            ];
                            if (in_array($targetTransfer->status, ['in_transit', 'dispatched', 'completed'])) {
                                $itemUpdate['sent_quantity'] = (float)$existingItem->sent_quantity + $count;
                            }
                            if ($targetTransfer->status === 'completed') {
                                $itemUpdate['received_quantity'] = (float)$existingItem->received_quantity + $count;
                            }
                            $existingItem->update($itemUpdate);
                        } else {
                            $sentQty = in_array($targetTransfer->status, ['in_transit', 'dispatched', 'completed']) ? $count : 0;
                            $recQty = ($targetTransfer->status === 'completed') ? $count : 0;
                            $targetTransfer->items()->create([
                                'product_id'         => $prod->id,
                                'requested_quantity' => $count,
                                'approved_quantity'  => $count,
                                'sent_quantity'      => $sentQty,
                                'received_quantity'  => $recQty,
                                'unit'               => 'pcs',
                                'unit_codes'         => $unitCodes,
                            ]);
                        }
                    }
                }

                $mergeNote = "[Fixed Asset Units: " . implode(', ', $transferredCodes) . " merged by " . (auth()->user()->name ?? 'Store Manager') . " on " . now()->format('Y-m-d H:i') . "]";
                $newDispatchNotes = trim(($targetTransfer->dispatch_notes ? $targetTransfer->dispatch_notes . "\n" : '') . $mergeNote);
                $updateData = ['dispatch_notes' => $newDispatchNotes];
                if (!empty($validated['vehicle_plate']) && empty($targetTransfer->vehicle_plate_no)) {
                    $updateData['vehicle_plate_no'] = $validated['vehicle_plate'];
                }
                $targetTransfer->update($updateData);

                // Sync inventory for this target transfer immediately
                \App\Http\Controllers\StoreManagerController::syncTransferInventory($targetTransfer);

                try {
                    \App\Models\ActivityLog::log(
                        'fixed_asset_merged_transfer',
                        "Merged {$transferredCount} Fixed Asset unit(s) [" . implode(', ', $transferredCodes) . "] into Inventory Transfer #{$targetTransfer->transfer_no}",
                        'Transfers',
                        $targetTransfer
                    );
                } catch (\Throwable $e) {}
            } else {
                // Create dedicated Transfer record and sync inventory
                $transferNo = Transfer::generateUniqueNo();
                $transfer = Transfer::create([
                    'transfer_no'        => $transferNo,
                    'physical_slip_no'   => $validated['waybill_no'] ?? $transferNo,
                    'outgoing_slip_no'   => $validated['waybill_no'] ?? $transferNo,
                    'receiving_slip_no'  => $validated['waybill_no'] ?? $transferNo,
                    'from_store_id'      => $fromStoreId,
                    'to_store_id'        => $toStore->id,
                    'requested_by'       => auth()->id(),
                    'approved_by'        => auth()->id(),
                    'approved_at'        => now(),
                    'dispatched_by'      => auth()->id(),
                    'dispatched_at'      => now(),
                    'received_by'        => auth()->id(),
                    'received_at'        => now(),
                    'status'             => 'completed',
                    'required_date'      => today(),
                    'vehicle_plate_no'   => $validated['vehicle_plate'] ?? null,
                    'reason'             => ($validated['transfer_reason'] ?? 'Fixed Asset Unit Transfer') . ' [Unit Codes: ' . implode(', ', $transferredCodes) . ']',
                    'dispatch_notes'     => 'Transferred units: ' . implode(', ', $transferredCodes),
                ]);

                // Create transfer items WITH unit_codes
                foreach ($groupedByAsset as $originAssetId => $assetUnits) {
                    $originAsset = $assetUnits->first()->parentAsset;
                    $prod = Product::where('name', $originAsset->name)
                        ->orWhere('sku', 'FA-' . strtoupper(trim($originAsset->code_prefix)))
                        ->orWhere('sku', 'AST-' . strtoupper(trim($originAsset->code_prefix)))
                        ->orWhereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($originAsset->name))])
                        ->first();

                    if (!$prod) {
                        $prod = $originAsset->syncWithCatalogAndInventory();
                    }

                    if ($prod) {
                        $transfer->items()->create([
                            'product_id'         => $prod->id,
                            'requested_quantity' => $assetUnits->count(),
                            'approved_quantity'  => $assetUnits->count(),
                            'sent_quantity'      => $assetUnits->count(),
                            'received_quantity'  => $assetUnits->count(),
                            'unit'               => 'pcs',
                            'unit_codes'         => $assetUnits->pluck('unit_code')->toArray(),
                        ]);
                    }
                }

                \App\Http\Controllers\StoreManagerController::syncTransferInventory($transfer);
            }
        });

        try {
            FixedAsset::syncFromInventory();
        } catch (\Throwable $e) {}

        $codesStr = implode(', ', $transferredCodes);
        if ($targetTransfer) {
            return back()->with('success', "{$transferredCount} Fixed Asset unit(s) ({$codesStr}) successfully transferred and merged into Inventory Transfer #{$targetTransfer->transfer_no}!");
        }
        return back()->with('success', "{$transferredCount} Fixed Asset unit(s) successfully transferred by individual code to {$toStore->name}! (Unit codes: {$codesStr})");
    }

    /**
     * Transfer a single unit by code (Convenience wrapper)
     */
    public function transferUnit(Request $request, FixedAssetUnit $unit)
    {
        $request->merge(['unit_ids' => [$unit->id]]);
        return $this->transferUnits($request);
    }

    /**
     * Action: Return / Unassign a unit from an employee (used by HR or Store Manager).
     */
    public function returnUnit(Request $request, FixedAssetUnit $unit)
    {
        $validated = $request->validate([
            'condition' => 'required|in:new,good,fair,needs_repair,damaged',
            'notes'     => 'nullable|string',
        ]);

        try {
            $unit->returnToStore(auth()->id(), $validated['notes'] ?? null, $validated['condition']);

            return back()->with('success', "Asset {$unit->unit_code} successfully returned to store!");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Failed to return unit: ' . $e->getMessage()]);
        }
    }

    /**
     * Action: Assign a unit directly to an employee.
     */
    public function assignUnit(Request $request, FixedAssetUnit $unit)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'notes'       => 'nullable|string',
        ]);

        if ($unit->status !== FixedAssetUnit::STATUS_IN_STORE) {
            return back()->withErrors(['error' => "Unit {$unit->unit_code} is not available in store (Current status: {$unit->status})."]);
        }

        $employee = Employee::findOrFail($validated['employee_id']);

        try {
            $unit->assignToEmployee($employee->id, auth()->id(), $validated['notes'] ?? null);

            return back()->with('success', "Unit {$unit->unit_code} assigned to {$employee->full_name} successfully!");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Cannot assign unit: ' . $e->getMessage()]);
        }
    }

    /**
     * Manual sync trigger from UI: Sync all inventory items to Fixed Assets.
     */
    public function syncInventoryNow(Request $request)
    {
        try {
            $count = FixedAsset::syncFromInventory();
            return redirect()->route('store-manager.fixed-assets.index')
                ->with('success', "✅ Successfully synchronized with Store Inventory! {$count} asset(s) updated/created.");
        } catch (\Throwable $e) {
            return redirect()->route('store-manager.fixed-assets.index')
                ->with('error', "Sync failed: " . $e->getMessage());
        }
    }
}
