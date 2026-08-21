<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class EmployeeExperience extends Model
{
    use HasFactory;

    protected $table = 'employee_experience';

    protected $fillable = [
        'employee_id',
        'job_title',
        'company_name',
        'location',
        'start_date',
        'end_date',
        'is_current',
        'responsibilities',
        'reference_name',
        'reference_phone',
        'license_document',
        'license_number',
        'license_expiry',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'license_expiry' => 'date',
        'is_current' => 'boolean',
    ];

    /**
     * Get the employee that owns this experience record
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the license document URL
     */
    public function getLicenseUrlAttribute()
    {
        if (empty($this->license_document)) {
            return null;
        }

        if (\Illuminate\Support\Str::startsWith($this->license_document, ['http://', 'https://'])) {
            return $this->license_document;
        }

        if ($this->id) {
            try {
                return route('employees.experience.license', $this->id);
            } catch (\Throwable $e) {}
        }

        return \App\Services\FileUploadService::url($this->license_document);
    }

    /**
     * Get duration of employment
     */
    public function getDurationAttribute()
    {
        if ($this->is_current) {
            $months = $this->start_date->diffInMonths(now());
            $years = floor($months / 12);
            $remainingMonths = $months % 12;
            
            if ($years > 0) {
                return $years . ' year' . ($years > 1 ? 's' : '') . ($remainingMonths > 0 ? ', ' . $remainingMonths . ' months' : '');
            }
            return $remainingMonths . ' month' . ($remainingMonths > 1 ? 's' : '');
        }
        
        if ($this->start_date && $this->end_date) {
            $months = $this->start_date->diffInMonths($this->end_date);
            $years = floor($months / 12);
            $remainingMonths = $months % 12;
            
            if ($years > 0) {
                return $years . ' year' . ($years > 1 ? 's' : '') . ($remainingMonths > 0 ? ', ' . $remainingMonths . ' months' : '');
            }
            return $remainingMonths . ' month' . ($remainingMonths > 1 ? 's' : '');
        }
        
        return 'N/A';
    }

    /**
     * Get formatted period
     */
    public function getPeriodAttribute()
    {
        $start = $this->start_date ? $this->start_date->format('M Y') : 'N/A';
        $end = $this->is_current ? 'Present' : ($this->end_date ? $this->end_date->format('M Y') : 'N/A');
        return $start . ' - ' . $end;
    }

    /**
     * Check if license is expired
     */
    public function getIsLicenseExpiredAttribute()
    {
        if ($this->license_expiry) {
            return $this->license_expiry->isPast();
        }
        return false;
    }
}
