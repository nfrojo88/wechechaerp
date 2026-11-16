<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubconAgreement extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'start_date'         => 'date',
        'end_date'           => 'date',
        'approved_at'        => 'datetime',
        'unit_price_per_m2'  => 'decimal:2',
        'estimated_total_m2' => 'decimal:2',
        'base_amount'        => 'decimal:2',
        'vat_rate'           => 'decimal:2',
        'vat_amount'         => 'decimal:2',
    ];

    // Relationships
    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function subcontractor()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(SubconAgreementItem::class, 'agreement_id');
    }

    public function ipcs()
    {
        return $this->hasMany(IpcRecord::class, 'agreement_id');
    }

    public function expenseRequests()
    {
        return $this->hasMany(ExpenseRequest::class, 'subcon_agreement_id');
    }

    // Financial Tracking & Cross-Check Accessors
    public function getTotalPaidExpensesAttribute(): float
    {
        return (float) $this->expenseRequests()
            ->where('status', ExpenseRequest::STATUS_PAID)
            ->sum('amount');
    }

    public function getTotalPendingExpensesAttribute(): float
    {
        return (float) $this->expenseRequests()
            ->whereNotIn('status', [ExpenseRequest::STATUS_PAID, ExpenseRequest::STATUS_REJECTED])
            ->sum('amount');
    }

    public function getTotalApprovedIpcAmountAttribute(): float
    {
        return (float) $this->ipcs()
            ->where('status', 'approved')
            ->sum('net_payable');
    }

    public function getTotalDisbursedAmountAttribute(): float
    {
        $directPaid = (float)($this->attributes['paid_to_date'] ?? 0);
        return max($directPaid, round($this->total_paid_expenses + $this->total_approved_ipc_amount, 2));
    }

    public function getTotalCommittedAmountAttribute(): float
    {
        return round($this->total_disbursed_amount + $this->total_pending_expenses, 2);
    }

    public function getRemainingContractBalanceAttribute(): float
    {
        $contractVal = $this->effective_total_amount;
        if ($contractVal <= 0) {
            return 0.0;
        }
        return max(0.0, round($contractVal - $this->total_committed_amount, 2));
    }

    public function getPercentConsumedAttribute(): float
    {
        $contractVal = $this->effective_total_amount;
        if ($contractVal <= 0) {
            return 0.0;
        }
        return min(100.0, round(($this->total_committed_amount / $contractVal) * 100, 1));
    }

    public function getCrossCheckSummary($currentAmount = 0.0): array
    {
        $contractVal = (float)$this->effective_total_amount;
        $paid = (float)$this->total_disbursed_amount;
        $pending = (float)$this->total_pending_expenses;
        $committed = (float)$this->total_committed_amount;
        $remaining = (float)$this->remaining_contract_balance;
        $percent = (float)$this->percent_consumed;

        $currentAmount = (float)$currentAmount;
        $balanceAfter = round($remaining - $currentAmount, 2);
        $isOverBudget = ($currentAmount > $remaining && $contractVal > 0);
        $overageAmount = $isOverBudget ? round($currentAmount - $remaining, 2) : 0.0;

        return [
            'id'                            => $this->id,
            'agreement_no'                  => $this->agreement_no,
            'subcontractor_name'            => $this->subcontractor_display_name,
            'project_id'                    => $this->project_id,
            'project_name'                  => $this->project->project_name ?? $this->project->name ?? 'General / Head Office',
            'work_description'              => $this->work_description ?? $this->scope_of_work ?? 'Contract Work',
            'contract_value'                => $contractVal,
            'effective_total_amount'        => $contractVal,
            'paid_amount'                   => $paid,
            'total_disbursed_to_date'       => $paid,
            'pending_amount'                => $pending,
            'committed_amount'              => $committed,
            'remaining_balance'             => $remaining,
            'available_balance_before'      => $remaining,
            'percent_consumed'              => $percent,
            'is_exceeded'                   => ($committed > $contractVal && $contractVal > 0),
            'over_budget_amount'            => max(0.0, round($committed - $contractVal, 2)),
            'balance_after_current_request' => $balanceAfter,
            'is_over_budget'                => $isOverBudget,
            'overage_amount'                => $overageAmount,
        ];
    }
    
    // Takeoff Integration
    public function takeoffItems() 
    { 
        return $this->belongsToMany(
            TakeoffItem::class,
            'subcon_agreement_takeoff_items',
            'agreement_id',
            'takeoff_item_id'
        )->withPivot('selected_quantity', 'rate', 'total_amount')->withTimestamps();
    }

    public function takeoffSheet() 
    { 
        return $this->belongsTo(TakeoffSheet::class);
    }

    // Accessors & Helpers
    public function getSubcontractorDisplayNameAttribute(): string
    {
        if ($this->supplier && !empty($this->supplier->name)) {
            return $this->supplier->name;
        }
        if (!empty($this->subcontractor_name)) {
            return $this->subcontractor_name;
        }
        return 'Subcontractor / Supplier';
    }

    public function getDescriptionDisplayAttribute(): string
    {
        return $this->work_description ?? $this->scope_of_work ?? '';
    }

    public function getEffectiveTotalAmountAttribute(): float
    {
        $raw = (float)($this->attributes['total_amount'] ?? 0);
        if ($raw > 0) {
            return $raw;
        }
        $contractVal = (float)($this->attributes['contract_value'] ?? 0);
        if ($contractVal > 0) {
            return $contractVal;
        }
        $itemsSum = (float)$this->items()->sum('total_amount');
        if ($itemsSum > 0) {
            return $itemsSum;
        }
        return 0.0;
    }

    public function getTotalTakeoffAmountAttribute(): float
    {
        return (float)($this->takeoffItems()->sum('subcon_agreement_takeoff_items.total_amount') ?? 0);
    }

    public function getAgreementFileUrlAttribute(): ?string
    {
        if (empty($this->agreement_file)) {
            return null;
        }
        if (str_starts_with($this->agreement_file, 'http://') || str_starts_with($this->agreement_file, 'https://')) {
            return $this->agreement_file;
        }
        return asset('storage/' . $this->agreement_file);
    }

    public function getIsPdfAttribute(): bool
    {
        if (empty($this->agreement_file)) {
            return false;
        }
        return (bool)preg_match('/\.pdf$/i', $this->agreement_file);
    }

    public function getIsImageAttribute(): bool
    {
        if (empty($this->agreement_file)) {
            return false;
        }
        return (bool)preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $this->agreement_file);
    }

    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'draft'      => 'secondary',
            'pending'    => 'warning',
            'approved'   => 'success',
            'active'     => 'info',
            'completed'  => 'success',
            'rejected'   => 'danger',
            'terminated' => 'dark',
            'cancelled'  => 'dark',
            default      => 'secondary'
        };
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && (!$this->end_date || $this->end_date >= now()->toDateString());
    }

    public function isExpired(): bool
    {
        return $this->end_date && $this->end_date < now()->toDateString();
    }
}
