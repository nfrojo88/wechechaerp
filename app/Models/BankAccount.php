<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'account_name', 'account_number', 'bank_name', 'branch',
        'account_type', 'currency', 'current_balance', 'coa_id',
        'is_active', 'is_default', 'notes', 'assigned_to',
    ];

    protected $casts = ['is_active' => 'boolean', 'is_default' => 'boolean'];

    protected static function booted()
    {
        static::saving(function ($bankAccount) {
            // Model Validation: Enforce 1:1 mapping between BankAccount and GL ChartOfAccount
            if ($bankAccount->coa_id) {
                $duplicateQuery = static::where('coa_id', $bankAccount->coa_id)
                    ->where('id', '!=', $bankAccount->id ?? 0);
                if ($duplicateQuery->exists()) {
                    throw new \InvalidArgumentException("Chart of Account ID [{$bankAccount->coa_id}] is already linked to another bank account. Each GL cash/bank sub-account must map 1:1 to exactly one bank account.");
                }
            }
        });

        static::updating(function ($bankAccount) {
            // Model Guard: Block any external write to bank_accounts.current_balance unless explicitly authorized by JournalEngine
            if ($bankAccount->isDirty('current_balance')) {
                if (!app()->bound('gl_posting_in_progress') || !app('gl_posting_in_progress')) {
                    throw new \RuntimeException("Direct update of bank_accounts.current_balance is strictly prohibited. Balances derive exclusively from posted journal lines via JournalEngine.");
                }
            }
        });
    }

    public function coa()         { return $this->belongsTo(ChartOfAccount::class, 'coa_id'); }
    public function transactions() { return $this->hasMany(BankTransaction::class); }
    public function assignedStaff() { return $this->belongsTo(User::class, 'assigned_to'); }

    public function getLedgerBalanceAttribute(): float
    {
        if ($this->coa_id) {
            $coa = $this->coa;
            return $coa ? (float) $coa->ledger_balance : 0.00;
        }
        return (float) $this->current_balance;
    }

    public function getAssignedUserAttribute()
    {
        return $this->assignedStaff ?? ($this->coa ? $this->coa->manager : null);
    }
}
