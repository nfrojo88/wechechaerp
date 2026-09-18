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
                'toStore',
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

                $isTransferReceipt = ($receipt->slip_type === 'send') 
                    || !empty($receipt->to_store_id) 
                    || str_contains(strtolower($receipt->supplier_name ?? ''), 'transfer');

                $fromStoreName = $receipt->store?->name ?? 'Origin Store';
                $toStoreName = $receipt->toStore?->name ?? ($receipt->to_store_id ? 'Destination Store' : null);

                $assignedSlips->push([
                    'slip_no'             => $receipt->dr_no ?: $this->formatSlipNumber($numeric),
                    'numeric_no'          => $numeric,
                    'source_type'         => $isTransferReceipt ? 'transfer' : 'delivery_receipt',
                    'slip_type'           => $receipt->slip_type ?: ($isTransferReceipt ? 'send' : $slipType),
                    'document_id'         => $receipt->id,
                    'document_ref'        => $isTransferReceipt 
                        ? ('Store Issue Note / Transfer Slip #' . ($receipt->dr_no ?: $receipt->id)) 
                        : ('GRN / Delivery Receipt #' . ($receipt->dr_no ?: $receipt->id)),
                    'document_url'        => route('delivery-receipts.show', $receipt->id),
                    'status'              => $receipt->is_void ? 'void' : ($receipt->status ?: 'verified'),
                    'is_void'             => (bool) $receipt->is_void,
                    'date'                => $receipt->received_date ?: $receipt->receipt_date ?: $receipt->created_at,
                    'store_name'          => $receipt->store?->name ?? 'Store',
                    'from_store_name'     => $fromStoreName,
                    'to_store_name'       => $toStoreName,
                    'supplier_name'       => $isTransferReceipt 
                        ? ('Store Transfer (' . $fromStoreName . ($toStoreName ? ' ➔ ' . $toStoreName : '') . ')')
                        : ($receipt->supplier_name ?: ($receipt->purchaseOrder?->supplier?->name ?? ($receipt->purchaseRequest?->supplier_name ?? 'Supplier / Store'))),
                    'transfer_no'         => $isTransferReceipt ? ($receipt->reference_no ?: ('TR-SLIP-' . ($receipt->dr_no ?: $receipt->id))) : null,
                    'transfer_id'         => null,
                    'transfer_role'       => $isTransferReceipt ? 'Store Issue' : null,
                    'transfer_status'     => $receipt->status,
                    'driver_name'         => null,
                    'vehicle_plate_no'    => $receipt->vehicle_no,
                    'purchase_request_id' => $receipt->purchase_request_id,
                    'pr_no'               => $receipt->purchaseRequest?->pr_no,
                    'pr_title'            => $receipt->purchaseRequest?->title ?? $receipt->purchaseRequest?->item_name,
                    'pr_url'              => $receipt->purchase_request_id ? route('purchase-requests.show', $receipt->purchase_request_id) : null,
                    'project_name'        => $toStoreName ?: ($receipt->purchaseRequest?->project?->name ?? ($receipt->store?->name ?? 'N/A')),
                    'purchase_order_ref'  => $receipt->purchaseOrder?->reference_number,
                    'po_id'               => $receipt->purchase_order_id,
                    'handled_by'          => $receipt->receivedBy?->name ?? 'Store Keeper',
                    'items_count'         => $itemsList->count(),
                    'items'               => $itemsList,
                    'notes'               => $receipt->notes,
                    'slip_file_url'       => null,
                    'book_id'             => $this->id,
                    'book_label'          => $this->label,
                    'book_range'          => "{$this->book_start_no} - {$this->book_end_no}",
                    'book_status'         => $this->status,
                    'is_current_book'     => true,
                ]);
            }
        }

        // 2. Find matching Transfers across the database (including withTrashed for cancelled/soft-deleted transfers)
        $transfers = Transfer::withTrashed()
            ->with([
                'fromStore', 
                'toStore', 
                'items.product', 
                'requestedBy', 
                'approvedBy', 
                'dispatchedBy', 
                'receivedBy', 
                'driver'
            ])
            ->where(function ($q) use ($storeId) {
                $q->where('from_store_id', $storeId)
                  ->orWhere('to_store_id', $storeId)
                  ->orWhereNotNull('outgoing_slip_no')
                  ->orWhereNotNull('physical_slip_no')
                  ->orWhereNotNull('receiving_slip_no')
                  ->orWhereNotNull('dispatch_notes')
                  ->orWhereNotNull('receiving_notes')
                  ->orWhereNotNull('reason');
            })
            ->latest('id')
            ->get();

        foreach ($transfers as $tr) {
            $slipsToCheck = [
                'outgoing'        => $tr->outgoing_slip_no,
                'physical'        => $tr->physical_slip_no,
                'receiving'       => $tr->receiving_slip_no,
                'dispatch_notes'  => $tr->dispatch_notes,
                'receiving_notes' => $tr->receiving_notes,
                'reason'          => $tr->reason,
                'transfer_no'     => $tr->transfer_no,
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
                            'quantity' => (float) ($it->sent_quantity > 0 ? $it->sent_quantity : ($it->received_quantity > 0 ? $it->received_quantity : ($it->requested_quantity ?? 0))),
                            'unit'     => $it->product?->unit?->name ?? ($it->unit ?? $it->product?->unit ?? ''),
                        ];
                    });

                    $roleLabel = match($role) {
                        'receiving', 'receiving_notes' => 'Receiving Slip',
                        'outgoing'                     => 'Outgoing Slip',
                        'physical'                     => 'Physical Waybill',
                        default                        => 'Transfer Reference',
                    };

                    $isReceiving = in_array($role, ['receiving', 'receiving_notes']);
                    $slipFileUrl = $isReceiving ? $tr->receiving_slip_url : $tr->outgoing_slip_url;

                    // Detect any linked PR from reason or material request
                    $linkedPr = null;
                    if (!empty($tr->reason) && preg_match('/PR[-#\s]*(\d+)/i', $tr->reason, $prm)) {
                        try {
                            $linkedPr = PurchaseRequest::where('pr_no', 'like', '%' . $prm[1] . '%')->first();
                        } catch (\Throwable $e) {}
                    }

                    $assignedSlips->push([
                        'slip_no'             => $this->formatSlipNumber($num),
                        'numeric_no'          => $num,
                        'source_type'         => 'transfer',
                        'slip_type'           => $isReceiving ? 'receive' : 'send',
                        'document_id'         => $tr->id,
                        'document_ref'        => 'Transfer #' . $tr->transfer_no . ' (' . $roleLabel . ')',
                        'document_url'        => route('store-manager.transfers.show', $tr->id),
                        'status'              => $tr->deleted_at ? 'cancelled' : ($tr->status ?: 'completed'),
                        'is_void'             => (bool)$tr->deleted_at || in_array($tr->status, ['rejected', 'cancelled']),
                        'date'                => $isReceiving ? ($tr->received_at ?: $tr->updated_at) : ($tr->dispatched_at ?: $tr->created_at),
                        'store_name'          => ($isReceiving ? $tr->toStore?->name : $tr->fromStore?->name) ?? ($this->store?->name ?? 'Store'),
                        'from_store_name'     => $tr->fromStore?->name ?? 'Source Store',
                        'to_store_name'       => $tr->toStore?->name ?? 'Destination Store',
                        'supplier_name'       => 'Store Transfer: ' . ($tr->fromStore?->name ?? 'From') . ' ➔ ' . ($tr->toStore?->name ?? 'To'),
                        'transfer_no'         => $tr->transfer_no,
                        'transfer_id'         => $tr->id,
                        'transfer_role'       => $roleLabel,
                        'transfer_status'     => $tr->status,
                        'driver_name'         => $tr->driver?->full_name,
                        'vehicle_plate_no'    => $tr->vehicle_plate_no,
                        'purchase_request_id' => $linkedPr?->id,
                        'pr_no'               => $linkedPr?->pr_no,
                        'pr_title'            => $linkedPr?->title ?? $linkedPr?->item_name,
                        'pr_url'              => $linkedPr ? route('purchase-requests.show', $linkedPr->id) : null,
                        'project_name'        => $tr->toStore?->project?->name ?? ($tr->toStore?->name ?? 'Transfer Destination'),
                        'purchase_order_ref'  => null,
                        'po_id'               => null,
                        'handled_by'          => ($isReceiving ? $tr->receivedBy?->name : $tr->dispatchedBy?->name) ?? ($tr->requestedBy?->name ?? 'Store Staff'),
                        'items_count'         => $itemsList->count(),
                        'items'               => $itemsList,
                        'notes'               => $tr->dispatch_notes ?? ($tr->receiving_notes ?? $tr->reason),
                        'slip_file_url'       => $slipFileUrl,
                        'book_id'             => $this->id,
                        'book_label'          => $this->label,
                        'book_range'          => "{$this->book_start_no} - {$this->book_end_no}",
                        'book_status'         => $this->status,
                        'is_current_book'     => true,
                    ]);
                }
            }
        }

        // 3. Find matching Inventory Movements with Slip or Transfer references
        try {
            $movements = InventoryMovement::where(function ($q) {
                    $q->where('remarks', 'like', '%Slip%')
                      ->orWhere('remarks', 'like', '%slip%')
                      ->orWhere('remarks', 'like', '%Transfer%')
                      ->orWhere('remarks', 'like', '%transfer%')
                      ->orWhereIn('reference_type', [
                          Transfer::class,
                          'App\Models\Transfer',
                          'Transfer',
                          PurchaseRequest::class,
                          'App\Models\PurchaseRequest',
                      ]);
                })
                ->with(['performer'])
                ->latest('id')
                ->take(1500)
                ->get();

            foreach ($movements as $mov) {
                $isTransferRef = in_array($mov->reference_type, [
                    Transfer::class,
                    'App\Models\Transfer',
                    'Transfer',
                ]);

                $tr = null;
                if ($isTransferRef && $mov->reference_id) {
                    $tr = Transfer::withTrashed()
                        ->with(['fromStore', 'toStore', 'items.product', 'requestedBy', 'driver', 'dispatchedBy', 'receivedBy'])
                        ->find($mov->reference_id);
                }

                // Extract slip number from remarks or linked transfer slip fields
                $num = $extractSlipNumber($mov->remarks);
                if ($num === null && $tr) {
                    $num = $extractSlipNumber($tr->outgoing_slip_no)
                        ?? $extractSlipNumber($tr->physical_slip_no)
                        ?? $extractSlipNumber($tr->receiving_slip_no)
                        ?? $extractSlipNumber($tr->dispatch_notes)
                        ?? $extractSlipNumber($tr->receiving_notes);
                }

                if ($num !== null && !$assignedSlips->contains('numeric_no', $num)) {
                    if ($tr) {
                        // Transfer linked movement
                        $itemsList = $tr->items->map(function ($it) {
                            return [
                                'name'     => $it->product?->name ?? 'Item',
                                'quantity' => (float) ($it->sent_quantity > 0 ? $it->sent_quantity : ($it->received_quantity > 0 ? $it->received_quantity : ($it->requested_quantity ?? 0))),
                                'unit'     => $it->product?->unit?->name ?? ($it->unit ?? $it->product?->unit ?? ''),
                            ];
                        });

                        $isReceiving = ($mov->type === 'transfer_in');

                        $assignedSlips->push([
                            'slip_no'             => $this->formatSlipNumber($num),
                            'numeric_no'          => $num,
                            'source_type'         => 'transfer',
                            'slip_type'           => $isReceiving ? 'receive' : 'send',
                            'document_id'         => $tr->id,
                            'document_ref'        => 'Transfer #' . $tr->transfer_no . ($isReceiving ? ' (Receive)' : ' (Dispatch)'),
                            'document_url'        => route('store-manager.transfers.show', $tr->id),
                            'status'              => $tr->deleted_at ? 'cancelled' : ($tr->status ?: 'completed'),
                            'is_void'             => (bool)$tr->deleted_at || in_array($tr->status, ['rejected', 'cancelled']),
                            'date'                => $mov->created_at,
                            'store_name'          => ($isReceiving ? $tr->toStore?->name : $tr->fromStore?->name) ?? ($this->store?->name ?? 'Store'),
                            'from_store_name'     => $tr->fromStore?->name ?? 'Source Store',
                            'to_store_name'       => $tr->toStore?->name ?? 'Destination Store',
                            'supplier_name'       => 'Store Transfer: ' . ($tr->fromStore?->name ?? 'Source') . ' ➔ ' . ($tr->toStore?->name ?? 'Destination'),
                            'transfer_no'         => $tr->transfer_no,
                            'transfer_id'         => $tr->id,
                            'transfer_role'       => $isReceiving ? 'Transfer In' : 'Transfer Out',
                            'transfer_status'     => $tr->status,
                            'driver_name'         => $tr->driver?->full_name,
                            'vehicle_plate_no'    => $tr->vehicle_plate_no,
                            'purchase_request_id' => null,
                            'pr_no'               => null,
                            'pr_title'            => null,
                            'pr_url'              => null,
                            'project_name'        => $tr->toStore?->name ?? 'Transfer Destination',
                            'purchase_order_ref'  => null,
                            'po_id'               => null,
                            'handled_by'          => $mov->performer?->name ?? ($tr->dispatchedBy?->name ?? 'Store Staff'),
                            'items_count'         => $itemsList->count(),
                            'items'               => $itemsList,
                            'notes'               => $mov->remarks,
                            'slip_file_url'       => $isReceiving ? $tr->receiving_slip_url : $tr->outgoing_slip_url,
                        ]);
                    } else {
                        // PR or direct inventory movement
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
                            'from_store_name'     => null,
                            'to_store_name'       => null,
                            'supplier_name'       => $pr?->supplier_name ?? ($this->store?->name ?? 'Store Intake'),
                            'transfer_no'         => null,
                            'transfer_id'         => null,
                            'transfer_role'       => null,
                            'transfer_status'     => null,
                            'driver_name'         => null,
                            'vehicle_plate_no'    => null,
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
                            'slip_file_url'       => null,
                            'book_id'             => $this->id,
                            'book_label'          => $this->label,
                            'book_range'          => "{$this->book_start_no} - {$this->book_end_no}",
                            'book_status'         => $this->status,
                            'is_current_book'     => true,
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {}

        // 4. Find matching Purchase Request Workflow Logs with Slip references
        try {
            $logs = PrWorkflowLog::where(function ($q) {
                    $q->where('notes', 'like', '%Slip%')
                      ->orWhere('notes', 'like', '%slip%');
                })
                ->with(['purchaseRequest.project', 'purchaseRequest.requestedBy', 'purchaseRequest.items.product', 'actor'])
                ->latest('id')
                ->take(500)
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
                        'from_store_name'     => null,
                        'to_store_name'       => null,
                        'supplier_name'       => $pr?->supplier_name ?? ($this->store?->name ?? 'Store Intake'),
                        'transfer_no'         => null,
                        'transfer_id'         => null,
                        'transfer_role'       => null,
                        'transfer_status'     => null,
                        'driver_name'         => null,
                        'vehicle_plate_no'    => null,
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
                        'slip_file_url'       => null,
                        'book_id'             => $this->id,
                        'book_label'          => $this->label,
                        'book_range'          => "{$this->book_start_no} - {$this->book_end_no}",
                        'book_status'         => $this->status,
                        'is_current_book'     => true,
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

    /**
     * Get ALL slips ever recorded for this store and slip type across all sequence books (past & present)
     * @return \Illuminate\Support\Collection
     */
    public function getAllStoreSlipsDetail(): \Illuminate\Support\Collection
    {
        return self::getGlobalSlipHistory([
            'store_id'  => $this->store_id,
            'slip_type' => $this->slip_type,
        ], $this->id);
    }

    /**
     * Master Slip History Query: Retrieves all slips recorded across books and stores
     * @param array $filters ['store_id', 'slip_type', 'source_type', 'sequence_id', 'search']
     * @param int|null $currentSequenceId
     * @return \Illuminate\Support\Collection
     */
    public static function getGlobalSlipHistory(array $filters = [], ?int $currentSequenceId = null): \Illuminate\Support\Collection
    {
        $storeId = !empty($filters['store_id']) ? (int) $filters['store_id'] : null;
        $slipType = !empty($filters['slip_type']) ? (string) $filters['slip_type'] : null;
        $sourceType = !empty($filters['source_type']) ? (string) $filters['source_type'] : null;
        $sequenceId = !empty($filters['sequence_id']) ? (int) $filters['sequence_id'] : null;
        $search = !empty($filters['search']) ? trim(strtolower((string) $filters['search'])) : null;

        // Load all sequence books for book attribution
        $allBooksQuery = self::query();
        if ($storeId) {
            $allBooksQuery->where('store_id', $storeId);
        }
        if ($slipType) {
            $allBooksQuery->where('slip_type', $slipType);
        }
        $books = $allBooksQuery->get();

        // Helper to find which book a slip number belongs to
        $findBookForSlip = function (int $slipNo, ?int $itemStoreId, ?string $itemSlipType) use ($books, $currentSequenceId) {
            foreach ($books as $b) {
                $storeMatches = !$itemStoreId || (int)$b->store_id === (int)$itemStoreId;
                $typeMatches = !$itemSlipType || $b->slip_type === $itemSlipType;
                if ($storeMatches && $typeMatches && $slipNo >= (int)$b->book_start_no && $slipNo <= (int)$b->book_end_no) {
                    return $b;
                }
            }
            // Fallback store match only
            foreach ($books as $b) {
                if ($slipNo >= (int)$b->book_start_no && $slipNo <= (int)$b->book_end_no) {
                    return $b;
                }
            }
            return null;
        };

        $extractSlipNumber = function ($str) {
            if (empty($str)) return null;
            if (preg_match_all('/\d+/', (string) $str, $matches)) {
                foreach ($matches[0] as $match) {
                    $val = (int) $match;
                    if ($val > 0) {
                        return $val;
                    }
                }
            }
            return null;
        };

        $assignedSlips = collect();

        // 1. Delivery Receipts
        if (!$sourceType || $sourceType === 'delivery_receipt' || $sourceType === 'transfer') {
            $receiptsQuery = DeliveryReceipt::with([
                'purchaseRequest.project',
                'purchaseRequest.requestedBy',
                'purchaseRequest.items.product',
                'purchaseOrder.supplier',
                'store',
                'toStore',
                'receivedBy',
                'items.product',
            ])->latest('id');

            if ($storeId) {
                $receiptsQuery->where(function ($q) use ($storeId) {
                    $q->where('store_id', $storeId)
                      ->orWhere('to_store_id', $storeId);
                });
            }

            $receipts = $receiptsQuery->get();

            foreach ($receipts as $receipt) {
                $numeric = $extractSlipNumber($receipt->dr_no) 
                        ?? $extractSlipNumber($receipt->reference_no)
                        ?? $extractSlipNumber($receipt->challan_no)
                        ?? $extractSlipNumber($receipt->notes);

                if ($numeric === null) {
                    continue;
                }

                $isTransferReceipt = ($receipt->slip_type === 'send') 
                    || !empty($receipt->to_store_id) 
                    || str_contains(strtolower($receipt->supplier_name ?? ''), 'transfer');

                $recSlipType = $receipt->slip_type ?: ($isTransferReceipt ? 'send' : 'receive');
                $actualSourceType = $isTransferReceipt ? 'transfer' : 'delivery_receipt';

                if ($slipType && $recSlipType !== $slipType) {
                    continue;
                }
                if ($sourceType && $actualSourceType !== $sourceType) {
                    continue;
                }

                $book = $findBookForSlip($numeric, $receipt->store_id, $recSlipType);
                if ($sequenceId && (!$book || $book->id !== $sequenceId)) {
                    continue;
                }

                // Check duplicate
                $dupKey = 'dr_' . $receipt->id . '_' . $numeric;
                if ($assignedSlips->has($dupKey)) {
                    continue;
                }

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

                $fromStoreName = $receipt->store?->name ?? 'Origin Store';
                $toStoreName = $receipt->toStore?->name ?? ($receipt->to_store_id ? 'Destination Store' : null);

                $assignedSlips->put($dupKey, [
                    'slip_no'             => $receipt->dr_no ?: ($book ? $book->formatSlipNumber($numeric) : str_pad($numeric, 5, '0', STR_PAD_LEFT)),
                    'numeric_no'          => $numeric,
                    'source_type'         => $actualSourceType,
                    'slip_type'           => $recSlipType,
                    'document_id'         => $receipt->id,
                    'document_ref'        => $isTransferReceipt 
                        ? ('Store Issue Note / Transfer Slip #' . ($receipt->dr_no ?: $receipt->id)) 
                        : ('GRN / Delivery Receipt #' . ($receipt->dr_no ?: $receipt->id)),
                    'document_url'        => route('delivery-receipts.show', $receipt->id),
                    'status'              => $receipt->is_void ? 'void' : ($receipt->status ?: 'verified'),
                    'is_void'             => (bool) $receipt->is_void,
                    'date'                => $receipt->received_date ?: $receipt->receipt_date ?: $receipt->created_at,
                    'store_name'          => $receipt->store?->name ?? 'Store',
                    'from_store_name'     => $fromStoreName,
                    'to_store_name'       => $toStoreName,
                    'supplier_name'       => $isTransferReceipt 
                        ? ('Store Transfer (' . $fromStoreName . ($toStoreName ? ' ➔ ' . $toStoreName : '') . ')')
                        : ($receipt->supplier_name ?: ($receipt->purchaseOrder?->supplier?->name ?? ($receipt->purchaseRequest?->supplier_name ?? 'Supplier / Store'))),
                    'transfer_no'         => $isTransferReceipt ? ($receipt->reference_no ?: ('TR-SLIP-' . ($receipt->dr_no ?: $receipt->id))) : null,
                    'transfer_id'         => null,
                    'transfer_role'       => $isTransferReceipt ? 'Store Issue' : null,
                    'transfer_status'     => $receipt->status,
                    'driver_name'         => null,
                    'vehicle_plate_no'    => $receipt->vehicle_no,
                    'purchase_request_id' => $receipt->purchase_request_id,
                    'pr_no'               => $receipt->purchaseRequest?->pr_no,
                    'pr_title'            => $receipt->purchaseRequest?->title ?? $receipt->purchaseRequest?->item_name,
                    'pr_url'              => $receipt->purchase_request_id ? route('purchase-requests.show', $receipt->purchase_request_id) : null,
                    'project_name'        => $toStoreName ?: ($receipt->purchaseRequest?->project?->name ?? ($receipt->store?->name ?? 'N/A')),
                    'purchase_order_ref'  => $receipt->purchaseOrder?->reference_number,
                    'po_id'               => $receipt->purchase_order_id,
                    'handled_by'          => $receipt->receivedBy?->name ?? 'Store Keeper',
                    'items_count'         => $itemsList->count(),
                    'items'               => $itemsList,
                    'notes'               => $receipt->notes,
                    'slip_file_url'       => null,
                    'book_id'             => $book?->id,
                    'book_label'          => $book?->label ?? 'Sequence Book',
                    'book_range'          => $book ? "{$book->book_start_no} - {$book->book_end_no}" : 'Manual / Other',
                    'book_status'         => $book?->status ?? 'archived',
                    'is_current_book'     => ($currentSequenceId && $book && $book->id === $currentSequenceId),
                ]);
            }
        }

        // 2. Transfers
        if (!$sourceType || $sourceType === 'transfer') {
            $transfersQuery = Transfer::withTrashed()->with([
                'fromStore', 
                'toStore', 
                'items.product', 
                'requestedBy', 
                'approvedBy', 
                'dispatchedBy', 
                'receivedBy', 
                'driver'
            ])->latest('id');

            if ($storeId) {
                $transfersQuery->where(function ($q) use ($storeId) {
                    $q->where('from_store_id', $storeId)
                      ->orWhere('to_store_id', $storeId);
                });
            }

            $transfers = $transfersQuery->get();

            foreach ($transfers as $tr) {
                $slipsToCheck = [
                    'outgoing'        => $tr->outgoing_slip_no,
                    'physical'        => $tr->physical_slip_no,
                    'receiving'       => $tr->receiving_slip_no,
                    'dispatch_notes'  => $tr->dispatch_notes,
                    'receiving_notes' => $tr->receiving_notes,
                    'reason'          => $tr->reason,
                    'transfer_no'     => $tr->transfer_no,
                ];

                foreach ($slipsToCheck as $role => $sVal) {
                    if (empty($sVal)) continue;
                    $num = $extractSlipNumber($sVal);
                    if ($num === null) continue;

                    $isReceiving = in_array($role, ['receiving', 'receiving_notes']);
                    $trSlipType = $isReceiving ? 'receive' : 'send';
                    $trStoreId = $isReceiving ? $tr->to_store_id : $tr->from_store_id;

                    if ($slipType && $trSlipType !== $slipType) {
                        continue;
                    }
                    if ($storeId && $trStoreId != $storeId) {
                        // Allow if matching either origin or destination
                        if ($tr->to_store_id != $storeId && $tr->from_store_id != $storeId) {
                            continue;
                        }
                    }

                    $book = $findBookForSlip($num, $trStoreId, $trSlipType);
                    if ($sequenceId && (!$book || $book->id !== $sequenceId)) {
                        continue;
                    }

                    $dupKey = 'tr_' . $tr->id . '_' . $role . '_' . $num;
                    if ($assignedSlips->has($dupKey)) {
                        continue;
                    }

                    $itemsList = $tr->items->map(function ($it) {
                        return [
                            'name'     => $it->product?->name ?? 'Item',
                            'quantity' => (float) ($it->sent_quantity > 0 ? $it->sent_quantity : ($it->received_quantity > 0 ? $it->received_quantity : ($it->requested_quantity ?? 0))),
                            'unit'     => $it->product?->unit?->name ?? ($it->unit ?? $it->product?->unit ?? ''),
                        ];
                    });

                    $roleLabel = match($role) {
                        'receiving', 'receiving_notes' => 'Receiving Slip',
                        'outgoing'                     => 'Outgoing Slip',
                        'physical'                     => 'Physical Waybill',
                        default                        => 'Transfer Reference',
                    };

                    $slipFileUrl = $isReceiving ? $tr->receiving_slip_url : $tr->outgoing_slip_url;

                    $linkedPr = null;
                    if (!empty($tr->reason) && preg_match('/PR[-#\s]*(\d+)/i', $tr->reason, $prm)) {
                        try {
                            $linkedPr = PurchaseRequest::where('pr_no', 'like', '%' . $prm[1] . '%')->first();
                        } catch (\Throwable $e) {}
                    }

                    $assignedSlips->put($dupKey, [
                        'slip_no'             => $book ? $book->formatSlipNumber($num) : str_pad($num, 5, '0', STR_PAD_LEFT),
                        'numeric_no'          => $num,
                        'source_type'         => 'transfer',
                        'slip_type'           => $trSlipType,
                        'document_id'         => $tr->id,
                        'document_ref'        => 'Transfer #' . $tr->transfer_no . ' (' . $roleLabel . ')',
                        'document_url'        => route('store-manager.transfers.show', $tr->id),
                        'status'              => $tr->deleted_at ? 'cancelled' : ($tr->status ?: 'completed'),
                        'is_void'             => (bool)$tr->deleted_at || in_array($tr->status, ['rejected', 'cancelled']),
                        'date'                => $isReceiving ? ($tr->received_at ?: $tr->updated_at) : ($tr->dispatched_at ?: $tr->created_at),
                        'store_name'          => ($isReceiving ? $tr->toStore?->name : $tr->fromStore?->name) ?? 'Store',
                        'from_store_name'     => $tr->fromStore?->name ?? 'Source Store',
                        'to_store_name'       => $tr->toStore?->name ?? 'Destination Store',
                        'supplier_name'       => 'Store Transfer: ' . ($tr->fromStore?->name ?? 'From') . ' ➔ ' . ($tr->toStore?->name ?? 'To'),
                        'transfer_no'         => $tr->transfer_no,
                        'transfer_id'         => $tr->id,
                        'transfer_role'       => $roleLabel,
                        'transfer_status'     => $tr->status,
                        'driver_name'         => $tr->driver?->full_name,
                        'vehicle_plate_no'    => $tr->vehicle_plate_no,
                        'purchase_request_id' => $linkedPr?->id,
                        'pr_no'               => $linkedPr?->pr_no,
                        'pr_title'            => $linkedPr?->title ?? $linkedPr?->item_name,
                        'pr_url'              => $linkedPr ? route('purchase-requests.show', $linkedPr->id) : null,
                        'project_name'        => $tr->toStore?->project?->name ?? ($tr->toStore?->name ?? 'Transfer Destination'),
                        'purchase_order_ref'  => null,
                        'po_id'               => null,
                        'handled_by'          => ($isReceiving ? $tr->receivedBy?->name : $tr->dispatchedBy?->name) ?? ($tr->requestedBy?->name ?? 'Store Staff'),
                        'items_count'         => $itemsList->count(),
                        'items'               => $itemsList,
                        'notes'               => $tr->dispatch_notes ?? ($tr->receiving_notes ?? $tr->reason),
                        'slip_file_url'       => $slipFileUrl,
                        'book_id'             => $book?->id,
                        'book_label'          => $book?->label ?? 'Sequence Book',
                        'book_range'          => $book ? "{$book->book_start_no} - {$book->book_end_no}" : 'Manual / Other',
                        'book_status'         => $book?->status ?? 'archived',
                        'is_current_book'     => ($currentSequenceId && $book && $book->id === $currentSequenceId),
                    ]);
                }
            }
        }

        // 3. Inventory Movements
        try {
            $movementsQuery = InventoryMovement::where(function ($q) {
                $q->where('remarks', 'like', '%Slip%')
                  ->orWhere('remarks', 'like', '%slip%')
                  ->orWhere('remarks', 'like', '%Transfer%')
                  ->orWhere('remarks', 'like', '%transfer%');
            })->with(['performer'])->latest('id')->take(1000);

            $movements = $movementsQuery->get();

            foreach ($movements as $mov) {
                $num = $extractSlipNumber($mov->remarks);
                if ($num === null) continue;

                $movSlipType = ($mov->type === 'transfer_out') ? 'send' : 'receive';
                if ($slipType && $movSlipType !== $slipType) continue;

                $book = $findBookForSlip($num, null, $movSlipType);
                if ($sequenceId && (!$book || $book->id !== $sequenceId)) continue;

                $dupKey = 'mov_' . $mov->id . '_' . $num;
                if ($assignedSlips->has($dupKey)) continue;

                $assignedSlips->put($dupKey, [
                    'slip_no'             => $book ? $book->formatSlipNumber($num) : str_pad($num, 5, '0', STR_PAD_LEFT),
                    'numeric_no'          => $num,
                    'source_type'         => 'inventory_movement',
                    'slip_type'           => $movSlipType,
                    'document_id'         => $mov->id,
                    'document_ref'        => 'Inventory Movement #' . $mov->id,
                    'document_url'        => route('store-manager.inventory.all'),
                    'status'              => 'verified',
                    'is_void'             => false,
                    'date'                => $mov->created_at,
                    'store_name'          => 'Store',
                    'from_store_name'     => null,
                    'to_store_name'       => null,
                    'supplier_name'       => 'Inventory Intake',
                    'transfer_no'         => null,
                    'transfer_id'         => null,
                    'transfer_role'       => null,
                    'transfer_status'     => null,
                    'driver_name'         => null,
                    'vehicle_plate_no'    => null,
                    'purchase_request_id' => null,
                    'pr_no'               => null,
                    'pr_title'            => null,
                    'pr_url'              => null,
                    'project_name'        => 'N/A',
                    'purchase_order_ref'  => null,
                    'po_id'               => null,
                    'handled_by'          => $mov->performer?->name ?? 'Store Staff',
                    'items_count'         => 0,
                    'items'               => collect(),
                    'notes'               => $mov->remarks,
                    'slip_file_url'       => null,
                    'book_id'             => $book?->id,
                    'book_label'          => $book?->label ?? 'Sequence Book',
                    'book_range'          => $book ? "{$book->book_start_no} - {$book->book_end_no}" : 'Manual / Other',
                    'book_status'         => $book?->status ?? 'archived',
                    'is_current_book'     => ($currentSequenceId && $book && $book->id === $currentSequenceId),
                ]);
            }
        } catch (\Throwable $e) {}

        // Apply search filter if provided
        $results = $assignedSlips->values();
        if ($search) {
            $results = $results->filter(function ($row) use ($search) {
                return str_contains(strtolower($row['slip_no'] ?? ''), $search)
                    || str_contains(strtolower((string)($row['numeric_no'] ?? '')), $search)
                    || str_contains(strtolower($row['document_ref'] ?? ''), $search)
                    || str_contains(strtolower($row['pr_no'] ?? ''), $search)
                    || str_contains(strtolower($row['pr_title'] ?? ''), $search)
                    || str_contains(strtolower($row['transfer_no'] ?? ''), $search)
                    || str_contains(strtolower($row['supplier_name'] ?? ''), $search)
                    || str_contains(strtolower($row['driver_name'] ?? ''), $search)
                    || str_contains(strtolower($row['store_name'] ?? ''), $search)
                    || str_contains(strtolower($row['project_name'] ?? ''), $search)
                    || str_contains(strtolower($row['notes'] ?? ''), $search);
            });
        }

        return $results->sortByDesc('numeric_no')->values();
    }
}
