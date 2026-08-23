<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrReportSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_type',
        'from_date',
        'to_date',
        'total_employees',
        'avg_attendance_rate',
        'total_working_days',
        'notes',
        'summary_data',
        'submitted_by',
        'status',
        'reviewed_by',
        'reviewed_at',
        'gm_remarks',
    ];

    protected $casts = [
        'from_date'           => 'date',
        'to_date'             => 'date',
        'avg_attendance_rate' => 'decimal:2',
        'summary_data'        => 'array',
        'reviewed_at'         => 'datetime',
    ];

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
