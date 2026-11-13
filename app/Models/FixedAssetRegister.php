<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedAssetRegister extends Model
{
    protected $table = 'fixed_asset_register';

    protected $fillable = [
        'asset_code',
        'name',
        'asset_account_id',
        'accumulated_depr_account_id',
        'depreciation_expense_account_id',
        'acquisition_date',
        'historical_cost',
        'salvage_value',
        'audited_cutover_accumulated_depreciation',
        'current_accumulated_depreciation',
        'depreciation_method',
        'useful_life_years',
        'declining_rate_percent',
        'total_expected_units',
        'cumulative_units_consumed',
        'project_id',
        'cost_center_id',
        'site_id',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'acquisition_date'                        => 'date',
        'historical_cost'                         => 'decimal:2',
        'salvage_value'                           => 'decimal:2',
        'audited_cutover_accumulated_depreciation'=> 'decimal:2',
        'current_accumulated_depreciation'        => 'decimal:2',
        'useful_life_years'                       => 'decimal:6',
        'declining_rate_percent'                  => 'decimal:2',
        'total_expected_units'                    => 'decimal:2',
        'cumulative_units_consumed'               => 'decimal:2',
        'is_active'                               => 'boolean',
    ];

    public function assetAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'asset_account_id');
    }

    public function accumulatedDeprAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'accumulated_depr_account_id');
    }

    public function depreciationExpenseAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'depreciation_expense_account_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Compute current net book value (NBV).
     * NBV = Historical Cost - Current Accumulated Depreciation.
     */
    public function getNetBookValueAttribute(): float
    {
        $cost = (float) $this->historical_cost;
        $accDepr = (float) $this->current_accumulated_depreciation;
        return max(0.0, round($cost - $accDepr, 2));
    }
}
