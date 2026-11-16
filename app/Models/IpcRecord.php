<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IpcRecord extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
    ];

    public function agreement() { return $this->belongsTo(SubconAgreement::class, 'agreement_id'); }
    public function project() { return $this->belongsTo(Project::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function approvedBy() { return $this->belongsTo(User::class, 'approved_by'); }
    public function items() { return $this->hasMany(IpcItem::class, 'ipc_record_id'); }

    /**
     * Get net payable / certified amount dynamically across schema variations.
     */
    public function getNetPayableAttribute(): float
    {
        return (float) (
            $this->attributes['net_payable']
            ?? $this->attributes['net_amount']
            ?? $this->attributes['paid_amount']
            ?? $this->attributes['current_certified']
            ?? $this->attributes['current_work_value']
            ?? 0.0
        );
    }
}
