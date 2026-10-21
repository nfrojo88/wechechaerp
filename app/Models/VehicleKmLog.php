<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleKmLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'vehicle_km_logs';

    const SOURCE_DIRECT_LOG   = 'direct_log';
    const SOURCE_FUEL_REQUEST = 'fuel_request';
    const SOURCE_MAINTENANCE  = 'maintenance';
    const SOURCE_INITIAL      = 'initial';

    protected $fillable = [
        'fixed_asset_unit_id',
        'driver_employee_id',
        'logged_by_user_id',
        'odometer_km',
        'km_date',
        'odometer_photo',
        'source',
        'km_difference',
        'is_large_jump',
        'notes',
    ];

    protected $casts = [
        'odometer_km'   => 'integer',
        'km_date'       => 'date',
        'km_difference' => 'integer',
        'is_large_jump' => 'boolean',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'deleted_at'    => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function assetUnit(): BelongsTo
    {
        return $this->belongsTo(FixedAssetUnit::class, 'fixed_asset_unit_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'driver_employee_id');
    }

    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by_user_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForVehicle($query, int $unitId)
    {
        return $query->where('fixed_asset_unit_id', $unitId);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderBy('km_date', 'desc')->orderBy('id', 'desc');
    }

    // ── Accessors & Helpers ───────────────────────────────────────────────────

    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->odometer_photo) return null;
        return \App\Services\FileUploadService::url($this->odometer_photo);
    }
}
