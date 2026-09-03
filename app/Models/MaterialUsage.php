<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\ScopesByStore;

class MaterialUsage extends Model
{
    use ScopesByStore;

    protected $guarded = [];

    protected $casts = [
        'usage_date' => 'date',
        'confirmed_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function task()
    {
        return $this->belongsTo(ErpPlanTask::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }

    public function items()
    {
        return $this->hasMany(MaterialUsageItem::class);
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function getTotalQuantityAttribute(): float
    {
        return (float) $this->items->sum(function ($item) {
            return $item->quantity ?? $item->used_quantity ?? 0;
        });
    }

    public function getTotalEstimatedCostAttribute(): float
    {
        return (float) $this->items->sum(function ($item) {
            $qty = $item->quantity ?? $item->used_quantity ?? 0;
            $unitCost = $item->unit_cost ?? ($item->product->unit_cost ?? 0);
            return $qty * $unitCost;
        });
    }
}

