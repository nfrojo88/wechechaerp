<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleFuelRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'vehicle_fuel_requests';

    const STATUS_PENDING_FINANCE   = 'pending_finance';
    const STATUS_ASSIGNED          = 'assigned';
    const STATUS_PAID              = 'paid';
    const STATUS_RECEIPT_ATTACHED  = 'receipt_attached';
    const STATUS_CLOSED            = 'closed';
    const STATUS_REJECTED          = 'rejected';

    const FUEL_TYPE_DIESEL  = 'diesel';
    const FUEL_TYPE_BENZINE = 'benzine';

    protected $fillable = [
        'request_no',
        'fixed_asset_unit_id',
        'driver_employee_id',
        'requested_by_user_id',
        'driver_booking_id',
        'project_id',
        'expense_request_id',
        'current_odometer_km',
        'is_km_skipped',
        'fuel_type',
        'liters_requested',
        'price_per_liter',
        'estimated_amount',
        'reason',
        'status',
        'service_due_warning',
        'service_due_km',
        'receipt_photo',
        'receipt_odometer_km',
        'actual_liters',
        'actual_amount',
        'fuel_station_name',
        'fuel_station_tin',
        'receipt_uploaded_at',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'current_odometer_km' => 'integer',
        'is_km_skipped'       => 'boolean',
        'liters_requested'    => 'decimal:2',
        'price_per_liter'     => 'decimal:2',
        'estimated_amount'    => 'decimal:2',
        'service_due_warning' => 'boolean',
        'service_due_km'      => 'integer',
        'receipt_odometer_km' => 'integer',
        'actual_liters'       => 'decimal:2',
        'actual_amount'       => 'decimal:2',
        'receipt_uploaded_at' => 'datetime',
        'closed_at'           => 'datetime',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
        'deleted_at'          => 'datetime',
    ];

    // ── Boot ──────────────────────────────────────────────────────────────────

    protected static function booted()
    {
        static::creating(function (VehicleFuelRequest $model) {
            if (empty($model->request_no)) {
                $year = date('Y');
                $last = static::withTrashed()
                    ->where('request_no', 'like', "VFR-{$year}-%")
                    ->orderBy('id', 'desc')
                    ->first();
                $nextSeq = 1;
                if ($last && preg_match('/VFR-\d{4}-(\d+)/', $last->request_no, $m)) {
                    $nextSeq = ((int) $m[1]) + 1;
                }
                $model->request_no = sprintf("VFR-%s-%04d", $year, $nextSeq);
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function assetUnit(): BelongsTo
    {
        return $this->belongsTo(FixedAssetUnit::class, 'fixed_asset_unit_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'driver_employee_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function driverBooking(): BelongsTo
    {
        return $this->belongsTo(DriverBooking::class, 'driver_booking_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function expenseRequest(): BelongsTo
    {
        return $this->belongsTo(ExpenseRequest::class, 'expense_request_id');
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForVehicle($query, int $unitId)
    {
        return $query->where('fixed_asset_unit_id', $unitId);
    }

    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', [self::STATUS_CLOSED, self::STATUS_REJECTED]);
    }

    public function scopePendingReceipt($query)
    {
        return $query->whereIn('status', [self::STATUS_PAID, 'paid'])
            ->whereNull('receipt_photo');
    }

    // ── Helpers & Badges ──────────────────────────────────────────────────────

    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            self::STATUS_PENDING_FINANCE  => ['class' => 'warning text-dark', 'icon' => 'fa-clock',            'label' => 'Pending Finance Head'],
            self::STATUS_ASSIGNED         => ['class' => 'info text-dark',    'icon' => 'fa-user-gear',        'label' => 'Assigned to Cashier'],
            self::STATUS_PAID             => ['class' => 'primary',           'icon' => 'fa-money-bill-wave',  'label' => 'Paid — Needs Receipt'],
            self::STATUS_RECEIPT_ATTACHED,
            self::STATUS_CLOSED           => ['class' => 'success',           'icon' => 'fa-circle-check',      'label' => 'Closed (Receipt Verified)'],
            self::STATUS_REJECTED         => ['class' => 'danger',            'icon' => 'fa-circle-xmark',      'label' => 'Rejected'],
            default                       => ['class' => 'secondary',         'icon' => 'fa-circle',            'label' => ucfirst(str_replace('_', ' ', $this->status))],
        };
    }

    public function getKmTraveledAttribute(): ?int
    {
        if ($this->receipt_odometer_km && $this->receipt_odometer_km >= $this->current_odometer_km) {
            return $this->receipt_odometer_km - $this->current_odometer_km;
        }
        return null;
    }

    public function getKmPerLiterAttribute(): ?float
    {
        $km = $this->km_traveled;
        $liters = (float) ($this->actual_liters ?: $this->liters_requested);
        if ($km && $liters > 0) {
            return round($km / $liters, 2);
        }
        return null;
    }

    public function getCostPerKmAttribute(): ?float
    {
        $km = $this->km_traveled;
        $cost = (float) ($this->actual_amount ?: $this->estimated_amount);
        if ($km && $km > 0 && $cost > 0) {
            return round($cost / $km, 2);
        }
        return null;
    }

    public function getReceiptPhotoUrlAttribute(): ?string
    {
        if (!$this->receipt_photo) return null;
        return \App\Services\FileUploadService::url($this->receipt_photo);
    }
}
