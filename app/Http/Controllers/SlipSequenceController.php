<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\SlipSequence;
use App\Models\DeliveryReceipt;
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
            if ($request->boolean('archive_previous') || $existing->status === 'full' || $existing->current_slip_no > $existing->book_end_no) {
                try {
                    $existing->update(['status' => 'full']);
                } catch (\Illuminate\Database\QueryException $e) {
                    if (str_contains($e->getMessage(), 'unique_active_per_store_type') || $e->getCode() == 23000) {
                        $this->dropFlawedUniqueIndexIfPresent();
                        $existing->update(['status' => 'full']);
                    } else {
                        throw $e;
                    }
                }
            } else {
                return back()->withInput()->withErrors([
                    'slip_type' => "Active sequence already exists for {$existing->label} (#{$existing->book_start_no}–#{$existing->book_end_no}). Select 'Archive previous sequence' to activate this new book without deleting any history."
                ]);
            }
        }

        $seq = SlipSequence::create([
            'store_id'        => $request->store_id,
            'slip_type'       => $request->slip_type,
            'label'           => $request->label,
            'prefix'          => $request->prefix,
            'book_start_no'   => $request->book_start_no,
            'book_end_no'     => $request->book_end_no,
            'current_slip_no' => $request->book_start_no,
            'used_count'      => 0,
            'status'          => 'active',
            'notes'           => $request->notes,
        ]);

        return redirect()->route('store-manager.slip-sequences.edit', $seq)->with('success', "New sequence book (#{$seq->book_start_no}–#{$seq->book_end_no}) configured and activated. All past slips remain preserved in history.");
    }

    /**
     * Seamlessly transition to Next Sequence Book for this store + type
     */
    public function storeNextBook(Request $request, SlipSequence $slipSequence)
    {
        $request->validate([
            'label'         => 'required|string|max:100',
            'prefix'        => 'nullable|string|max:50',
            'book_start_no' => 'required|integer|min:1',
            'book_end_no'   => 'required|integer|gt:book_start_no',
            'notes'         => 'nullable|string',
        ]);

        // Archive / mark previous sequence book as full
        try {
            if ($request->boolean('archive_previous', true)) {
                $slipSequence->update(['status' => 'full']);
            }

            // Deactivate any other active sequence for this store + type
            SlipSequence::where('store_id', $slipSequence->store_id)
                ->where('slip_type', $slipSequence->slip_type)
                ->where('status', 'active')
                ->where('id', '!=', $slipSequence->id)
                ->update(['status' => 'full']);
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'unique_active_per_store_type') || $e->getCode() == 23000) {
                $this->dropFlawedUniqueIndexIfPresent();
                if ($request->boolean('archive_previous', true)) {
                    $slipSequence->update(['status' => 'full']);
                }
                SlipSequence::where('store_id', $slipSequence->store_id)
                    ->where('slip_type', $slipSequence->slip_type)
                    ->where('status', 'active')
                    ->where('id', '!=', $slipSequence->id)
                    ->update(['status' => 'full']);
            } else {
                throw $e;
            }
        }

        // Create the new active sequence
        $newSequence = SlipSequence::create([
            'store_id'        => $slipSequence->store_id,
            'slip_type'       => $slipSequence->slip_type,
            'label'           => $request->label,
            'prefix'          => $request->prefix !== null ? $request->prefix : $slipSequence->prefix,
            'book_start_no'   => (int) $request->book_start_no,
            'book_end_no'     => (int) $request->book_end_no,
            'current_slip_no' => (int) $request->book_start_no,
            'used_count'      => 0,
            'status'          => 'active',
            'notes'           => $request->notes ?: "Continuation after Book #{$slipSequence->book_start_no}–#{$slipSequence->book_end_no}",
        ]);

        return redirect()->route('store-manager.slip-sequences.edit', $newSequence)->with('success', "Next Sequence Book (#{$newSequence->book_start_no}–#{$newSequence->book_end_no}) has been activated! All previous slip records (#{$slipSequence->book_start_no}–#{$slipSequence->book_end_no}) are 100% preserved and viewable below.");
    }

    /**
     * Display / Show Slip Sequence details
     */
    public function show(SlipSequence $slipSequence, Request $request)
    {
        return $this->edit($slipSequence, $request);
    }

    /**
     * Edit Slip Sequence
     */
    public function edit(SlipSequence $slipSequence, Request $request = null)
    {
        $slipSequence->load('store');
        $stores = Store::where('is_active', true)->orderBy('name')->get();

        // All sequence books for this store and slip type
        $allStoreSequences = SlipSequence::where('store_id', $slipSequence->store_id)
            ->where('slip_type', $slipSequence->slip_type)
            ->orderBy('book_start_no', 'desc')
            ->get();

        // Slips assigned to this specific book
        $assignedSlips = method_exists($slipSequence, 'getAssignedSlipsDetail')
            ? $slipSequence->getAssignedSlipsDetail()
            : collect();
        $bookMap = method_exists($slipSequence, 'getBookRangeMap')
            ? $slipSequence->getBookRangeMap($assignedSlips)
            : [];

        // ALL slips ever assigned for this store & slip type across all books
        $allStoreSlips = collect();
        try {
            if (method_exists($slipSequence, 'getAllStoreSlipsDetail')) {
                $allStoreSlips = $slipSequence->getAllStoreSlipsDetail();
            } elseif (method_exists(SlipSequence::class, 'getGlobalSlipHistory')) {
                $allStoreSlips = SlipSequence::getGlobalSlipHistory([
                    'store_id'  => $slipSequence->store_id,
                    'slip_type' => $slipSequence->slip_type,
                ], $slipSequence->id);
            } else {
                $allStoreSlips = $assignedSlips;
            }
        } catch (\Throwable $e) {
            $allStoreSlips = $assignedSlips;
        }

        return view('slip-sequences.edit', compact(
            'slipSequence', 
            'stores', 
            'assignedSlips', 
            'bookMap',
            'allStoreSequences',
            'allStoreSlips'
        ));
    }

    /**
     * Master Slip History Hub across all sequences and stores
     */
    public function history(Request $request)
    {
        $stores = Store::where('is_active', true)->orderBy('name')->get();
        $sequences = SlipSequence::with('store')->orderBy('store_id')->orderBy('book_start_no')->get();

        $filters = [
            'store_id'    => $request->store_id,
            'slip_type'   => $request->slip_type,
            'source_type' => $request->source_type,
            'sequence_id' => $request->sequence_id,
            'search'      => $request->search,
        ];

        $allSlips = collect();
        try {
            if (method_exists(SlipSequence::class, 'getGlobalSlipHistory')) {
                $allSlips = SlipSequence::getGlobalSlipHistory($filters);
            }
        } catch (\Throwable $e) {
            $allSlips = collect();
        }

        $reconciliationReports = [];
        try {
            $reconciliationReports = app(\App\Services\SlipSequenceService::class)->reconcileAll();
        } catch (\Throwable $e) {}

        return view('slip-sequences.history', compact('stores', 'sequences', 'allSlips', 'filters', 'reconciliationReports'));
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
        try {
            $slipSequence->update(['status' => 'inactive']);
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'unique_active_per_store_type') || $e->getCode() == 23000) {
                $this->dropFlawedUniqueIndexIfPresent();
                $slipSequence->update(['status' => 'inactive']);
            } else {
                throw $e;
            }
        }
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

        try {
            // Deactivate other active sequences for this store + type
            SlipSequence::where('store_id', $slipSequence->store_id)
                ->where('slip_type', $slipSequence->slip_type)
                ->where('status', 'active')
                ->where('id', '!=', $slipSequence->id)
                ->update(['status' => 'inactive']);

            $slipSequence->update(['status' => 'active']);
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'unique_active_per_store_type') || $e->getCode() == 23000) {
                $this->dropFlawedUniqueIndexIfPresent();
                SlipSequence::where('store_id', $slipSequence->store_id)
                    ->where('slip_type', $slipSequence->slip_type)
                    ->where('status', 'active')
                    ->where('id', '!=', $slipSequence->id)
                    ->update(['status' => 'inactive']);

                $slipSequence->update(['status' => 'active']);
            } else {
                throw $e;
            }
        }

        return back()->with('success', 'Slip sequence reactivated.');
    }

    /**
     * Safely drop flawed unique_active_per_store_type index if still present in MySQL.
     */
    protected function dropFlawedUniqueIndexIfPresent(): void
    {
        try {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE slip_sequences DROP INDEX unique_active_per_store_type");
        } catch (\Throwable $e) {
            // Index already dropped or not present
        }
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
     * Cross-check / Reconcile sequence book continuity
     */
    public function crossCheck(SlipSequence $slipSequence)
    {
        $slipService = app(\App\Services\SlipSequenceService::class);
        $report = $slipService->reconcile($slipSequence);

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json($report);
        }

        return back()->with('cross_check_report', $report);
    }

    /**
     * Void missing slips to allow sequence to advance
     */
    public function voidMissing(Request $request)
    {
        $request->validate([
            'store_id'  => 'required|exists:stores,id',
            'slip_type' => 'required|in:receive,send',
            'numbers'   => 'required|array|min:1',
            'numbers.*' => 'required|integer',
            'reason'    => 'required|string|max:500',
        ]);

        try {
            $slipService = app(\App\Services\SlipSequenceService::class);
            $result = $slipService->voidMissingSlips(
                (int)$request->store_id,
                $request->slip_type,
                $request->numbers,
                $request->reason,
                \Illuminate\Support\Facades\Auth::id()
            );

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Successfully voided {$result['voided_count']} slip(s).",
                    'data'    => $result,
                ]);
            }

            return back()->with('success', "Successfully voided {$result['voided_count']} slip(s). Next expected slip is now #{$result['next_expected']}.");
        } catch (\Throwable $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Get next available slip for store + type (API)
     */
    public function getNextSlip($storeId, $slipType)
    {
        $slipService = app(\App\Services\SlipSequenceService::class);
        $data = $slipService->getNextExpected((int)$storeId, $slipType);

        if (!$data['has_sequence']) {
            return response()->json([
                'has_sequence' => false,
                'error'        => $data['error'] ?? 'No active slip sequence configured for this store.',
            ], 200);
        }

        return response()->json([
            'has_sequence'    => true,
            'id'              => $data['sequence_id'] ?? null,
            'next_slip_no'    => $data['current_slip_no'],
            'formatted_slip'  => $data['formatted_slip'],
            'prefix'          => $data['prefix'],
            'label'           => $data['label'],
            'book_start_no'   => $data['book_start_no'],
            'book_end_no'     => $data['book_end_no'],
            'current_slip_no' => $data['current_slip_no'],
            'used_count'      => $data['used_count'],
            'remaining'       => $data['remaining'],
            'percentage_used' => $data['percentage_used'],
            'is_full'         => $data['is_full'],
        ]);
    }

    /**
     * Check all currently added slips in Store Manager & Store Keeper,
     * accept all added slips, automatically fill any missing gap numbers as void entries,
     * and synchronize active sequence counters.
     */
    public function syncAndFillGaps(Request $request)
    {
        $slipService = app(\App\Services\SlipSequenceService::class);
        $storeId = $request->input('store_id') ? (int)$request->input('store_id') : null;
        $slipType = $request->input('slip_type') ?: null;

        $result = $slipService->checkAndFillMissingSlips($storeId, $slipType);

        $gapsFilled = $result['gaps_filled'];
        $updatedCount = count($result['sequences_updated']);

        if ($gapsFilled > 0 || $updatedCount > 0) {
            $msg = "Checked current added slips across Store Manager & Store Keeper. Filled {$gapsFilled} missing slip gap(s) and synchronized {$updatedCount} sequence book(s). All entered slips accepted.";
            return back()->with('success', $msg);
        }

        return back()->with('info', "Checked all currently added slips in Store Manager and Store Keeper sections: All sequences are continuous with zero missing gaps.");
    }
}
