<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyManpowerSheet extends Model
{
    use HasFactory;

    protected $table = 'daily_manpower_sheets';

    protected $fillable = [
        'sheet_number',
        'project_id',
        'date',
        'site_engineer_id',
        'trade',
        'gang_subcontractor',
        'status',
        'current_stage',
        'total_headcount',
        'total_regular_hours',
        'total_overtime_hours',
        'total_amount',
        'total_adjusted_amount',
        'rejected_by_stage',
        'rejection_reason',
        'rejection_comment',
        'rejected_by_user_id',
        'weekly_batch_id',
        'notes',
    ];

    protected $casts = [
        'date'                  => 'date',
        'total_regular_hours'   => 'decimal:2',
        'total_overtime_hours'  => 'decimal:2',
        'total_amount'          => 'decimal:2',
        'total_adjusted_amount' => 'decimal:2',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function siteEngineer()
    {
        return $this->belongsTo(User::class, 'site_engineer_id');
    }

    public function rejectedByUser()
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    public function weeklyBatch()
    {
        return $this->belongsTo(WeeklyManpowerBatch::class, 'weekly_batch_id');
    }

    public function lines()
    {
        return $this->hasMany(DailyManpowerLine::class, 'sheet_id');
    }

    public function approvalLogs()
    {
        return $this->hasMany(ManpowerApprovalLog::class, 'record_id')
            ->where('record_type', 'daily_sheet')
            ->orderBy('id', 'asc');
    }

    /**
     * Effective amount of the sheet (total_adjusted_amount if set, otherwise total_amount)
     */
    public function getEffectiveTotalAmountAttribute(): float
    {
        return $this->total_adjusted_amount !== null ? (float)$this->total_adjusted_amount : (float)$this->total_amount;
    }
}
