<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstructionContract extends Model
{
    use HasFactory;

    protected $table = 'construction_contracts';

    protected $fillable = [
        'contract_number',
        'title',
        'client_id',
        'project_id',
        'cost_center_id',
        'contract_value_exc_vat',
        'vat_rate_percent',
        'retention_rate_percent',
        'retention_cap_amount',
        'advance_payment_amount',
        'advance_recovery_rate_percent',
        'vat_base_type',
        'vat_base_status',
        'original_budget_cost',
        'current_budget_cost',
        'estimate_to_complete',
        'status',
        'start_date',
        'end_date',
        'defect_liability_end_date',
        'created_by',
    ];

    protected $casts = [
        'contract_value_exc_vat'        => 'decimal:2',
        'vat_rate_percent'              => 'decimal:2',
        'retention_rate_percent'        => 'decimal:2',
        'retention_cap_amount'          => 'decimal:2',
        'advance_payment_amount'        => 'decimal:2',
        'advance_recovery_rate_percent' => 'decimal:2',
        'original_budget_cost'          => 'decimal:2',
        'current_budget_cost'           => 'decimal:2',
        'estimate_to_complete'          => 'decimal:2',
        'start_date'                    => 'date',
        'end_date'                      => 'date',
        'defect_liability_end_date'     => 'date',
    ];

    public function budgetRevisions(): HasMany
    {
        return $this->hasMany(ContractBudgetRevision::class, 'contract_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'contract_id');
    }

    /**
     * Total Expected Contract Costs (BAC) = Incurred to Date + ETC.
     */
    public function getTotalExpectedCostAttribute(): float
    {
        return (float) $this->current_budget_cost;
    }

    /**
     * Check if contract is currently forecast to be loss-making (onerous).
     */
    public function isOnerous(): bool
    {
        return (float) $this->current_budget_cost > (float) $this->contract_value_exc_vat;
    }

    /**
     * Total expected forecast loss.
     */
    public function getForecastLossAttribute(): float
    {
        if (!$this->isOnerous()) {
            return 0.00;
        }

        return round((float) $this->current_budget_cost - (float) $this->contract_value_exc_vat, 2);
    }
}
