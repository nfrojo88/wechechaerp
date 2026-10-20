<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseHubMonthLock extends Model
{
    protected $table = 'expense_hub_month_locks';

    protected $fillable = [
        'year',
        'month',
        'calendar',
        'is_locked',
        'locked_by',
        'locked_at',
        'unlocked_by',
        'unlocked_at',
        'unlock_reason',
    ];

    protected $casts = [
        'is_locked'   => 'boolean',
        'year'        => 'integer',
        'month'       => 'integer',
        'locked_at'   => 'datetime',
        'unlocked_at' => 'datetime',
    ];

    public function lockedByUser()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function unlockedByUser()
    {
        return $this->belongsTo(User::class, 'unlocked_by');
    }

    public static function isMonthLocked(int $year, int $month, string $calendar = 'EC'): bool
    {
        return static::where('year', $year)
            ->where('month', $month)
            ->where('calendar', $calendar)
            ->where('is_locked', true)
            ->exists();
    }
}
