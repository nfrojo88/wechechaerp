<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManpowerDailyReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'submitted_by',
        'report_date',
        'skilled_workers',
        'unskilled_workers',
        'supervisors',
        'engineers',
        'operators',
        'daily_laborers',
        'subcontractor_workers',
        'roles_breakdown',
        'subcontractors_breakdown',
        'total_present',
        'total_absent',
        'work_area',
        'planned_activities',
        'completed_activities',
        'challenges',
        'notes',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'report_date'               => 'date',
        'reviewed_at'               => 'datetime',
        'roles_breakdown'           => 'array',
        'subcontractors_breakdown'  => 'array',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // Auto-calculate totals before saving
    public static function boot()
    {
        parent::boot();
        static::saving(function ($model) {
            $totalCompany = 0;
            if (!empty($model->roles_breakdown) && is_array($model->roles_breakdown)) {
                foreach ($model->roles_breakdown as $item) {
                    $totalCompany += (int)($item['count'] ?? 0);
                }
            }

            $totalSubcon = 0;
            if (!empty($model->subcontractors_breakdown) && is_array($model->subcontractors_breakdown)) {
                foreach ($model->subcontractors_breakdown as $sub) {
                    if (!empty($sub['roles']) && is_array($sub['roles'])) {
                        foreach ($sub['roles'] as $r) {
                            $totalSubcon += (int)($r['count'] ?? 0);
                        }
                    } else {
                        $totalSubcon += (int)($sub['workers_count'] ?? $sub['count'] ?? 0);
                    }
                }
                $model->subcontractor_workers = $totalSubcon;
            } else {
                $totalSubcon = (int)($model->subcontractor_workers ?? 0);
            }

            if (!empty($model->roles_breakdown) || !empty($model->subcontractors_breakdown)) {
                $model->total_present = $totalCompany + $totalSubcon;
            } else {
                $model->total_present = (int)$model->skilled_workers
                    + (int)$model->unskilled_workers
                    + (int)$model->supervisors
                    + (int)$model->engineers
                    + (int)$model->operators
                    + (int)$model->daily_laborers
                    + (int)$model->subcontractor_workers;
            }
        });
    }

    public function getTotalWorkforceAttribute(): int
    {
        return $this->total_present ?? 0;
    }

    public function getCompanyWorkersCountAttribute(): int
    {
        if (!empty($this->roles_breakdown) && is_array($this->roles_breakdown)) {
            return array_sum(array_column($this->roles_breakdown, 'count'));
        }
        return (int)$this->skilled_workers + (int)$this->unskilled_workers + (int)$this->supervisors + (int)$this->engineers + (int)$this->operators + (int)$this->daily_laborers;
    }

    public function getSubcontractorWorkersCountAttribute(): int
    {
        if (!empty($this->subcontractors_breakdown) && is_array($this->subcontractors_breakdown)) {
            $sum = 0;
            foreach ($this->subcontractors_breakdown as $sub) {
                if (!empty($sub['roles']) && is_array($sub['roles'])) {
                    foreach ($sub['roles'] as $r) {
                        $sum += (int)($r['count'] ?? 0);
                    }
                } else {
                    $sum += (int)($sub['workers_count'] ?? $sub['count'] ?? 0);
                }
            }
            return $sum;
        }
        return (int)($this->subcontractor_workers ?? 0);
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            'approved' => 'bg-success',
            'rejected' => 'bg-danger',
            default    => 'bg-warning text-dark',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default    => 'Pending Review',
        };
    }
}
