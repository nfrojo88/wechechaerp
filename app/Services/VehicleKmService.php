<?php

namespace App\Services;

use App\Models\FixedAssetUnit;
use App\Models\VehicleKmLog;
use App\Models\VehicleReminder;
use App\Models\VehicleReminderHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VehicleKmService
{
    /**
     * Threshold (in KM) above which a jump is considered unusually large and flagged.
     */
    const LARGE_JUMP_THRESHOLD_KM = 1000;

    /**
     * Get the last recorded odometer reading for a vehicle.
     */
    public function getLastOdometer(int $unitId): int
    {
        // 1. Check latest KM log
        $lastLog = VehicleKmLog::where('fixed_asset_unit_id', $unitId)
            ->latestFirst()
            ->first();

        if ($lastLog) {
            return (int) $lastLog->odometer_km;
        }

        // 2. Fallback to existing VehicleReminder current_odometer_km
        $reminder = VehicleReminder::where('fixed_asset_unit_id', $unitId)
            ->where('reminder_type', VehicleReminder::TYPE_SERVICE_KM)
            ->latest('id')
            ->first();

        if ($reminder && $reminder->current_odometer_km) {
            return (int) $reminder->current_odometer_km;
        }

        return 0;
    }

    /**
     * Record a new KM log and synchronize vehicle reminders.
     *
     * @param int $unitId
     * @param int $newOdometerKm
     * @param string $kmDate
     * @param int|null $driverEmployeeId
     * @param int|null $loggedByUserId
     * @param string|null $photoPath
     * @param string $source
     * @param string|null $notes
     * @return VehicleKmLog
     * @throws ValidationException
     */
    public function recordKm(
        int $unitId,
        int $newOdometerKm,
        string $kmDate,
        ?int $driverEmployeeId = null,
        ?int $loggedByUserId = null,
        ?string $photoPath = null,
        string $source = VehicleKmLog::SOURCE_DIRECT_LOG,
        ?string $notes = null
    ): VehicleKmLog {
        $lastOdometer = $this->getLastOdometer($unitId);

        // Rule: New KM cannot be lower than the last recorded KM
        if ($lastOdometer > 0 && $newOdometerKm < $lastOdometer) {
            throw ValidationException::withMessages([
                'odometer_km' => ["The new odometer reading ({$newOdometerKm} KM) cannot be lower than the last recorded reading ({$lastOdometer} KM)."],
            ]);
        }

        $kmDiff = $lastOdometer > 0 ? ($newOdometerKm - $lastOdometer) : 0;
        $isLargeJump = $kmDiff >= self::LARGE_JUMP_THRESHOLD_KM;

        $userId = $loggedByUserId ?: Auth::id();

        return DB::transaction(function () use (
            $unitId,
            $newOdometerKm,
            $kmDate,
            $driverEmployeeId,
            $userId,
            $photoPath,
            $source,
            $kmDiff,
            $isLargeJump,
            $notes,
            $lastOdometer
        ) {
            // 1. Create KM log record
            $log = VehicleKmLog::create([
                'fixed_asset_unit_id' => $unitId,
                'driver_employee_id'  => $driverEmployeeId,
                'logged_by_user_id'   => $userId,
                'odometer_km'         => $newOdometerKm,
                'km_date'             => $kmDate,
                'odometer_photo'      => $photoPath,
                'source'              => $source,
                'km_difference'       => $kmDiff,
                'is_large_jump'       => $isLargeJump,
                'notes'               => $notes,
            ]);

            // 2. Synchronize existing VehicleReminder records without altering their core logic
            $reminders = VehicleReminder::where('fixed_asset_unit_id', $unitId)
                ->where('reminder_type', VehicleReminder::TYPE_SERVICE_KM)
                ->get();

            foreach ($reminders as $reminder) {
                $oldKm = $reminder->current_odometer_km;

                // Log history snapshot
                VehicleReminderHistory::create([
                    'vehicle_reminder_id' => $reminder->id,
                    'fixed_asset_unit_id' => $unitId,
                    'reminder_type'       => VehicleReminder::TYPE_SERVICE_KM,
                    'action'              => 'km_log_updated',
                    'snapshot'            => [
                        'old_odometer' => $oldKm,
                        'new_odometer' => $newOdometerKm,
                        'km_log_id'    => $log->id,
                        'source'       => $source,
                    ],
                    'notes'               => "KM updated to {$newOdometerKm} via {$source} (Log #{$log->id})" . ($notes ? ": {$notes}" : ""),
                    'performed_by'        => $userId,
                    'performed_at'        => now(),
                ]);

                // Update reminder odometer and compute new status
                $reminder->current_odometer_km = $newOdometerKm;
                $reminder->status = $reminder->computeStatus();
                $reminder->updated_by = $userId;
                $reminder->save();
            }

            return $log;
        });
    }

    /**
     * Get real-time service reminder status for a vehicle unit.
     */
    public function getServiceStatus(int $unitId): array
    {
        $reminder = VehicleReminder::where('fixed_asset_unit_id', $unitId)
            ->where('reminder_type', VehicleReminder::TYPE_SERVICE_KM)
            ->latest('id')
            ->first();

        if (!$reminder) {
            return [
                'has_service_reminder' => false,
                'next_service_km'      => null,
                'current_odometer_km'  => $this->getLastOdometer($unitId),
                'remaining_km'         => null,
                'is_due_soon'          => false,
                'is_overdue'           => false,
                'badge'                => ['class' => 'secondary', 'label' => 'No Service Plan', 'icon' => 'fa-circle-minus'],
            ];
        }

        $currentKm = $reminder->current_odometer_km ?: $this->getLastOdometer($unitId);
        $nextKm = $reminder->next_service_km;
        $remaining = $nextKm ? ($nextKm - $currentKm) : null;
        $threshold = $reminder->reminder_threshold_km ?? 500;

        $isOverdue = $remaining !== null && $remaining <= 0;
        $isDueSoon = $remaining !== null && !$isOverdue && $remaining <= $threshold;

        $badge = $isOverdue
            ? ['class' => 'danger', 'label' => 'Service Overdue (' . abs($remaining) . ' KM past)', 'icon' => 'fa-gauge-high']
            : ($isDueSoon
                ? ['class' => 'warning', 'label' => 'Service Due Soon (' . $remaining . ' KM remaining)', 'icon' => 'fa-triangle-exclamation']
                : ['class' => 'success', 'label' => 'Service Healthy (' . ($remaining !== null ? $remaining . ' KM left' : 'OK') . ')', 'icon' => 'fa-circle-check']);

        return [
            'has_service_reminder' => true,
            'next_service_km'      => $nextKm,
            'current_odometer_km'  => $currentKm,
            'remaining_km'         => $remaining,
            'threshold_km'         => $threshold,
            'is_due_soon'          => $isDueSoon,
            'is_overdue'           => $isOverdue,
            'badge'                => $badge,
        ];
    }
}
