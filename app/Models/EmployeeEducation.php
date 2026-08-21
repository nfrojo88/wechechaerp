<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EmployeeEducation extends Model
{
    use HasFactory;

    protected $table = 'employee_education';

    protected $fillable = [
        'employee_id',
        'degree_level',
        'field_of_study',
        'institution_name',
        'location',
        'start_date',
        'end_date',
        'grade_gpa',
        'description',
        'certificate_photo',
        'is_verified',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_verified' => 'boolean',
    ];

    /**
     * Get the employee that owns this education record
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the certificate photo URL
     */
    public function getCertificateUrlAttribute()
    {
        if (empty($this->certificate_photo)) {
            return null;
        }

        if (\Illuminate\Support\Str::startsWith($this->certificate_photo, ['http://', 'https://'])) {
            return $this->certificate_photo;
        }

        if ($this->id) {
            try {
                return route('employees.education.certificate', $this->id);
            } catch (\Throwable $e) {}
        }

        return \App\Services\FileUploadService::url($this->certificate_photo);
    }

    /**
     * Get duration of study
     */
    public function getDurationAttribute()
    {
        if ($this->start_date && $this->end_date) {
            return $this->start_date->format('Y') . ' - ' . $this->end_date->format('Y');
        }
        return $this->start_date ? $this->start_date->format('Y') . ' - Present' : 'N/A';
    }
}
