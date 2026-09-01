<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubconAgreement extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'approved_at'=> 'datetime',
    ];

    // Relationships
    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function subcontractor()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(SubconAgreementItem::class, 'agreement_id');
    }

    public function ipcs()
    {
        return $this->hasMany(IpcRecord::class, 'agreement_id');
    }
    
    // Takeoff Integration
    public function takeoffItems() 
    { 
        return $this->belongsToMany(
            TakeoffItem::class,
            'subcon_agreement_takeoff_items',
            'agreement_id',
            'takeoff_item_id'
        )->withPivot('selected_quantity', 'rate', 'total_amount')->withTimestamps();
    }

    public function takeoffSheet() 
    { 
        return $this->belongsTo(TakeoffSheet::class);
    }

    // Accessors & Helpers
    public function getSubcontractorDisplayNameAttribute(): string
    {
        if ($this->supplier && !empty($this->supplier->name)) {
            return $this->supplier->name;
        }
        if (!empty($this->subcontractor_name)) {
            return $this->subcontractor_name;
        }
        return 'Subcontractor / Supplier';
    }

    public function getDescriptionDisplayAttribute(): string
    {
        return $this->work_description ?? $this->scope_of_work ?? '';
    }

    public function getEffectiveTotalAmountAttribute(): float
    {
        $raw = (float)($this->attributes['total_amount'] ?? 0);
        if ($raw > 0) {
            return $raw;
        }
        $contractVal = (float)($this->attributes['contract_value'] ?? 0);
        if ($contractVal > 0) {
            return $contractVal;
        }
        $itemsSum = (float)$this->items()->sum('total_amount');
        if ($itemsSum > 0) {
            return $itemsSum;
        }
        return 0.0;
    }

    public function getTotalTakeoffAmountAttribute(): float
    {
        return (float)($this->takeoffItems()->sum('subcon_agreement_takeoff_items.total_amount') ?? 0);
    }

    public function getAgreementFileUrlAttribute(): ?string
    {
        if (empty($this->agreement_file)) {
            return null;
        }
        if (str_starts_with($this->agreement_file, 'http://') || str_starts_with($this->agreement_file, 'https://')) {
            return $this->agreement_file;
        }
        return asset('storage/' . $this->agreement_file);
    }

    public function getIsPdfAttribute(): bool
    {
        if (empty($this->agreement_file)) {
            return false;
        }
        return (bool)preg_match('/\.pdf$/i', $this->agreement_file);
    }

    public function getIsImageAttribute(): bool
    {
        if (empty($this->agreement_file)) {
            return false;
        }
        return (bool)preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $this->agreement_file);
    }

    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'draft'      => 'secondary',
            'pending'    => 'warning',
            'approved'   => 'success',
            'active'     => 'info',
            'completed'  => 'success',
            'rejected'   => 'danger',
            'terminated' => 'dark',
            'cancelled'  => 'dark',
            default      => 'secondary'
        };
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && (!$this->end_date || $this->end_date >= now()->toDateString());
    }

    public function isExpired(): bool
    {
        return $this->end_date && $this->end_date < now()->toDateString();
    }
}
