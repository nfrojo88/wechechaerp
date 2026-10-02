<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeeklyBatchItem extends Model
{
    use HasFactory;

    protected $table = 'weekly_batch_items';

    protected $fillable = [
        'batch_id',
        'worker_id',
        'days_worked',
        'total_regular_hours',
        'total_overtime_hours',
        'gross_amount',
        'deductions',
        'advances',
        'net_payable',
        'daily_sheet_ids',
        'notes',
    ];

    protected $casts = [
        'days_worked'          => 'decimal:2',
        'total_regular_hours'  => 'decimal:2',
        'total_overtime_hours' => 'decimal:2',
        'gross_amount'         => 'decimal:2',
        'deductions'           => 'decimal:2',
        'advances'             => 'decimal:2',
        'net_payable'          => 'decimal:2',
        'daily_sheet_ids'      => 'array',
    ];

    public function batch()
    {
        return $this->belongsTo(WeeklyManpowerBatch::class, 'batch_id');
    }

    public function worker()
    {
        return $this->belongsTo(Worker::class, 'worker_id');
    }
}
