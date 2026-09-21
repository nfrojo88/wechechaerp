<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditStorePayment extends Model
{
    protected $fillable = [
        'credit_store_ledger_id',
        'expense_request_id',
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
        'gross_amount',
        'vat_type',
        'vat_rate',
        'vat_amount',
        'has_withholding',
        'withholding_rate',
        'withholding_amount',
        'withholding_receipt',
        'withholding_receipt_number',
        'net_amount',
    ];

    protected $casts = [
        'payment_date'       => 'date',
        'amount'             => 'decimal:2',
        'gross_amount'       => 'decimal:2',
        'vat_rate'           => 'decimal:2',
        'vat_amount'         => 'decimal:2',
        'has_withholding'    => 'boolean',
        'withholding_rate'   => 'decimal:2',
        'withholding_amount' => 'decimal:2',
        'net_amount'         => 'decimal:2',
    ];

    public function ledger()
    {
        return $this->belongsTo(CreditStoreLedger::class, 'credit_store_ledger_id');
    }

    public function expenseRequest()
    {
        return $this->belongsTo(ExpenseRequest::class, 'expense_request_id');
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
