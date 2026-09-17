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
        $storeId = (int) $this->store_id;
        $slipType = $this->slip_type;
        $prefix = trim((string) $this->prefix);

        $assignedSlips = collect();

        // Helper function to extract any integer in [$start, $end] from any text field
        $extractSlipNumber = function ($str) use ($start, $end) {
            if (empty($str)) return null;
            if (preg_match_all('/\d+/', (string) $str, $matches)) {
                foreach ($matches[0] as $match) {
                    $val = (int) $match;
                    if ($val >= $start && $val <= $end) {
                        return $val;
                    }
                }
            }
            return null;
        };

        // 1. Find all matching Delivery Receipts across the database
        $receipts = DeliveryReceipt::where(function ($q) use ($storeId, $prefix, $start, $end) {
                $q->where('store_id', $storeId)
                  ->orWhere('to_store_id', $storeId)
                  ->orWhereNotNull('dr_no')
                  ->orWhereNotNull('reference_no')
                  ->orWhereNotNull('challan_no');
            })
            ->with([
                'purchaseRequest.project',
                'purchaseRequest.requestedBy',
                'purchaseRequest.items.product',
                'purchaseOrder.supplier',
                'store',
                'receivedBy',
                'items.product',
            ])
            ->latest('id')
            ->get();

        foreach ($receipts as $receipt) {
            // Check dr_no, reference_no, challan_no, notes
            $numeric = $extractSlipNumber($receipt->dr_no) 
                    ?? $extractSlipNumber($receipt->reference_no)
                    ?? $extractSlipNumber($receipt->challan_no)
                    ?? $extractSlipNumber($receipt->notes);

            if ($numeric !== null) {
                // If slip already added, don't duplicate
                if ($assignedSlips->contains('numeric_no', $numeric)) {
                    continue;
                }

                // Extract items from DeliveryReceiptItem or fallback to PR items
                $itemsList = collect();
                if ($receipt->items && $receipt->items->isNotEmpty()) {
                    $itemsList = $receipt->items->map(function ($it) {
                        return [
                            'name'     => $it->product?->name ?? 'Item',
                            'quantity' => (float) ($it->quantity ?? $it->quantity_received ?? $it->accepted_quantity ?? 0),
                            'unit'     => $it->unit ?? $it->product?->unit ?? '',
                        ];
                    });
                } elseif ($receipt->purchaseRequest && $receipt->purchaseRequest->items) {
                    $itemsList = $receipt->purchaseRequest->items->map(function ($it) {
                        return [
                            'name'     => $it->product?->name ?? $it->item_name ?? 'Item',
                            'quantity' => (float) ($it->quantity ?? $it->received_quantity ?? 0),
                            'unit'     => $it->unit ?? $it->product?->unit ?? '',
                        ];
                    });
                }

                $assignedSlips->push([
                    'slip_no'             => $receipt->dr_no ?: $this->formatSlipNumber($numeric),
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
                    'project_name'        => $receipt->purchaseRequest?->project?->name ?? ($receipt->store?->name ?? 'N/A'),
                    'purchase_order_ref'  => $receipt->purchaseOrder?->reference_number,
                    'po_id'               => $receipt->purchase_order_id,
                    'handled_by'          => $receipt->receivedBy?->name ?? 'Store Keeper',
                    'items_count'         => $itemsList->count(),
                    'items'               => $itemsList,
                    'notes'               => $receipt->notes,
                ]);
            }
        }

        // 2. Find matching Transfers across the database
        $transfers = Transfer::where(function ($q) use ($storeId) {
                $q->where('from_store_id', $storeId)
                  ->orWhere('to_store_id', $storeId)
                  ->orWhereNotNull('outgoing_slip_no')
                  ->orWhereNotNull('physical_slip_no')
                  ->orWhereNotNull('receiving_slip_no');
            })
            ->with(['fromStore', 'toStore', 'items.product', 'requestedBy', 'approvedBy'])
            ->latest('id')
            ->get();

        foreach ($transfers as $tr) {
            $slipsToCheck = [
                'outgoing'  => $tr->outgoing_slip_no,
                'physical'  => $tr->physical_slip_no,
                'receiving' => $tr->receiving_slip_no,
            ];

            foreach ($slipsToCheck as $role => $sVal) {
                if (empty($sVal)) continue;
                $num = $extractSlipNumber($sVal);

                if ($num !== null) {
                    if ($assignedSlips->contains('numeric_no', $num)) {
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

        // 3. Find matching Inventory Movements with Slip references
        try {
            $movements = InventoryMovement::where('remarks', 'like', '%Slip%')
                ->with(['performer'])
                ->latest('id')
                ->take(300)
                ->get();

            foreach ($movements as $mov) {
                $num = $extractSlipNumber($mov->remarks);
                if ($num !== null && !$assignedSlips->contains('numeric_no', $num)) {
                    $pr = null;
                    if ($mov->reference_type === PurchaseRequest::class && $mov->reference_id) {
                        $pr = PurchaseRequest::with(['project', 'requestedBy', 'items.product'])->find($mov->reference_id);
                    }

                    $itemsList = collect();
                    if ($pr && $pr->items) {
                        $itemsList = $pr->items->map(function ($it) {
                            return [
                                'name'     => $it->product?->name ?? $it->item_name ?? 'Item',
                                'quantity' => (float) ($it->quantity ?? $it->received_quantity ?? 0),
                                'unit'     => $it->unit ?? $it->product?->unit ?? '',
                            ];
                        });
                    }

                    $assignedSlips->push([
                        'slip_no'             => $this->formatSlipNumber($num),
                        'numeric_no'          => $num,
                        'source_type'         => 'inventory_movement',
                        'slip_type'           => $slipType,
                        'document_id'         => $mov->id,
                        'document_ref'        => $pr ? 'PR #' . $pr->pr_no . ' (Stock In)' : 'Inventory Movement #' . $mov->id,
                        'document_url'        => $pr ? route('purchase-requests.show', $pr->id) : route('store-manager.inventory.all'),
                        'status'              => 'verified',
                        'is_void'             => false,
                        'date'                => $mov->created_at,
                        'store_name'          => $this->store?->name ?? 'Store',
                        'supplier_name'       => $pr?->supplier_name ?? ($this->store?->name ?? 'Store Intake'),
                        'purchase_request_id' => $pr?->id,
                        'pr_no'               => $pr?->pr_no,
                        'pr_title'            => $pr?->title ?? $pr?->item_name,
                        'pr_url'              => $pr ? route('purchase-requests.show', $pr->id) : null,
                        'project_name'        => $pr?->project?->name ?? ($this->store?->name ?? 'N/A'),
                        'purchase_order_ref'  => null,
                        'po_id'               => null,
                        'handled_by'          => $mov->performer?->name ?? 'Store Staff',
                        'items_count'         => $itemsList->count(),
                        'items'               => $itemsList,
                        'notes'               => $mov->remarks,
                    ]);
                }
            }
        } catch (\Throwable $e) {}

        // 4. Find matching Purchase Request Workflow Logs with Slip references
        try {
            $logs = PrWorkflowLog::where('notes', 'like', '%Slip%')
                ->with(['purchaseRequest.project', 'purchaseRequest.requestedBy', 'purchaseRequest.items.product', 'actor'])
                ->latest('id')
                ->take(300)
                ->get();

            foreach ($logs as $log) {
                $num = $extractSlipNumber($log->notes);
                if ($num !== null && !$assignedSlips->contains('numeric_no', $num)) {
                    $pr = $log->purchaseRequest;
                    $itemsList = collect();
                    if ($pr && $pr->items) {
                        $itemsList = $pr->items->map(function ($it) {
                            return [
                                'name'     => $it->product?->name ?? $it->item_name ?? 'Item',
                                'quantity' => (float) ($it->quantity ?? $it->received_quantity ?? 0),
                                'unit'     => $it->unit ?? $it->product?->unit ?? '',
                            ];
                        });
                    }

                    $assignedSlips->push([
                        'slip_no'             => $this->formatSlipNumber($num),
                        'numeric_no'          => $num,
                        'source_type'         => 'workflow_log',
                        'slip_type'           => $slipType,
                        'document_id'         => $log->id,
                        'document_ref'        => $pr ? 'PR #' . $pr->pr_no . ' (Intake Log)' : 'Intake Log #' . $log->id,
                        'document_url'        => $pr ? route('purchase-requests.show', $pr->id) : '#',
                        'status'              => 'verified',
                        'is_void'             => false,
                        'date'                => $log->created_at,
                        'store_name'          => $this->store?->name ?? 'Store',
                        'supplier_name'       => $pr?->supplier_name ?? ($this->store?->name ?? 'Store Intake'),
                        'purchase_request_id' => $pr?->id,
                        'pr_no'               => $pr?->pr_no,
                        'pr_title'            => $pr?->title ?? $pr?->item_name,
                        'pr_url'              => $pr ? route('purchase-requests.show', $pr->id) : null,
                        'project_name'        => $pr?->project?->name ?? ($this->store?->name ?? 'N/A'),
                        'purchase_order_ref'  => null,
                        'po_id'               => null,
                        'handled_by'          => $log->actor?->name ?? 'Store Manager',
                        'items_count'         => $itemsList->count(),
                        'items'               => $itemsList,
                        'notes'               => $log->notes,
                    ]);
                }
            }
        } catch (\Throwable $e) {}

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
