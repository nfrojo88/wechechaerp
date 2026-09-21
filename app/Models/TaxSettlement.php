<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxSettlement extends Model
{
    use HasFactory;

    protected $table = 'tax_settlements';

    const STATUS_PENDING   = 'pending_payment';
    const STATUS_PAID      = 'paid';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'settlement_number',
        'tax_type',
        'period_from',
        'period_to',
        'total_base_amount',
        'vat_amount',
        'withholding_amount',
        'total_tax_paid',
        'records_count',
        'status',
        'finance_head_id',
        'assigned_finance_staff_id',
        'paid_by',
        'paid_at',
        'payment_method',
        'bank_account_id',
        'coa_id',
        'payment_reference',
        'attachment',
        'payment_notes',
    ];

    protected $casts = [
        'period_from'        => 'datetime',
        'period_to'          => 'datetime',
        'paid_at'            => 'datetime',
        'total_base_amount'  => 'decimal:2',
        'vat_amount'         => 'decimal:2',
        'withholding_amount' => 'decimal:2',
        'total_tax_paid'     => 'decimal:2',
        'records_count'      => 'integer',
    ];

    public function financeHead()
    {
        return $this->belongsTo(User::class, 'finance_head_id');
    }

    public function assignedStaff()
    {
        return $this->belongsTo(User::class, 'assigned_finance_staff_id');
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function chartOfAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id');
    }

    public function expenseRequests()
    {
        return $this->hasMany(ExpenseRequest::class, 'tax_settlement_id');
    }

    /**
     * Generate unique settlement reference number
     */
    public static function generateSettlementNumber(): string
    {
        $prefix = 'TAX-REM-' . date('Ym') . '-';
        $count = static::whereYear('created_at', date('Y'))
            ->whereMonth('created_at', date('m'))
            ->count() + 1;
        
        $number = $prefix . str_pad($count, 3, '0', STR_PAD_LEFT);
        while (static::where('settlement_number', $number)->exists()) {
            $count++;
            $number = $prefix . str_pad($count, 3, '0', STR_PAD_LEFT);
        }
        return $number;
    }
}
