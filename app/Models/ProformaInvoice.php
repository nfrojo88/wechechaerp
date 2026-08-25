<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProformaInvoice extends Model
{
    protected $fillable = [
        'proforma_no', 'purchase_request_id', 'supplier_id', 'proforma_date',
        'valid_until', 'subtotal', 'tax_amount', 'grand_total', 'item_prices', 'status',
        'gm_selected', 'notes', 'file_path',
    ];

    protected $casts = [
        'proforma_date' => 'date',
        'valid_until'   => 'date',
        'gm_selected'   => 'boolean',
        'item_prices'   => 'array',
    ];

    public function purchaseRequest() { return $this->belongsTo(PurchaseRequest::class); }
    public function supplier()        { return $this->belongsTo(Supplier::class); }
    public function purchaseOrders()  { return $this->hasMany(PurchaseOrder::class); }
}
