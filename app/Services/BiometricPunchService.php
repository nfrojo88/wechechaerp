<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\DeviceAttendanceLog;
use App\Models\Employee;
use App\Models\ZkDevice;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BiometricPunchService
{
    /**
     * Get the configured timezone offset hours for biometric devices (e.g. -5).
     * Physical machine clock runs 5h ahead of Ethiopia (e.g. Machine 05:07 PM -> Local 12:07 PM).
     */
    public static function getTimezoneOffsetHours(?string $deviceSn = null): int
    {
        // 1. Device-specific offset if available
        if ($deviceSn) {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('zk_devices') && \Illuminate\Support\Facades\Schema::hasColumn('zk_devices', 'timezone_offset_hours')) {
                    $dev = DB::table('zk_devices')->where('serial_number', $deviceSn)->first();
                    if ($dev && isset($dev->timezone_offset_hours) && $dev->timezone_offset_hours !== null) {
                        return (int)$dev->timezone_offset_hours;
                    }
                }
            } catch (\Throwable $e) {}
        }

        // 2. Global system setting
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('system_settings')) {
                $val = DB::table('system_settings')->where('key', 'biometric_timezone_offset_hours')->value('value');
                if ($val !== null && is_numeric($val)) {
                    return (int)$val;
                }
            }
        } catch (\Throwable $e) {}

        // 3. Fallback to env or default -5 hours
        return (int)env('BIOMETRIC_TIMEZONE_OFFSET_HOURS', -5);
    }

    /**
     * Convert machine punch time string to real local time string.
     * Example: '2026-09-29 17:07:00' -> '2026-09-29 12:07:00' (-5 hours).
     */
    public static function convertMachineTimeToLocal(string $machinePunchTime, ?string $deviceSn = null): string
    {
        $offset = self::getTimezoneOffsetHours($deviceSn);
        if ($offset === 0) {
            return $machinePunchTime;
        }

        try {
            return Carbon::parse($machinePunchTime)->addHours($offset)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return $machinePunchTime;
        }
    }

    /**
     * Official check-in cutoff (08:40 AM).
     */
    public const OFFICIAL_CHECKIN_CUTOFF = '08:40';
    public const OFFICIAL_CHECKIN_CUTOFF_MINUTES = 520; // 8 * 60 + 40

    /**
     * RULE 1: Late calculation (strict)
     * - Official check-in cutoff: 8:40 AM.
     * - Punch at or before 8:40 -> On time, 0 minutes late.
     * - Punch at 8:41 -> 1 minute late.
     * - Punch at 8:42 -> 2 minutes late, and so on.
     * - Formula: late minutes = punch time (in minutes) - 8:40 (in minutes), only if positive. Otherwise 0.
     * - Compare at minute level only, ignoring seconds. 8:40:59 counts as 8:40 (on time). 8:41:00 counts as 1 minute late.
     * - No grace period and no rounding beyond the rule above.
     */
    public static function calculateLateMinutes(?string $punchTime, string $cutoff = self::OFFICIAL_CHECKIN_CUTOFF): int
    {
        if (empty($punchTime)) {
            return 0;
        }

        // Clean string and extract 'H:i' (ignoring seconds)
        $timeStr = trim($punchTime);
        if (strlen($timeStr) >= 19 && str_contains($timeStr, ' ')) {
            $timeStr = substr($timeStr, 11, 5);
        } else {
            $timeStr = substr($timeStr, 0, 5);
        }

        $parts = explode(':', $timeStr);
        if (count($parts) < 2) {
            return 0;
        }

        $punchHours   = (int)$parts[0];
        $punchMinutes = (int)$parts[1];
        $totalPunchMins = ($punchHours * 60) + $punchMinutes;

        // Cutoff calculation at minute level
        $cParts = explode(':', $cutoff);
        $cutoffHours   = isset($cParts[0]) ? (int)$cParts[0] : 8;
        $cutoffMinutes = isset($cParts[1]) ? (int)$cParts[1] : 40;
        $totalCutoffMins = ($cutoffHours * 60) + $cutoffMinutes;

        return max(0, $totalPunchMins - $totalCutoffMins);
    }

    /**
     * Format a late minutes count into user-facing label: 'On time' or 'X min late'.
     */
    public static function formatLateLabel(int $lateMinutes): string
    {
        return $lateMinutes === 0 ? 'On time' : "{$lateMinutes} min late";
    }

    /**
     * Get structured late status array:
     * ['late_minutes' => int, 'is_late' => bool, 'label' => string]
     */
    public static function getLateStatus(?string $punchTime, string $cutoff = self::OFFICIAL_CHECKIN_CUTOFF): array
    {
        $lateMins = self::calculateLateMinutes($punchTime, $cutoff);
        return [
            'late_minutes' => $lateMins,
            'is_late'      => $lateMins > 0,
            'label'        => self::formatLateLabel($lateMins),
        ];
    }

    /**
     * Normalize an array of punch times into standardized 'H:i:s' strings (24h format).
     * Sorts ascending and removes duplicates.
     */
    public static function normalizePunchTimes(array $punchTimes): array
    {
        $times = [];
        foreach ($punchTimes as $pt) {
            if ($pt instanceof \DateTimeInterface) {
                $times[] = $pt->format('H:i:s');
            } elseif (is_string($pt)) {
                $pt = trim($pt);
                if (strlen($pt) >= 19 && str_contains($pt, ' ')) {
                    $times[] = substr($pt, 11, 8);
                } elseif (strlen($pt) >= 5) {
                    $times[] = strlen($pt) === 5 ? $pt . ':00' : substr($pt, 0, 8);
                }
            }
        }
        $times = array_values(array_unique($times));
        sort($times);
        return $times;
    }

    /**
     * RULE 2 (IN Section):
     * If an employee punches more than once in an arrival/check-in window,
     * ALWAYS use the FIRST punch and ignore later ones.
     * Sort punches ascending before picking first.
     * Example: Punches 8:38 and 8:50 -> use 8:38 (On time), ignore 8:50.
     */
    public static function resolveInPunch(array $punches): ?string
    {
        $times = self::normalizePunchTimes($punches);
        if (empty($times)) {
            return null;
        }
        return $times[0];
    }

    /**
     * RULE 2 & Test Case 6 (OUT Section):
     * If an employee punches more than once in a departure/check-out window,
     * use the departure punch.
     * Example (Test Case 6): Two OUT punches 17:00 and 17:05 -> use 17:05.
     */
    public static function resolveOutPunch(array $punches): ?string
    {
        $times = self::normalizePunchTimes($punches);
        if (empty($times)) {
            return null;
        }
        return end($times);
    }

    /**
     * Master shared attendance calculation function.
     * Used everywhere: IN section, OUT section, reports, summaries, and exports.
     * Guarantees identical values across every view.
     */
    public static function calculateAttendanceRecord(array $punchTimes, ?string $date = null, bool $isSaturday = false): array
    {
        $times = self::normalizePunchTimes($punchTimes);

        if (empty($times)) {
            return [
                'check_in'      => null,
                'check_out'     => null,
                'morning_in'    => null,
                'morning_out'   => null,
                'afternoon_in'  => null,
                'afternoon_out' => null,
                'hours_worked'  => 0,
                'late_minutes'  => 0,
                'is_late'       => false,
                'late_label'    => 'On time',
                'status'        => 'absent',
            ];
        }

        $morningIn    = null;
        $morningOut   = null;
        $afternoonIn  = null;
        $afternoonOut = null;

        if ($isSaturday) {
            // Saturday is Morning shift only (08:40 - 12:30, no lunch or afternoon)
            $morningIn  = self::resolveInPunch($times);
            $morningOut = count($times) > 1 ? self::resolveOutPunch($times) : null;
        } else {
            // Official company shift policy:
            // Morning Shift: 08:40 - 12:30 | Lunch: 12:30 - 13:35 | Afternoon: 13:35 - 17:30

            // 1. Morning Arrival Window: punches before lunch (< 12:00:00)
            $morningArrivals = array_values(array_filter($times, fn($t) => $t < '12:00:00'));
            if (!empty($morningArrivals)) {
                // RULE 2: use the FIRST punch (e.g. 8:38 and 8:50 -> 8:38)
                $morningIn = self::resolveInPunch($morningArrivals);
            }

            // 2. Morning Departure (Lunch Out) Window: punches between 12:00:00 and 13:14:59
            $lunchOuts = array_values(array_filter($times, fn($t) => $t >= '12:00:00' && $t < '13:15:00'));
            if (!empty($lunchOuts)) {
                // Resolve OUT punch (e.g. 12:35 and 12:36)
                $morningOut = self::resolveOutPunch($lunchOuts);
            }

            // 3. Afternoon Arrival (Lunch Return) Window: punches between 13:15:00 and 15:29:59
            $lunchReturns = array_values(array_filter($times, fn($t) => $t >= '13:15:00' && $t < '15:30:00'));
            if (!empty($lunchReturns)) {
                // RULE 2: use the FIRST punch (e.g. 13:29 and 13:32 -> use 13:29, ignore 13:32)
                $afternoonIn = self::resolveInPunch($lunchReturns);
            }

            // 4. Afternoon Departure (Day End Out) Window: punches >= 15:30:00
            $dayOuts = array_values(array_filter($times, fn($t) => $t >= '15:30:00'));
            if (!empty($dayOuts)) {
                // Test Case 6: Two OUT punches 17:00 and 17:05 -> use 17:05
                $afternoonOut = self::resolveOutPunch($dayOuts);
            }

            // Fallback for non-standard arrivals (only if no sessions were detected at all)
            if (empty($morningIn) && empty($morningOut) && empty($afternoonIn) && empty($afternoonOut) && !empty($times)) {
                $earliest = self::resolveInPunch($times);
                if ($earliest < '13:15:00') {
                    $morningIn = $earliest;
                } elseif ($earliest < '15:30:00') {
                    $afternoonIn = $earliest;
                } else {
                    // All punches are end-of-day departures (>= 15:30:00)
                    $afternoonOut = self::resolveOutPunch($times);
                }
            }
        }

        // Daily Check-in & Check-out:
        $checkIn = $morningIn ?: $afternoonIn;
        if (empty($checkIn)) {
            $earlyPunches = array_values(array_filter($times, fn($t) => $t < '15:30:00'));
            if (!empty($earlyPunches)) {
                $checkIn = self::resolveInPunch($earlyPunches);
            }
        }

        $checkOut = $afternoonOut;
        if (empty($checkOut)) {
            if ($morningOut && empty($afternoonIn)) {
                $checkOut = $morningOut;
            }
        }
        if ($checkIn && $checkOut && $checkOut <= $checkIn) {
            $checkOut = null;
        }

        // RULE 1: Late calculation strictly against 8:40 AM
        $lateMinutes = self::calculateLateMinutes($morningIn ?: $checkIn);
        $isLate      = $lateMinutes > 0;
        $lateLabel   = self::formatLateLabel($lateMinutes);

        // Hours Worked calculation
        $hoursWorked = 0;
        $datePrefix = $date ?: today()->toDateString();

        if ($morningIn && $morningOut) {
            $inSec  = strtotime("{$datePrefix} {$morningIn}");
            $outSec = strtotime("{$datePrefix} {$morningOut}");
            if ($outSec > $inSec) {
                $hoursWorked += ($outSec - $inSec) / 3600;
            }
        }

        if ($afternoonIn && $afternoonOut) {
            $inSec  = strtotime("{$datePrefix} {$afternoonIn}");
            $outSec = strtotime("{$datePrefix} {$afternoonOut}");
            if ($outSec > $inSec) {
                $hoursWorked += ($outSec - $inSec) / 3600;
            }
        }

        // Cross-session span without separate lunch punches (e.g. 08:38 to 17:05)
        if ($morningIn && $afternoonOut && empty($morningOut) && empty($afternoonIn)) {
            $inSec  = strtotime("{$datePrefix} {$morningIn}");
            $outSec = strtotime("{$datePrefix} {$afternoonOut}");
            if ($outSec > $inSec) {
                $span = ($outSec - $inSec) / 3600;
                $hoursWorked = $span >= 5.0 ? max(0, $span - 1.0) : $span;
            }
        }

        // Fallback for check_in / check_out if session hours are 0
        if ($hoursWorked <= 0 && $checkIn && $checkOut) {
            $inSec  = strtotime("{$datePrefix} {$checkIn}");
            $outSec = strtotime("{$datePrefix} {$checkOut}");
            if ($outSec > $inSec) {
                $span = ($outSec - $inSec) / 3600;
                $hoursWorked = $span >= 5.0 ? max(0, $span - 1.0) : $span;
            }
        }

        $hoursWorked = round($hoursWorked, 1);
        $status = (!empty($checkIn) || $hoursWorked > 0) ? 'present' : 'absent';

        return [
            'check_in'      => $checkIn,
            'check_out'     => $checkOut,
            'morning_in'    => $morningIn,
            'morning_out'   => $morningOut,
            'afternoon_in'  => $afternoonIn,
            'afternoon_out' => $afternoonOut,
            'hours_worked'  => $hoursWorked,
            'late_minutes'  => $lateMinutes,
            'is_late'       => $isLate,
            'late_label'    => $lateLabel,
            'status'        => $status,
        ];
    }

    /**
     * Map punch times to sessions by delegating directly to calculateAttendanceRecord.
     */
    public static function mapPunchesToSessions(array $punchTimes, string $date, bool $isSaturday = false): array
    {
        return self::calculateAttendanceRecord($punchTimes, $date, $isSaturday);
    }

    /**
     * Synchronize a specific employee's attendance record for a given date from raw device punch logs.
     */
    public static function syncEmployeeDatePunches(Employee $employee, string $date, ?string $deviceSn = null): ?Attendance
    {
        $devId = trim((string)($employee->device_user_id ?? ''));
        $code  = trim((string)($employee->employee_code ?? ''));

        // Query all raw punch logs for this employee on this date
        $query = DB::table('device_attendance_logs')
            ->whereDate('punch_time', $date)
            ->whereNotNull('punch_time')
            ->where(function ($q) use ($devId, $code) {
                if ($devId !== '') {
                    $q->where('device_user_id', $devId)
                      ->orWhere('device_user_id', ltrim($devId, '0'));
                }
                if ($code !== '') {
                    $q->orWhere('device_user_id', $code)
                      ->orWhere('device_user_id', str_replace('EMP-', '', $code));
                }
            })
            ->orderBy('punch_time', 'asc');

        if ($deviceSn) {
            $query->where('device_sn', $deviceSn);
        }

        $logs = $query->get();

        $existing = Attendance::where('employee_id', $employee->id)
            ->whereDate('attendance_date', $date)
            ->first();

        // If no device punch logs found, check if existing record already has check_in/check_out
        if ($logs->isEmpty()) {
            if ($existing && ($existing->check_in || $existing->check_out)) {
                $existingTimes = array_filter([$existing->check_in, $existing->check_out]);
                $mapped = self::mapPunchesToSessions($existingTimes, $date, Carbon::parse($date)->isSaturday());

                $updateData = [];
                if (empty($existing->morning_in) && !empty($mapped['morning_in'])) {
                    $updateData['morning_in'] = $mapped['morning_in'];
                }
                if (empty($existing->morning_out) && !empty($mapped['morning_out'])) {
                    $updateData['morning_out'] = $mapped['morning_out'];
                }
                if (empty($existing->afternoon_in) && !empty($mapped['afternoon_in'])) {
                    $updateData['afternoon_in'] = $mapped['afternoon_in'];
                }
                if (empty($existing->afternoon_out) && !empty($mapped['afternoon_out'])) {
                    $updateData['afternoon_out'] = $mapped['afternoon_out'];
                }
                if (($existing->hours_worked === null || $existing->hours_worked == 0) && !empty($mapped['hours_worked'])) {
                    $updateData['hours_worked'] = $mapped['hours_worked'];
                }
                if (isset($mapped['late_minutes'])) {
                    $updateData['late_minutes'] = $mapped['late_minutes'];
                }

                if (!empty($updateData)) {
                    $updateData['status'] = 'present';
                    $existing->update($updateData);
                }
            }
            return $existing;
        }

        // We have raw device punches!
        $punchTimes = $logs->pluck('punch_time')->toArray();
        $isSat = Carbon::parse($date)->isSaturday();
        $mapped = self::mapPunchesToSessions($punchTimes, $date, $isSat);

        // Detect device SN and location/site assignment
        $actualDevSn = $logs->first()->device_sn ?? ($deviceSn ?: 'AF6P230860018');
        $zkDev = ZkDevice::where('serial_number', $actualDevSn)->first();

        $siteProjId   = null;
        $siteProjName = null;
        if ($zkDev && $zkDev->device_type === 'site') {
            $siteProjId   = $zkDev->project_id;
            $siteProjName = $zkDev->project?->name ?? ($zkDev->location ?? 'Site');
        }

        $data = [
            'employee_id'         => $employee->id,
            'attendance_date'     => $date,
            'check_in'            => $mapped['check_in'],
            'check_out'           => $mapped['check_out'],
            'morning_in'          => $mapped['morning_in'],
            'morning_out'         => $mapped['morning_out'],
            'afternoon_in'        => $mapped['afternoon_in'],
            'afternoon_out'       => $mapped['afternoon_out'],
            'hours_worked'        => $mapped['hours_worked'] ?? ($existing?->hours_worked),
            'late_minutes'        => $mapped['late_minutes'] ?? 0,
            'status'              => 'present',
            'source'              => 'biometric',
            'biometric_device_id' => $actualDevSn,
            'site_project_id'     => $siteProjId ?? ($existing?->site_project_id),
            'site_name'           => $siteProjName ?? ($existing?->site_name),
            'is_approved'         => true,
            'updated_at'          => now(),
        ];

        if ($existing) {
            $existing->update($data);
            $record = $existing;
        } else {
            $data['created_at'] = now();
            $record = Attendance::create($data);
        }

        // Mark device logs as synced
        DB::table('device_attendance_logs')
            ->whereDate('punch_time', $date)
            ->where(function ($q) use ($devId, $code) {
                if ($devId !== '') {
                    $q->where('device_user_id', $devId)
                      ->orWhere('device_user_id', ltrim($devId, '0'));
                }
                if ($code !== '') {
                    $q->orWhere('device_user_id', $code);
                }
            })
            ->update(['synced_at' => now()]);

        return $record;
    }

    /**
     * Auto-heal attendance records where punches exist or check_in/check_out exists,
     * but morning/afternoon session fields are null.
     */
    public static function autoHealMissingSessionTimes(?string $date = null): int
    {
        try {
            $query = Attendance::with('employee')
                ->where(function ($q) {
                    $q->whereNull('morning_in')
                      ->orWhereNull('morning_out')
                      ->orWhereNull('afternoon_in')
                      ->orWhereNull('afternoon_out');
                })
                ->where(function ($sq) {
                    $sq->whereNotNull('check_in')
                       ->orWhereNotNull('check_out')
                       ->orWhere('source', 'biometric')
                       ->orWhere('source', 'device');
                });

            if ($date) {
                $query->whereDate('attendance_date', $date);
            } else {
                // Heal current month by default
                $query->whereDate('attendance_date', '>=', now()->subDays(30)->toDateString());
            }

            $records = $query->limit(200)->get();
            $healed = 0;

            foreach ($records as $att) {
                if (!$att->employee) continue;

                $dStr = $att->attendance_date->format('Y-m-d');
                self::syncEmployeeDatePunches($att->employee, $dStr, $att->biometric_device_id);
                $healed++;
            }

            return $healed;
        } catch (\Throwable $e) {
            Log::warning("BiometricPunchService::autoHealMissingSessionTimes failed: " . $e->getMessage());
            return 0;
        }
    }
}
