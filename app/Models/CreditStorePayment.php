<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditStorePayment extends Model
{
    protected $fillable = [
        'credit_store_ledger_id',
        'payment_date',
        'amount',
        'payment_method',
        'bank_account_id',
        'coa_account_id',
        'reference_no',
        'receipt_path',
        'original_filename',
        'notes',
        'journal_entry_id',
        'recorded_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount'       => 'decimal:2',
    ];

    public function ledger()
    {
        return $this->belongsTo(CreditStoreLedger::class, 'credit_store_ledger_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function coaAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_account_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function recordedByUser()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
