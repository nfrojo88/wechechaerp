<?php

namespace App\Models;

use App\Models\Payroll;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $table = 'attendance';

    protected $fillable = [
        'employee_id', 'attendance_date', 
        'morning_in', 'morning_out', 'afternoon_in', 'afternoon_out',
        'check_in', 'check_out',
        'hours_worked', 'late_minutes', 'status', 'source', 'biometric_device_id',
        'notes', 'is_approved', 'approved_by',
        'overtime_hours', 'overtime_type', 'overtime_pay',
        'decided_by', 'decided_by_role', 'site_project_id', 'site_name',
        'site_task', 'site_start_date', 'site_end_date',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'site_start_date' => 'date',
        'site_end_date'   => 'date',
        'is_approved'     => 'boolean',
        'late_minutes'    => 'integer',
        'overtime_hours'  => 'decimal:2',
        'overtime_pay'    => 'decimal:2',
    ];

    /**
     * Get late minutes (RULE 1: strictly calculated against 08:40 AM cutoff).
     */
    public function getLateMinutesAttribute($value): int
    {
        if ($value !== null && is_numeric($value)) {
            return (int)$value;
        }
        $inTime = $this->morning_in ?: $this->check_in;
        return \App\Services\BiometricPunchService::calculateLateMinutes($inTime);
    }

    /**
     * Determine if attendance is late.
     */
    public function getIsLateAttribute(): bool
    {
        return $this->late_minutes > 0;
    }

    /**
     * Formatted label: 'On time' or 'X min late'.
     */
    public function getLateLabelAttribute(): string
    {
        return \App\Services\BiometricPunchService::formatLateLabel($this->late_minutes);
    }

    public function employee()    { return $this->belongsTo(Employee::class); }

    /**
     * Scope query to strictly include attendance records for active employees (excluding Dead File).
     */
    public function scopeActiveRoster($query)
    {
        return $query->whereHas('employee', function ($q) {
            $q->where(function ($sq) {
                $sq->where('is_dead_file', false)->orWhereNull('is_dead_file');
            })->where('status', '!=', 'dead_file');
        });
    }

    /**
     * Scope query to strictly include attendance records for Head Office staff only
     * (excludes site workers, drivers, and remote workers).
     */
    public function scopeOfficeStaffOnly($query)
    {
        return $query->whereHas('employee', function ($q) {
            $q->officeStaffOnly();
        });
    }

    /**
     * Scope query to attendance records for Site, Driver, and Remote workers.
     */
    public function scopeSiteDriverRemoteOnly($query)
    {
        return $query->whereHas('employee', function ($q) {
            $q->siteDriverRemoteOnly();
        });
    }

    public function approvedBy()  { return $this->belongsTo(User::class, 'approved_by'); }
    public function decidedBy()   { return $this->belongsTo(User::class, 'decided_by'); }
    public function siteProject() { return $this->belongsTo(Project::class, 'site_project_id'); }

    /**
     * Check if this record is an on-site deployment
     */
    public function isOnSite(): bool
    {
        return in_array(strtolower((string)$this->status), ['s', 'site', 'on_site'])
            || str_contains((string)($this->notes ?? ''), 'On-Site')
            || $this->source === 'site_dispatch';
    }

    /**
     * OT type labels for display.
     */
    public static function overtimeTypeLabels(): array
    {
        return [
            'none'       => 'No OT',
            'holiday'    => 'Holiday (×2.5)',
            'rest_day'   => 'Rest Day / Sunday (×2.0)',
            'night_12_4' => 'Night 12AM–4AM (×1.5)',
            'night_4_12' => 'Night 4PM–12AM (×1.75)',
        ];
    }

    /**
     * Compute OT pay for this attendance record using the employee's basic salary.
     */
    public function computeOvertimePay(): float
    {
        $basic = $this->employee->basic_salary ?? 0;
        return Payroll::calculateOvertimePay(
            (float) $basic,
            (float) ($this->overtime_hours ?? 0),
            $this->overtime_type ?? 'none'
        );
    }
}

