<?php

namespace App\Services;

use App\Models\Store;
use App\Models\SlipSequence;
use App\Models\DeliveryReceipt;
use App\Models\Transfer;
use App\Exceptions\SlipSequenceException;
use App\Exceptions\SlipSequenceGapException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class SlipSequenceService
{
    /**
     * Get the next expected slip number and status for a store and slip type
     */
    public function getNextExpected(int $storeId, string $slipType): array
    {
        $sequence = SlipSequence::where('store_id', $storeId)
            ->where('slip_type', $slipType)
            ->where('status', 'active')
            ->first();

        if (!$sequence) {
            $fullSequence = SlipSequence::where('store_id', $storeId)
                ->where('slip_type', $slipType)
                ->where('status', 'full')
                ->latest()
                ->first();

            if ($fullSequence) {
                return [
                    'has_sequence'    => true,
                    'sequence_id'     => $fullSequence->id,
                    'label'           => $fullSequence->label,
                    'prefix'          => $fullSequence->prefix,
                    'book_start_no'   => (int)$fullSequence->book_start_no,
                    'book_end_no'     => (int)$fullSequence->book_end_no,
                    'current_slip_no' => (int)$fullSequence->current_slip_no,
                    'formatted_slip'  => $fullSequence->formatSlipNumber((int)$fullSequence->current_slip_no),
                    'remaining'       => 0,
                    'percentage_used' => 100,
                    'used_count'      => (int)$fullSequence->used_count,
                    'is_full'         => true,
                    'status'          => 'full',
                    'error'           => "Slip sequence book '{$fullSequence->label}' (#{$fullSequence->book_start_no}–#{$fullSequence->book_end_no}) is full. Cannot issue more slips. Please activate the Next Book first.",
                ];
            }

            return [
                'has_sequence'    => false,
                'is_full'         => false,
                'error'           => "No active slip sequence configured for this store and slip type ({$slipType}).",
                'current_slip_no' => null,
                'formatted_slip'  => null,
                'remaining'       => 0,
            ];
        }

        $isFull = $sequence->status === 'full' || (int)$sequence->current_slip_no > (int)$sequence->book_end_no;
        $expectedInt = (int)$sequence->current_slip_no;
        $formatted = $sequence->formatSlipNumber($expectedInt);

        return [
            'has_sequence'    => true,
            'sequence_id'     => $sequence->id,
            'label'           => $sequence->label,
            'prefix'          => $sequence->prefix,
            'book_start_no'   => (int)$sequence->book_start_no,
            'book_end_no'     => (int)$sequence->book_end_no,
            'current_slip_no' => $expectedInt,
            'formatted_slip'  => $formatted,
            'remaining'       => $sequence->getRemainingSlips(),
            'percentage_used' => $sequence->getPercentageUsed(),
            'used_count'      => (int)$sequence->used_count,
            'is_full'         => $isFull,
            'status'          => $sequence->status,
        ];
    }

    /**
     * Consume the next sequential slip number inside a database lock.
     * Enforces the strict sequential rule (no skipping, no jumping, duplicate guard).
     *
     * @param int $storeId
     * @param string $slipType ('receive' or 'send')
     * @param string|null $inputSlipNo Optional manual or auto-filled slip string
     * @param string $sourceType ('procurement', 'petty_cash', 'transfer_in', 'transfer_out', 'manual')
     * @param array $metadata
     * @return array
     * @throws SlipSequenceException
     * @throws SlipSequenceGapException
     */
    public function consumeNext(
        int $storeId,
        string $slipType,
        ?string $inputSlipNo = null,
        string $sourceType = 'manual',
        array $metadata = []
    ): array {
        $execute = function () use ($storeId, $slipType, $inputSlipNo, $sourceType) {
            $sequence = SlipSequence::where('store_id', $storeId)
                ->where('slip_type', $slipType)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                $fullSequence = SlipSequence::where('store_id', $storeId)
                    ->where('slip_type', $slipType)
                    ->where('status', 'full')
                    ->latest()
                    ->first();

                if ($fullSequence) {
                    throw new SlipSequenceException("Slip sequence book '{$fullSequence->label}' (#{$fullSequence->book_start_no}–#{$fullSequence->book_end_no}) is full. Cannot issue more slips. Please activate the Next Book first.");
                }

                $typeLabel = $slipType === 'receive' ? 'Receiving (GRN)' : 'Outgoing (SIN)';
                throw new SlipSequenceException("No active slip sequence configured for this store and slip type ({$typeLabel}). Please configure an active sequence book in Slip Sequences.");
            }

            if ($sequence->status === 'full' || (int)$sequence->current_slip_no > (int)$sequence->book_end_no) {
                throw new SlipSequenceException("Slip sequence book '{$sequence->label}' (#{$sequence->book_start_no}–#{$sequence->book_end_no}) is full. Cannot issue more slips. Please activate the Next Book first.");
            }

            $expectedInt = (int)$sequence->current_slip_no;
            $expectedFormatted = $sequence->formatSlipNumber($expectedInt);

            $targetInt = $expectedInt;
            $targetFormatted = $expectedFormatted;

            // If user supplied a slip number, accept it and auto-fill any missing gap parts
            if (!empty($inputSlipNo) && trim((string)$inputSlipNo) !== '') {
                $rawInput = trim((string)$inputSlipNo);
                $parsedInt = $this->extractNumeric($rawInput, $sequence);

                if ($parsedInt === null) {
                    throw new SlipSequenceException("Invalid slip number '{$rawInput}'. Must contain numeric digits matching the book sequence.");
                }

                // If outside active sequence book range, check if another book for this store covers it
                if ($parsedInt < (int)$sequence->book_start_no || $parsedInt > (int)$sequence->book_end_no) {
                    $altSeq = SlipSequence::where('store_id', $storeId)
                        ->where('slip_type', $slipType)
                        ->where('book_start_no', '<=', $parsedInt)
                        ->where('book_end_no', '>=', $parsedInt)
                        ->first();
                    if ($altSeq) {
                        $sequence = $altSeq;
                        $expectedInt = (int)$sequence->current_slip_no;
                    } else {
                        throw new SlipSequenceException("Slip number #{$rawInput} is outside the active sequence book range (#{$sequence->book_start_no}–#{$sequence->book_end_no}).");
                    }
                }

                // Rule: Lower than expected / already used -> Duplicate unless claiming a void gap
                if ($parsedInt < $expectedInt) {
                    $claimed = false;
                    try {
                        $existingVoid = DeliveryReceipt::where('store_id', $storeId)
                            ->where('slip_type', $slipType)
                            ->where('is_void', true)
                            ->where('source_type', 'void')
                            ->where(function ($q) use ($sequence, $parsedInt, $rawInput) {
                                $fmt = $sequence->formatSlipNumber($parsedInt);
                                $q->where('dr_no', $fmt)
                                  ->orWhere('dr_no', (string)$parsedInt)
                                  ->orWhere('slip_no', $fmt)
                                  ->orWhere('slip_no', (string)$parsedInt)
                                  ->orWhere('slip_no', $rawInput);
                            })
                            ->first();
                        if ($existingVoid) {
                            $existingVoid->delete();
                            $claimed = true;
                        }
                    } catch (\Throwable $e) {}

                    if (!$claimed) {
                        throw new SlipSequenceException("Duplicate Entry: Slip number #{$rawInput} has already been used or passed in sequence. The next expected slip for this book is #{$expectedFormatted}.");
                    }
                }

                // Rule: Higher than expected -> User jumped ahead in physical book.
                // ACCEPT IT and AUTOMATICALLY FILL all missing gaps between expectedInt and parsedInt - 1!
                if ($parsedInt > $expectedInt) {
                    $missingNumbers = range($expectedInt, $parsedInt - 1);
                    $today = now()->toDateString();
                    $actorId = Auth::id() ?: 1;

                    foreach ($missingNumbers as $missNum) {
                        $formattedMiss = $sequence->formatSlipNumber($missNum);
                        $alreadyRecorded = DeliveryReceipt::where('store_id', $storeId)
                            ->where('slip_type', $slipType)
                            ->where(function ($q) use ($formattedMiss, $missNum) {
                                $q->where('dr_no', $formattedMiss)
                                  ->orWhere('dr_no', (string)$missNum)
                                  ->orWhere('slip_no', $formattedMiss)
                                  ->orWhere('slip_no', (string)$missNum);
                            })
                            ->exists();

                        if (!$alreadyRecorded) {
                            try {
                                DeliveryReceipt::create([
                                    'dr_no'           => $formattedMiss,
                                    'slip_no'         => $formattedMiss,
                                    'slip_type'       => $slipType,
                                    'source_type'     => 'void',
                                    'store_id'        => $storeId,
                                    'received_date'   => $today,
                                    'receipt_date'    => $today,
                                    'received_by'     => $actorId,
                                    'created_by'      => $actorId,
                                    'status'          => 'void',
                                    'is_void'         => true,
                                    'void_reason'     => "Auto-filled missing slip gap: skipped when Slip #{$rawInput} was recorded in {$sourceType}",
                                    'voided_by'       => $actorId,
                                    'voided_at'       => now(),
                                    'sequence_status' => 'valid',
                                    'notes'           => "Auto-filled missing gap #{$formattedMiss}",
                                ]);
                            } catch (\Throwable $e) {}
                        }
                    }
                }

                $targetInt = $parsedInt;
                $targetFormatted = $sequence->formatSlipNumber($targetInt);
            }

            // Database duplicate check across active non-void delivery receipts
            $dupQuery = DeliveryReceipt::where('store_id', $storeId)
                ->where('slip_type', $slipType)
                ->where('is_void', false)
                ->where(function ($q) use ($targetFormatted, $targetInt, $inputSlipNo) {
                    $q->where('dr_no', $targetFormatted)
                      ->orWhere('dr_no', (string)$targetInt)
                      ->orWhere('slip_no', $targetFormatted)
                      ->orWhere('slip_no', (string)$targetInt);
                    if (!empty($inputSlipNo)) {
                        $q->orWhere('slip_no', trim((string)$inputSlipNo));
                    }
                });

            if (!empty($metadata['exclude_receipt_id'])) {
                $dupQuery->where('id', '!=', $metadata['exclude_receipt_id']);
            }

            $duplicateExists = $dupQuery->first();
            if ($duplicateExists) {
                throw new SlipSequenceException("Duplicate Entry: Slip #{$targetFormatted} is already recorded in delivery receipt #{$duplicateExists->id} (recorded on {$duplicateExists->received_date}).");
            }

            // Advance the sequence counter past the highest used number
            $newCurrent = max($expectedInt, $targetInt) + 1;
            $newUsed = (int)$sequence->used_count + 1;
            if ($targetInt > $expectedInt) {
                $newUsed = (int)$sequence->used_count + ($targetInt - $expectedInt) + 1;
            }
            $newStatus = ($newCurrent > (int)$sequence->book_end_no) ? 'full' : 'active';

            $sequence->update([
                'current_slip_no' => $newCurrent,
                'used_count'      => $newUsed,
                'status'          => $newStatus,
            ]);

            return [
                'sequence'        => $sequence,
                'slip_no'         => $targetFormatted,
                'numeric_no'      => $targetInt,
                'numeric_slip_no' => $targetInt,
                'source_type'     => $sourceType,
                'is_full'         => $newStatus === 'full',
                'remaining'       => $sequence->getRemainingSlips(),
                'percentage_used' => $sequence->getPercentageUsed(),
            ];
        };

        if (DB::transactionLevel() > 0) {
            return $execute();
        }

        return DB::transaction($execute);
    }

    /**
     * Void one or more contiguous missing slips to allow sequence to advance.
     * Records DeliveryReceipt with source_type = 'void', is_void = true, without stock movement.
     *
     * @param int $storeId
     * @param string $slipType
     * @param array $numbers Array of integer slip numbers to void
     * @param string $reason Required text reason
     * @param int|null $userId
     * @return array
     */
    public function voidMissingSlips(
        int $storeId,
        string $slipType,
        array $numbers,
        string $reason,
        ?int $userId = null
    ): array {
        $reason = trim((string)$reason);
        if (empty($reason)) {
            throw new SlipSequenceException("A void reason is required when voiding slip numbers.");
        }

        $userId = $userId ?: (Auth::id() ?: 1);

        return DB::transaction(function () use ($storeId, $slipType, $numbers, $reason, $userId) {
            $sequence = SlipSequence::where('store_id', $storeId)
                ->where('slip_type', $slipType)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                throw new SlipSequenceException("No active slip sequence found to void.");
            }

            sort($numbers);
            $expected = (int)$sequence->current_slip_no;

            // Ensure the numbers to void start at expected sequence
            if (empty($numbers) || $numbers[0] !== $expected) {
                throw new SlipSequenceException("Voiding must begin with the next expected slip (#{$sequence->formatSlipNumber($expected)}).");
            }

            // Verify numbers are strictly consecutive
            for ($i = 0; $i < count($numbers); $i++) {
                if ($numbers[$i] !== $expected + $i) {
                    throw new SlipSequenceException("Void slip numbers must be contiguous without gaps.");
                }
                if ($numbers[$i] > (int)$sequence->book_end_no) {
                    throw new SlipSequenceException("Slip number {$numbers[$i]} is beyond the sequence book range.");
                }
            }

            $voidedReceipts = [];
            $today = now()->toDateString();

            foreach ($numbers as $num) {
                $formatted = $sequence->formatSlipNumber($num);

                // Create void delivery receipt leaf
                $receipt = DeliveryReceipt::create([
                    'dr_no'           => $formatted,
                    'slip_no'         => $formatted,
                    'slip_type'       => $slipType,
                    'source_type'     => 'void',
                    'store_id'        => $storeId,
                    'received_date'   => $today,
                    'receipt_date'    => $today,
                    'received_by'     => $userId,
                    'created_by'      => $userId,
                    'status'          => 'void',
                    'is_void'         => true,
                    'void_reason'     => $reason,
                    'voided_by'       => $userId,
                    'voided_at'       => now(),
                    'sequence_status' => 'valid',
                    'notes'           => "Voided slip #{$formatted}: {$reason}",
                ]);

                $voidedReceipts[] = $receipt;
            }

            $lastNum = end($numbers);
            $newCurrent = $lastNum + 1;
            $newUsed = (int)$sequence->used_count + count($numbers);
            $newStatus = ($newCurrent > (int)$sequence->book_end_no) ? 'full' : 'active';

            $sequence->update([
                'current_slip_no' => $newCurrent,
                'used_count'      => $newUsed,
                'status'          => $newStatus,
            ]);

            return [
                'voided_count'    => count($voidedReceipts),
                'voided_numbers'  => $numbers,
                'voided_receipts' => $voidedReceipts,
                'next_expected'   => $sequence->formatSlipNumber($newCurrent),
                'sequence'        => $sequence,
            ];
        });
    }

    /**
     * Mark an existing already-posted slip as void and reverse any associated inventory movements.
     */
    public function voidExistingReceipt(DeliveryReceipt $receipt, string $reason, ?int $userId = null): void
    {
        $reason = trim((string)$reason);
        if (empty($reason)) {
            throw new SlipSequenceException("A void reason is required when voiding an existing slip.");
        }

        if ($receipt->is_void) {
            throw new SlipSequenceException("Slip {$receipt->dr_no} is already voided.");
        }

        $userId = $userId ?: (Auth::id() ?: 1);

        DB::transaction(function () use ($receipt, $reason, $userId) {
            $receipt->loadMissing(['items', 'store', 'toStore']);
            $inventoryService = app(InventoryService::class);

            foreach ($receipt->items as $item) {
                $qty = (float)($item->accepted_quantity ?: ($item->quantity_received ?: 0));
                if ($qty <= 0) continue;

                if ($receipt->slip_type === 'receive') {
                    // Reversal: deduct previously received stock
                    $inventoryService->applyMovement(
                        storeId: $receipt->store_id,
                        productId: $item->product_id,
                        quantityDelta: -$qty,
                        type: 'slip_void',
                        referenceType: DeliveryReceipt::class,
                        referenceId: $receipt->id,
                        remarks: "Reversal for voided receive slip {$receipt->dr_no}: {$reason}",
                        performedBy: $userId
                    );
                } elseif ($receipt->slip_type === 'send') {
                    // Reversal: restore stock to issuing store
                    $inventoryService->applyMovement(
                        storeId: $receipt->store_id,
                        productId: $item->product_id,
                        quantityDelta: +$qty,
                        type: 'slip_void',
                        referenceType: DeliveryReceipt::class,
                        referenceId: $receipt->id,
                        remarks: "Reversal for voided send slip {$receipt->dr_no} (restored to issuing store): {$reason}",
                        performedBy: $userId
                    );

                    if ($receipt->to_store_id) {
                        $inventoryService->applyMovement(
                            storeId: $receipt->to_store_id,
                            productId: $item->product_id,
                            quantityDelta: -$qty,
                            type: 'slip_void',
                            referenceType: DeliveryReceipt::class,
                            referenceId: $receipt->id,
                            remarks: "Reversal for voided send slip {$receipt->dr_no} (deducted from destination store): {$reason}",
                            performedBy: $userId
                        );
                    }
                }
            }

            $receipt->update([
                'is_void'     => true,
                'status'      => 'void',
                'void_reason' => $reason,
                'voided_by'   => $userId,
                'voided_at'   => now(),
                'source_type' => 'void',
            ]);
        });
    }

    /**
     * Cross-check / Reconciliation for a specific sequence book.
     * Verifies:
     * - Every number from book_start_no to current_slip_no - 1 is either used or void
     * - No duplicates
     * - No gaps
     * - used_count matches the actual rows
     */
    public function reconcile(SlipSequence $sequence): array
    {
        $start = (int)$sequence->book_start_no;
        $current = (int)$sequence->current_slip_no;
        $end = (int)$sequence->book_end_no;
        $storeId = (int)$sequence->store_id;
        $slipType = $sequence->slip_type;

        $lastIssued = min($current - 1, $end);
        $expectedCount = max(0, $lastIssued - $start + 1);

        // Fetch all delivery receipts and transfers for this store and slip type
        $receipts = DeliveryReceipt::where('store_id', $storeId)
            ->where('slip_type', $slipType)
            ->get();

        $transfers = collect();
        if ($slipType === 'send') {
            $transfers = Transfer::withTrashed()->where('from_store_id', $storeId)->get();
        } elseif ($slipType === 'receive') {
            $transfers = Transfer::withTrashed()->where('to_store_id', $storeId)->get();
        }

        // Map recorded numbers
        $numbersOccurrences = [];
        $recordsByNumber = [];

        foreach ($receipts as $r) {
            $num = $this->extractNumeric($r->slip_no ?: $r->dr_no, $sequence);
            if ($num !== null && $num >= $start && $num <= $end) {
                $numbersOccurrences[$num] = ($numbersOccurrences[$num] ?? 0) + 1;
                $recordsByNumber[$num][] = [
                    'type'        => 'delivery_receipt',
                    'id'          => $r->id,
                    'dr_no'       => $r->dr_no,
                    'source_type' => $r->source_type ?: ($r->is_void ? 'void' : 'manual'),
                    'is_void'     => (bool)$r->is_void,
                    'date'        => optional($r->received_date)->format('Y-m-d') ?: $r->created_at?->format('Y-m-d'),
                ];
            }
        }

        foreach ($transfers as $tr) {
            $slipVal = ($slipType === 'receive') ? $tr->receiving_slip_no : ($tr->outgoing_slip_no ?: $tr->physical_slip_no);
            $num = $this->extractNumeric($slipVal, $sequence);
            if ($num !== null && $num >= $start && $num <= $end) {
                // If not already covered by a delivery receipt with transfer_id
                $alreadyCounted = isset($recordsByNumber[$num]) && collect($recordsByNumber[$num])->contains('type', 'delivery_receipt');
                if (!$alreadyCounted) {
                    $numbersOccurrences[$num] = ($numbersOccurrences[$num] ?? 0) + 1;
                    $recordsByNumber[$num][] = [
                        'type'        => 'transfer',
                        'id'          => $tr->id,
                        'dr_no'       => $slipVal,
                        'source_type' => $slipType === 'receive' ? 'transfer_in' : 'transfer_out',
                        'is_void'     => (bool)$tr->deleted_at,
                        'date'        => optional($tr->dispatched_at ?: $tr->created_at)->format('Y-m-d'),
                    ];
                }
            }
        }

        $gaps = [];
        $duplicates = [];
        $recordedCount = 0;
        $voidCount = 0;

        for ($num = $start; $num <= $lastIssued; $num++) {
            $count = $numbersOccurrences[$num] ?? 0;
            if ($count === 0) {
                $gaps[] = [
                    'number'    => $num,
                    'formatted' => $sequence->formatSlipNumber($num),
                ];
            } else {
                $recordedCount++;
                if ($count > 1) {
                    $duplicates[] = [
                        'number'      => $num,
                        'formatted'   => $sequence->formatSlipNumber($num),
                        'occurrences' => $count,
                        'records'     => $recordsByNumber[$num] ?? [],
                    ];
                }
                $hasVoid = collect($recordsByNumber[$num] ?? [])->contains('is_void', true);
                if ($hasVoid) {
                    $voidCount++;
                }
            }
        }

        $usedCountMatches = ((int)$sequence->used_count === $recordedCount);
        $hasProblems = !empty($gaps) || !empty($duplicates) || !$usedCountMatches;

        return [
            'sequence_id'         => $sequence->id,
            'label'               => $sequence->label,
            'store_name'          => $sequence->store?->name ?? 'Store',
            'slip_type'           => $sequence->slip_type,
            'book_range'          => "{$start} - {$end}",
            'book_start_no'       => $start,
            'book_end_no'         => $end,
            'current_slip_no'     => $current,
            'expected_used_count' => $expectedCount,
            'actual_used_count'   => $recordedCount,
            'sequence_used_count' => (int)$sequence->used_count,
            'void_count'          => $voidCount,
            'gaps_count'          => count($gaps),
            'gaps'                => $gaps,
            'gap_numbers'         => array_column($gaps, 'number'),
            'duplicates_count'    => count($duplicates),
            'duplicates'          => $duplicates,
            'duplicate_numbers'   => array_column($duplicates, 'number'),
            'used_count_matches'  => $usedCountMatches,
            'has_issues'          => $hasProblems,
            'mismatches'          => !$usedCountMatches ? ["Used count in sequence ({$sequence->used_count}) does not match actual recorded slips ({$recordedCount})"] : [],
            'status'              => $hasProblems ? 'problems' : 'ok',
            'status_label'        => $hasProblems ? 'Problems Detected' : 'Reconciled & Continuous (OK)',
        ];
    }

    /**
     * Cross-check / Reconciliation across all sequences
     */
    public function reconcileAll(): array
    {
        $sequences = SlipSequence::with('store')->orderBy('store_id')->orderBy('book_start_no')->get();
        $results = [];

        foreach ($sequences as $seq) {
            $results[] = $this->reconcile($seq);
        }

        $problemCount = collect($results)->where('status', 'problems')->count();
        $okCount = collect($results)->where('status', 'ok')->count();

        return [
            'total_sequences' => count($results),
            'problem_count'   => $problemCount,
            'ok_count'        => $okCount,
            'results'         => $results,
        ];
    }

    /**
     * Helper to safely extract integer slip number from formatted string
     */
    public function extractNumeric(?string $str, ?SlipSequence $sequence = null): ?int
    {
        if (empty($str)) {
            return null;
        }

        $str = trim((string)$str);

        // If prefix exists, try stripping it
        if ($sequence && !empty($sequence->prefix)) {
            $prefix = trim($sequence->prefix);
            if (stripos($str, $prefix) === 0) {
                $after = substr($str, strlen($prefix));
                if (preg_match('/^\d+$/', $after)) {
                    return (int)$after;
                }
            }
        }

        // Match all consecutive digits and find a candidate
        if (preg_match_all('/\d+/', $str, $matches)) {
            if ($sequence) {
                $start = (int)$sequence->book_start_no;
                $end = (int)$sequence->book_end_no;
                foreach ($matches[0] as $match) {
                    $val = (int)$match;
                    if ($val >= $start && $val <= $end) {
                        return $val;
                    }
                }
            }
            // Return last numeric block or first
            return (int)end($matches[0]);
        }

        return null;
    }

    /**
     * Inspect all currently added slips across Store Manager and Store Keeper sections,
     * accept all added slips, automatically fill any missing gap numbers as void entries,
     * and synchronize active sequence counters.
     *
     * @param int|null $targetStoreId
     * @param string|null $targetSlipType
     * @return array Summary of checked and filled records
     */
    public function checkAndFillMissingSlips(?int $targetStoreId = null, ?string $targetSlipType = null): array
    {
        $storesQuery = Store::query();
        if ($targetStoreId) {
            $storesQuery->where('id', $targetStoreId);
        }
        $stores = $storesQuery->get();

        $types = $targetSlipType ? [$targetSlipType] : ['receive', 'send'];

        $totalChecked = 0;
        $totalGapsFilled = 0;
        $sequencesUpdated = [];
        $today = now()->toDateString();
        $actorId = Auth::id() ?: 1;

        foreach ($stores as $store) {
            foreach ($types as $slipType) {
                // Find active or latest sequences for this store & slip type
                $sequences = SlipSequence::where('store_id', $store->id)
                    ->where('slip_type', $slipType)
                    ->orderBy('book_start_no', 'asc')
                    ->get();

                if ($sequences->isEmpty()) {
                    continue;
                }

                $totalChecked++;

                // 1. Collect all slip numbers added in DeliveryReceipt
                $existingSlips = DeliveryReceipt::where('store_id', $store->id)
                    ->where('slip_type', $slipType)
                    ->get();

                $recordedInts = [];
                foreach ($existingSlips as $dr) {
                    $num1 = $this->extractNumeric($dr->slip_no);
                    $num2 = $this->extractNumeric($dr->dr_no);
                    if ($num1 !== null) $recordedInts[$num1] = true;
                    if ($num2 !== null) $recordedInts[$num2] = true;
                }

                // 2. Collect slips added in Transfers (Store Manager transfer hub)
                if (Schema::hasTable('transfers')) {
                    if ($slipType === 'send') {
                        $transfers = DB::table('transfers')
                            ->where('from_store_id', $store->id)
                            ->get();
                        foreach ($transfers as $t) {
                            foreach (['outgoing_slip_no', 'physical_slip_no'] as $fld) {
                                if (!empty($t->$fld)) {
                                    $n = $this->extractNumeric($t->$fld);
                                    if ($n !== null) $recordedInts[$n] = true;
                                }
                            }
                        }
                    } else {
                        $transfers = DB::table('transfers')
                            ->where('to_store_id', $store->id)
                            ->get();
                        foreach ($transfers as $t) {
                            if (!empty($t->receiving_slip_no)) {
                                $n = $this->extractNumeric($t->receiving_slip_no);
                                if ($n !== null) $recordedInts[$n] = true;
                            }
                        }
                    }
                }

                // 3. Collect slips added in Procurement Receipts
                if ($slipType === 'receive' && Schema::hasTable('procurement_receipts')) {
                    $procSlips = DB::table('procurement_receipts')
                        ->where('store_id', $store->id)
                        ->whereNotNull('slip_no')
                        ->pluck('slip_no');
                    foreach ($procSlips as $s) {
                        $n = $this->extractNumeric($s);
                        if ($n !== null) $recordedInts[$n] = true;
                    }
                }

                // 4. Collect slips added in Petty Cash Purchases
                if ($slipType === 'receive' && Schema::hasTable('petty_cash_material_purchases')) {
                    $pcSlips = DB::table('petty_cash_material_purchases')
                        ->where('store_id', $store->id)
                        ->whereNotNull('slip_no')
                        ->pluck('slip_no');
                    foreach ($pcSlips as $s) {
                        $n = $this->extractNumeric($s);
                        if ($n !== null) $recordedInts[$n] = true;
                    }
                }

                // For each sequence book of this store, fill missing gaps up to highest added slip
                foreach ($sequences as $sequence) {
                    $bookStart = (int)$sequence->book_start_no;
                    $bookEnd = (int)$sequence->book_end_no;
                    $currentSlip = (int)$sequence->current_slip_no;

                    // Match all added integers that belong to this book's range
                    $bookInts = array_filter(array_keys($recordedInts), fn($n) => $n >= $bookStart && $n <= $bookEnd);

                    if (empty($bookInts) && $sequence->status !== 'active') {
                        continue;
                    }

                    $maxAdded = !empty($bookInts) ? max($bookInts) : ($currentSlip - 1);
                    $ceiling = max($maxAdded, $currentSlip - 1);

                    // Find missing numbers from bookStart up to ceiling
                    $gapsInBook = [];
                    for ($n = $bookStart; $n <= $ceiling; $n++) {
                        if (!isset($recordedInts[$n])) {
                            $gapsInBook[] = $n;
                        }
                    }

                    // Auto-fill all missing numbers with void receipts so book has no missing holes
                    $filledForThisSequence = 0;
                    foreach ($gapsInBook as $gapNum) {
                        $formattedGap = $sequence->formatSlipNumber($gapNum);
                        try {
                            DeliveryReceipt::create([
                                'dr_no'           => $formattedGap,
                                'slip_no'         => $formattedGap,
                                'slip_type'       => $slipType,
                                'source_type'     => 'void',
                                'store_id'        => $store->id,
                                'received_date'   => $today,
                                'receipt_date'    => $today,
                                'received_by'     => $actorId,
                                'created_by'      => $actorId,
                                'status'          => 'void',
                                'is_void'         => true,
                                'void_reason'     => 'Auto-filled missing slip gap from current added store slips',
                                'voided_by'       => $actorId,
                                'voided_at'       => now(),
                                'sequence_status' => 'valid',
                                'notes'           => "Auto-filled missing gap #{$formattedGap}",
                            ]);
                            $recordedInts[$gapNum] = true;
                            $filledForThisSequence++;
                            $totalGapsFilled++;
                        } catch (\Throwable $e) {}
                    }

                    // Calculate new current slip number and used count
                    $newCurrent = max($currentSlip, $maxAdded + 1);
                    $totalRecordedInBook = count(array_filter(array_keys($recordedInts), fn($n) => $n >= $bookStart && $n < $newCurrent));
                    $newStatus = ($newCurrent > $bookEnd) ? 'full' : $sequence->status;

                    $oldCurrent = $sequence->current_slip_no;
                    $sequence->update([
                        'current_slip_no' => $newCurrent,
                        'used_count'      => max((int)$sequence->used_count, $totalRecordedInBook),
                        'status'          => $newStatus,
                    ]);

                    if ($filledForThisSequence > 0 || $newCurrent !== $oldCurrent) {
                        $sequencesUpdated[] = [
                            'store_name'     => $store->name,
                            'slip_type'      => $slipType,
                            'book_range'     => "#{$bookStart}–#{$bookEnd}",
                            'old_current'    => $oldCurrent,
                            'new_current'    => $newCurrent,
                            'max_added'      => $maxAdded,
                            'gaps_filled'    => $filledForThisSequence,
                        ];
                    }
                }
            }
        }

        return [
            'stores_checked'    => $stores->count(),
            'gaps_filled'       => $totalGapsFilled,
            'sequences_updated' => $sequencesUpdated,
        ];
    }
}
