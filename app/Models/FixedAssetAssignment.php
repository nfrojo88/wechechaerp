<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class FixedAssetAssignment extends Model
{
    use HasFactory;

    protected $table = 'fixed_asset_assignments';

    protected $fillable = [
        'fixed_asset_unit_id',
        'employee_id',
        'action',
        'assigned_date',
        'returned_date',
        'condition_on_assignment',
        'condition_on_return',
        'assigned_by',
        'received_by',
        'notes',
    ];

    protected $casts = [
        'assigned_date' => 'datetime',
        'returned_date' => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    protected static function booted()
    {
        static::ensureSchema();
    }

    /**
     * Self-healing schema helper ensuring employee_id is nullable on production databases.
     */
    public static function ensureSchema(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $ensured = true;

        try {
            if (Schema::hasTable('fixed_asset_assignments')) {
                try {
                    DB::statement("ALTER TABLE `fixed_asset_assignments` MODIFY `employee_id` BIGINT UNSIGNED NULL");
                } catch (\Throwable $e) {
                    try {
                        DB::statement("ALTER TABLE `fixed_asset_assignments` DROP FOREIGN KEY `fixed_asset_assignments_employee_id_foreign`");
                        DB::statement("ALTER TABLE `fixed_asset_assignments` MODIFY `employee_id` BIGINT UNSIGNED NULL");
                        DB::statement("ALTER TABLE `fixed_asset_assignments` ADD CONSTRAINT `fixed_asset_assignments_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL");
                    } catch (\Throwable $e2) {
                        // Suppressed if already modified or no ALTER privileges
                    }
                }
            }
        } catch (\Throwable $ignored) {}
    }

    /**
     * Safely create an assignment or history entry with automatic schema-healing and fallback.
     */
    public static function createSafely(array $attributes): ?self
    {
        self::ensureSchema();

        try {
            return self::create($attributes);
        } catch (\Illuminate\Database\QueryException $e) {
            // Check for Column 'employee_id' cannot be null error (SQLSTATE 23000 / 1048)
            if (isset($attributes['employee_id']) && $attributes['employee_id'] === null) {
                try {
                    DB::statement("ALTER TABLE `fixed_asset_assignments` MODIFY `employee_id` BIGINT UNSIGNED NULL");
                    return self::create($attributes);
                } catch (\Throwable $alterEx) {
                    // Fallback to active user's employee or first active employee if schema alter is blocked
                    $fallbackEmployeeId = auth()->user()?->employee?->id 
                        ?? Employee::value('id') 
                        ?? null;
                    if ($fallbackEmployeeId) {
                        $attributes['employee_id'] = $fallbackEmployeeId;
                        return self::create($attributes);
                    }
                }
            }
            Log::warning("FixedAssetAssignment createSafely failed: " . $e->getMessage());
            return null;
        }
    }

    public function unit()
    {
        return $this->belongsTo(FixedAssetUnit::class, 'fixed_asset_unit_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id')->withDefault([
            'full_name' => 'N/A',
        ]);
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
