<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRequest extends Model
{
    use SoftDeletes;

    // ── Status State Machine Constants ─────────────────────────────────────
    const STATUS_DRAFT                      = 'draft';
    const STATUS_PENDING_PLANNING           = 'pending_planning_approval';
    const STATUS_PENDING_HR_APPROVAL        = 'pending_hr_approval';
    const STATUS_APPROVED                   = 'approved';
    const STATUS_PENDING_STORE_REVIEW       = 'pending_store_review';
    const STATUS_TRANSFERRED                = 'transferred';
    const STATUS_PENDING_PROC_MANAGER       = 'pending_procurement_manager';
    const STATUS_PENDING_PROC_TEAM          = 'pending_procurement_team';
    const STATUS_PENDING_MARKETING          = 'pending_marketing_review';
    const STATUS_PENDING_PROFORMA_SELECTION = 'pending_proforma_selection';
    const STATUS_PENDING_GM                 = 'pending_gm_decision';
    const STATUS_PENDING_FINANCE            = 'pending_finance';
    const STATUS_PENDING_PAYMENT            = 'pending_payment';
    const STATUS_PENDING_RECEIPT_UPLOAD     = 'pending_receipt_upload';
    const STATUS_PENDING_RECEIPT_VERIFY     = 'pending_receipt_verification';
    const STATUS_PENDING_DRIVER             = 'pending_driver_booking';
    const STATUS_INTAKE_COMPLETE            = 'intake_complete';
    const STATUS_COMPLETED                  = 'completed';
    const STATUS_REJECTED                   = 'rejected';
    const STATUS_CANCELLED                  = 'cancelled';

    public static function allStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PENDING_PLANNING,
            self::STATUS_PENDING_HR_APPROVAL,
            self::STATUS_APPROVED,
            self::STATUS_PENDING_STORE_REVIEW,
            self::STATUS_TRANSFERRED,
            self::STATUS_PENDING_PROC_MANAGER,
            self::STATUS_PENDING_PROC_TEAM,
            self::STATUS_PENDING_MARKETING,
            self::STATUS_PENDING_PROFORMA_SELECTION,
            self::STATUS_PENDING_GM,
            self::STATUS_PENDING_FINANCE,
            self::STATUS_PENDING_PAYMENT,
            self::STATUS_PENDING_RECEIPT_UPLOAD,
            self::STATUS_PENDING_RECEIPT_VERIFY,
            self::STATUS_PENDING_DRIVER,
            self::STATUS_INTAKE_COMPLETE,
            self::STATUS_COMPLETED,
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED,
        ];
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_DRAFT                      => 'Draft',
            self::STATUS_PENDING_PLANNING           => 'Pending Planning Approval',
            self::STATUS_PENDING_HR_APPROVAL        => 'Pending Coordinator Review',
            self::STATUS_APPROVED                   => 'Approved',
            self::STATUS_PENDING_STORE_REVIEW       => 'Pending Store Review',
            self::STATUS_TRANSFERRED                => 'Transferred',
            self::STATUS_PENDING_PROC_MANAGER       => 'Pending Procurement Manager',
            self::STATUS_PENDING_PROC_TEAM          => 'Pending Procurement Team',
            self::STATUS_PENDING_MARKETING          => 'Pending PM Price Review',
            self::STATUS_PENDING_PROFORMA_SELECTION => 'Pending Proforma Selection',
            self::STATUS_PENDING_GM                 => 'Pending GM Decision',
            self::STATUS_PENDING_FINANCE            => 'Pending Finance (Credit)',
            self::STATUS_PENDING_PAYMENT            => 'Pending Payment',
            self::STATUS_PENDING_RECEIPT_UPLOAD     => 'Pending Receipt Upload',
            self::STATUS_PENDING_RECEIPT_VERIFY     => 'Pending Receipt Verification',
            self::STATUS_PENDING_DRIVER             => 'Pending Driver Booking',
            self::STATUS_INTAKE_COMPLETE            => 'Intake Complete',
            self::STATUS_COMPLETED                  => 'Completed',
            self::STATUS_REJECTED                   => 'Rejected',
            self::STATUS_CANCELLED                  => 'Cancelled',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    public function getEstimatedTotalCostAttribute(): float
    {
        if (isset($this->attributes['estimated_total_cost'])) {
            return (float)$this->attributes['estimated_total_cost'];
        }
        if (!empty($this->direct_buy_amount) && (float)$this->direct_buy_amount > 0) {
            return (float)$this->direct_buy_amount;
        }
        if ($this->relationLoaded('items')) {
            return (float)$this->items->sum('estimated_total');
        }
        return 0.0;
    }

    public static function statusBadgeClass(string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT                      => 'secondary',
            self::STATUS_PENDING_PLANNING           => 'warning',
            self::STATUS_PENDING_HR_APPROVAL        => 'warning',
            self::STATUS_APPROVED                   => 'success',
            self::STATUS_PENDING_STORE_REVIEW       => 'info',
            self::STATUS_TRANSFERRED                => 'primary',
            self::STATUS_PENDING_PROC_MANAGER       => 'warning',
            self::STATUS_PENDING_PROC_TEAM          => 'warning',
            self::STATUS_PENDING_MARKETING          => 'warning',
            self::STATUS_PENDING_PROFORMA_SELECTION => 'warning',
            self::STATUS_PENDING_GM                 => 'danger',
            self::STATUS_PENDING_FINANCE            => 'info',
            self::STATUS_PENDING_PAYMENT            => 'info',
            self::STATUS_PENDING_RECEIPT_UPLOAD     => 'primary',
            self::STATUS_PENDING_RECEIPT_VERIFY     => 'primary',
            self::STATUS_PENDING_DRIVER             => 'info',
            self::STATUS_INTAKE_COMPLETE            => 'success',
            self::STATUS_COMPLETED                  => 'success',
            self::STATUS_REJECTED                   => 'danger',
            self::STATUS_CANCELLED                  => 'dark',
            default                                 => 'secondary',
        };
    }

    public static function resolveDefaultOwnerRoleForStatus(?string $status): ?string
    {
        return match ($status) {
            self::STATUS_PENDING_PLANNING           => 'planning',
            self::STATUS_PENDING_HR_APPROVAL        => 'coordinator',
            self::STATUS_PENDING_STORE_REVIEW       => 'store_manager',
            self::STATUS_PENDING_PROC_MANAGER       => 'purchase_manager',
            self::STATUS_PENDING_PROC_TEAM          => 'purchase',
            self::STATUS_PENDING_MARKETING          => 'purchase_manager',
            self::STATUS_PENDING_PROFORMA_SELECTION => 'purchase_manager',
            self::STATUS_PENDING_GM                 => 'gm',
            self::STATUS_PENDING_FINANCE            => 'finance_head',
            self::STATUS_PENDING_PAYMENT            => 'finance',
            self::STATUS_PENDING_RECEIPT_UPLOAD     => 'purchase',
            self::STATUS_PENDING_RECEIPT_VERIFY     => 'store_keeper',
            self::STATUS_PENDING_DRIVER             => 'general_service',
            self::STATUS_INTAKE_COMPLETE            => 'store_keeper',
            self::STATUS_COMPLETED, self::STATUS_CANCELLED => null,
            default                                 => null,
        };
    }

    protected static function booted()
    {
        static::creating(function (PurchaseRequest $pr) {
            if (empty($pr->pr_no)) {
                $pr->pr_no = static::generatePrNo();
            } else {
                if (static::withTrashed()->where('pr_no', $pr->pr_no)->exists()) {
                    $pr->pr_no = static::generatePrNo($pr->pr_no);
                }
            }
        });

        static::updating(function (PurchaseRequest $pr) {
            if ($pr->isDirty('status') && !$pr->isDirty('current_owner_role')) {
                $newRole = static::resolveDefaultOwnerRoleForStatus($pr->status);
                if ($newRole !== null) {
                    $pr->current_owner_role = $newRole;
                }
            }
        });
    }

    /**
     * Generate a guaranteed unique PR number (e.g. PR-20261017-0111).
     */
    public static function generatePrNo(?string $preferredNo = null): string
    {
        $datePrefix = 'PR-' . date('Ymd') . '-';

        if (!empty($preferredNo)) {
            $candidate = trim($preferredNo);
            if (!static::withTrashed()->where('pr_no', $candidate)->exists()) {
                return $candidate;
            }

            if (preg_match('/^PR-(\d{8})-(\d+)$/', $candidate, $matches)) {
                $pDate = $matches[1];
                $pSeq  = (int)$matches[2];
                do {
                    $pSeq++;
                    $candidate = 'PR-' . $pDate . '-' . str_pad($pSeq, 4, '0', STR_PAD_LEFT);
                } while (static::withTrashed()->where('pr_no', $candidate)->exists());
                return $candidate;
            }

            $suffix = 1;
            $original = $candidate;
            do {
                $candidate = $original . '-' . $suffix;
                $suffix++;
            } while (static::withTrashed()->where('pr_no', $candidate)->exists());
            return $candidate;
        }

        $existingNos = static::withTrashed()
            ->where('pr_no', 'like', $datePrefix . '%')
            ->pluck('pr_no')
            ->all();

        $maxSeq = 0;
        foreach ($existingNos as $no) {
            if (preg_match('/' . preg_quote($datePrefix, '/') . '(\d+)/', $no, $m)) {
                $seq = (int)$m[1];
                if ($seq > $maxSeq) {
                    $maxSeq = $seq;
                }
            }
        }

        $nextSeq = max($maxSeq + 1, static::withTrashed()->count() + 1);

        do {
            $candidate = $datePrefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
            $exists = in_array($candidate, $existingNos) || static::withTrashed()->where('pr_no', $candidate)->exists();
            if ($exists) {
                $nextSeq++;
            }
        } while ($exists);

        return $candidate;
    }

    protected $fillable = [
        'pr_no', 'project_id', 'store_id', 'requested_by', 'material_request_id',
        'priority', 'type', 'is_office_request', 'office_purpose', 'required_date', 'justification', 'status',
        'merged_into_pr_id', 'approved_by', 'approved_at', 'rejection_reason',
        'hr_coordinator_notes', 'hr_coordinator_approved_by', 'hr_coordinator_approved_at',
        'sourcing_method', 'direct_buy_amount', 'direct_buy_added_by',
        'procurement_team_notes', 'pm_sendback_reason',
        'gm_loop_count', 'current_owner_role',
    ];

    protected $casts = [
        'is_office_request'          => 'boolean',
        'required_date'              => 'date',
        'approved_at'                => 'datetime',
        'hr_coordinator_approved_at' => 'datetime',
        'direct_buy_amount'          => 'decimal:2',
    ];

    // ── Core Relations ─────────────────────────────────────────────────────
    public function project()        { return $this->belongsTo(Project::class); }
    public function store()          { return $this->belongsTo(Store::class); }
    public function supplier()       { return $this->belongsTo(Supplier::class, 'supplier_id'); }
    public function requestedBy()    { return $this->belongsTo(User::class, 'requested_by'); }
    public function requester()      { return $this->requestedBy(); }
    public function approvedBy()     { return $this->belongsTo(User::class, 'approved_by'); }
    public function hrCoordinatorApprovedBy() { return $this->belongsTo(User::class, 'hr_coordinator_approved_by'); }
    public function materialRequest(){ return $this->belongsTo(MaterialRequest::class); }
    public function mergedInto()     { return $this->belongsTo(PurchaseRequest::class, 'merged_into_pr_id'); }
    public function directBuyBy()    { return $this->belongsTo(User::class, 'direct_buy_added_by'); }

    public function items()           { return $this->hasMany(PurchaseRequestItem::class); }
    public function marketResearch()  { return $this->hasMany(MarketResearch::class); }
    public function proformaInvoices(){ return $this->hasMany(ProformaInvoice::class); }
    public function proformas()       { return $this->proformaInvoices(); }
    public function purchaseOrders()  { return $this->hasMany(PurchaseOrder::class); }

    // ── New Lifecycle Relations ────────────────────────────────────────────
    public function gmDecisions()    { return $this->hasMany(PrGmDecision::class)->orderBy('round'); }
    public function latestGmDecision(){ return $this->hasOne(PrGmDecision::class)->latestOfMany('round'); }
    public function marketingVariance(){ return $this->hasOne(PrMarketingVariance::class)->latestOfMany(); }
    public function payment()        { return $this->hasOne(ProcurementPayment::class); }
    public function payments()       { return $this->hasMany(ProcurementPayment::class); }
    public function creditLedger()   { return $this->hasOne(CreditStoreLedger::class); }
    public function receipt()        { return $this->hasOne(ProcurementReceipt::class); }
    public function driverBooking()  { return $this->hasOne(DriverBooking::class); }
    public function workflowLogs()   { return $this->hasMany(PrWorkflowLog::class)->orderBy('created_at'); }
    public function deliveryReceipts(){ return $this->hasMany(DeliveryReceipt::class); }

    public function getWithholdingReceiptUrlAttribute(): ?string
    {
        $path = $this->receipt?->withholding_receipt_path
            ?? $this->payment?->withholding_receipt;
        return $path ? \App\Services\FileUploadService::url($path) : null;
    }

    public function getEffectiveWithholdingReceiptNumberAttribute(): ?string
    {
        return $this->receipt?->withholding_receipt_number
            ?? $this->payment?->withholding_receipt_number;
    }

    public function getEffectiveWithholdingAmountAttribute(): float
    {
        return (float)($this->receipt?->withholding_amount
            ?? $this->payment?->withholding_amount
            ?? 0);
    }

    public function getEffectiveWithholdingRateAttribute(): float
    {
        return (float)($this->receipt?->withholding_rate
            ?? $this->payment?->withholding_rate
            ?? 2.00);
    }

    public function getHasEffectiveWithholdingAttribute(): bool
    {
        return (bool)(
            $this->receipt?->has_withholding
            || $this->payment?->has_withholding
            || !empty($this->receipt?->withholding_receipt_path)
            || !empty($this->payment?->withholding_receipt)
            || ((float)($this->receipt?->withholding_amount ?? 0) > 0)
            || ((float)($this->payment?->withholding_amount ?? 0) > 0)
        );
    }

    /**
     * Get transfers associated with this PR (by reason matching PR no or material_request_id).
     */
    public function getLinkedTransfersAttribute()
    {
        return \App\Models\Transfer::with(['fromStore', 'toStore', 'items.product', 'driver', 'requestedBy'])
            ->where(function ($q) {
                $q->where('reason', 'like', "%{$this->pr_no}%");
                if ($this->material_request_id) {
                    $q->orWhere('reason', 'like', "%MR-{$this->material_request_id}%");
                }
            })
            ->latest()
            ->get();
    }

    /**
     * Get all effective items for this PR, combining direct PR items and items transferred via store transfers.
     */
    public function getEffectiveItemsList(): array
    {
        $list = [];

        // Direct PR items
        if ($this->relationLoaded('items')) {
            foreach ($this->items as $it) {
                $list[] = [
                    'source'        => 'direct',
                    'product_id'    => $it->product_id,
                    'product_name'  => $it->product?->name ?? ('Item #' . $it->product_id),
                    'quantity'      => (float)$it->quantity,
                    'unit'          => $it->unit ?? ($it->product?->unit ?? 'pcs'),
                    'status'        => 'In PR Queue',
                    'transfer'      => null,
                ];
            }
        } elseif ($this->items()->exists()) {
            foreach ($this->items()->with('product')->get() as $it) {
                $list[] = [
                    'source'        => 'direct',
                    'product_id'    => $it->product_id,
                    'product_name'  => $it->product?->name ?? ('Item #' . $it->product_id),
                    'quantity'      => (float)$it->quantity,
                    'unit'          => $it->unit ?? ($it->product?->unit ?? 'pcs'),
                    'status'        => 'In PR Queue',
                    'transfer'      => null,
                ];
            }
        }

        // Transferred items
        $transfers = $this->linked_transfers;
        foreach ($transfers as $tr) {
            foreach ($tr->items as $ti) {
                $list[] = [
                    'source'        => 'transferred',
                    'product_id'    => $ti->product_id,
                    'product_name'  => $ti->product?->name ?? ('Item #' . $ti->product_id),
                    'quantity'      => (float)($ti->requested_quantity ?? $ti->approved_quantity ?? 0),
                    'unit'          => $ti->unit ?? ($ti->product?->unit ?? 'pcs'),
                    'status'        => 'Transferred via ' . ($tr->transfer_no ?? ('Transfer #' . $tr->id)),
                    'transfer'      => $tr,
                    'transfer_no'   => $tr->transfer_no,
                    'from_store'    => $tr->fromStore?->name,
                    'to_store'      => $tr->toStore?->name,
                ];
            }
        }

        return $list;
    }

    public function receiptRecords(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Receipt::class, 'purchasable');
    }

    /**
     * Compute a full comparative bids matrix across all attached proformas and material items.
     */
    public function getProformaComparisonMatrix(): array
    {
        $proformas = $this->proformaInvoices()->with('supplier')->get();
        $items = $this->items()->with('product', 'supplier', 'proformaInvoice')->get();

        $matrix = [
            'has_proformas'        => $proformas->isNotEmpty(),
            'proforma_count'       => $proformas->count(),
            'proformas'            => [],
            'items'                => [],
            'best_overall_total'   => 0.0,
            'selected_proforma_id' => $proformas->firstWhere('gm_selected', true)?->id ?? $proformas->first()?->id,
            'lowest_total_proforma'=> $proformas->sortBy('grand_total')->first(),
        ];

        $minGrandTotal = $proformas->where('grand_total', '>', 0)->min('grand_total');
        $selectedProfId = $proformas->firstWhere('gm_selected', true)?->id ?? $proformas->first()?->id;

        foreach ($proformas as $prof) {
            $isLowestOverall = ($minGrandTotal !== null && (float)$prof->grand_total == (float)$minGrandTotal);
            $isSelected = ($prof->id === $selectedProfId || (bool)$prof->gm_selected);

            $matrix['proformas'][$prof->id] = [
                'id'                => $prof->id,
                'proforma_no'       => $prof->proforma_no,
                'supplier_id'       => $prof->supplier_id,
                'supplier_name'     => $prof->supplier?->name ?? ($prof->supplier_name ?: 'Vendor #' . $prof->supplier_id),
                'supplier_phone'    => $prof->supplier?->phone,
                'proforma_date'     => $prof->proforma_date?->format('M d, Y'),
                'valid_until'       => $prof->valid_until?->format('M d, Y'),
                'subtotal'          => (float)$prof->subtotal,
                'tax_amount'        => (float)$prof->tax_amount,
                'grand_total'       => (float)$prof->grand_total,
                'gm_selected'       => (bool)$prof->gm_selected,
                'is_selected'       => $isSelected,
                'is_lowest_overall' => $isLowestOverall,
                'file_path'         => $prof->file_path,
                'file_url'          => \App\Services\FileUploadService::url($prof->file_path),
                'notes'             => $prof->notes,
            ];
        }

        $optimalCombinedTotal = 0.0;

        foreach ($items as $item) {
            $qty = (float)$item->quantity;
            $itemQuotes = [];
            $lowestPrice = null;
            $lowestProfId = null;

            foreach ($proformas as $prof) {
                $quotedUnit = null;
                $quotedTotal = null;

                // 1. Look in structured item_prices
                if (!empty($prof->item_prices) && is_array($prof->item_prices)) {
                    foreach ($prof->item_prices as $ip) {
                        if (is_array($ip)) {
                            $match = (!empty($ip['item_id']) && $ip['item_id'] == $item->id)
                                || (!empty($ip['product_id']) && $ip['product_id'] == $item->product_id);
                            if ($match && isset($ip['unit_price'])) {
                                $quotedUnit = (float)$ip['unit_price'];
                                $quotedTotal = isset($ip['total']) ? (float)$ip['total'] : round($quotedUnit * $qty, 2);
                                break;
                            }
                        }
                    }
                    // Associative array check: [$item->id => price]
                    if ($quotedUnit === null && isset($prof->item_prices[$item->id])) {
                        $quotedUnit = (float)$prof->item_prices[$item->id];
                        $quotedTotal = round($quotedUnit * $qty, 2);
                    }
                }

                // 2. Fallback if single-item PR and grand_total is present
                if ($quotedUnit === null && $items->count() === 1 && (float)$prof->grand_total > 0) {
                    $quotedUnit = $qty > 0 ? round((float)$prof->grand_total / $qty, 2) : (float)$prof->grand_total;
                    $quotedTotal = (float)$prof->grand_total;
                }

                if ($quotedUnit !== null && $quotedUnit > 0) {
                    if ($lowestPrice === null || $quotedUnit < $lowestPrice) {
                        $lowestPrice = $quotedUnit;
                        $lowestProfId = $prof->id;
                    }
                }

                $itemQuotes[$prof->id] = [
                    'proforma_id'   => $prof->id,
                    'proforma_no'   => $prof->proforma_no,
                    'supplier_name' => $matrix['proformas'][$prof->id]['supplier_name'] ?? 'Vendor',
                    'unit_price'    => $quotedUnit,
                    'line_total'    => $quotedTotal,
                ];
            }

            // Flag which quote is lowest
            foreach ($itemQuotes as $pId => &$q) {
                $q['is_lowest'] = ($q['unit_price'] !== null && $lowestPrice !== null && $q['unit_price'] == $lowestPrice);
            }
            unset($q);

            if ($lowestPrice !== null) {
                $optimalCombinedTotal += ($lowestPrice * $qty);
            } else {
                $optimalCombinedTotal += ((float)($item->estimated_unit_cost ?? 0) * $qty);
            }

            // Check currently awarded proforma for this item
            $awardedProfId = $item->proforma_invoice_id;
            if (!$awardedProfId) {
                // If PR has a selected proforma, default item to that proforma
                $awardedProfId = $matrix['selected_proforma_id'];
            }

            $matrix['items'][$item->id] = [
                'item_id'             => $item->id,
                'product_id'          => $item->product_id,
                'product_name'        => $item->product?->name ?? ('Item #' . $item->product_id),
                'product_code'        => $item->product?->code,
                'quantity'            => $qty,
                'unit'                => $item->unit,
                'specifications'      => $item->specifications,
                'current_unit_cost'   => (float)($item->estimated_unit_cost ?? 0),
                'current_total'       => (float)($item->estimated_unit_cost ?? 0) * $qty,
                'quotes'              => $itemQuotes,
                'lowest_unit_price'   => $lowestPrice,
                'lowest_proforma_id'  => $lowestProfId,
                'awarded_proforma_id' => $awardedProfId,
                'payment_method'      => $item->payment_method ?? 'pay_and_buy',
            ];
        }

        $matrix['best_overall_total'] = round($optimalCombinedTotal, 2);

        return $matrix;
    }
}

