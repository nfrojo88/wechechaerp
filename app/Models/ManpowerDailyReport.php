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
        'report_date'  => 'date',
        'reviewed_at'  => 'datetime',
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
            $model->total_present = (int)$model->skilled_workers
                + (int)$model->unskilled_workers
                + (int)$model->supervisors
                + (int)$model->engineers
                + (int)$model->operators
                + (int)$model->daily_laborers
                + (int)$model->subcontractor_workers;
        });
    }

    public function getTotalWorkforceAttribute(): int
    {
        return $this->total_present ?? 0;
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
