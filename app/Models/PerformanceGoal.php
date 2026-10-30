<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceGoal extends Model
{
    protected $fillable = [
        'employee_id',
        'goal_title',
        'description',
        'start_date',
        'target_date',
        'priority',
        'target_value',
        'current_value',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'target_date' => 'date',
        'target_value' => 'decimal:2',
        'current_value' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withoutGlobalScope(\App\Scopes\NotDeadFileScope::class);
    }

    public function getProgressPercentageAttribute()
    {
        if ($this->target_value == 0) return 0;
        return ($this->current_value / $this->target_value) * 100;
    }

    public function getPriorityBadgeAttribute()
    {
        return match($this->priority) {
            'high' => 'danger',
            'medium' => 'warning',
            'low' => 'info',
            default => 'secondary'
        };
    }
}
