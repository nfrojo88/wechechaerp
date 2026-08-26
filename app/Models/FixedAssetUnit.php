<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedAssetUnit extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fixed_asset_units';

    const STATUS_IN_STORE    = 'in_store';
    const STATUS_ASSIGNED    = 'assigned';
    const STATUS_MAINTENANCE = 'maintenance';
    const STATUS_DISPOSED    = 'disposed';

    protected $fillable = [
        'fixed_asset_id',
        'unit_code',
        'sequence_number',
        'status',
        'condition',
        'brand',
        'model',
        'serial_number',
        'plate_number',
        'chassis_number',
        'engine_number',
        'year',
        'specifications',
        'custom_attributes',
        'assigned_to_employee_id',
        'assigned_date',
        'current_location',
        'purchase_price',
        'warranty_expiry',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'sequence_number'   => 'integer',
        'year'              => 'integer',
        'purchase_price'    => 'decimal:2',
        'assigned_date'     => 'datetime',
        'warranty_expiry'   => 'date',
        'custom_attributes' => 'array',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
        'deleted_at'        => 'datetime',
    ];

    protected static function booted()
    {
        static::saved(function (FixedAssetUnit $unit) {
            $unit->parentAsset?->syncWithCatalogAndInventory();
        });

        static::deleted(function (FixedAssetUnit $unit) {
            $unit->parentAsset?->syncWithCatalogAndInventory();
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function parentAsset()
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function assignedEmployee()
    {
        return $this->belongsTo(Employee::class, 'assigned_to_employee_id');
    }

    public function assignments()
    {
        return $this->hasMany(FixedAssetAssignment::class, 'fixed_asset_unit_id')->latest();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Status Helpers ───────────────────────────────────────────────────────

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_IN_STORE;
    }

    public function isAssigned(): bool
    {
        return $this->status === self::STATUS_ASSIGNED && !empty($this->assigned_to_employee_id);
    }

    /**
     * Assign this unit to an employee.
     */
    public function assignToEmployee(int $employeeId, ?int $assignedByUserId = null, ?string $notes = null): bool
    {
        $this->update([
            'status'                  => self::STATUS_ASSIGNED,
            'assigned_to_employee_id' => $employeeId,
            'assigned_date'           => now(),
            'notes'                   => $notes ? ($this->notes ? $this->notes . "\n" . $notes : $notes) : $this->notes,
        ]);

        FixedAssetAssignment::create([
            'fixed_asset_unit_id'    => $this->id,
            'employee_id'            => $employeeId,
            'action'                 => 'assigned',
            'assigned_date'          => now(),
            'condition_on_assignment'=> $this->condition ?: 'good',
            'assigned_by'            => $assignedByUserId ?: auth()->id(),
            'notes'                  => $notes,
        ]);

        return true;
    }

    /**
     * Return this unit back to store.
     */
    public function returnToStore(?int $receivedByUserId = null, ?string $notes = null, string $condition = 'good'): bool
    {
        $previousEmployeeId = $this->assigned_to_employee_id;

        $this->update([
            'status'                  => self::STATUS_IN_STORE,
            'assigned_to_employee_id' => null,
            'assigned_date'           => null,
            'condition'               => $condition,
            'current_location'        => $this->parentAsset->store->name ?? 'Main Store',
            'notes'                   => $notes ? ($this->notes ? $this->notes . "\n" . $notes : $notes) : $this->notes,
        ]);

        if ($previousEmployeeId) {
            FixedAssetAssignment::create([
                'fixed_asset_unit_id'    => $this->id,
                'employee_id'            => $previousEmployeeId,
                'action'                 => 'returned',
                'assigned_date'          => $this->assigned_date ?: now(),
                'returned_date'          => now(),
                'condition_on_return'    => $condition,
                'received_by'            => $receivedByUserId ?: auth()->id(),
                'notes'                  => $notes,
            ]);
        }

        return true;
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getDisplayTitleAttribute(): string
    {
        $name = $this->parentAsset->name ?? 'Asset';
        $spec = '';

        if ($this->plate_number) {
            $spec = " (Plate: {$this->plate_number})";
        } elseif ($this->serial_number) {
            $spec = " (SN: {$this->serial_number})";
        } elseif ($this->brand || $this->model) {
            $spec = " (" . trim("{$this->brand} {$this->model}") . ")";
        }

        return "{$this->unit_code} - {$name}{$spec}";
    }

    public function getStatusBadgeAttribute(): array
    {
        return match($this->status) {
            self::STATUS_IN_STORE    => ['class' => 'bg-success', 'label' => 'In Store (Available)', 'icon' => 'fa-warehouse'],
            self::STATUS_ASSIGNED    => ['class' => 'bg-primary', 'label' => 'Assigned', 'icon' => 'fa-user-check'],
            self::STATUS_MAINTENANCE => ['class' => 'bg-warning text-dark', 'label' => 'Under Maintenance', 'icon' => 'fa-wrench'],
            self::STATUS_DISPOSED    => ['class' => 'bg-danger', 'label' => 'Disposed / Retired', 'icon' => 'fa-trash'],
            default                  => ['class' => 'bg-secondary', 'label' => ucfirst($this->status), 'icon' => 'fa-circle-info'],
        };
    }

    public function getConditionBadgeAttribute(): array
    {
        return match(strtolower($this->condition)) {
            'new'          => ['class' => 'bg-info', 'label' => 'Brand New'],
            'good'         => ['class' => 'bg-success', 'label' => 'Good Condition'],
            'fair'         => ['class' => 'bg-warning text-dark', 'label' => 'Fair Condition'],
            'needs_repair' => ['class' => 'bg-warning text-dark', 'label' => 'Needs Repair'],
            'damaged'      => ['class' => 'bg-danger', 'label' => 'Damaged'],
            default        => ['class' => 'bg-light text-dark border', 'label' => ucfirst($this->condition)],
        };
    }
}
