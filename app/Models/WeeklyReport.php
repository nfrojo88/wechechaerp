<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WeeklyReport extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected $casts = [
        'week_start' => 'date',
        'week_end' => 'date',
        'daily_report_ids' => 'array',
    ];

    public function project() { return $this->belongsTo(Project::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }

    /**
     * Retrieve attached daily reports, falling back to project and date-range match.
     */
    public function getAttachedDailyReportsAttribute()
    {
        if (!empty($this->daily_report_ids) && is_array($this->daily_report_ids)) {
            $reports = DailyReport::with(['items', 'createdBy'])
                ->whereIn('id', $this->daily_report_ids)
                ->orderBy('report_date', 'asc')
                ->get();

            if ($reports->isNotEmpty()) {
                return $reports;
            }
        }

        return DailyReport::with(['items', 'createdBy'])
            ->where('project_id', $this->project_id)
            ->whereBetween('report_date', [$this->week_start, $this->week_end])
            ->orderBy('report_date', 'asc')
            ->get();
    }
}
