<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleReminder extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'vehicle_reminders';

    // ── Reminder types ────────────────────────────────────────────────────────
    const TYPE_BOLO               = 'bolo';
    const TYPE_SERVICE_KM         = 'service_km';
    const TYPE_THIRD_PARTY_INS    = 'third_party_insurance';
    const TYPE_INSURANCE          = 'insurance';

    const TYPES = [
        self::TYPE_BOLO            => 'Bolo (Annual Inspection)',
        self::TYPE_SERVICE_KM      => 'Service by KM',
        self::TYPE_THIRD_PARTY_INS => 'Third-Party Insurance',
        self::TYPE_INSURANCE       => 'Comprehensive Insurance',
    ];

    // ── Statuses ──────────────────────────────────────────────────────────────
    const STATUS_ACTIVE   = 'active';
    const STATUS_DUE_SOON = 'due_soon';
    const STATUS_EXPIRED  = 'expired';
    const STATUS_OVERDUE  = 'overdue';
    const STATUS_RENEWED  = 'renewed';
    const STATUS_SERVICED = 'serviced';

    protected $fillable = [
        'fixed_asset_unit_id',
        'fixed_asset_id',
        'reminder_type',
        'status',
        // Bolo
        'bolo_last_date',
        'bolo_expiry_date',
        // Service KM
        'current_odometer_km',
        'last_service_km',
        'service_interval_km',
        'next_service_km',
        'last_service_date',
        'reminder_threshold_km',
        // Insurance
        'insurance_company',
        'policy_number',
        'insurance_start_date',
        'insurance_expiry_date',
        'premium_amount',
        'coverage_type',
        // Shared
        'attachment',
        'notes',
        'alert_days_before',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'bolo_last_date'        => 'date',
        'bolo_expiry_date'      => 'date',
        'last_service_date'     => 'date',
        'insurance_start_date'  => 'date',
        'insurance_expiry_date' => 'date',
        'premium_amount'        => 'decimal:2',
        'alert_days_before'     => 'array',
        'current_odometer_km'   => 'integer',
        'last_service_km'       => 'integer',
        'service_interval_km'   => 'integer',
        'next_service_km'       => 'integer',
        'reminder_threshold_km' => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function assetUnit()
    {
        return $this->belongsTo(FixedAssetUnit::class, 'fixed_asset_unit_id');
    }

    public function fixedAsset()
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function history()
    {
        return $this->hasMany(VehicleReminderHistory::class, 'vehicle_reminder_id')->latest('performed_at');
    }

    public function notifications()
    {
        return $this->hasMany(VehicleReminderNotification::class, 'vehicle_reminder_id');
    }

    // ── Computed status helpers ───────────────────────────────────────────────

    /**
     * Returns computed status badge data: color + label
     */
    public function getComputedStatusAttribute(): array
    {
        return match ($this->computeStatus()) {
            self::STATUS_EXPIRED  => ['class' => 'danger',  'label' => 'Expired / Overdue', 'icon' => 'fa-circle-xmark'],
            self::STATUS_OVERDUE  => ['class' => 'danger',  'label' => 'Overdue (KM)',       'icon' => 'fa-gauge-high'],
            self::STATUS_DUE_SOON => ['class' => 'warning', 'label' => 'Due Soon',           'icon' => 'fa-triangle-exclamation'],
            self::STATUS_RENEWED  => ['class' => 'info',    'label' => 'Renewed',            'icon' => 'fa-rotate'],
            self::STATUS_SERVICED => ['class' => 'info',    'label' => 'Serviced',           'icon' => 'fa-check-circle'],
            default               => ['class' => 'success', 'label' => 'OK',                 'icon' => 'fa-circle-check'],
        };
    }

    public function computeStatus(): string
    {
        if (in_array($this->status, [self::STATUS_RENEWED, self::STATUS_SERVICED])) {
            return $this->status;
        }

        if ($this->reminder_type === self::TYPE_SERVICE_KM) {
            return $this->computeKmStatus();
        }

        // Date-based
        $expiry = $this->getExpiryDate();
        if (!$expiry) return self::STATUS_ACTIVE;

        $today = Carbon::today();
        if ($expiry->lt($today)) {
            return self::STATUS_EXPIRED;
        }
        if ($expiry->lte($today->copy()->addDays(30))) {
            return self::STATUS_DUE_SOON;
        }

        return self::STATUS_ACTIVE;
    }

    private function computeKmStatus(): string
    {
        if (!$this->next_service_km || !$this->current_odometer_km) return self::STATUS_ACTIVE;

        $remaining = $this->next_service_km - $this->current_odometer_km;
        $threshold = $this->reminder_threshold_km ?? 500;

        if ($remaining <= 0)          return self::STATUS_OVERDUE;
        if ($remaining <= $threshold) return self::STATUS_DUE_SOON;

        return self::STATUS_ACTIVE;
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        $expiry = $this->getExpiryDate();
        if (!$expiry) return null;
        return Carbon::today()->diffInDays($expiry, false);
    }

    public function getKmRemainingAttribute(): ?int
    {
        if ($this->reminder_type !== self::TYPE_SERVICE_KM) return null;
        if (!$this->next_service_km || !$this->current_odometer_km) return null;
        return $this->next_service_km - $this->current_odometer_km;
    }

    public function getExpiryDate(): ?Carbon
    {
        return match ($this->reminder_type) {
            self::TYPE_BOLO            => $this->bolo_expiry_date,
            self::TYPE_THIRD_PARTY_INS,
            self::TYPE_INSURANCE       => $this->insurance_expiry_date,
            default                    => null,
        };
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getReminderTypeLabelAttribute(): string
    {
        return self::TYPES[$this->reminder_type] ?? ucfirst(str_replace('_', ' ', $this->reminder_type));
    }

    public function getVehicleDisplayNameAttribute(): string
    {
        $unit = $this->assetUnit;
        $asset = $this->fixedAsset;
        $name = $asset?->name ?? 'Unknown Vehicle';
        $plate = $unit?->plate_number ?? null;
        $code = $unit?->unit_code ?? null;
        $model = trim(($unit?->brand ?? '') . ' ' . ($unit?->model ?? ''));

        $parts = array_filter([$code, $plate ? "Plate: $plate" : null]);
        return $name . ($parts ? ' (' . implode(' | ', $parts) . ')' : '');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($q)
    {
        return $q->whereNotIn('status', [self::STATUS_RENEWED, self::STATUS_SERVICED]);
    }

    public function scopeForVehicles($q)
    {
        return $q->whereHas('fixedAsset', fn($q) =>
            $q->whereIn('category', ['Vehicles', 'Vehicle', 'Cars', 'Car', 'Vehicles/Cars'])
        );
    }

    public function scopeExpiringSoon($q, int $days = 30)
    {
        $today = Carbon::today();
        return $q->whereIn('reminder_type', [self::TYPE_BOLO, self::TYPE_THIRD_PARTY_INS, self::TYPE_INSURANCE])
            ->where(function ($q) use ($today, $days) {
                $q->whereBetween('bolo_expiry_date', [$today, $today->copy()->addDays($days)])
                  ->orWhereBetween('insurance_expiry_date', [$today, $today->copy()->addDays($days)]);
            });
    }

    public function scopeExpired($q)
    {
        $today = Carbon::today();
        return $q->where(function ($q) use ($today) {
            $q->where(function ($q) use ($today) {
                $q->whereIn('reminder_type', [self::TYPE_BOLO])->where('bolo_expiry_date', '<', $today);
            })->orWhere(function ($q) use ($today) {
                $q->whereIn('reminder_type', [self::TYPE_THIRD_PARTY_INS, self::TYPE_INSURANCE])
                  ->where('insurance_expiry_date', '<', $today);
            });
        });
    }
}
