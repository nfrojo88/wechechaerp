<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseRequestItem extends Model
{
    protected $fillable = [
        'purchase_request_id', 'product_id', 'quantity', 'purchased_quantity', 'received_quantity', 'unit',
        'specifications', 'estimated_unit_cost',
    ];

    protected $casts = [
        'quantity'            => 'decimal:3',
        'purchased_quantity'  => 'decimal:3',
        'received_quantity'   => 'decimal:3',
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

    /**
     * Target quantity to intake into store (purchased quantity, fallback to effective purchased, fallback to requested).
     */
    public function getTargetIntakeQty(): float
    {
        $directVal = (float)($this->purchased_quantity ?? 0);
        if ($directVal > 0) {
            return $directVal;
        }
        $eff = $this->getEffectivePurchasedQty();
        if ($eff > 0) {
            return $eff;
        }
        return (float)($this->quantity ?? 0);
    }

    /**
     * Total quantity accepted and received into store across all delivery slips.
     */
    public function getReceivedQty(): float
    {
        $direct = (float)($this->received_quantity ?? 0);

        try {
            $prId = $this->purchase_request_id;
            if ($prId && $this->product_id) {
                $drQty = (float)\App\Models\DeliveryReceiptItem::whereHas('deliveryReceipt', function ($q) use ($prId) {
                    $q->where('purchase_request_id', $prId);
                })->where('product_id', $this->product_id)->sum('accepted_quantity');

                return max($direct, $drQty);
            }
        } catch (\Throwable $e) {}

        return $direct;
    }

    /**
     * Remaining unreceived balance to intake into store.
     */
    public function getRemainingIntakeQty(): float
    {
        return max(0.0, $this->getTargetIntakeQty() - $this->getReceivedQty());
    }

    /**
     * Intake fulfillment status: 'completed', 'partial', 'pending'.
     */
    public function getIntakeStatus(): string
    {
        $target = $this->getTargetIntakeQty();
        $received = $this->getReceivedQty();

        if ($target > 0 && $received >= $target) {
            return 'completed';
        }
        if ($received > 0) {
            return 'partial';
        }
        return 'pending';
    }
}
