<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialUsageItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'used_quantity' => 'decimal:3',
        'quantity' => 'decimal:3',
        'returned_quantity' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function materialUsage()
    {
        return $this->belongsTo(MaterialUsage::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getEffectiveQuantityAttribute(): float
    {
        return (float) ($this->quantity ?? $this->used_quantity ?? 0);
    }
}

