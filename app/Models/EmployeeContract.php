<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class EmployeeContract extends Model
{
    protected $fillable = [
        'employee_id', 'project_id', 'contract_type', 'duration_type', 'is_project_based',
        'start_date', 'end_date', 'salary', 'terms', 'contract_file', 'status', 'created_by',
        'contract_number', 'approved_by', 'approved_at', 'termination_reason',
        'renewal_date', 'is_renewable', 'renewal_count', 'benefits_amount',
        'special_terms',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'renewal_date' => 'date',
        'approved_at' => 'datetime',
        'salary' => 'decimal:2',
        'benefits_amount' => 'decimal:2',
        'is_renewable' => 'boolean',
        'is_project_based' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function milestones()
    {
        return $this->hasMany(ContractMilestone::class, 'employee_contract_id');
    }

    public function amendments()
    {
        return $this->hasMany(ContractAmendment::class, 'employee_contract_id');
    }

    public function renewals()
    {
        return $this->hasMany(ContractRenewal::class, 'employee_contract_id');
    }

    public function approvals()
    {
        return $this->hasMany(ContractApproval::class, 'employee_contract_id');
    }

    public function getPendingApprovalsAttribute()
    {
        return $this->approvals()->where('status', 'pending')->count();
    }

    public function isProjectBased(): bool
    {
        return $this->duration_type === 'until_project_completion' || (bool)$this->is_project_based;
    }

    public function isActive()
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->isProjectBased()) {
            $project = $this->project ?? ($this->employee ? $this->employee->project : null);
            if ($project) {
                return !in_array(strtolower($project->status), ['completed', 'finished', 'closed', 'cancelled', 'handover', 'archived']);
            }
            return true;
        }

        $startDateCheck = !$this->start_date || $this->start_date <= now()->toDateString();
        $endDateCheck   = !$this->end_date || $this->end_date >= now()->toDateString();
        return $startDateCheck && $endDateCheck;
    }

    public function isExpired()
    {
        if ($this->isProjectBased()) {
            $project = $this->project ?? ($this->employee ? $this->employee->project : null);
            if ($project) {
                return in_array(strtolower($project->status), ['completed', 'finished', 'closed', 'cancelled', 'handover', 'archived']);
            }
            return false;
        }

        return $this->end_date && $this->end_date < now()->toDateString();
    }

    public function getDaysRemainingAttribute()
    {
        if ($this->isProjectBased()) {
            $project = $this->project ?? ($this->employee ? $this->employee->project : null);
            if ($project && $project->end_date && !$this->isExpired()) {
                return max(0, (int)now()->diffInDays($project->end_date, false));
            }
            return null;
        }

        if ($this->isExpired() || !$this->end_date) return 0;
        return max(0, (int)$this->end_date->diffInDays(now()));
    }

    public function getExpiryStatusAttribute()
    {
        if ($this->isProjectBased()) {
            return $this->isExpired() ? 'project_finished' : 'project_active';
        }

        $daysRemaining = $this->days_remaining;
        if ($daysRemaining <= 0) return 'expired';
        if ($daysRemaining <= 30) return 'expiring_soon';
        if ($daysRemaining <= 90) return 'expiring_3months';
        return 'active';
    }


    public function getTotalCompensationAttribute()
    {
        return ($this->salary ?? 0) + ($this->benefits_amount ?? 0);
    }
}

