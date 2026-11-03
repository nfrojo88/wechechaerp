<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryReceipt extends Model
{
    protected $fillable = [
        'dr_no', 'slip_no', 'purchase_order_id', 'purchase_request_id', 'transfer_id',
        'received_by', 'store_id', 'received_date', 'notes', 'challan_no', 'vehicle_no', 'status',
        'slip_type', 'source_type', 'is_void', 'void_reason', 'voided_by', 'voided_at',
        'sequence_status', 'to_store_id', 'created_by', 'supplier_name', 'reference_no',
        'receipt_date', 'intake_completed_at',
    ];

    protected $casts = [
        'received_date' => 'date',
        'receipt_date'  => 'date',
        'is_void'       => 'boolean',
        'voided_at'     => 'datetime',
    ];

    public function purchaseOrder()   { return $this->belongsTo(PurchaseOrder::class); }
    public function store()           { return $this->belongsTo(Store::class); }
    public function toStore()         { return $this->belongsTo(Store::class, 'to_store_id'); }
    public function receivedBy()      { return $this->belongsTo(User::class, 'received_by'); }
    public function createdBy()       { return $this->belongsTo(User::class, 'created_by'); }
    public function voidedBy()        { return $this->belongsTo(User::class, 'voided_by'); }
    public function transfer()        { return $this->belongsTo(Transfer::class); }

    public function items()
    {
        return $this->hasMany(DeliveryReceiptItem::class);
    }

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class)->withTrashed();
    }
}
