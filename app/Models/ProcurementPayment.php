<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcurementPayment extends Model
{
    protected $fillable = [
        'purchase_request_id', 'method', 'coa_account_id', 'amount',
        'assigned_finance_staff_id', 'paid_by', 'paid_at', 'notes',
        'status', 'journal_entry_id', 'created_by',
        'gross_amount', 'vat_type', 'vat_rate', 'vat_amount',
        'has_withholding', 'withholding_rate', 'withholding_amount',
        'withholding_receipt', 'withholding_receipt_number', 'net_amount',
    ];

    protected $casts = [
        'paid_at'            => 'datetime',
        'amount'             => 'decimal:2',
        'gross_amount'       => 'decimal:2',
        'vat_rate'           => 'decimal:2',
        'vat_amount'         => 'decimal:2',
        'has_withholding'    => 'boolean',
        'withholding_rate'   => 'decimal:2',
        'withholding_amount' => 'decimal:2',
        'net_amount'         => 'decimal:2',
    ];

    public function purchaseRequest()      { return $this->belongsTo(PurchaseRequest::class); }
    public function coaAccount()           { return $this->belongsTo(ChartOfAccount::class, 'coa_account_id'); }
    public function assignedStaff()        { return $this->belongsTo(User::class, 'assigned_finance_staff_id'); }
    public function paidByUser()           { return $this->belongsTo(User::class, 'paid_by'); }
    public function journalEntry()         { return $this->belongsTo(JournalEntry::class); }
    public function createdBy()            { return $this->belongsTo(User::class, 'created_by'); }
}
