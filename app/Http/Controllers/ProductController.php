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
        $query = Product::query();

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

        // Order alphabetically by product/material name by default
        $sort = $request->input('sort', 'name');
        $direction = strtolower($request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        if (in_array($sort, ['name', 'sku', 'category', 'unit', 'unit_price', 'max_stock'])) {
            $query->orderBy($sort, $direction)->orderBy('id', 'asc');
        } elseif ($sort === 'created_at') {
            $query->orderBy('created_at', $direction);
        } else {
            $query->orderBy('name', 'asc')->orderBy('id', 'asc');
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

        // Parse one or multiple SKUs separated by comma or newlines (supports numbered lists e.g. "1 FA-BUST")
        $skus = $this->parseSkusFromInput($rawInput);

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

        $purgeResult = app(\App\Services\ProductPurgeService::class)->purgeSkus($skus);
        $deletedCount = $purgeResult['deleted_count'];
        $deletedSkus = $purgeResult['deleted_skus'];
        $failedSkus = $purgeResult['failed_skus'];

        if ($deletedCount > 0 && empty($failedSkus)) {
            $skuList = implode(', ', $deletedSkus);
            return back()->with('success', "Material [SKU: {$skuList}] has been completely and permanently deleted from the system, including inventory, movements, and ledger references.");
        } elseif ($deletedCount > 0 && !empty($failedSkus)) {
            $skuList = implode(', ', $deletedSkus);
            $failList = implode('; ', $failedSkus);
            return back()->with('warning', "Successfully deleted {$deletedCount} SKU(s): [{$skuList}]. Notice: {$failList}.");
        } else {
            $failList = implode('; ', $failedSkus);
            return back()->with('error', "Complete delete failed: {$failList}.");
        }
    }

    /**
     * Convert Material(s) from Fixed Asset to Consumable by SKU (Global Admin only).
     * Retains inventory on-hand balances, removes serialized fixed asset units/records,
     * and sets category to 'Consumable'.
     */
    public function convertToConsumableBySku(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->isGlobalAdmin()) {
            abort(403, 'Unauthorized: Converting material category by SKU is strictly reserved for Global Admin.');
        }

        $request->validate([
            'sku' => ['required', 'string'],
            'confirm_action' => ['required', 'string'],
        ]);

        $rawInput = trim((string)$request->input('sku'));
        $confirmInput = trim((string)$request->input('confirm_action'));

        $skus = $this->parseSkusFromInput($rawInput);

        if (empty($skus)) {
            return back()->with('error', 'Please provide at least one valid material SKU code to convert.');
        }

        $firstSku = $skus[0];
        $isConfirmed = (strtoupper($confirmInput) === 'CONVERT')
            || (strcasecmp($confirmInput, $firstSku) === 0)
            || (strcasecmp($confirmInput, $rawInput) === 0);

        if (!$isConfirmed) {
            return back()->with('error', "Confirmation failed: You must type 'CONVERT' to confirm converting the selected materials to Consumable.");
        }

        $result = app(\App\Services\MaterialToConsumableService::class)->convertSkusToConsumable($skus);

        $convertedCount = $result['converted_count'];
        $faRemoved = $result['fixed_assets_removed'];
        $unitsRemoved = $result['units_removed'];
        $notFound = $result['not_found'];
        $convertedProducts = $result['converted_products'];

        if ($convertedCount > 0 && empty($notFound)) {
            $prodNames = array_map(function ($p) {
                return "{$p['sku']} ({$p['name']})";
            }, array_slice($convertedProducts, 0, 10));
            $summary = implode(', ', $prodNames);
            if (count($convertedProducts) > 10) {
                $summary .= ' and ' . (count($convertedProducts) - 10) . ' more';
            }
            return back()->with('success', "Successfully converted {$convertedCount} material(s) [{$summary}] to Consumable. Serialized assets/units removed ({$faRemoved} assets, {$unitsRemoved} units) while all store inventory stock on-hand was safely preserved.");
        } elseif ($convertedCount > 0 && !empty($notFound)) {
            $prodNames = array_map(fn($p) => $p['sku'], array_slice($convertedProducts, 0, 8));
            $missing = implode(', ', array_slice($notFound, 0, 8));
            return back()->with('warning', "Converted {$convertedCount} material(s) [SKUs: " . implode(', ', $prodNames) . "] to Consumable ({$faRemoved} assets, {$unitsRemoved} units removed). Not found or already consumable: [{$missing}].");
        } else {
            $missing = implode(', ', array_slice($notFound, 0, 10));
            return back()->with('error', "Conversion failed: No active Fixed Asset materials matched the provided SKU(s): [{$missing}].");
        }
    }

    /**
     * Parse multi-line, comma-separated, or numbered text input into unique clean SKU strings.
     */
    protected function parseSkusFromInput(string $input): array
    {
        $lines = preg_split('/[\r\n,]+/', $input);
        $skus = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strtoupper($line) === 'DELETE' || strtoupper($line) === 'CONVERT') {
                continue;
            }
            // Strip numeric list prefixes e.g. "1\tFA-BUST", "1. FA-BUST", "1 FA-BUST"
            if (preg_match('/^[0-9]+[\.\t\s]+(.+)$/', $line, $m)) {
                $line = trim($m[1]);
            }
            if ($line !== '' && strtoupper($line) !== 'DELETE' && strtoupper($line) !== 'CONVERT') {
                $skus[] = $line;
            }
        }
        return array_values(array_unique($skus));
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
        if (preg_match('/^[0-9]+[\.\t\s]+(.+)$/', $sku, $m)) {
            $sku = trim($m[1]);
        }

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
