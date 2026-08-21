<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'project_id', 'employee_code', 'full_name',
        'phone', 'email', 'role_title', 'department',
        'employment_type', 'contract_type', 'date_of_joining', 'basic_salary',
        'transport_allowance', 'house_allowance', 'position_allowance',
        'status', 'notes', 'bank_name', 'account_number',
        'guarantee_letter', 'guarantee_letter_submitted_at', 'guarantee_letter_required',
        'device_user_id', 'is_approved_by_gm', 'gm_approved_at', 'gm_approved_by',
        'gm_approval_status', 'gm_rejection_reason', 'gm_rejected_at', 'gm_rejected_by',
    ];

    protected $casts = [
        'date_of_joining' => 'date',
        'basic_salary'    => 'decimal:2',
        'guarantee_letter_submitted_at' => 'date',
        'guarantee_letter_required' => 'boolean',
        'is_approved_by_gm' => 'boolean',
        'gm_approved_at' => 'datetime',
        'gm_rejected_at' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     * Enforces strict GM approval policy for every newly created or re-added employee.
     */
    protected static function booted()
    {
        static::creating(function ($employee) {
            // Strict Rule: Every new or re-added employee MUST strictly start with pending GM approval
            $employee->is_approved_by_gm = false;
            $employee->gm_approval_status = 'pending';
            $employee->gm_approved_at = null;
            $employee->gm_approved_by = null;
            $employee->gm_rejection_reason = null;
            $employee->gm_rejected_at = null;
            $employee->gm_rejected_by = null;
        });

        static::deleting(function ($employee) {
            // Unassign fixed assets upon employee deletion so they return to available inventory
            try {
                if ($employee->assignedFixedAssets) {
                    foreach ($employee->assignedFixedAssets as $unit) {
                        $unit->returnToStore(auth()->id() ?? 1, 'Auto-returned upon employee profile deletion', 'good');
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Asset unassign on employee delete: ' . $e->getMessage());
            }
        });
    }

    public function gmApprovedBy()
    {
        return $this->belongsTo(User::class, 'gm_approved_by');
    }

    public function gmRejectedBy()
    {
        return $this->belongsTo(User::class, 'gm_rejected_by');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function skills()
    {
        return $this->hasMany(EmployeeSkill::class);
    }

    public function availability()
    {
        return $this->hasMany(ResourceAvailability::class);
    }

    public function manpowerAssignments()
    {
        return $this->hasMany(ManpowerAssignment::class);
    }

    public function performanceReviews()
    {
        return $this->hasMany(PerformanceReview::class);
    }

    public function performanceMetrics()
    {
        return $this->hasMany(PerformanceMetric::class);
    }

    public function performanceGoals()
    {
        return $this->hasMany(PerformanceGoal::class);
    }

    public function competencyAssessments()
    {
        return $this->hasMany(CompetencyAssessment::class);
    }

    public function achievements()
    {
        return $this->hasMany(EmployeeAchievement::class);
    }

    public function contracts()
    {
        return $this->hasMany(EmployeeContract::class);
    }

    public function salaryStructure()
    {
        return $this->hasOne(SalaryStructure::class)->where('is_active', true);
    }

    public function ratings()
    {
        return $this->hasMany(EmployeeRating::class);
    }

    public function advances()
    {
        return $this->hasMany(EmployeeAdvance::class);
    }

    public function assets()
    {
        return $this->hasMany(EmployeeAsset::class);
    }

    public function activeAssets()
    {
        return $this->assets()->whereIn('status', ['assigned', 'in_use']);
    }

    public function assignedFixedAssets()
    {
        return $this->hasMany(FixedAssetUnit::class, 'assigned_to_employee_id')->orderBy('unit_code');
    }

    public function fixedAssetAssignments()
    {
        return $this->hasMany(FixedAssetAssignment::class, 'employee_id')->latest();
    }

    public function education()
    {
        return $this->hasMany(EmployeeEducation::class)->orderBy('end_date', 'desc');
    }

    public function experience()
    {
        return $this->hasMany(EmployeeExperience::class)->orderBy('start_date', 'desc');
    }

    public function licenses()
    {
        return $this->hasMany(EmployeeLicense::class)->orderBy('expiry_date', 'desc');
    }

    /**
     * Check if guarantee letter is overdue and account should be blocked (30+ days)
     */
    public function isGuaranteeLetterExpired()
    {
        if (!$this->guarantee_letter_required || $this->guarantee_letter || !$this->date_of_joining) {
            return false;
        }
        
        return $this->date_of_joining->addDays(30)->isPast();
    }

    /**
     * Get guarantee letter URL
     */
    public function getGuaranteeLetterUrlAttribute()
    {
        if (empty($this->guarantee_letter)) {
            return null;
        }

        if (\Illuminate\Support\Str::startsWith($this->guarantee_letter, ['http://', 'https://'])) {
            return $this->guarantee_letter;
        }

        if ($this->id) {
            try {
                return route('employees.guarantee-letter.view', $this->id);
            } catch (\Throwable $e) {}
        }

        return \App\Services\FileUploadService::url($this->guarantee_letter);
    }

    /**
     * Check if guarantee letter is overdue (30+ days without submission)
     */
    public function getIsGuaranteeOverdueAttribute()
    {
        if (!$this->guarantee_letter_required || $this->guarantee_letter || !$this->date_of_joining) {
            return false;
        }
        
        return $this->date_of_joining->addDays(30)->isPast();
    }

    /**
     * Check if guarantee letter warning should show (20+ days without submission)
     */
    public function getShowGuaranteeWarningAttribute()
    {
        if (!$this->guarantee_letter_required || $this->guarantee_letter || !$this->date_of_joining) {
            return false;
        }
        
        return $this->date_of_joining->addDays(20)->isPast();
    }

    /**
     * Get days until guarantee letter deadline
     */
    public function getDaysUntilGuaranteeDeadlineAttribute()
    {
        if (!$this->guarantee_letter_required || $this->guarantee_letter || !$this->date_of_joining) {
            return null;
        }
        
        $deadline = $this->date_of_joining->addDays(30);
        return now()->diffInDays($deadline, false); // negative if overdue
    }

    public function getCurrentMonthlyDeductionAttribute()
    {
        return $this->advances()
            ->where('status', 'disbursed')
            ->where('recovered_at', null)
            ->sum(\DB::raw('amount / installments'));
    }

    public function deviceLogs()
    {
        return $this->hasMany(DeviceAttendanceLog::class, 'device_user_id', 'device_user_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
}
