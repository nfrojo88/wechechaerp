<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevenueRecognitionRun extends Model
{
    use HasFactory;

    protected $table = 'revenue_recognition_runs';

    protected $fillable = [
        'contract_id',
        'financial_period_id',
        'as_of_date',
        'contract_price',
        'current_budget_cost',
        'qualifying_incurred_cost',
        'uninstalled_material_cost',
        'poc_percentage',
        'cumulative_revenue',
        'previously_recognised_revenue',
        'catch_up_revenue',
        'cumulative_billed',
        'net_asset_amount',
        'net_liability_amount',
        'journal_entry_id',
        'is_posted',
        'created_by',
    ];

    protected $casts = [
        'contract_price'                => 'decimal:2',
        'current_budget_cost'           => 'decimal:2',
        'qualifying_incurred_cost'      => 'decimal:2',
        'uninstalled_material_cost'     => 'decimal:2',
        'poc_percentage'                => 'decimal:4',
        'cumulative_revenue'            => 'decimal:2',
        'previously_recognised_revenue' => 'decimal:2',
        'catch_up_revenue'              => 'decimal:2',
        'cumulative_billed'             => 'decimal:2',
        'net_asset_amount'              => 'decimal:2',
        'net_liability_amount'          => 'decimal:2',
        'is_posted'                     => 'boolean',
        'as_of_date'                    => 'date',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(ConstructionContract::class, 'contract_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
