<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccrualEntry extends Model
{
    protected $table = 'accrual_entries';

    protected $fillable = [
        'financial_period_id',
        'accrual_type',
        'journal_entry_id',
        'reversal_journal_entry_id',
        'amount',
        'expense_account_id',
        'accrual_account_id',
        'project_id',
        'cost_center_id',
        'site_id',
        'is_reversed',
        'reversed_at',
        'reversed_by',
        'notes',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'is_reversed' => 'boolean',
        'reversed_at' => 'datetime',
    ];

    public function period()
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function financialPeriod()
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function reversalJournalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_journal_entry_id');
    }

    public function expenseAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }

    public function accrualAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'accrual_account_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
