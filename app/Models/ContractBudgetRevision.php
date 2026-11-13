<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractBudgetRevision extends Model
{
    use HasFactory;

    protected $table = 'contract_budget_revisions';

    protected $fillable = [
        'contract_id',
        'revision_no',
        'revision_type',
        'previous_budget_cost',
        'revised_budget_cost',
        'previous_contract_value',
        'revised_contract_value',
        'justification',
        'is_approved',
        'approved_by',
        'approved_at',
        'created_by',
    ];

    protected $casts = [
        'previous_budget_cost'    => 'decimal:2',
        'revised_budget_cost'     => 'decimal:2',
        'previous_contract_value' => 'decimal:2',
        'revised_contract_value'  => 'decimal:2',
        'is_approved'             => 'boolean',
        'approved_at'             => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(ConstructionContract::class, 'contract_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
