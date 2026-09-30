<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_request_id',
        'product_id',
        'quantity_requested',
        'quantity_fulfilled',
        'notes',
    ];

    protected $casts = [
        'quantity_requested' => 'decimal:3',
        'quantity_fulfilled' => 'decimal:3',
    ];

    public function materialRequest()
    {
        return $this->belongsTo(MaterialRequest::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getQuantityAttribute(): float
    {
        return (float)($this->attributes['quantity_requested'] ?? $this->attributes['quantity'] ?? 0);
    }
}
