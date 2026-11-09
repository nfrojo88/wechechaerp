<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseRequestItem extends Model
{
    protected $fillable = [
        'purchase_request_id', 'product_id', 'quantity', 'purchased_quantity', 'received_quantity', 'unit',
        'specifications', 'estimated_unit_cost', 'payment_method',
        'proforma_invoice_id', 'supplier_id',
        'unaccepted_quantity', 'is_fulfillment_closed', 'fulfillment_closed_at', 'fulfillment_closed_by', 'rejection_reason',
    ];

    protected $casts = [
        'quantity'              => 'decimal:3',
        'purchased_quantity'    => 'decimal:3',
        'received_quantity'     => 'decimal:3',
        'unaccepted_quantity'   => 'decimal:3',
        'is_fulfillment_closed' => 'boolean',
        'fulfillment_closed_at' => 'datetime',
        'estimated_unit_cost'   => 'decimal:2',
        'estimated_total'       => 'decimal:2',
    ];

    protected static function booted()
    {
        static::saving(function ($model) {
            // Unset generated column estimated_total before saving to prevent MySQL error 1906
            unset($model->attributes['estimated_total']);
        });
    }

    /**
     * Accessor for estimated_total: returns stored value or computes dynamically.
     */
    public function getEstimatedTotalAttribute($value)
    {
        if ($value !== null && (float)$value > 0) {
            return (float)$value;
        }
        return round((float)$this->quantity * (float)($this->estimated_unit_cost ?? 0), 2);
    }

    public function purchaseRequest() { return $this->belongsTo(PurchaseRequest::class); }
    public function product()         { return $this->belongsTo(Product::class); }
    public function proformaInvoice() { return $this->belongsTo(ProformaInvoice::class, 'proforma_invoice_id'); }
    public function supplier()        { return $this->belongsTo(Supplier::class, 'supplier_id'); }

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
        if ($this->is_fulfillment_closed) {
            return 0.0;
        }
        $unaccepted = (float)($this->unaccepted_quantity ?? 0);
        return max(0.0, $this->getTargetIntakeQty() - $this->getReceivedQty() - $unaccepted);
    }

    /**
     * Intake fulfillment status: 'completed', 'partial', 'pending'.
     */
    public function getIntakeStatus(): string
    {
        if ($this->is_fulfillment_closed) {
            return 'completed';
        }
        $target = $this->getTargetIntakeQty();
        $received = $this->getReceivedQty();
        $unaccepted = (float)($this->unaccepted_quantity ?? 0);

        if ($target > 0 && ($received + $unaccepted) >= $target) {
            return 'completed';
        }
        if ($received > 0) {
            return 'partial';
        }
        return 'pending';
    }

    /**
     * Ensure database columns for fulfillment closure exist.
     */
    public static function ensureFulfillmentColumns(): void
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('purchase_request_items')) {
                $missing = [];
                if (!\Illuminate\Support\Facades\Schema::hasColumn('purchase_request_items', 'unaccepted_quantity')) {
                    $missing[] = 'unaccepted_quantity';
                }
                if (!\Illuminate\Support\Facades\Schema::hasColumn('purchase_request_items', 'is_fulfillment_closed')) {
                    $missing[] = 'is_fulfillment_closed';
                }
                if (!\Illuminate\Support\Facades\Schema::hasColumn('purchase_request_items', 'fulfillment_closed_at')) {
                    $missing[] = 'fulfillment_closed_at';
                }
                if (!\Illuminate\Support\Facades\Schema::hasColumn('purchase_request_items', 'fulfillment_closed_by')) {
                    $missing[] = 'fulfillment_closed_by';
                }
                if (!\Illuminate\Support\Facades\Schema::hasColumn('purchase_request_items', 'rejection_reason')) {
                    $missing[] = 'rejection_reason';
                }

                if (!empty($missing)) {
                    \Illuminate\Support\Facades\Schema::table('purchase_request_items', function (\Illuminate\Database\Schema\Blueprint $table) use ($missing) {
                        if (in_array('unaccepted_quantity', $missing)) {
                            $table->decimal('unaccepted_quantity', 15, 3)->default(0)->after('received_quantity');
                        }
                        if (in_array('is_fulfillment_closed', $missing)) {
                            $table->boolean('is_fulfillment_closed')->default(false)->after('unaccepted_quantity');
                        }
                        if (in_array('fulfillment_closed_at', $missing)) {
                            $table->timestamp('fulfillment_closed_at')->nullable()->after('is_fulfillment_closed');
                        }
                        if (in_array('fulfillment_closed_by', $missing)) {
                            $table->unsignedBigInteger('fulfillment_closed_by')->nullable()->after('fulfillment_closed_at');
                        }
                        if (in_array('rejection_reason', $missing)) {
                            $table->text('rejection_reason')->nullable()->after('fulfillment_closed_by');
                        }
                    });
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('PurchaseRequestItem: Error ensuring fulfillment columns: ' . $e->getMessage());
        }
    }
}
