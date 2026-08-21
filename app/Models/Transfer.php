<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\ScopesByStore;

class Transfer extends Model
{
    use SoftDeletes, ScopesByStore;

    protected $fillable = [
        'transfer_no', 'physical_slip_no', 'from_store_id', 'to_store_id', 'requested_by',
        'required_date', 'reason', 'status', 'approved_by', 'approved_at',
        'received_by', 'received_at', 'rejection_reason',
    ];

    protected $casts = [
        'required_date' => 'date',
        'approved_at'   => 'datetime',
        'received_at'   => 'datetime',
    ];

    public function fromStore()
    {
        return $this->belongsTo(Store::class, 'from_store_id');
    }

    public function toStore()
    {
        return $this->belongsTo(Store::class, 'to_store_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items()
    {
        return $this->hasMany(TransferItem::class);
    }
}
