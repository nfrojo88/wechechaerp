<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(Product::class, 'product');
    }

    public function index(Request $request)
    {
        $query = Product::latest();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('sub_category', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('sub_category')) {
            $query->where('sub_category', $request->input('sub_category'));
        }

        if ($request->filled('asset_status')) {
            $query->where('asset_status', $request->input('asset_status'));
        }

        $products    = $query->paginate(20)->appends($request->query());
        $categories  = $this->categories();
        $subCategories = $this->subCategories();
        $assetStatuses = $this->assetStatuses();
        return view('products.index', compact('products', 'categories', 'subCategories', 'assetStatuses'));
    }

    public function create()
    {
        $categories    = $this->categories();
        $units         = $this->units();
        $subCategories = $this->subCategories();
        $assetStatuses = $this->assetStatuses();
        $conditions    = $this->conditions();
        return view('products.create', compact('categories', 'units', 'subCategories', 'assetStatuses', 'conditions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'               => ['required', 'string', 'max:255'],
            'sku'                => ['required', 'string', 'max:100', 'unique:products,sku'],
            'category'           => ['nullable', 'string', 'max:100'],
            'sub_category'       => ['nullable', 'string', 'max:100'],
            'unit'               => ['required', 'string', 'max:50'],
            'unit_price'         => ['nullable', 'numeric', 'min:0'],
            'selling_price'      => ['nullable', 'numeric', 'min:0'],
            'max_stock'          => ['nullable', 'numeric', 'min:0'],
            'reorder_level'      => ['nullable', 'integer', 'min:0'],
            'carton_size'        => ['nullable', 'integer', 'min:0'],
            'standard_length'    => ['nullable', 'numeric', 'min:0'],
            'standard_width'     => ['nullable', 'numeric', 'min:0'],
            'equipment_condition'=> ['nullable', 'string', 'max:100'],
            'assigned_to'        => ['nullable', 'string', 'max:255'],
            'current_location'   => ['nullable', 'string', 'max:255'],
            'asset_status'       => ['nullable', 'string', 'max:50'],
            'baseline_date'      => ['nullable', 'date'],
            'purchase_threshold' => ['nullable', 'numeric', 'min:0'],
        ]);

        $validated['unit_price']         = $validated['unit_price'] ?? 0;
        $validated['selling_price']      = $validated['selling_price'] ?? 0;
        $validated['max_stock']          = $validated['max_stock'] ?? 100;
        $validated['reorder_level']      = $validated['reorder_level'] ?? 20;
        $validated['standard_length']    = $validated['standard_length'] ?? 0;
        $validated['standard_width']     = $validated['standard_width'] ?? 0;
        $validated['equipment_condition']= $validated['equipment_condition'] ?? 'Good';
        $validated['assigned_to']        = $validated['assigned_to'] ?? 'Unassigned';
        $validated['current_location']   = $validated['current_location'] ?? 'Main Store';
        $validated['asset_status']       = $validated['asset_status'] ?? 'Available';
        $validated['purchase_threshold'] = $validated['purchase_threshold'] ?? 5;

        Product::create($validated);

        return redirect()->route('products.index')->with('success', 'Product created successfully.');
    }

    public function show(Product $product)
    {
        $product->load('inventory.store');
        return view('products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        $categories    = $this->categories();
        $units         = $this->units();
        $subCategories = $this->subCategories();
        $assetStatuses = $this->assetStatuses();
        $conditions    = $this->conditions();
        return view('products.edit', compact('product', 'categories', 'units', 'subCategories', 'assetStatuses', 'conditions'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name'               => ['required', 'string', 'max:255'],
            'sku'                => ['required', 'string', 'max:100', 'unique:products,sku,' . $product->id],
            'category'           => ['nullable', 'string', 'max:100'],
            'sub_category'       => ['nullable', 'string', 'max:100'],
            'unit'               => ['required', 'string', 'max:50'],
            'unit_price'         => ['nullable', 'numeric', 'min:0'],
            'selling_price'      => ['nullable', 'numeric', 'min:0'],
            'max_stock'          => ['nullable', 'numeric', 'min:0'],
            'reorder_level'      => ['nullable', 'integer', 'min:0'],
            'carton_size'        => ['nullable', 'integer', 'min:0'],
            'standard_length'    => ['nullable', 'numeric', 'min:0'],
            'standard_width'     => ['nullable', 'numeric', 'min:0'],
            'equipment_condition'=> ['nullable', 'string', 'max:100'],
            'assigned_to'        => ['nullable', 'string', 'max:255'],
            'current_location'   => ['nullable', 'string', 'max:255'],
            'asset_status'       => ['nullable', 'string', 'max:50'],
            'baseline_date'      => ['nullable', 'date'],
            'purchase_threshold' => ['nullable', 'numeric', 'min:0'],
        ]);

        $validated['unit_price']         = $validated['unit_price'] ?? ($product->unit_price ?? 0);
        $validated['selling_price']      = $validated['selling_price'] ?? ($product->selling_price ?? 0);
        $validated['max_stock']          = $validated['max_stock'] ?? ($product->max_stock ?? 100);
        $validated['reorder_level']      = $validated['reorder_level'] ?? ($product->reorder_level ?? 20);
        $validated['standard_length']    = $validated['standard_length'] ?? ($product->standard_length ?? 0);
        $validated['standard_width']     = $validated['standard_width'] ?? ($product->standard_width ?? 0);
        $validated['equipment_condition']= $validated['equipment_condition'] ?? ($product->equipment_condition ?? 'Good');
        $validated['assigned_to']        = $validated['assigned_to'] ?? ($product->assigned_to ?? 'Unassigned');
        $validated['current_location']   = $validated['current_location'] ?? ($product->current_location ?? 'Main Store');
        $validated['asset_status']       = $validated['asset_status'] ?? ($product->asset_status ?? 'Available');
        $validated['purchase_threshold'] = $validated['purchase_threshold'] ?? ($product->purchase_threshold ?? 5);

        $product->update($validated);

        return redirect()->route('products.index')->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route('products.index')->with('success', 'Product archived.');
    }

    /**
     * Complete Delete (Hard Delete / Permanent Purge) a Material by SKU code (Global Admin only).
     */
    public function completeDeleteBySku(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->isGlobalAdmin()) {
            abort(403, 'Unauthorized: Complete material deletion by SKU is strictly reserved for Global Admin.');
        }

        $request->validate([
            'sku' => ['required', 'string'],
            'confirm_sku' => ['required', 'string'],
        ]);

        $rawInput = trim((string)$request->input('sku'));
        $confirmInput = trim((string)$request->input('confirm_sku'));

        // Parse one or multiple SKUs separated by comma or newlines
        $skus = preg_split('/[\r\n,]+/', $rawInput);
        $skus = array_values(array_filter(array_map('trim', $skus)));

        if (empty($skus)) {
            return back()->with('error', 'Please provide a valid material SKU code to delete.');
        }

        // Security confirmation check: user must type the exact SKU (or first SKU) or "DELETE"
        $firstSku = $skus[0];
        $isConfirmed = (strtoupper($confirmInput) === 'DELETE')
            || (strcasecmp($confirmInput, $firstSku) === 0)
            || (strcasecmp($confirmInput, $rawInput) === 0);

        if (!$isConfirmed) {
            return back()->with('error', "Confirmation failed: You must type the SKU code '{$firstSku}' or 'DELETE' to confirm permanent complete deletion.");
        }

        $deletedCount = 0;
        $deletedSkus = [];
        $failedSkus = [];

        foreach ($skus as $sku) {
            try {
                $purged = $this->purgeMaterialBySku($sku);
                if ($purged) {
                    $deletedCount++;
                    $deletedSkus[] = $sku;
                } else {
                    $failedSkus[] = "SKU '{$sku}' not found";
                }
            } catch (\Throwable $e) {
                Log::error("Failed to completely delete material SKU '{$sku}': " . $e->getMessage(), [
                    'exception' => $e
                ]);
                $failedSkus[] = "SKU '{$sku}' failed ({$e->getMessage()})";
            }
        }

        if ($deletedCount > 0 && empty($failedSkus)) {
            $skuList = implode(', ', $deletedSkus);
            return back()->with('success', "Material [SKU: {$skuList}] has been completely and permanently deleted from the system, including inventory, movements, and ledger references.");
        } elseif ($deletedCount > 0 && !empty($failedSkus)) {
            $skuList = implode(', ', $deletedSkus);
            $failList = implode('; ', $failedSkus);
            return back()->with('warning', "Successfully deleted: [{$skuList}]. Notice: {$failList}.");
        } else {
            $failList = implode('; ', $failedSkus);
            return back()->with('error', "Complete delete failed: {$failList}.");
        }
    }

    /**
     * AJAX/API Lookup material details by SKU code before complete deletion.
     */
    public function lookupBySku(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->isGlobalAdmin()) {
            return response()->json(['found' => false, 'message' => 'Unauthorized.'], 403);
        }

        $sku = trim((string)$request->input('sku'));
        if (empty($sku)) {
            return response()->json(['found' => false, 'message' => 'SKU code is required.'], 400);
        }

        $products = Product::withTrashed()
            ->where('sku', $sku)
            ->orWhere('sku', strtoupper($sku))
            ->orWhere(DB::raw('LOWER(TRIM(sku))'), strtolower($sku))
            ->orWhere('sku', 'like', "%{$sku}%")
            ->take(5)
            ->get();

        if ($products->isEmpty()) {
            return response()->json([
                'found' => false,
                'message' => "No material found matching SKU code '{$sku}'."
            ]);
        }

        $data = $products->map(function ($p) {
            $invOnHand = 0;
            if (Schema::hasTable('inventory')) {
                $invOnHand = (float)DB::table('inventory')->where('product_id', $p->id)->sum('quantity_on_hand');
            }
            return [
                'id'              => $p->id,
                'name'            => $p->name,
                'sku'             => $p->sku,
                'category'        => $p->category ?? 'N/A',
                'sub_category'    => $p->sub_category ?? 'N/A',
                'unit'            => $p->unit ?? 'pcs',
                'unit_price'      => (float)($p->unit_price ?? 0),
                'stock_on_hand'   => $invOnHand,
                'is_soft_deleted' => $p->trashed(),
            ];
        });

        return response()->json([
            'found'    => true,
            'count'    => $products->count(),
            'products' => $data,
        ]);
    }

    /**
     * Force delete a specific product instance (Global Admin only).
     */
    public function forceDeleteProduct(Product $product)
    {
        $user = Auth::user();
        if (!$user || !$user->isGlobalAdmin()) {
            abort(403, 'Unauthorized: Complete material deletion is strictly reserved for Global Admin.');
        }

        $sku = $product->sku;
        $name = $product->name;

        $this->purgeMaterialBySku($sku);

        return redirect()->route('products.index')->with('success', "Material '{$name}' [SKU: {$sku}] has been completely and permanently deleted.");
    }

    /**
     * Internal worker to purge/force-delete a product and all references by SKU.
     */
    public function purgeMaterialBySku(string $sku): bool
    {
        $sku = trim($sku);
        if (empty($sku)) return false;

        $skuUpper = strtoupper($sku);
        $skuLower = strtolower($sku);

        $matchingProducts = Product::withTrashed()
            ->where('sku', $sku)
            ->orWhere('sku', $skuUpper)
            ->orWhere(DB::raw('LOWER(TRIM(sku))'), $skuLower)
            ->get();

        $productIds = $matchingProducts->pluck('id')->toArray();

        // Also check if fixed_assets has any matching record even if product record was already partially deleted
        $faIds = [];
        if (Schema::hasTable('fixed_assets')) {
            $faQuery = DB::table('fixed_assets');
            $hasFaCol = false;
            if (Schema::hasColumn('fixed_assets', 'sku')) {
                $faQuery->where(function($q) use ($sku, $skuUpper) {
                    $q->where('sku', $sku)->orWhere('sku', $skuUpper);
                });
                $hasFaCol = true;
            }
            if (Schema::hasColumn('fixed_assets', 'description')) {
                $faQuery->orWhere('description', 'like', "%SKU: {$sku}%");
                $hasFaCol = true;
            }
            if (!empty($productIds) && Schema::hasColumn('fixed_assets', 'product_id')) {
                $faQuery->orWhereIn('product_id', $productIds);
                $hasFaCol = true;
            }
            if ($hasFaCol) {
                $faIds = $faQuery->pluck('id')->toArray();
            }
        }

        if (empty($productIds) && empty($faIds)) {
            // Also check direct table query in case Product model global scopes or casts hide it
            $rawProductIds = DB::table('products')
                ->where('sku', $sku)
                ->orWhere('sku', $skuUpper)
                ->orWhere(DB::raw('LOWER(TRIM(sku))'), $skuLower)
                ->pluck('id')
                ->toArray();
            if (!empty($rawProductIds)) {
                $productIds = $rawProductIds;
            } else {
                return false;
            }
        }

        DB::transaction(function () use ($productIds, $faIds, $sku, $skuUpper, $skuLower) {
            // 1. Delete Fixed Assets and units
            if (!empty($faIds)) {
                if (Schema::hasTable('fixed_asset_units')) {
                    DB::table('fixed_asset_units')->whereIn('fixed_asset_id', $faIds)->delete();
                }
                if (Schema::hasTable('fixed_asset_assignments')) {
                    DB::table('fixed_asset_assignments')->whereIn('fixed_asset_id', $faIds)->delete();
                }
                if (Schema::hasTable('fixed_asset_maintenance_logs')) {
                    DB::table('fixed_asset_maintenance_logs')->whereIn('fixed_asset_id', $faIds)->delete();
                }
                DB::table('fixed_assets')->whereIn('id', $faIds)->delete();
            }

            if (!empty($productIds)) {
                if (Schema::hasTable('fixed_asset_units') && Schema::hasColumn('fixed_asset_units', 'product_id')) {
                    DB::table('fixed_asset_units')->whereIn('product_id', $productIds)->delete();
                }

                // 2. Inventory movements & Inventory records
                if (Schema::hasTable('inventory')) {
                    $invIds = DB::table('inventory')->whereIn('product_id', $productIds)->pluck('id')->toArray();
                    if (!empty($invIds) && Schema::hasTable('inventory_movements')) {
                        DB::table('inventory_movements')->whereIn('inventory_id', $invIds)->delete();
                    }
                    DB::table('inventory')->whereIn('product_id', $productIds)->delete();
                }
                if (Schema::hasTable('inventories')) {
                    $invsIds = DB::table('inventories')->whereIn('product_id', $productIds)->pluck('id')->toArray();
                    if (!empty($invsIds) && Schema::hasTable('inventory_movements')) {
                        DB::table('inventory_movements')->whereIn('inventory_id', $invsIds)->delete();
                    }
                    DB::table('inventories')->whereIn('product_id', $productIds)->delete();
                }

                // 3. Rebar dia products
                if (Schema::hasTable('rebar_dia_products')) {
                    DB::table('rebar_dia_products')->whereIn('product_id', $productIds)->delete();
                }

                // 4. Material prices
                if (Schema::hasTable('material_prices')) {
                    DB::table('material_prices')->whereIn('product_id', $productIds)->delete();
                }

                // 5. Material damage reports
                if (Schema::hasTable('material_damage_reports')) {
                    DB::table('material_damage_reports')->whereIn('product_id', $productIds)->delete();
                }

                // 6. Employee assets
                if (Schema::hasTable('employee_assets')) {
                    DB::table('employee_assets')->whereIn('product_id', $productIds)->delete();
                }

                // 7. Petty cash material purchases & items
                if (Schema::hasTable('petty_cash_material_purchase_items')) {
                    DB::table('petty_cash_material_purchase_items')->whereIn('product_id', $productIds)->delete();
                }
                if (Schema::hasTable('petty_cash_material_purchases')) {
                    DB::table('petty_cash_material_purchases')->whereIn('product_id', $productIds)->delete();
                }

                // 8. Delivery receipts & items
                if (Schema::hasTable('delivery_receipt_items')) {
                    DB::table('delivery_receipt_items')->whereIn('product_id', $productIds)->delete();
                }
                if (Schema::hasTable('delivery_receipts')) {
                    DB::table('delivery_receipts')->whereIn('product_id', $productIds)->delete();
                }

                // 9. Transfer items & transfers
                if (Schema::hasTable('transfer_items')) {
                    DB::table('transfer_items')->whereIn('product_id', $productIds)->delete();
                }
                if (Schema::hasTable('transfers')) {
                    DB::table('transfers')->whereIn('product_id', $productIds)->delete();
                }

                // 10. Purchase order items, purchase request items, material request items
                if (Schema::hasTable('purchase_order_items')) {
                    DB::table('purchase_order_items')->whereIn('product_id', $productIds)->delete();
                }
                if (Schema::hasTable('purchase_request_items')) {
                    DB::table('purchase_request_items')->whereIn('product_id', $productIds)->delete();
                }
                if (Schema::hasTable('material_request_items')) {
                    DB::table('material_request_items')->whereIn('product_id', $productIds)->delete();
                }

                // 11. Material usage items & cut optimization
                if (Schema::hasTable('material_usage_items')) {
                    DB::table('material_usage_items')->whereIn('product_id', $productIds)->delete();
                }
                if (Schema::hasTable('cut_optimization_items')) {
                    DB::table('cut_optimization_items')->whereIn('product_id', $productIds)->delete();
                }
                if (Schema::hasTable('waste_items')) {
                    DB::table('waste_items')->whereIn('product_id', $productIds)->delete();
                }
                if (Schema::hasTable('material_plan_items')) {
                    DB::table('material_plan_items')->whereIn('product_id', $productIds)->delete();
                }

                // 12. Market research
                if (Schema::hasTable('market_research_items')) {
                    DB::table('market_research_items')->whereIn('product_id', $productIds)->delete();
                }
                if (Schema::hasTable('market_research')) {
                    DB::table('market_research')->whereIn('product_id', $productIds)->delete();
                }

                // 13. Tool transactions
                if (Schema::hasTable('tool_transactions')) {
                    DB::table('tool_transactions')->whereIn('equipment_id', $productIds)->delete();
                }

                // 14. Nullify references in boq_items, takeoff_items, office_material_requests, equipment_masters
                if (Schema::hasTable('boq_items') && Schema::hasColumn('boq_items', 'product_id')) {
                    DB::table('boq_items')->whereIn('product_id', $productIds)->update(['product_id' => null]);
                }
                if (Schema::hasTable('takeoff_items') && Schema::hasColumn('takeoff_items', 'product_id')) {
                    DB::table('takeoff_items')->whereIn('product_id', $productIds)->update(['product_id' => null]);
                }
                if (Schema::hasTable('office_material_request_items') && Schema::hasColumn('office_material_request_items', 'product_id')) {
                    DB::table('office_material_request_items')->whereIn('product_id', $productIds)->update(['product_id' => null]);
                }
                if (Schema::hasTable('office_material_requests') && Schema::hasColumn('office_material_requests', 'product_id')) {
                    DB::table('office_material_requests')->whereIn('product_id', $productIds)->update(['product_id' => null]);
                }
                if (Schema::hasTable('equipment_masters') && Schema::hasColumn('equipment_masters', 'product_id')) {
                    DB::table('equipment_masters')->whereIn('product_id', $productIds)->update(['product_id' => null]);
                }
                if (Schema::hasTable('equipment_fixed_asset_units') && Schema::hasColumn('equipment_fixed_asset_units', 'product_id')) {
                    DB::table('equipment_fixed_asset_units')->whereIn('product_id', $productIds)->update(['product_id' => null]);
                }

                // 15. Force delete the Product model records
                Product::withTrashed()->whereIn('id', $productIds)->forceDelete();

                // Direct table purge for any residual row
                DB::table('products')->whereIn('id', $productIds)->delete();
            }

            // Direct table purge by SKU just in case
            DB::table('products')
                ->where('sku', $sku)
                ->orWhere('sku', $skuUpper)
                ->orWhere(DB::raw('LOWER(TRIM(sku))'), $skuLower)
                ->delete();

            Log::info("Material SKU '{$sku}' permanently deleted by Global Admin ID " . Auth::id());
        });

        return true;
    }

    private function categories(): array
    {
        return [
            'Steel & Metals', 'Cement & Concrete', 'Aggregates', 'Timber & Wood',
            'Finishing Materials', 'Plumbing', 'Electrical', 'Mechanical',
            'Safety Equipment', 'Tools & Equipment', 'Chemicals & Adhesives',
            'Paints & Coatings', 'Roofing', 'Insulation', 'Fixed Asset', 'Consumable', 'Other',
        ];
    }

    private function subCategories(): array
    {
        return [
            'Rebar', 'Structural Steel', 'Pipe', 'Fittings', 'Cables', 'Switches',
            'Valves', 'Pumps', 'Formwork', 'Scaffolding', 'PPE', 'Lubricants',
            'Adhesives', 'Sealants', 'Tiles', 'Marble', 'Glass', 'Doors', 'Windows',
            'Other',
        ];
    }

    private function units()
    {
        return \App\Models\UnitOfMeasurement::allUnits();
    }

    private function assetStatuses(): array
    {
        return ['Available', 'In Use', 'Under Maintenance', 'Damaged', 'Disposed', 'Lost'];
    }

    private function conditions(): array
    {
        return ['Excellent', 'Good', 'Fair', 'Poor', 'Damaged'];
    }
}
