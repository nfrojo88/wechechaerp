<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodClosingChecklist extends Model
{
    protected $table = 'period_closing_checklists';

    protected $fillable = [
        'financial_period_id',
        'item_key',
        'item_name',
        'status',
        'verified_at',
        'verified_by',
        'details',
        'waiver_reason',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'details'     => 'array',
    ];

    public function financialPeriod()
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function verifiedByUser()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
