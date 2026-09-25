<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'resolved_at' => 'datetime',
        'sent_to_store_manager_at' => 'datetime',
        'gm_approved_at' => 'datetime',
        'gm_decision_locked' => 'boolean',
    ];

    // ─── Boot ─────────────────────────────────────────────────────────────────

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

    public function expenseRequests()
    {
        return $this->hasMany(ExpenseRequest::class, 'maintenance_request_id');
    }

    public function materialRequests()
    {
        return $this->hasMany(MaterialRequest::class, 'maintenance_request_id');
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getStatusBadgeAttribute(): array
    {
        return match($this->status) {
            'pending'                  => ['class' => 'bg-warning text-dark', 'label' => 'Pending', 'icon' => 'fa-clock'],
            'in_progress'              => ['class' => 'bg-primary',           'label' => 'In Progress', 'icon' => 'fa-wrench'],
            'sent_to_store_manager'    => ['class' => 'bg-info text-dark',    'label' => 'Sent to Store Manager', 'icon' => 'fa-paper-plane'],
            'resolved'                 => ['class' => 'bg-success',           'label' => 'Resolved', 'icon' => 'fa-circle-check'],
            'closed'                   => ['class' => 'bg-secondary',         'label' => 'Closed', 'icon' => 'fa-xmark-circle'],
            'rejected'                 => ['class' => 'bg-danger text-white', 'label' => 'Rejected', 'icon' => 'fa-circle-xmark'],
            default                    => ['class' => 'bg-light text-dark border', 'label' => ucfirst(str_replace('_', ' ', $this->status)), 'icon' => 'fa-circle'],
        };
    }

    /**
     * Retrieve all previous maintenance requests for this asset.
     */
    public function getMaintenanceHistoryAttribute()
    {
        $assetUnitId = $this->fixed_asset_unit_id;
        $assetCode = $this->asset_code;
        $assetName = $this->asset_name;

        return static::with(['assignedTo', 'expenseRequests', 'materialRequests.items.product', 'employee'])
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
            ->take(15)
            ->get();
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
