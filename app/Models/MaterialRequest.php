<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'destination_store_id',
        'maintenance_request_id',
        'reference_number',
        'source',
        'status',
        'required_date',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
        'planning_approval_status',
        'planning_approved_by',
        'planning_approved_at',
        'planning_rejection_reason',
    ];

    protected $casts = [
        'required_date'        => 'date',
        'approved_at'          => 'datetime',
        'planning_approved_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'destination_store_id');
    }

    public function items()
    {
        return $this->hasMany(MaterialRequestItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function planningApprover()
    {
        return $this->belongsTo(User::class, 'planning_approved_by');
    }

    public function purchaseRequests()
    {
        return $this->hasMany(PurchaseRequest::class);
    }

    public function purchaseRequest()
    {
        return $this->hasOne(PurchaseRequest::class)->latestOfMany();
    }

    public function maintenanceRequest()
    {
        return $this->belongsTo(MaintenanceRequest::class, 'maintenance_request_id');
    }

    /**
     * Seamlessly generate or retrieve the linked Purchase Request so this Material Request
     * flows through the exact complete Procurement lifecycle (Planning -> Coordinator ->
     * Store Review -> Procurement Manager -> GM Decision -> Finance Payment -> Receipt -> Driver -> Store Intake).
     */
    public function createOrGetPurchaseRequest(?int $actorId = null): PurchaseRequest
    {
        $existing = $this->purchaseRequests()->latest()->first();
        if ($existing) {
            return $existing;
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($actorId) {
            $count = PurchaseRequest::withTrashed()->count() + 1;
            $prNo = 'PR-' . date('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            while (PurchaseRequest::withTrashed()->where('pr_no', $prNo)->exists()) {
                $count++;
                $prNo = 'PR-' . date('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }

            // Determine initial status & owner based on current MR state
            $initialStatus = PurchaseRequest::STATUS_PENDING_STORE_REVIEW;
            $initialOwner = 'store_manager';

            if (in_array($this->status, ['pending_planning', 'submitted', 'pending']) && $this->planning_approval_status !== 'approved') {
                $initialStatus = PurchaseRequest::STATUS_PENDING_PLANNING;
                $initialOwner = 'planning';
            } elseif ($this->status === 'planning_approved') {
                $initialStatus = PurchaseRequest::STATUS_PENDING_HR_APPROVAL;
                $initialOwner = 'coordinator';
            } elseif (in_array($this->status, ['needs_purchase', 'sent_to_pr'])) {
                $initialStatus = PurchaseRequest::STATUS_PENDING_MARKETING;
                $initialOwner = 'purchase_manager';
            }

            $creatorId = $this->created_by ?: ($actorId ?: (\Illuminate\Support\Facades\Auth::id() ?: 1));

            $pr = PurchaseRequest::create([
                'pr_no'               => $prNo,
                'project_id'          => $this->project_id,
                'store_id'            => $this->destination_store_id,
                'requested_by'        => $creatorId,
                'material_request_id' => $this->id,
                'priority'            => 'normal',
                'type'                => ($this->source === 'Emergency' ? 'emergency' : 'normal'),
                'is_office_request'   => false,
                'required_date'       => $this->required_date,
                'justification'       => "Material Requisition #{$this->reference_number}" . ($this->notes ? ": {$this->notes}" : ''),
                'status'              => $initialStatus,
                'current_owner_role'  => $initialOwner,
            ]);

            $this->loadMissing('items.product');

            foreach ($this->items as $item) {
                $prod = $item->product;
                $qty = (float)($item->quantity_requested ?: ($item->quantity ?: 1));
                $unit = $prod?->unit ?? 'pcs';

                $latestPrice = \App\Models\MaterialPrice::where('product_id', $item->product_id)
                    ->orderBy('effective_date', 'desc')
                    ->first();
                $estCost = $latestPrice ? (float)$latestPrice->price : (float)($prod?->unit_price ?? $prod?->selling_price ?? 0);

                PurchaseRequestItem::create([
                    'purchase_request_id' => $pr->id,
                    'product_id'          => $item->product_id,
                    'quantity'            => $qty,
                    'unit'                => $unit,
                    'specifications'      => $item->notes,
                    'estimated_unit_cost' => $estCost,
                ]);
            }

            if (!in_array($this->status, ['sent_to_pr', 'issued', 'processed'])) {
                $this->update(['status' => 'sent_to_pr']);
            }

            try {
                PrWorkflowLog::create([
                    'purchase_request_id' => $pr->id,
                    'from_status'         => 'draft',
                    'to_status'           => $initialStatus,
                    'action'              => 'mr_converted_to_pr',
                    'actor_role'          => 'system',
                    'actor_id'            => $creatorId,
                    'notes'               => "Purchase Request auto-generated from Material Request #{$this->reference_number} to follow complete procurement lifecycle.",
                ]);
            } catch (\Throwable $e) {}

            return $pr;
        });
    }
}
