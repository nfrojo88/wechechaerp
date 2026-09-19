<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PettyCashMaterialPurchaseItem extends Model
{
    use HasFactory;

    protected $table = 'petty_cash_material_purchase_items';

    protected $fillable = [
        'purchase_id',
        'product_id',
        'quantity',
        'unit',
        'unit_price',
        'total_price',
        'remarks',
    ];

    protected $casts = [
        'quantity'    => 'decimal:3',
        'unit_price'  => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function purchase()
    {
        return $this->belongsTo(PettyCashMaterialPurchase::class, 'purchase_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
