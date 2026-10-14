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

    protected static function booted()
    {
        static::saved(function (TransferItem $item) {
            try {
                $transfer = $item->relationLoaded('transfer') ? $item->transfer : Transfer::find($item->transfer_id);
                if ($transfer && !in_array($transfer->status, ['rejected', 'cancelled'])) {
                    \App\Http\Controllers\StoreManagerController::syncTransferInventory($transfer);
                }
            } catch (\Throwable $e) {}
        });

        static::deleted(function (TransferItem $item) {
            try {
                $transfer = $item->relationLoaded('transfer') ? $item->transfer : Transfer::find($item->transfer_id);
                if ($transfer && !in_array($transfer->status, ['rejected', 'cancelled'])) {
                    \App\Http\Controllers\StoreManagerController::syncTransferInventory($transfer);
                }
            } catch (\Throwable $e) {}
        });
    }

    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
