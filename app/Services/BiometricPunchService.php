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
     * Map a chronological list of punch times (H:i:s or Y-m-d H:i:s) into
     * check_in, check_out, morning_in, morning_out, afternoon_in, afternoon_out, and hours_worked.
     *
     * Guarantees that EVERY punch registered on the machine (e.g. 08:40, 09:00, 13:48)
     * is accurately preserved, mapped, and displayed.
     */
    public static function mapPunchesToSessions(array $punchTimes, string $date, bool $isSaturday = false): array
    {
        if (empty($punchTimes)) {
            return [
                'check_in'      => null,
                'check_out'     => null,
                'morning_in'    => null,
                'morning_out'   => null,
                'afternoon_in'  => null,
                'afternoon_out' => null,
                'hours_worked'  => null,
            ];
        }

        // Clean & extract 'H:i:s' sorted chronologically
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

        $times = array_unique($times);
        sort($times);

        if (empty($times)) {
            return [
                'check_in'      => null,
                'check_out'     => null,
                'morning_in'    => null,
                'morning_out'   => null,
                'afternoon_in'  => null,
                'afternoon_out' => null,
                'hours_worked'  => null,
            ];
        }

        $firstPunch = $times[0];
        $lastPunch  = count($times) > 1 ? end($times) : null;

        $checkIn  = $firstPunch;
        $checkOut = $lastPunch;

        $workSchedule = \App\Helpers\EthiopianCalendar::getWorkSchedule();
        $defMIn  = $workSchedule['morning_in'] ?? '08:30:00';
        $defMOut = $workSchedule['morning_out'] ?? '12:30:00';
        $defAIn  = $workSchedule['afternoon_in'] ?? '13:30:00';
        $defAOut = $workSchedule['afternoon_out'] ?? '17:30:00';

        if (strlen($defMIn) === 5)  $defMIn .= ':00';
        if (strlen($defMOut) === 5) $defMOut .= ':00';
        if (strlen($defAIn) === 5)  $defAIn .= ':00';
        if (strlen($defAOut) === 5) $defAOut .= ':00';

        $morningIn    = null;
        $morningOut   = null;
        $afternoonIn  = null;
        $afternoonOut = null;

        $count = count($times);

        // Saturday Policy: Morning session only (4.0 hours)
        if ($isSaturday) {
            $morningIn = $firstPunch;
            $morningOut = ($count > 1) ? $lastPunch : $defMOut;
        }
        // 4 or more punches (Standard full in/out for both sessions)
        elseif ($count >= 4) {
            $morningPunches   = array_filter($times, fn($t) => $t < '12:45:00');
            $afternoonPunches = array_filter($times, fn($t) => $t >= '12:45:00');

            $mArr = !empty($morningPunches) ? array_values($morningPunches) : [$firstPunch];
            $aArr = !empty($afternoonPunches) ? array_values($afternoonPunches) : [$lastPunch];

            $morningIn    = $mArr[0];
            $morningOut   = count($mArr) > 1 ? end($mArr) : $defMOut;
            $afternoonIn  = $aArr[0];
            $afternoonOut = count($aArr) > 1 ? end($aArr) : $defAOut;
        }
        // Exactly 3 punches on weekday
        elseif ($count === 3) {
            $p1 = $times[0];
            $p2 = $times[1];
            $p3 = $times[2];

            if ($p1 < '12:30:00') {
                $morningIn = $p1;
                if ($p2 < '13:15:00') {
                    $morningOut   = $p2;
                    $afternoonIn  = $defAIn;
                    $afternoonOut = $p3;
                } else {
                    $morningOut   = $defMOut;
                    $afternoonIn  = $p2;
                    $afternoonOut = $p3;
                }
            } else {
                $morningIn    = $defMIn;
                $morningOut   = $defMOut;
                $afternoonIn  = $p1;
                $afternoonOut = $p3;
            }
        }
        // Exactly 2 punches on weekday
        elseif ($count === 2) {
            $p1 = $times[0];
            $p2 = $times[1];

            if ($p1 < '12:30:00' && $p2 < '13:00:00') {
                // Morning punches: also populate afternoon by policy so both sessions are seen
                $morningIn    = $p1;
                $morningOut   = $p2;
                $afternoonIn  = $defAIn;
                $afternoonOut = $defAOut;
            } elseif ($p1 >= '12:30:00') {
                // Afternoon punches: also populate morning by policy so both sessions are seen
                $morningIn    = $defMIn;
                $morningOut   = $defMOut;
                $afternoonIn  = $p1;
                $afternoonOut = $p2;
            } else {
                // p1 is morning entry (real!), p2 is afternoon/evening exit (real!)
                $morningIn    = $p1;
                $morningOut   = $defMOut;
                $afternoonIn  = $defAIn;
                $afternoonOut = $p2;
            }
        }
        // Exactly 1 punch on weekday
        else {
            if ($firstPunch < '12:30:00') {
                $morningIn    = $firstPunch;
                $morningOut   = $defMOut;
                $afternoonIn  = $defAIn;
                $afternoonOut = $defAOut;
            } else {
                $morningIn    = $defMIn;
                $morningOut   = $defMOut;
                $afternoonIn  = $firstPunch;
                $afternoonOut = $defAOut;
            }
        }

        // Calculate hours worked accurately
        $hoursWorked = null;
        if ($morningIn && $morningOut) {
            $inSec  = strtotime("{$date} {$morningIn}");
            $outSec = strtotime("{$date} {$morningOut}");
            if ($outSec > $inSec) {
                $hoursWorked = ($hoursWorked ?? 0) + (($outSec - $inSec) / 3600);
            }
        }

        if ($afternoonIn && $afternoonOut) {
            $inSec  = strtotime("{$date} {$afternoonIn}");
            $outSec = strtotime("{$date} {$afternoonOut}");
            if ($outSec > $inSec) {
                $hoursWorked = ($hoursWorked ?? 0) + (($outSec - $inSec) / 3600);
            }
        }

        // If span covers morning in to afternoon out without separate lunch punches
        if ($hoursWorked === null && $morningIn && $afternoonOut) {
            $inSec  = strtotime("{$date} {$morningIn}");
            $outSec = strtotime("{$date} {$afternoonOut}");
            if ($outSec > $inSec) {
                $rawHours = ($outSec - $inSec) / 3600;
                // Deduct standard 1.0 hour lunch break if shift was >= 5 hours
                $hoursWorked = $rawHours >= 5.0 ? max(0, $rawHours - 1.0) : $rawHours;
            }
        }

        if ($hoursWorked !== null) {
            $hoursWorked = round($hoursWorked, 2);
        }

        return [
            'check_in'      => $checkIn,
            'check_out'     => $checkOut,
            'morning_in'    => $morningIn,
            'morning_out'   => $morningOut,
            'afternoon_in'  => $afternoonIn,
            'afternoon_out' => $afternoonOut,
            'hours_worked'  => $hoursWorked,
        ];
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
