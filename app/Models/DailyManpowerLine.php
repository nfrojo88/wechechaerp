<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyManpowerLine extends Model
{
    use HasFactory;

    protected $table = 'daily_manpower_lines';

    protected $fillable = [
        'sheet_id',
        'worker_id',
        'check_in',
        'check_out',
        'regular_hours',
        'overtime_hours',
        'daily_rate',
        'amount',
        'adjusted_amount',
        'attendance_status',
        'source',
        'device_user_id',
        'remark',
    ];

    protected $casts = [
        'regular_hours'   => 'decimal:2',
        'overtime_hours'  => 'decimal:2',
        'daily_rate'      => 'decimal:2',
        'amount'          => 'decimal:2',
        'adjusted_amount' => 'decimal:2',
    ];

    public function sheet()
    {
        return $this->belongsTo(DailyManpowerSheet::class, 'sheet_id');
    }

    public function worker()
    {
        return $this->belongsTo(Worker::class, 'worker_id');
    }

    /**
     * Get effective payable amount (adjusted_amount if set by approver, else original amount)
     */
    public function getEffectiveAmountAttribute(): float
    {
        return $this->adjusted_amount !== null ? (float)$this->adjusted_amount : (float)$this->amount;
    }
}
