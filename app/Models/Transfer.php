<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\ScopesByStore;

class Transfer extends Model
{
    use SoftDeletes, ScopesByStore;

    const STATUS_DRAFT            = 'draft';
    const STATUS_PENDING_APPROVAL = 'pending_approval';
    const STATUS_APPROVED         = 'approved';
    const STATUS_IN_TRANSIT       = 'in_transit';
    const STATUS_COMPLETED        = 'completed';
    const STATUS_REJECTED         = 'rejected';
    const STATUS_CANCELLED        = 'cancelled';

    protected $fillable = [
        'transfer_no', 'physical_slip_no', 'from_store_id', 'to_store_id', 'requested_by',
        'required_date', 'reason', 'status', 'approved_by', 'approved_at',
        'received_by', 'received_at', 'rejection_reason',
        'driver_employee_id', 'vehicle_plate_no', 'dispatch_notes',
        'dispatched_by', 'dispatched_at', 'outgoing_slip_file', 'outgoing_slip_no',
        'receiving_slip_file', 'receiving_slip_no', 'receiving_notes',
        'material_request_id',
    ];

    protected $casts = [
        'required_date' => 'date',
        'approved_at'   => 'datetime',
        'received_at'   => 'datetime',
        'dispatched_at' => 'datetime',
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

    public function dispatchedBy()
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function driver()
    {
        return $this->belongsTo(Employee::class, 'driver_employee_id');
    }

    public function items()
    {
        return $this->hasMany(TransferItem::class);
    }

    public function getOutgoingSlipUrlAttribute(): ?string
    {
        if (empty($this->outgoing_slip_file)) {
            return null;
        }
        if (str_starts_with($this->outgoing_slip_file, 'http://') || str_starts_with($this->outgoing_slip_file, 'https://')) {
            return $this->outgoing_slip_file;
        }
        return asset('storage/' . $this->outgoing_slip_file);
    }

    public function getReceivingSlipUrlAttribute(): ?string
    {
        if (empty($this->receiving_slip_file)) {
            return null;
        }
        if (str_starts_with($this->receiving_slip_file, 'http://') || str_starts_with($this->receiving_slip_file, 'https://')) {
            return $this->receiving_slip_file;
        }
        return asset('storage/' . $this->receiving_slip_file);
    }
}

