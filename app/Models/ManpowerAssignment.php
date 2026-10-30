<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManpowerAssignment extends Model
{
    protected $fillable = [
        'manpower_forecast_id',
        'employee_id',
        'hours_assigned',
        'billable',
        'notes',
        'status',
    ];

    protected $casts = [
        'hours_assigned' => 'decimal:2',
        'billable' => 'boolean',
    ];

    public function forecast()
    {
        return $this->belongsTo(ManpowerForecast::class, 'manpower_forecast_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withoutGlobalScope(\App\Scopes\NotDeadFileScope::class);
    }
}
