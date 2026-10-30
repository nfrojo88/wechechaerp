<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeAdvance extends Model
{
    protected $fillable = [
        'employee_id',
        'amount',
        'advance_date',
        'installments',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'disbursed_at',
        'recovered_at',
        'gm_notes',
        'finance_notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'advance_date' => 'date',
        'approved_at' => 'datetime',
        'disbursed_at' => 'datetime',
        'recovered_at' => 'datetime',
    ];

    public function employee()
    {
        // withoutGlobalScope: historical advance records must still show the employee name
        // even after they move to Dead File. The scope only affects live lookups (forms/dropdowns).
        return $this->belongsTo(Employee::class)->withoutGlobalScope(\App\Scopes\NotDeadFileScope::class);
    }

    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getMonthlyDeductionAttribute()
    {
        if ($this->installments == 0) return 0;
        return $this->amount / $this->installments;
    }
}
