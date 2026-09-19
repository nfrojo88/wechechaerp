<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PettyCashMaterialPurchase extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'petty_cash_material_purchases';

    protected $fillable = [
        'purchase_no',
        'store_id',
        'chart_of_account_id',
        'purchased_by',
        'purchase_date',
        'supplier_name',
        'receipt_no',
        'total_amount',
        'notes',
        'attachment_path',
        'delivery_receipt_id',
        'journal_entry_id',
        'status',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'total_amount'  => 'decimal:2',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function chartOfAccount()
    {
        return $this->belongsTo(ChartOfAccount::class);
    }

    public function purchaser()
    {
        return $this->belongsTo(User::class, 'purchased_by');
    }

    public function deliveryReceipt()
    {
        return $this->belongsTo(DeliveryReceipt::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function items()
    {
        return $this->hasMany(PettyCashMaterialPurchaseItem::class, 'purchase_id');
    }
}
