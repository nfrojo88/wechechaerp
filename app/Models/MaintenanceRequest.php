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
        'resolved_at',
        'reported_by_user_id',
        'assigned_to_user_id',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    // ─── Boot ─────────────────────────────────────────────────────────────────

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $last = static::withTrashed()->orderBy('id', 'desc')->first();
            $next = $last ? ($last->id + 1) : 1;
            $model->request_no = 'MNT-' . str_pad($next, 4, '0', STR_PAD_LEFT);
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

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getStatusBadgeAttribute(): array
    {
        return match($this->status) {
            'pending'     => ['class' => 'bg-warning text-dark', 'label' => 'Pending', 'icon' => 'fa-clock'],
            'in_progress' => ['class' => 'bg-primary',           'label' => 'In Progress', 'icon' => 'fa-wrench'],
            'resolved'    => ['class' => 'bg-success',           'label' => 'Resolved', 'icon' => 'fa-circle-check'],
            'closed'      => ['class' => 'bg-secondary',         'label' => 'Closed', 'icon' => 'fa-xmark-circle'],
            default       => ['class' => 'bg-light text-dark border', 'label' => ucfirst($this->status), 'icon' => 'fa-circle'],
        };
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
