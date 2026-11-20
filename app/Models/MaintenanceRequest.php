<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class MaintenanceRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'request_no',
        'employee_id',
        'fixed_asset_unit_id',
        'employee_asset_id',
        'asset_name',
        'asset_code',
        'issue_type',
        'description',
        'urgency',
        'status',
        'gs_status',
        'diagnosis',
        'diagnosis_attachments',
        'gs_verified_at',
        'gs_verified_by',
        'gs_rejected_at',
        'gs_rejected_by',
        'materials_data',
        'material_cost',
        'labor_cost',
        'total_cost',
        'technician_name',
        'technician_phone',
        'technician_bank_name',
        'technician_account_name',
        'technician_account_number',
        'condition_rating',
        'quotation_attachments',
        'remarks',
        'gs_details_locked',
        'hold_reason',
        'hold_review_date',
        'previous_asset_status',
        'odometer',
        'maintenance_person_name',
        'maintenance_person_account',
        'maintenance_person_phone',
        'petty_cash_owner_id',
        'money_amount',
        'estimated_time',
        'material_description',
        'gm_initial_status',
        'gm_initial_action_at',
        'gm_initial_notes',
        'gm_return_reason',
        'gm_final_approved_at',
        'is_returned_flow',
        'admin_notes',
        'rejection_reason',
        'replacement_action',
        'replacement_condition',
        'sent_to_store_manager_at',
        'resolved_at',
        'reported_by_user_id',
        'assigned_to_user_id',
        'gm_approved_at',
        'gm_approver_id',
        'gm_decision_locked',
        'gm_decision_summary',
    ];

    protected $casts = [
        'resolved_at'             => 'datetime',
        'sent_to_store_manager_at'=> 'datetime',
        'gm_approved_at'          => 'datetime',
        'gm_initial_action_at'    => 'datetime',
        'gm_final_approved_at'    => 'datetime',
        'gs_verified_at'          => 'datetime',
        'gs_rejected_at'          => 'datetime',
        'hold_review_date'        => 'date',
        'gm_decision_locked'      => 'boolean',
        'gs_details_locked'       => 'boolean',
        'is_returned_flow'        => 'boolean',
        'money_amount'            => 'decimal:2',
        'material_cost'           => 'decimal:2',
        'labor_cost'              => 'decimal:2',
        'total_cost'              => 'decimal:2',
        'materials_data'          => 'array',
        'diagnosis_attachments'   => 'array',
        'quotation_attachments'   => 'array',
    ];

    // ─── Boot & Schema Self-Healing ──────────────────────────────────────────

    public static function ensureSchema(): void
    {
        if (!Schema::hasTable('maintenance_requests')) {
            return;
        }

        $neededColumns = [
            'diagnosis'                  => 'text',
            'diagnosis_attachments'      => 'json',
            'gs_verified_at'             => 'timestamp',
            'gs_verified_by'             => 'unsignedBigInteger',
            'gs_rejected_at'             => 'timestamp',
            'gs_rejected_by'             => 'unsignedBigInteger',
            'materials_data'             => 'json',
            'material_cost'              => 'decimal',
            'labor_cost'                 => 'decimal',
            'total_cost'                 => 'decimal',
            'technician_name'            => 'string',
            'technician_phone'           => 'string',
            'technician_bank_name'       => 'string',
            'technician_account_name'    => 'string',
            'technician_account_number'  => 'string',
            'condition_rating'           => 'string',
            'quotation_attachments'      => 'json',
            'remarks'                    => 'text',
            'gs_details_locked'          => 'boolean',
            'hold_reason'                => 'text',
            'hold_review_date'           => 'date',
            'previous_asset_status'      => 'string',
            'odometer'                   => 'string',
            'gs_status'                  => 'string',
            'rejection_reason'           => 'text',
            'gm_approved_at'             => 'timestamp',
            'gm_approver_id'             => 'unsignedBigInteger',
            'gm_decision_locked'         => 'boolean',
            'gm_decision_summary'        => 'text',
            'maintenance_person_name'    => 'string',
            'maintenance_person_account' => 'string',
            'maintenance_person_phone'   => 'string',
            'petty_cash_owner_id'        => 'unsignedBigInteger',
            'money_amount'               => 'decimal',
            'estimated_time'             => 'string',
            'material_description'       => 'text',
            'gm_initial_status'          => 'string',
            'gm_initial_action_at'       => 'timestamp',
            'gm_initial_notes'           => 'text',
            'gm_return_reason'           => 'text',
            'gm_final_approved_at'       => 'timestamp',
            'is_returned_flow'           => 'boolean',
            'replacement_condition'      => 'string',
            'replacement_action'         => 'string',
            'sent_to_store_manager_at'   => 'timestamp',
            'resolved_at'                => 'timestamp',
            'reported_by_user_id'        => 'unsignedBigInteger',
            'assigned_to_user_id'        => 'unsignedBigInteger',
        ];

        Schema::table('maintenance_requests', function (Blueprint $table) use ($neededColumns) {
            foreach ($neededColumns as $column => $type) {
                if (!Schema::hasColumn('maintenance_requests', $column)) {
                    switch ($type) {
                        case 'text':
                            $table->text($column)->nullable();
                            break;
                        case 'json':
                            $table->json($column)->nullable();
                            break;
                        case 'timestamp':
                            $table->timestamp($column)->nullable();
                            break;
                        case 'unsignedBigInteger':
                            $table->unsignedBigInteger($column)->nullable();
                            break;
                        case 'decimal':
                            $table->decimal($column, 15, 2)->nullable()->default(0);
                            break;
                        case 'boolean':
                            $table->boolean($column)->default(false);
                            break;
                        case 'date':
                            $table->date($column)->nullable();
                            break;
                        case 'string':
                        default:
                            $table->string($column, 255)->nullable();
                            break;
                    }
                }
            }
        });
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->request_no)) {
                $last = static::withTrashed()->orderBy('id', 'desc')->first();
                $next = $last ? ($last->id + 1) : 1;
                $candidate = 'MNT-' . str_pad($next, 4, '0', STR_PAD_LEFT);
                while (static::withTrashed()->where('request_no', $candidate)->exists()) {
                    $next++;
                    $candidate = 'MNT-' . str_pad($next, 4, '0', STR_PAD_LEFT);
                }
                $model->request_no = $candidate;
            }

            if (empty($model->gs_status)) {
                $model->gs_status = 'pending_gs';
            }
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function fixedAssetUnit(): BelongsTo
    {
        return $this->belongsTo(FixedAssetUnit::class, 'fixed_asset_unit_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function gmApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gm_approver_id');
    }

    public function pettyCashOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petty_cash_owner_id');
    }

    public function gsVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gs_verified_by');
    }

    public function gsRejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gs_rejected_by');
    }

    public function expenseRequests()
    {
        return $this->hasMany(ExpenseRequest::class, 'maintenance_request_id');
    }

    public function materialRequests()
    {
        return $this->hasMany(MaterialRequest::class, 'maintenance_request_id');
    }

    // ─── Stage & Permission Helpers ──────────────────────────────────────────

    /**
     * Check if the ticket is locked awaiting GM decision or finalized.
     * When locked, no one (GS, Admin, or via API) can edit the ticket fields.
     */
    public function isLocked(): bool
    {
        if ($this->gs_details_locked || $this->gm_decision_locked) {
            return true;
        }

        return in_array($this->gs_status, [
            'pending_gm_final',
            'gm_on_hold_final',
            'approved',
            'rejected',
            'gs_rejected',
        ]);
    }

    /**
     * Check if GS is allowed to fill in materials, labor cost, and technician details (Stage 4).
     */
    public function canGsAddDetails(): bool
    {
        if ($this->isLocked()) {
            return false;
        }

        return in_array($this->gs_status, ['gm_approved_initial', 'gm_returned_to_gs']);
    }

    /**
     * Check if GS is at Stage 2 (inspection & accept/reject only).
     */
    public function canGsVerify(): bool
    {
        if ($this->isLocked()) {
            return false;
        }

        return in_array($this->gs_status, ['pending_gs', null]) && $this->status === 'pending';
    }

    /**
     * Check if this asset is currently under maintenance.
     */
    public function isUnderMaintenance(): bool
    {
        if (in_array($this->status, ['resolved', 'closed', 'rejected'])) {
            return false;
        }

        return in_array($this->gs_status, [
            'gs_verified',
            'submitted_to_gm',
            'gm_on_hold',
            'gm_approved_initial',
            'pending_gm_final',
            'gm_on_hold_final',
            'approved',
        ]);
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getGsStatusBadgeAttribute(): array
    {
        return match($this->gs_status ?? 'pending_gs') {
            'pending_gs'          => ['class' => 'bg-secondary bg-opacity-25 text-secondary border', 'label' => 'Awaiting GS Inspection', 'icon' => 'fa-clipboard-check'],
            'gs_rejected'         => ['class' => 'bg-danger text-white', 'label' => 'Rejected by GS', 'icon' => 'fa-circle-xmark'],
            'gs_verified'         => ['class' => 'bg-info bg-opacity-25 text-info-emphasis border border-info border-opacity-25', 'label' => 'GS Verified (Ready for GM)', 'icon' => 'fa-check-double'],
            'submitted_to_gm'     => ['class' => 'bg-warning text-dark', 'label' => 'Awaiting GM First Review', 'icon' => 'fa-paper-plane'],
            'gm_on_hold'          => ['class' => 'bg-secondary text-white', 'label' => 'On Hold (GM First Review)', 'icon' => 'fa-pause-circle'],
            'gm_approved_initial' => ['class' => 'bg-primary text-white', 'label' => 'GM Approved — GS Adding Details', 'icon' => 'fa-user-pen'],
            'pending_gm_final'    => ['class' => 'bg-warning text-dark border border-warning', 'label' => 'Locked: Awaiting GM Final Approval', 'icon' => 'fa-lock'],
            'gm_on_hold_final'    => ['class' => 'bg-secondary text-white', 'label' => 'Locked: On Hold (GM Final Decision)', 'icon' => 'fa-lock'],
            'approved'            => ['class' => 'bg-success text-white', 'label' => 'Approved & Active', 'icon' => 'fa-circle-check'],
            'rejected'            => ['class' => 'bg-danger text-white', 'label' => 'Rejected by GM', 'icon' => 'fa-circle-xmark'],
            default               => ['class' => 'bg-light text-dark border', 'label' => ucfirst(str_replace('_', ' ', $this->gs_status ?? 'pending')), 'icon' => 'fa-circle'],
        };
    }

    public function getStatusBadgeAttribute(): array
    {
        return match($this->status) {
            'pending'                  => ['class' => 'bg-warning text-dark', 'label' => 'Pending', 'icon' => 'fa-clock'],
            'in_progress'              => ['class' => 'bg-primary text-white', 'label' => 'In Progress', 'icon' => 'fa-wrench'],
            'sent_to_store_manager'    => ['class' => 'bg-info text-dark',    'label' => 'Sent to Store Manager', 'icon' => 'fa-paper-plane'],
            'resolved'                 => ['class' => 'bg-success text-white','label' => 'Resolved', 'icon' => 'fa-circle-check'],
            'closed'                   => ['class' => 'bg-secondary text-white', 'label' => 'Closed', 'icon' => 'fa-xmark-circle'],
            'rejected'                 => ['class' => 'bg-danger text-white', 'label' => 'Rejected', 'icon' => 'fa-circle-xmark'],
            default                    => ['class' => 'bg-light text-dark border', 'label' => ucfirst(str_replace('_', ' ', $this->status)), 'icon' => 'fa-circle'],
        };
    }

    /**
     * Retrieve all previous maintenance requests for this asset with full details.
     */
    public function getMaintenanceHistoryAttribute()
    {
        $assetUnitId = $this->fixed_asset_unit_id;
        $assetCode = $this->asset_code;
        $assetName = $this->asset_name;

        return static::with(['assignedTo', 'reportedBy', 'expenseRequests', 'materialRequests.items.product', 'employee'])
            ->where('id', '!=', $this->id)
            ->where(function ($q) use ($assetUnitId, $assetCode, $assetName) {
                $hasCond = false;
                if ($assetUnitId) {
                    $q->where('fixed_asset_unit_id', $assetUnitId);
                    $hasCond = true;
                }
                if ($assetCode) {
                    if ($hasCond) {
                        $q->orWhere('asset_code', $assetCode);
                    } else {
                        $q->where('asset_code', $assetCode);
                        $hasCond = true;
                    }
                }
                if (!$hasCond && $assetName) {
                    $q->where('asset_name', $assetName);
                }
            })
            ->latest()
            ->take(25)
            ->get();
    }

    /**
     * Compute summary statistics for previous repairs on this asset.
     */
    public function getHistorySummaryAttribute(): array
    {
        $history = $this->maintenance_history;

        $totalRepairs = $history->count();
        $totalSpent = 0.0;
        $lastRepairDate = null;

        foreach ($history as $h) {
            $hSpent = (float) ($h->total_cost ?: 0);
            if ($hSpent <= 0) {
                $hSpent += (float) ($h->labor_cost ?: 0) + (float) ($h->material_cost ?: 0) + (float) ($h->money_amount ?: 0);
            }
            if ($hSpent <= 0 && $h->expenseRequests->isNotEmpty()) {
                $hSpent += (float) $h->expenseRequests->sum('amount');
            }
            $totalSpent += $hSpent;

            if (!$lastRepairDate && $h->created_at) {
                $lastRepairDate = $h->created_at;
            }
        }

        return [
            'total_repairs'    => $totalRepairs,
            'total_spent'      => $totalSpent,
            'last_repair_date' => $lastRepairDate,
        ];
    }

    public function getUrgencyBadgeAttribute(): array
    {
        return match($this->urgency) {
            'critical' => ['class' => 'bg-danger',           'label' => '🔴 Critical', 'icon' => 'fa-circle-exclamation'],
            'urgent'   => ['class' => 'bg-warning text-dark','label' => '🟠 Urgent',   'icon' => 'fa-triangle-exclamation'],
            'normal'   => ['class' => 'bg-info',             'label' => '🔵 Normal',   'icon' => 'fa-circle-info'],
            'low'      => ['class' => 'bg-secondary',        'label' => '🟢 Low',      'icon' => 'fa-circle'],
            default    => ['class' => 'bg-light text-dark border', 'label' => ucfirst($this->urgency), 'icon' => 'fa-circle'],
        };
    }

    public function getIssueTypeLabelAttribute(): string
    {
        return match($this->issue_type) {
            'breakdown'   => '⚡ Breakdown',
            'damage'      => '💥 Physical Damage',
            'service_due' => '🔧 Service Due',
            'malfunction' => '⚠️ Malfunction',
            'needs_repair'=> '🛠️ Needs Repair',
            'other'       => '📋 Other',
            default       => ucfirst(str_replace('_', ' ', $this->issue_type)),
        };
    }
}
