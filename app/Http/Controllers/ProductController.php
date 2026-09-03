<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

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
