<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeMaterialRequestItem extends Model
{
    use HasFactory;

    protected $table = 'office_material_request_items';

    protected $fillable = [
        'office_material_request_id',
        'product_id',
        'item_name',
        'quantity',
        'unit',
        'specifications',
        'estimated_unit_price',
    ];

    protected $casts = [
        'quantity'             => 'decimal:2',
        'estimated_unit_price' => 'decimal:2',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(OfficeMaterialRequest::class, 'office_material_request_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
