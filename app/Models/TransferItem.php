<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferItem extends Model
{
    protected $fillable = [
        'transfer_id', 'product_id', 'requested_quantity',
        'approved_quantity', 'sent_quantity', 'received_quantity', 'unit',
        'unit_codes',
    ];

    protected $casts = [
        'unit_codes' => 'array',
    ];

    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
