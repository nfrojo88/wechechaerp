<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\SlipSequence;
use Illuminate\Http\Request;

class SlipSequenceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Slip Sequence Configuration Dashboard
     */
    public function index()
    {
        $sequences = SlipSequence::with('store')->latest()->paginate(20);
        $stores = Store::where('is_active', true)->orderBy('name')->get();
        
        return view('slip-sequences.index', compact('sequences', 'stores'));
    }

    /**
     * Create New Slip Sequence
     */
    public function create()
    {
        $stores = Store::where('is_active', true)->orderBy('name')->get();
        return view('slip-sequences.create', compact('stores'));
    }

    /**
     * Store Slip Sequence
     */
    public function store(Request $request)
    {
        $request->validate([
            'store_id'     => 'required|exists:stores,id',
            'slip_type'    => 'required|in:receive,send',
            'label'        => 'required|string|max:100',
            'prefix'       => 'nullable|string|max:50',
            'book_start_no'=> 'required|integer|min:1',
            'book_end_no'  => 'required|integer|gt:book_start_no',
            'notes'        => 'nullable|string',
        ]);

        // Check if active sequence already exists for this store + type
        $existing = SlipSequence::where('store_id', $request->store_id)
            ->where('slip_type', $request->slip_type)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return back()->withErrors(['slip_type' => "Active sequence already exists for {$existing->label}"]);
        }

        SlipSequence::create([
            'store_id'      => $request->store_id,
            'slip_type'     => $request->slip_type,
            'label'         => $request->label,
            'prefix'        => $request->prefix,
            'book_start_no' => $request->book_start_no,
            'book_end_no'   => $request->book_end_no,
            'current_slip_no' => $request->book_start_no,
            'used_count'    => 0,
            'status'        => 'active',
            'notes'         => $request->notes,
        ]);

        return redirect()->route('store-manager.slip-sequences.index')->with('success', 'Slip sequence configured successfully.');
    }

    /**
     * Edit Slip Sequence
     */
    public function edit(SlipSequence $slipSequence)
    {
        $stores = Store::where('is_active', true)->orderBy('name')->get();
        return view('slip-sequences.edit', compact('slipSequence', 'stores'));
    }

    /**
     * Update Slip Sequence
     */
    public function update(Request $request, SlipSequence $slipSequence)
    {
        $request->validate([
            'label'   => 'required|string|max:100',
            'prefix'  => 'nullable|string|max:50',
            'notes'   => 'nullable|string',
        ]);

        $slipSequence->update($request->only(['label', 'prefix', 'notes']));

        return redirect()->route('store-manager.slip-sequences.index')->with('success', 'Slip sequence updated.');
    }

    /**
     * Mark as Inactive
     */
    public function deactivate(SlipSequence $slipSequence)
    {
        $slipSequence->update(['status' => 'inactive']);
        return back()->with('success', 'Slip sequence deactivated.');
    }

    /**
     * Reactivate (if not full)
     */
    public function reactivate(SlipSequence $slipSequence)
    {
        if ($slipSequence->status === 'full') {
            return back()->withErrors(['status' => 'Cannot reactivate a full slip sequence book.']);
        }

        // Deactivate other active sequences for this store + type
        SlipSequence::where('store_id', $slipSequence->store_id)
            ->where('slip_type', $slipSequence->slip_type)
            ->where('status', 'active')
            ->update(['status' => 'inactive']);

        $slipSequence->update(['status' => 'active']);
        return back()->with('success', 'Slip sequence reactivated.');
    }

    /**
     * Reset sequence (admin only)
     */
    public function reset(SlipSequence $slipSequence)
    {
        $this->authorize('admin');
        
        $slipSequence->update([
            'current_slip_no' => $slipSequence->book_start_no,
            'used_count' => 0,
            'status' => 'active',
        ]);

        return back()->with('success', 'Slip sequence reset to start.');
    }

    /**
     * Get next available slip for store + type (API)
     */
    public function getNextSlip($storeId, $slipType)
    {
        $sequence = SlipSequence::where('store_id', $storeId)
            ->where('slip_type', $slipType)
            ->where('status', 'active')
            ->first();

        if (!$sequence) {
            return response()->json([
                'has_sequence' => false,
                'error'        => 'No active slip sequence configured for this store.',
            ], 200);
        }

        return response()->json([
            'has_sequence'    => true,
            'id'              => $sequence->id,
            'next_slip_no'    => $sequence->getNextSlipNumber(),
            'formatted_slip'  => $sequence->formatSlipNumber($sequence->current_slip_no),
            'prefix'          => $sequence->prefix,
            'label'           => $sequence->label,
            'book_start_no'   => $sequence->book_start_no,
            'book_end_no'     => $sequence->book_end_no,
            'current_slip_no' => $sequence->current_slip_no,
            'used_count'      => $sequence->used_count,
            'remaining'       => $sequence->getRemainingSlips(),
            'percentage_used' => $sequence->getPercentageUsed(),
        ]);
    }
}
