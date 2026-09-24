<?php

namespace App\Models;

use App\Traits\ScopesByStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory, ScopesByStore;

    protected $table = 'inventory';

    protected $fillable = [
        'store_id',
        'product_id',
        'quantity_on_hand',
        'quantity_reserved',
        'unit_cost',
        'min_stock',
        'last_movement_at',
    ];

    protected $casts = [
        'quantity_on_hand' => 'decimal:3',
        'quantity_reserved' => 'decimal:3',
        'quantity_available' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'total_value' => 'decimal:2',
        'min_stock' => 'decimal:3',
        'last_movement_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::saved(function (Inventory $inventory) {
            try {
                FixedAsset::syncFromInventory($inventory->product_id);
            } catch (\Throwable $e) {}
        });
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
