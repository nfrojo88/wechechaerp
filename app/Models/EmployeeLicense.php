<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EmployeeLicense extends Model
{
    use HasFactory;

    protected $table = 'employee_licenses';

    protected $fillable = [
        'employee_id',
        'license_name',
        'issuing_organization',
        'license_number',
        'issue_date',
        'expiry_date',
        'license_document',
        'status',
        'notes',
    ];

    protected $casts = [
        'issue_date'  => 'date',
        'expiry_date' => 'date',
    ];

    /**
     * Get the employee that owns this license.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the license document file URL.
     */
    public function getLicenseUrlAttribute()
    {
        if (empty($this->license_document)) {
            return null;
        }

        if (Str::startsWith($this->license_document, ['http://', 'https://'])) {
            return $this->license_document;
        }

        if ($this->id) {
            try {
                return route('employees.licenses.document', $this->id);
            } catch (\Throwable $e) {}
        }

        return \App\Services\FileUploadService::url($this->license_document);
    }

    /**
     * Check if license is expired.
     */
    public function getIsExpiredAttribute(): bool
    {
        if ($this->expiry_date) {
            return $this->expiry_date->isPast();
        }
        return false;
    }

    /**
     * Get status badge information.
     */
    public function getStatusBadgeAttribute(): array
    {
        if ($this->expiry_date) {
            if ($this->expiry_date->isPast()) {
                return ['class' => 'bg-danger', 'label' => 'Expired'];
            }
            if (now()->diffInDays($this->expiry_date, false) <= 90) {
                return ['class' => 'bg-warning text-dark', 'label' => 'Expiring Soon'];
            }
        }
        return ['class' => 'bg-success', 'label' => 'Valid / Active'];
    }
}
