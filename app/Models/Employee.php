<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'project_id', 'employee_code', 'full_name',
        'national_id_number', 'national_id_card', 'tin_number',
        'phone', 'email', 'role_title', 'department',
        'employment_type', 'contract_type', 'contract_end_date', 'date_of_joining',
        'probation_ends_at', 'probation_completed', 'lock_reason',
        'basic_salary', 'transport_allowance', 'house_allowance', 'position_allowance',
        'status', 'notes', 'bank_name', 'account_number',
        'guarantee_letter', 'guarantee_letter_2', 'guarantee_letter_submitted_at', 'guarantee_letter_required',
        'guarantor_name', 'guarantor_id_number', 'guarantor_id_card', 'guarantor_phone',
        'guarantor_2_name', 'guarantor_2_id_number', 'guarantor_2_id_card', 'guarantor_2_phone',
        'device_user_id', 'asset_handover_document', 'profile_picture', 'registration_letter', 'registration_letters',
        'is_approved_by_gm', 'gm_approved_at', 'gm_approved_by',
        'gm_approval_status', 'gm_rejection_reason', 'gm_rejected_at', 'gm_rejected_by',
    ];

    protected $casts = [
        'date_of_joining' => 'date',
        'contract_end_date' => 'date',
        'probation_ends_at' => 'date',
        'probation_completed' => 'boolean',
        'basic_salary'    => 'decimal:2',
        'guarantee_letter_submitted_at' => 'date',
        'guarantee_letter_required' => 'boolean',
        'registration_letters' => 'array',
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

            if ($employee->date_of_joining && !$employee->probation_ends_at) {
                $employee->probation_ends_at = \Carbon\Carbon::parse($employee->date_of_joining)->addDays(45);
            }
        });

        static::saving(function ($employee) {
            if ($employee->date_of_joining && !$employee->probation_ends_at) {
                $employee->probation_ends_at = \Carbon\Carbon::parse($employee->date_of_joining)->addDays(45);
            }
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

    public function letters()
    {
        return $this->hasMany(EmployeeLetter::class)->latest('issued_date');
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

    public function getPettyCashBalanceAttribute(): float
    {
        if (!$this->user_id) {
            return 0.00;
        }
        return (float) ChartOfAccount::where('assigned_to', $this->user_id)
            ->where(function($q) {
                $q->where('code', '1110')
                  ->orWhere('code', 'like', '1110%')
                  ->orWhere('name', 'like', '%petty cash%')
                  ->orWhere('subtype', 'cash');
            })
            ->sum('current_balance');
    }

    /**
     * Get effective probation end date (45 days from date_of_joining by default)
     */
    public function getProbationEndDateAttribute()
    {
        if ($this->probation_ends_at) {
            return $this->probation_ends_at;
        }
        if ($this->date_of_joining) {
            return $this->date_of_joining->copy()->addDays(45);
        }
        return null;
    }

    /**
     * Get days passed since employee joining date
     */
    public function getDaysSinceJoiningAttribute()
    {
        if (!$this->date_of_joining) {
            return 0;
        }
        return (int) $this->date_of_joining->diffInDays(now(), false);
    }

    /**
     * Get days remaining in 45-day test / probation period (negative if overdue)
     */
    public function getDaysUntilProbationEndAttribute()
    {
        $endDate = $this->probation_end_date;
        if (!$endDate) {
            return null;
        }
        return (int) now()->diffInDays($endDate, false);
    }

    /**
     * Whether the employee is currently within the 45-day test period
     */
    public function getIsInProbationAttribute()
    {
        if ($this->probation_completed) {
            return false;
        }
        $endDate = $this->probation_end_date;
        return $endDate ? now()->lte($endDate) : false;
    }

    /**
     * Whether the 45-day test period has expired without renewal / guarantee letter
     */
    public function getIsTestPeriodExpiredAttribute()
    {
        if ($this->probation_completed) {
            return false;
        }
        $endDate = $this->probation_end_date;
        if (!$endDate || now()->lte($endDate)) {
            return false;
        }
        // If 45 days passed, and guarantee letter is missing, it is expired/lockable
        return empty($this->guarantee_letter);
    }

    /**
     * Whether to show warning alert (from day 20 up to day 45 of test period)
     */
    public function getShowProbationAlertAttribute()
    {
        if ($this->status !== 'active' || $this->probation_completed) {
            return false;
        }
        if (!$this->date_of_joining) {
            return false;
        }
        $daysSinceJoining = $this->days_since_joining;
        // Trigger alert between Day 20 and Day 45 (or when overdue), if guarantee letter is missing
        if ($daysSinceJoining >= 20 && empty($this->guarantee_letter)) {
            return true;
        }
        return false;
    }

    /**
     * Check if guarantee letter / test period is expired and account should be blocked
     */
    public function isGuaranteeLetterExpired()
    {
        return $this->is_test_period_expired;
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
     * Check if guarantee letter is overdue (45+ days without submission/renewal)
     */
    public function getIsGuaranteeOverdueAttribute()
    {
        return $this->is_test_period_expired;
    }

    /**
     * Check if warning should show (20+ days into test period without submission)
     */
    public function getShowGuaranteeWarningAttribute()
    {
        return $this->show_probation_alert;
    }

    /**
     * Get days until guarantee deadline / probation end
     */
    public function getDaysUntilGuaranteeDeadlineAttribute()
    {
        return $this->days_until_probation_end;
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

    /**
     * Get employee profile picture URL with fallback
     */
    public function getProfilePictureUrlAttribute()
    {
        if ($this->profile_picture) {
            return route('employees.profile-picture', $this->id);
        }
        $name = urlencode($this->full_name ?? 'Employee');
        return "https://ui-avatars.com/api/?name={$name}&background=198754&color=fff&size=150&bold=true";
    }

    /**
     * Get national ID card document/photo URL
     */
    public function getNationalIdCardUrlAttribute()
    {
        if ($this->national_id_card) {
            return route('employees.national-id', $this->id);
        }
        return null;
    }

    /**
     * Get asset handover document URL
     */
    public function getAssetHandoverDocumentUrlAttribute()
    {
        if ($this->asset_handover_document) {
            return route('employees.asset-handover', $this->id);
        }
        return null;
    }

    /**
     * Get registration letter URL (first or single document)
     */
    public function getRegistrationLetterUrlAttribute()
    {
        $urls = $this->registration_letter_urls;
        if (!empty($urls)) {
            return $urls[0];
        }
        if ($this->registration_letter) {
            return route('employees.registration-letter', $this->id);
        }
        return null;
    }

    /**
     * Get all registration letter / contract document URLs
     */
    public function getRegistrationLetterUrlsAttribute()
    {
        $urls = [];
        $letters = $this->registration_letters;

        if (is_array($letters) && count($letters) > 0) {
            foreach ($letters as $idx => $path) {
                $urls[] = route('employees.registration-letter', ['employee' => $this->id, 'index' => $idx]);
            }
        } elseif (!empty($this->registration_letter)) {
            $decoded = json_decode($this->registration_letter, true);
            if (is_array($decoded) && count($decoded) > 0) {
                foreach ($decoded as $idx => $path) {
                    $urls[] = route('employees.registration-letter', ['employee' => $this->id, 'index' => $idx]);
                }
            } else {
                $urls[] = route('employees.registration-letter', $this->id);
            }
        }

        return $urls;
    }

    /**
     * Get guarantor 1 ID card document/photo URL
     */
    public function getGuarantorIdCardUrlAttribute()
    {
        if ($this->guarantor_id_card) {
            return route('employees.guarantor-id', $this->id);
        }
        return null;
    }

    /**
     * Get guarantor 2 ID card document/photo URL
     */
    public function getGuarantor2IdCardUrlAttribute()
    {
        if ($this->guarantor_2_id_card) {
            return route('employees.guarantor-2-id', $this->id);
        }
        return null;
    }

    /**
     * Get second guarantee letter URL
     */
    public function getGuaranteeLetter2UrlAttribute()
    {
        if ($this->guarantee_letter_2) {
            return route('employees.guarantee-letter-2.view', $this->id);
        }
        return null;
    }
}
