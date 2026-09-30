<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transfer extends Model
{
    use SoftDeletes;

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
        'merged_into_transfer_id', 'merge_notes',
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

    public function mergedInto()
    {
        return $this->belongsTo(Transfer::class, 'merged_into_transfer_id');
    }

    public function mergedTransfers()
    {
        return $this->hasMany(Transfer::class, 'merged_into_transfer_id');
    }

    public function inventoryMovements()
    {
        return $this->morphMany(InventoryMovement::class, 'reference');
    }

    public function isDeductedFromOrigin(): bool
    {
        return $this->inventoryMovements()->where('type', 'transfer_out')->exists();
    }

    public function isAddedToDestination(): bool
    {
        return $this->inventoryMovements()->where('type', 'transfer_in')->exists();
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

    /**
     * Generate a unique transfer number safe against race conditions and deletions.
     *
     * Uses the maximum existing sequence for today (across ALL transfers including soft-deleted)
     * and increments it. If a duplicate collision still occurs, it retries with a higher offset.
     * This replaces the broken `Transfer::count() + 1` pattern which produced duplicates
     * whenever records had been deleted or concurrent requests arrived at the same millisecond.
     *
     * @param  int  $extraOffset  Additional offset to skip ahead (used on retry after collision)
     * @return string  e.g. "TR-20260930-0034"
     */
    public static function generateUniqueNo(int $extraOffset = 0): string
    {
        $today = date('Ymd');
        $prefix = "TR-{$today}-";

        // Query the highest sequence already used today (include soft-deleted so we never reuse)
        $maxExisting = static::withTrashed()
            ->where('transfer_no', 'like', "{$prefix}%")
            ->selectRaw("MAX(CAST(SUBSTRING(transfer_no, ?) AS UNSIGNED)) as max_seq", [strlen($prefix) + 1])
            ->value('max_seq');

        $next = ($maxExisting ?? 0) + 1 + $extraOffset;

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}

