<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeeklyManpowerBatch extends Model
{
    use HasFactory;

    protected $table = 'weekly_manpower_batches';

    protected $fillable = [
        'batch_number',
        'project_id',
        'week_start',
        'week_end',
        'status',
        'current_stage',
        'total_workers_count',
        'total_days_worked',
        'total_gross_amount',
        'total_deductions',
        'total_advances',
        'total_net_payable',
        'prepared_by_hr_id',
        'approved_by_gm_id',
        'gm_approved_at',
        'gm_notes',
        'paid_by_finance_id',
        'payment_date',
        'payment_method',
        'payment_reference',
        'payment_attachment',
        'payment_notes',
        'rejection_reason',
        'rejection_comment',
        'hold_reason',
    ];

    protected $casts = [
        'week_start'           => 'date',
        'week_end'             => 'date',
        'gm_approved_at'       => 'datetime',
        'payment_date'         => 'date',
        'total_days_worked'    => 'decimal:2',
        'total_gross_amount'   => 'decimal:2',
        'total_deductions'     => 'decimal:2',
        'total_advances'       => 'decimal:2',
        'total_net_payable'    => 'decimal:2',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function preparedBy()
    {
        return $this->belongsTo(User::class, 'prepared_by_hr_id');
    }

    public function approvedByGm()
    {
        return $this->belongsTo(User::class, 'approved_by_gm_id');
    }

    public function paidByFinance()
    {
        return $this->belongsTo(User::class, 'paid_by_finance_id');
    }

    public function items()
    {
        return $this->hasMany(WeeklyBatchItem::class, 'batch_id');
    }

    public function dailySheets()
    {
        return $this->hasMany(DailyManpowerSheet::class, 'weekly_batch_id');
    }

    public function approvalLogs()
    {
        return $this->hasMany(ManpowerApprovalLog::class, 'record_id')
            ->where('record_type', 'weekly_batch')
            ->orderBy('id', 'asc');
    }
}
