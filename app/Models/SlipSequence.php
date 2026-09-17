<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlipSequence extends Model
{
    protected $fillable = [
        'store_id',
        'slip_type',
        'label',
        'prefix',
        'book_start_no',
        'book_end_no',
        'current_slip_no',
        'used_count',
        'status',
        'notes',
    ];

    protected $casts = [
        'slip_type' => 'string',
        'status' => 'string',
    ];

    /**
     * Relationship: belongs to Store
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get next slip number (raw numeric value)
     * @return int
     */
    public function getNextSlipNumber(): int
    {
        return $this->current_slip_no;
    }

    /**
     * Format slip number with prefix
     * @param int $number
     * @return string
     */
    public function formatSlipNumber(int $number): string
    {
        if ($this->prefix) {
            return $this->prefix . str_pad($number, 5, '0', STR_PAD_LEFT);
        }
        return str_pad($number, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Generate next slip number and increment counter
     * @return string (formatted slip number)
     */
    public function generateSlipNumber(): string
    {
        // Check if book is full
        if ($this->current_slip_no > $this->book_end_no) {
            $this->update(['status' => 'full']);
            throw new \Exception('Slip sequence book is full. Cannot generate more slips.');
        }

        $formatted = $this->formatSlipNumber($this->current_slip_no);

        // Increment for next call
        $this->update([
            'current_slip_no' => $this->current_slip_no + 1,
            'used_count' => $this->used_count + 1,
        ]);

        return $formatted;
    }

    /**
     * Get percentage of book used
     * @return float
     */
    public function getPercentageUsed(): float
    {
        $total = $this->book_end_no - $this->book_start_no + 1;
        if ($total === 0) {
            return 0;
        }
        return round(($this->used_count / $total) * 100, 2);
    }

    /**
     * Get remaining slips in book
     * @return int
     */
    public function getRemainingSlips(): int
    {
        return max(0, $this->book_end_no - $this->current_slip_no + 1);
    }

    /**
     * Check if sequence is valid for a given slip number
     * @param int $slipNumber
     * @return bool
     */
    public function isValidSlipNumber(int $slipNumber): bool
    {
        return $slipNumber >= $this->book_start_no && $slipNumber <= $this->book_end_no;
    }

    /**
     * Mark slip as used (for validation/audit)
     * @param int $slipNumber
     */
    public function markSlipAsUsed(int $slipNumber): void
    {
        if (!$this->isValidSlipNumber($slipNumber)) {
            throw new \Exception("Slip number $slipNumber is outside book range.");
        }

        // If it's the next expected, increment properly
        if ($slipNumber === $this->current_slip_no) {
            $this->update([
                'current_slip_no' => $slipNumber + 1,
                'used_count' => $this->used_count + 1,
            ]);
        } else {
            // Out of sequence - just log usage, flag as gap
            $this->update(['used_count' => $this->used_count + 1]);
        }
    }

    /**
     * Get all assigned slips and their linked documents for this sequence book
     * @return \Illuminate\Support\Collection
     */
    public function getAssignedSlipsDetail(): \Illuminate\Support\Collection
    {
        $start = (int) $this->book_start_no;
        $end = (int) $this->book_end_no;
        $storeId = $this->store_id;
        $slipType = $this->slip_type;
        $prefix = trim((string) $this->prefix);

        // 1. Find matching Delivery Receipts
        $receipts = DeliveryReceipt::where(function ($q) use ($storeId, $prefix) {
                $q->where('store_id', $storeId)
                  ->orWhere('to_store_id', $storeId);
                if (!empty($prefix)) {
                    $q->orWhere('dr_no', 'like', $prefix . '%');
                }
            })
            ->with([
                'purchaseRequest.project',
                'purchaseRequest.requester',
                'purchaseOrder.supplier',
                'store',
                'receivedBy',
                'items.product.unit',
            ])
            ->latest('id')
            ->get();

        $assignedSlips = collect();

        foreach ($receipts as $receipt) {
            if (empty($receipt->dr_no)) {
                continue;
            }

            // Extract numeric digits
            $rawDigits = preg_replace('/[^0-9]/', '', $receipt->dr_no);
            if ($rawDigits === '') {
                continue;
            }
            $numeric = (int) $rawDigits;

            if ($numeric >= $start && $numeric <= $end) {
                $itemsList = $receipt->items->map(function ($it) {
                    return [
                        'name'     => $it->product?->name ?? 'Item',
                        'quantity' => (float) ($it->quantity ?? $it->accepted_quantity ?? 0),
                        'unit'     => $it->product?->unit?->name ?? $it->product?->unit ?? '',
                    ];
                });

                $assignedSlips->push([
                    'slip_no'             => $receipt->dr_no,
                    'numeric_no'          => $numeric,
                    'source_type'         => 'delivery_receipt',
                    'slip_type'           => $receipt->slip_type ?: $slipType,
                    'document_id'         => $receipt->id,
                    'document_ref'        => 'GRN / Delivery Receipt #' . ($receipt->dr_no ?: $receipt->id),
                    'document_url'        => route('delivery-receipts.show', $receipt->id),
                    'status'              => $receipt->is_void ? 'void' : ($receipt->status ?: 'verified'),
                    'is_void'             => (bool) $receipt->is_void,
                    'date'                => $receipt->received_date ?: $receipt->receipt_date ?: $receipt->created_at,
                    'store_name'          => $receipt->store?->name ?? 'Store',
                    'supplier_name'       => $receipt->supplier_name ?: ($receipt->purchaseOrder?->supplier?->name ?? ($receipt->purchaseRequest?->supplier_name ?? 'Supplier / Store')),
                    'purchase_request_id' => $receipt->purchase_request_id,
                    'pr_no'               => $receipt->purchaseRequest?->pr_no,
                    'pr_title'            => $receipt->purchaseRequest?->title ?? $receipt->purchaseRequest?->item_name,
                    'pr_url'              => $receipt->purchase_request_id ? route('purchase-requests.show', $receipt->purchase_request_id) : null,
                    'project_name'        => $receipt->purchaseRequest?->project?->name ?? 'N/A',
                    'purchase_order_ref'  => $receipt->purchaseOrder?->reference_number,
                    'po_id'               => $receipt->purchase_order_id,
                    'handled_by'          => $receipt->receivedBy?->name ?? 'Store Keeper',
                    'items_count'         => $itemsList->count(),
                    'items'               => $itemsList,
                    'notes'               => $receipt->notes,
                ]);
            }
        }

        // 2. Find matching Transfers
        $transfers = Transfer::where(function ($q) use ($storeId) {
                $q->where('from_store_id', $storeId)
                  ->orWhere('to_store_id', $storeId);
            })
            ->with(['fromStore', 'toStore', 'items.product', 'requestedBy', 'approvedBy'])
            ->get();

        foreach ($transfers as $tr) {
            $slipsToCheck = [
                'outgoing'  => $tr->outgoing_slip_no,
                'physical'  => $tr->physical_slip_no,
                'receiving' => $tr->receiving_slip_no,
            ];

            foreach ($slipsToCheck as $role => $sVal) {
                if (empty($sVal)) continue;
                $digits = preg_replace('/[^0-9]/', '', $sVal);
                if ($digits === '') continue;
                $num = (int) $digits;

                if ($num >= $start && $num <= $end) {
                    if ($assignedSlips->contains('slip_no', $sVal)) {
                        continue;
                    }

                    $itemsList = $tr->items->map(function ($it) {
                        return [
                            'name'     => $it->product?->name ?? 'Item',
                            'quantity' => (float) ($it->quantity ?? $it->approved_quantity ?? 0),
                            'unit'     => $it->product?->unit?->name ?? $it->product?->unit ?? '',
                        ];
                    });

                    $assignedSlips->push([
                        'slip_no'             => $sVal,
                        'numeric_no'          => $num,
                        'source_type'         => 'transfer',
                        'slip_type'           => ($role === 'receiving') ? 'receive' : 'send',
                        'document_id'         => $tr->id,
                        'document_ref'        => 'Transfer #' . $tr->transfer_no . ' (' . ucfirst($role) . ')',
                        'document_url'        => route('store-manager.transfers.show', $tr->id),
                        'status'              => $tr->status ?: 'completed',
                        'is_void'             => false,
                        'date'                => $tr->dispatched_at ?: $tr->created_at,
                        'store_name'          => ($role === 'receiving' ? $tr->toStore?->name : $tr->fromStore?->name) ?? 'Store',
                        'supplier_name'       => 'Store Transfer (' . ($tr->fromStore?->name ?? 'From') . ' ➔ ' . ($tr->toStore?->name ?? 'To') . ')',
                        'purchase_request_id' => null,
                        'pr_no'               => null,
                        'pr_title'            => null,
                        'pr_url'              => null,
                        'project_name'        => $tr->toStore?->name ?? 'Transfer Destination',
                        'purchase_order_ref'  => null,
                        'po_id'               => null,
                        'handled_by'          => $tr->requestedBy?->name ?? 'Store Staff',
                        'items_count'         => $itemsList->count(),
                        'items'               => $itemsList,
                        'notes'               => $tr->dispatch_notes ?? $tr->reason,
                    ]);
                }
            }
        }

        return $assignedSlips->sortBy('numeric_no')->values();
    }

    /**
     * Get book range map with status for every slip leaf in the book
     * @param \Illuminate\Support\Collection|null $assignedSlips
     * @return array
     */
    public function getBookRangeMap(?\Illuminate\Support\Collection $assignedSlips = null): array
    {
        if ($assignedSlips === null) {
            $assignedSlips = $this->getAssignedSlipsDetail();
        }

        $assignedByNumber = $assignedSlips->keyBy('numeric_no');
        $map = [];
        $start = (int) $this->book_start_no;
        $end = (int) $this->book_end_no;
        $current = (int) $this->current_slip_no;

        for ($num = $start; $num <= $end; $num++) {
            $isAssigned = $assignedByNumber->has($num);
            $assignedData = $isAssigned ? $assignedByNumber->get($num) : null;

            if ($isAssigned) {
                $status = 'assigned';
            } elseif ($num === $current) {
                $status = 'next';
            } elseif ($num < $current) {
                $status = 'unrecorded';
            } else {
                $status = 'available';
            }

            $map[] = [
                'number'        => $num,
                'formatted'     => $this->formatSlipNumber($num),
                'status'        => $status,
                'assigned_data' => $assignedData,
            ];
        }

        return $map;
    }
}
