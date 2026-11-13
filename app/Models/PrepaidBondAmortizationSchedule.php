<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrepaidBondAmortizationSchedule extends Model
{
    protected $table = 'prepaid_bond_amortization_schedules';

    protected $fillable = [
        'schedule_no',
        'bond_reference',
        'name',
        'prepayment_account_id',
        'expense_account_id',
        'total_fee_amount',
        'amortized_amount',
        'monthly_amortization_amount',
        'start_date',
        'end_date',
        'validity_months',
        'project_id',
        'cost_center_id',
        'site_id',
        'is_active',
    ];

    protected $casts = [
        'total_fee_amount'            => 'decimal:2',
        'amortized_amount'            => 'decimal:2',
        'monthly_amortization_amount' => 'decimal:2',
        'start_date'                  => 'date',
        'end_date'                    => 'date',
        'validity_months'             => 'integer',
        'is_active'                   => 'boolean',
    ];

    public function prepaymentAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'prepayment_account_id');
    }

    public function expenseAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Remaining unamortized fee balance.
     */
    public function getUnamortizedBalanceAttribute(): float
    {
        return max(0.0, round((float)$this->total_fee_amount - (float)$this->amortized_amount, 2));
    }

    public function getRemainingFeeAmountAttribute(): float
    {
        return $this->unamortized_balance;
    }
}
