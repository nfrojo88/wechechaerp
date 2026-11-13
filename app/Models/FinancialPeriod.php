<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class FinancialPeriod extends Model
{
    protected $fillable = [
        'name',
        'code',
        'start_date',
        'end_date',
        'fiscal_year',
        'status',
        'is_locked',
        'is_period_13',
        'closed_at',
        'closed_by',
        'locked_at',
        'locked_by',
        'reopened_at',
        'reopened_by',
        'reopen_reason',
        'tb_balanced_at_close',
        'tb_total_debit',
        'tb_total_credit',
        'tb_difference',
        'closing_notes',
    ];

    protected $casts = [
        'start_date'           => 'date',
        'end_date'             => 'date',
        'is_locked'            => 'boolean',
        'is_period_13'         => 'boolean',
        'closed_at'            => 'datetime',
        'tb_balanced_at_close' => 'boolean',
        'locked_at'            => 'datetime',
        'reopened_at'          => 'datetime',
        'tb_total_debit'       => 'decimal:2',
        'tb_total_credit'      => 'decimal:2',
        'tb_difference'        => 'decimal:2',
    ];

    public function lockedByUser()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function reopenedByUser()
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    /**
     * Check if a given date falls within this financial period.
     */
    public function containsDate($date): bool
    {
        $d = Carbon::parse($date);
        return $d->betweenIncluded($this->start_date, $this->end_date);
    }

    /**
     * Scope for open periods.
     */
    public function scopeOpen($query)
    {
        return $query->where('is_locked', false);
    }

    /**
     * Scope for locked periods.
     */
    public function scopeLocked($query)
    {
        return $query->where('is_locked', true);
    }
}
