<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::withTrashed()->latest()->paginate(20);
        return view('suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('suppliers.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'code'           => 'required|string|max:50|unique:suppliers',
            'contact_person' => 'nullable|string|max:255',
            'phone'          => 'nullable|string|max:50',
            'email'          => 'nullable|email|max:255',
            'address'        => 'nullable|string',
            'tax_id'         => 'nullable|string|max:100',
            'status'         => 'required|in:active,inactive,blacklisted',
            'rating'         => 'nullable|numeric|min:0|max:5',
            'notes'          => 'nullable|string',
        ]);

        Supplier::create($data);
        return redirect()->route('suppliers.index')->with('success', 'Supplier created.');
    }

    public function show(Supplier $supplier)
    {
        $supplier->load(['purchaseOrders', 'marketResearch']);
        return view('suppliers.show', compact('supplier'));
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'code'           => 'required|string|max:50|unique:suppliers,code,' . $supplier->id,
            'contact_person' => 'nullable|string|max:255',
            'phone'          => 'nullable|string|max:50',
            'email'          => 'nullable|email|max:255',
            'address'        => 'nullable|string',
            'tax_id'         => 'nullable|string|max:100',
            'status'         => 'required|in:active,inactive,blacklisted',
            'rating'         => 'nullable|numeric|min:0|max:5',
            'notes'          => 'nullable|string',
        ]);

        $supplier->update($data);
        return redirect()->route('suppliers.index')->with('success', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();
        return redirect()->route('suppliers.index')->with('success', 'Supplier deleted.');
    }

    public function quickStore(Request $request)
    {
        $code = $request->code ?: ('SUP-' . date('Ymd') . '-' . str_pad(Supplier::withTrashed()->count() + 1, 4, '0', STR_PAD_LEFT));

        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'code'           => 'nullable|string|max:50|unique:suppliers,code',
            'tax_id'         => 'nullable|string|max:100',
            'phone'          => 'nullable|string|max:50',
            'email'          => 'nullable|email|max:255',
            'contact_person' => 'nullable|string|max:255',
            'address'        => 'nullable|string',
            'notes'          => 'nullable|string',
        ]);

        $data['code']   = $data['code'] ?? $code;
        $data['status'] = $data['status'] ?? 'active';

        $supplier = Supplier::create($data);

        return response()->json([
            'success'  => true,
            'message'  => 'Supplier added successfully.',
            'supplier' => $supplier,
        ]);
    }

    public function quickUpdate(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'code'           => 'nullable|string|max:50|unique:suppliers,code,' . $supplier->id,
            'tax_id'         => 'nullable|string|max:100',
            'phone'          => 'nullable|string|max:50',
            'email'          => 'nullable|email|max:255',
            'contact_person' => 'nullable|string|max:255',
            'address'        => 'nullable|string',
            'notes'          => 'nullable|string',
        ]);

        $supplier->update($data);

        return response()->json([
            'success'  => true,
            'message'  => 'Supplier updated successfully.',
            'supplier' => $supplier,
        ]);
    }

    public function apiAll()
    {
        $suppliers = Supplier::where('status', 'active')
            ->orWhereNull('status')
            ->orderBy('name')
            ->get();

        return response()->json($suppliers);
    }
}
