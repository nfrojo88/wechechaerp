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
        return app(\App\Services\ProductPurgeService::class)->purgeBySku($sku);
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
