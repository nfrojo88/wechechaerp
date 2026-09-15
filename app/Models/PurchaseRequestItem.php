<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseRequestItem extends Model
{
    protected $fillable = [
        'purchase_request_id', 'product_id', 'quantity', 'purchased_quantity', 'unit',
        'specifications', 'estimated_unit_cost',
    ];

    protected $casts = [
        'quantity'            => 'decimal:3',
        'purchased_quantity'  => 'decimal:3',
        'estimated_unit_cost' => 'decimal:2',
        'estimated_total'     => 'decimal:2',
    ];

    public function purchaseRequest() { return $this->belongsTo(PurchaseRequest::class); }
    public function product()         { return $this->belongsTo(Product::class); }

    /**
     * Get effective purchased quantity (from database column or fallback to linked PO items).
     */
    public function getEffectivePurchasedQty(): float
    {
        $directVal = (float)($this->purchased_quantity ?? 0);
        if ($directVal > 0) {
            return $directVal;
        }

        try {
            $pr = $this->purchaseRequest;
            if ($pr) {
                $poQty = \App\Models\PurchaseOrderItem::whereHas('purchaseOrder', function ($q) use ($pr) {
                    $q->where('purchase_request_id', $pr->id);
                })->where('product_id', $this->product_id)->sum('quantity');

                if ($poQty > 0) {
                    return (float)$poQty;
                }
            }
        } catch (\Throwable $e) {}

        return 0.0;
    }

    /**
     * Remaining quantity to fulfill.
     */
    public function getRemainingQty(): float
    {
        return max(0.0, (float)$this->quantity - $this->getEffectivePurchasedQty());
    }

    /**
     * Purchase status: 'completed', 'partial', 'pending'.
     */
    public function getPurchaseStatus(): string
    {
        $purchased = $this->getEffectivePurchasedQty();
        $requested = (float)$this->quantity;

        if ($purchased >= $requested && $requested > 0) {
            return 'completed';
        }
        if ($purchased > 0) {
            return 'partial';
        }
        return 'pending';
    }
}