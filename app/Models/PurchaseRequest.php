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
            self::STATUS_PENDING_HR_APPROVAL        => 'Pending HR / Coordinator Approval',
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
    public function purchaseOrders()  { return $this->hasMany(PurchaseOrder::class); }

    // ── New Lifecycle Relations ────────────────────────────────────────────
    public function gmDecisions()    { return $this->hasMany(PrGmDecision::class)->orderBy('round'); }
    public function latestGmDecision(){ return $this->hasOne(PrGmDecision::class)->latestOfMany('round'); }
    public function marketingVariance(){ return $this->hasOne(PrMarketingVariance::class)->latestOfMany(); }
    public function payment()        { return $this->hasOne(ProcurementPayment::class); }
    public function creditLedger()   { return $this->hasOne(CreditStoreLedger::class); }
    public function receipt()        { return $this->hasOne(ProcurementReceipt::class); }
    public function driverBooking()  { return $this->hasOne(DriverBooking::class); }
    public function workflowLogs()   { return $this->hasMany(PrWorkflowLog::class)->orderBy('created_at'); }
    public function deliveryReceipts(){ return $this->hasMany(DeliveryReceipt::class); }

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
}

