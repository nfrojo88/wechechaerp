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
        @ini_set('max_execution_time', 120);
        @set_time_limit(120);
    }

    /**
     * Slip Sequence Configuration Dashboard
     */
    public function index()
    {
        @ini_set('max_execution_time', 120);
        @set_time_limit(120);
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
        $validated = $request->validate([
            'store_id'      => 'required|exists:stores,id',
            'slip_type'     => 'required|in:receive,send',
            'label'         => 'required|string|max:100',
            'prefix'        => 'nullable|string|max:50',
            'book_start_no' => 'required|integer|min:1',
            'book_end_no'   => 'required|integer|gt:book_start_no',
            'notes'         => 'nullable|string',
        ]);

        // Validate current_slip_no separately after numeric coercion to avoid gte/lte field-compare failures
        $startNo  = (int) $request->book_start_no;
        $endNo    = (int) $request->book_end_no;
        $rawCurrent = $request->input('current_slip_no');
        if ($rawCurrent !== null && $rawCurrent !== '') {
            $currentSlipNo = (int) $rawCurrent;
            if ($currentSlipNo < $startNo || $currentSlipNo > $endNo) {
                return back()->withInput()->withErrors([
                    'current_slip_no' => "Next Available Slip must be between {$startNo} and {$endNo}.",
                ]);
            }
        } else {
            $currentSlipNo = $startNo;
        }

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
            'book_start_no'   => $startNo,
            'book_end_no'     => (int) $request->book_end_no,
            'current_slip_no' => $currentSlipNo,
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

        // Validate current_slip_no separately after numeric coercion to avoid gte/lte field-compare failures
        $bookStartNo = (int) $request->book_start_no;
        $bookEndNo   = (int) $request->book_end_no;
        $rawCurrent  = $request->input('current_slip_no');
        if ($rawCurrent !== null && $rawCurrent !== '') {
            $currentSlipNo = (int) $rawCurrent;
            if ($currentSlipNo < $bookStartNo || $currentSlipNo > $bookEndNo) {
                return back()->withInput()->withErrors([
                    'current_slip_no' => "Next Available Slip must be between {$bookStartNo} and {$bookEndNo}.",
                ]);
            }
        } else {
            $currentSlipNo = $bookStartNo;
        }

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
            'book_start_no'   => $bookStartNo,
            'book_end_no'     => (int) $request->book_end_no,
            'current_slip_no' => $currentSlipNo,
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
            'label'           => 'required|string|max:100',
            'prefix'          => 'nullable|string|max:50',
            'current_slip_no' => 'nullable|integer|min:' . $slipSequence->book_start_no . '|max:' . $slipSequence->book_end_no,
            'notes'           => 'nullable|string',
        ]);

        $data = $request->only(['label', 'prefix', 'notes']);
        if ($request->filled('current_slip_no')) {
            $data['current_slip_no'] = (int) $request->current_slip_no;
        }

        $slipSequence->update($data);

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
     * Void missing, spoiled, or damaged slips to allow sequence to advance.
     * Supports array of numbers, comma-separated string, ranges, or auto-voiding next expected slip.
     */
    public function voidMissing(Request $request)
    {
        $slipService = app(\App\Services\SlipSequenceService::class);

        // Normalize raw numbers input from multiple possible keys
        $rawNumbers = $request->input('numbers', $request->input('slip_number_input', $request->input('slip_number', $request->input('slip_no', $request->input('target_slip_no')))));
        
        $parsedNumbers = $slipService->parseSlipNumbersInput($rawNumbers);

        // If numbers were not provided or empty, attempt to default to the sequence's next expected slip
        if (empty($parsedNumbers) && $request->filled('store_id') && $request->filled('slip_type')) {
            $expected = $slipService->getNextExpected((int)$request->store_id, $request->slip_type);
            if (!empty($expected['has_sequence']) && !empty($expected['current_slip_no'])) {
                $parsedNumbers = [(int)$expected['current_slip_no']];
            }
        }

        $request->merge(['numbers' => $parsedNumbers]);

        $request->validate([
            'store_id'     => 'required|exists:stores,id',
            'slip_type'    => 'required|in:receive,send',
            'numbers'      => 'required|array|min:1',
            'numbers.*'    => 'required|integer',
            'reason'       => 'required|string|max:500',
            'slip_receipt' => 'nullable|file|mimes:jpeg,jpg,png,pdf,webp|max:10240',
            'attachment'   => 'nullable|file|mimes:jpeg,jpg,png,pdf,webp|max:10240',
        ]);

        try {
            $receiptPath = null;
            $file = $request->file('slip_receipt') ?: $request->file('attachment');
            if ($file) {
                $receiptPath = \App\Services\FileUploadService::upload($file, 'slips/void');
            }

            $result = $slipService->voidMissingSlips(
                (int)$request->store_id,
                $request->slip_type,
                $request->numbers,
                $request->reason,
                \Illuminate\Support\Facades\Auth::id(),
                $receiptPath
            );

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success'           => true,
                    'message'           => "Successfully voided {$result['voided_count']} slip(s). Next expected slip is now #{$result['next_expected']}.",
                    'voided_count'      => $result['voided_count'],
                    'voided_numbers'    => $result['voided_numbers'],
                    'formatted_numbers' => $result['formatted_numbers'] ?? array_map(fn($n) => (string)$n, $result['voided_numbers']),
                    'next_expected'     => $result['next_expected'],
                    'data'              => $result,
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

    /**
     * Delete empty sequence book (where no slips are added) or force delete.
     */
    public function destroy(SlipSequence $slipSequence, Request $request = null)
    {
        $req = $request ?: request();
        $assignedSlips = method_exists($slipSequence, 'getAssignedSlipsDetail')
            ? $slipSequence->getAssignedSlipsDetail()
            : collect();

        $hasSlips = ($slipSequence->used_count > 0) || $assignedSlips->isNotEmpty();

        if ($hasSlips && !$req->boolean('force')) {
            return back()->withErrors([
                'error' => "Cannot delete sequence book #{$slipSequence->book_start_no}–#{$slipSequence->book_end_no}: {$assignedSlips->count()} slip(s) are recorded in this sequence book. You must delete or unassign the slips first."
            ]);
        }

        $label = $slipSequence->label;
        $range = "#{$slipSequence->book_start_no}–#{$slipSequence->book_end_no}";
        $storeId = $slipSequence->store_id;
        $slipType = $slipSequence->slip_type;

        $slipSequence->delete();

        // Maintain active sequence for remaining books
        $remainingActive = SlipSequence::where('store_id', $storeId)
            ->where('slip_type', $slipType)
            ->where('status', 'active')
            ->first();

        if (!$remainingActive) {
            $latestBook = SlipSequence::where('store_id', $storeId)
                ->where('slip_type', $slipType)
                ->where('status', '!=', 'full')
                ->latest('book_end_no')
                ->first();

            if ($latestBook) {
                $latestBook->update(['status' => 'active']);
                $latestBook->recalculate(save: true);
            }
        }

        $msg = "Slip sequence book {$label} ({$range}) deleted successfully.";
        if ($req->wantsJson() || $req->ajax()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return redirect()->route('store-manager.slip-sequences.index')->with('success', $msg);
    }

    /**
     * Delete an individual slip history record.
     */
    public function destroyHistorySlip(Request $request)
    {
        $request->validate([
            'source_type' => 'required|string|in:delivery_receipt,procurement,petty_cash,transfer_out,transfer_in,transfer,manual,void,inventory_movement',
            'document_id' => 'required|integer',
            'slip_no'     => 'nullable|string',
            'numeric_no'  => 'nullable|integer',
        ]);

        $sourceType = (string) $request->input('source_type');
        $documentId = (int) $request->input('document_id');
        $slipNo = $request->input('slip_no');
        $numericNo = $request->input('numeric_no') ? (int) $request->input('numeric_no') : null;

        $deleted = $this->deleteSlipItem($sourceType, $documentId, $numericNo, $slipNo);

        $this->recalculateAllSequences();

        $displayName = $slipNo ? "#{$slipNo}" : ($numericNo ? "#{$numericNo}" : "record");

        if (!$deleted) {
            $errMsg = "Could not find or delete slip {$displayName} from {$sourceType}.";
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'error' => $errMsg], 404);
            }
            return back()->withErrors(['error' => $errMsg]);
        }

        $msg = "Slip {$displayName} removed from slip history successfully.";
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Bulk delete all slips from slip history that have no sequence book assigned (Manual / Other).
     */
    public function destroyUnassignedHistory(Request $request)
    {
        $filters = [
            'store_id'  => $request->input('store_id'),
            'slip_type' => $request->input('slip_type'),
        ];

        $allSlips = collect();
        if (method_exists(SlipSequence::class, 'getGlobalSlipHistory')) {
            $allSlips = SlipSequence::getGlobalSlipHistory($filters);
        }

        $unassigned = $allSlips->filter(function ($slip) {
            return empty($slip['book_id']) || ($slip['book_range'] ?? '') === 'Manual / Other';
        });

        $count = 0;
        foreach ($unassigned as $slip) {
            $deleted = $this->deleteSlipItem(
                $slip['source_type'],
                (int) $slip['document_id'],
                $slip['numeric_no'] ? (int) $slip['numeric_no'] : null,
                $slip['slip_no'] ?? null
            );
            if ($deleted) {
                $count++;
            }
        }

        $this->recalculateAllSequences();

        $msg = "Deleted {$count} slip record(s) with no sequence book assigned.";
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $msg, 'deleted_count' => $count]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Helper to delete/clear a slip item from DeliveryReceipt, Transfer, or InventoryMovement.
     */
    protected function deleteSlipItem(string $sourceType, int $documentId, ?int $numericNo, ?string $slipNo): bool
    {
        $deleted = false;

        if (in_array($sourceType, ['delivery_receipt', 'procurement', 'petty_cash', 'transfer_out', 'transfer_in', 'manual', 'void']) || DeliveryReceipt::where('id', $documentId)->exists()) {
            $receipt = DeliveryReceipt::find($documentId);
            if ($receipt) {
                try {
                    $receipt->items()->delete();
                } catch (\Throwable $e) {}

                if (!empty($receipt->slip_receipt_path)) {
                    try {
                        \App\Services\FileUploadService::delete($receipt->slip_receipt_path);
                    } catch (\Throwable $e) {}
                }

                $receipt->delete();
                $deleted = true;
            }
        } elseif ($sourceType === 'transfer') {
            $transfer = \App\Models\Transfer::withTrashed()->find($documentId);
            if ($transfer) {
                $matched = false;
                if ($numericNo !== null && (int)$this->extractFirstNumber($transfer->outgoing_slip_no) === $numericNo) {
                    $transfer->outgoing_slip_no = null;
                    $matched = true;
                } elseif (!empty($slipNo) && $transfer->outgoing_slip_no == $slipNo) {
                    $transfer->outgoing_slip_no = null;
                    $matched = true;
                }

                if ($numericNo !== null && (int)$this->extractFirstNumber($transfer->receiving_slip_no) === $numericNo) {
                    $transfer->receiving_slip_no = null;
                    $matched = true;
                } elseif (!empty($slipNo) && $transfer->receiving_slip_no == $slipNo) {
                    $transfer->receiving_slip_no = null;
                    $matched = true;
                }

                if ($numericNo !== null && (int)$this->extractFirstNumber($transfer->physical_slip_no) === $numericNo) {
                    $transfer->physical_slip_no = null;
                    $matched = true;
                } elseif (!empty($slipNo) && $transfer->physical_slip_no == $slipNo) {
                    $transfer->physical_slip_no = null;
                    $matched = true;
                }

                if (!$matched) {
                    if ($transfer->outgoing_slip_no) {
                        $transfer->outgoing_slip_no = null;
                    } elseif ($transfer->receiving_slip_no) {
                        $transfer->receiving_slip_no = null;
                    } elseif ($transfer->physical_slip_no) {
                        $transfer->physical_slip_no = null;
                    }
                }

                $transfer->save();

                // Clean mirrored delivery receipts
                try {
                    $trReceipts = DeliveryReceipt::where('transfer_id', $transfer->id)->get();
                    foreach ($trReceipts as $trReceipt) {
                        $trNum = (int)$this->extractFirstNumber($trReceipt->dr_no)
                              ?? (int)$this->extractFirstNumber($trReceipt->slip_no)
                              ?? (int)$this->extractFirstNumber($trReceipt->reference_no);
                        if ($numericNo === null || $trNum === $numericNo || $trReceipt->slip_no == $slipNo || $trReceipt->dr_no == $slipNo) {
                            try { $trReceipt->items()->delete(); } catch (\Throwable $e) {}
                            $trReceipt->delete();
                        }
                    }
                } catch (\Throwable $e) {}

                $deleted = true;
            }
        } elseif ($sourceType === 'inventory_movement') {
            try {
                $movement = \App\Models\InventoryMovement::find($documentId);
                if ($movement) {
                    $movement->delete();
                    $deleted = true;
                }
            } catch (\Throwable $e) {}
        }

        // Clean any dangling delivery receipt matching slip_no or numeric_no
        if (!empty($numericNo) || !empty($slipNo)) {
            try {
                $dangling = DeliveryReceipt::where(function ($q) use ($numericNo, $slipNo) {
                    if (!empty($slipNo)) {
                        $q->where('slip_no', $slipNo)
                          ->orWhere('dr_no', $slipNo);
                    }
                    if (!empty($numericNo)) {
                        $q->orWhere('slip_no', (string)$numericNo)
                          ->orWhere('dr_no', (string)$numericNo);
                    }
                })->get();

                foreach ($dangling as $dReceipt) {
                    try { $dReceipt->items()->delete(); } catch (\Throwable $e) {}
                    $dReceipt->delete();
                    $deleted = true;
                }
            } catch (\Throwable $e) {}
        }

        return $deleted;
    }

    /**
     * Helper to extract first integer in text.
     */
    protected function extractFirstNumber($str): ?int
    {
        if (empty($str)) return null;
        if (preg_match_all('/\d+/', (string) $str, $matches)) {
            foreach ($matches[0] as $match) {
                $val = (int) $match;
                if ($val > 0) return $val;
            }
        }
        return null;
    }

    /**
     * Recalculate all sequence books to synchronize current counters and status.
     */
    protected function recalculateAllSequences(): void
    {
        try {
            $sequences = SlipSequence::all();
            foreach ($sequences as $seq) {
                $seq->recalculate(save: true);
            }
        } catch (\Throwable $e) {}
    }
}
