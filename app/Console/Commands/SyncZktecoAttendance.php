<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\DeviceAttendanceLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncZktecoAttendance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Usage:
     *   php artisan zkteco:sync               ← syncs today
     *   php artisan zkteco:sync --date=2026-07-20
     *   php artisan zkteco:sync --from=2026-07-01 --to=2026-07-20
     *   php artisan zkteco:sync --date=2026-07-20 --force    ← re-sync already synced
     */
    protected $signature = 'zkteco:sync
                            {--date= : Date to sync in Y-m-d format (default: today)}
                            {--from= : Start date to sync in Y-m-d format}
                            {--to= : End date to sync in Y-m-d format}
                            {--location= : Filter by location type (head_office or site)}
                            {--project= : Filter by Project ID for site devices}
                            {--device-sn= : Filter by specific Device Serial Number}
                            {--force : Re-sync records that were already synced}
                            {--all : Sync ALL dates (use carefully)}';

    protected $description = 'Sync ZKTeco raw punch logs → HR Attendance table';

    public function handle(): int
    {
        $forceResync  = $this->option('force');
        $syncAll      = $this->option('all');
        $from         = $this->option('from');
        $to           = $this->option('to');
        $locationType = $this->option('location');
        $projectId    = $this->option('project');
        $deviceSn     = $this->option('device-sn');

        if ($syncAll) {
            $dates = DB::table('device_attendance_logs')
                ->selectRaw('DATE(punch_time) as d')
                ->whereNotNull('punch_time')
                ->groupByRaw('DATE(punch_time)')
                ->orderByRaw('DATE(punch_time) ASC')
                ->pluck('d')
                ->toArray();

            if (empty($dates)) {
                $this->warn('No punch records found in device_attendance_logs.');
                return self::SUCCESS;
            }

            $this->info("Syncing all " . count($dates) . " available date(s) with punches...");
            foreach ($dates as $date) {
                $this->syncDate($date, $forceResync, $locationType, $projectId ? (int)$projectId : null, $deviceSn);
            }
        } elseif ($from || $to) {
            try {
                $startDate = $from ? Carbon::parse($from) : ($to ? Carbon::parse($to) : now());
                $endDate   = $to ? Carbon::parse($to) : ($from ? Carbon::parse($from) : now());
            } catch (\Throwable $e) {
                $this->error("Invalid date format in --from or --to: " . $e->getMessage());
                return self::FAILURE;
            }

            if ($startDate->gt($endDate)) {
                $temp = $startDate;
                $startDate = $endDate;
                $endDate = $temp;
            }

            // Only query dates in that range that ACTUALLY have punch records
            $dates = DB::table('device_attendance_logs')
                ->selectRaw('DATE(punch_time) as d')
                ->whereNotNull('punch_time')
                ->whereDate('punch_time', '>=', $startDate->format('Y-m-d'))
                ->whereDate('punch_time', '<=', $endDate->format('Y-m-d'))
                ->groupByRaw('DATE(punch_time)')
                ->orderByRaw('DATE(punch_time) ASC')
                ->pluck('d')
                ->toArray();

            if (empty($dates)) {
                $totalInDb = DB::table('device_attendance_logs')->count();
                $minDate = DB::table('device_attendance_logs')->min('punch_time');
                $maxDate = DB::table('device_attendance_logs')->max('punch_time');

                if ($totalInDb === 0) {
                    $this->warn("⚠️ No biometric punches exist in the database at all. The attendance device has not pushed any punch logs to the server yet.");
                } else {
                    $minFmt = Carbon::parse($minDate)->format('M d, Y');
                    $maxFmt = Carbon::parse($maxDate)->format('M d, Y');
                    $this->warn("⚠️ No punch records found between {$startDate->format('Y-m-d')} and {$endDate->format('Y-m-d')}. Database has {$totalInDb} punches from {$minFmt} to {$maxFmt}. Please sync that date range instead or use 'Sync All'.");
                }
                return self::SUCCESS;
            }

            $this->info("Found " . count($dates) . " date(s) with punches in selected range ({$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}). Syncing...");
            foreach ($dates as $date) {
                $this->syncDate($date, $forceResync, $locationType, $projectId ? (int)$projectId : null, $deviceSn);
            }
        } else {
            $date = $this->option('date') ?? now()->format('Y-m-d');

            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $this->error("Invalid date format. Use Y-m-d e.g. 2026-07-20");
                return self::FAILURE;
            }

            $this->syncDate($date, $forceResync, $locationType, $projectId ? (int)$projectId : null, $deviceSn);
        }

        return self::SUCCESS;
    }

    /**
     * Sync all punch records for a given date into the attendance table.
     */
    private function syncDate(string $date, bool $force, ?string $locationType = null, ?int $projectId = null, ?string $deviceSn = null): void
    {
        $this->info("📅 Syncing attendance for: {$date}");

        $query = DB::table('device_attendance_logs')
            ->whereDate('punch_time', $date)
            ->whereNotNull('punch_time');

        if ($deviceSn) {
            $query->where('device_sn', $deviceSn);
        }

        $rawPunches = $query->get();

        // Get devices & projects mapping
        $devices = DB::table('zk_devices')->get()->keyBy('serial_number');
        $projects = DB::table('projects')->get()->keyBy('id');

        // Apply location / project filtering if requested
        if ($locationType || $projectId) {
            $rawPunches = $rawPunches->filter(function ($punch) use ($devices, $locationType, $projectId) {
                $dev = $devices[$punch->device_sn] ?? null;
                $devType = $dev ? ($dev->device_type ?? 'head_office') : 'head_office';
                if ($locationType && $devType !== $locationType) {
                    return false;
                }
                if ($projectId && ($dev->project_id ?? null) != $projectId) {
                    return false;
                }
                return true;
            });
        }

        if ($rawPunches->isEmpty()) {
            $this->line("  → No raw punch records in database for {$date}.");
            return;
        }

        // Cache all active employees for resilient matching (strictly exclude Dead File)
        $employees = DB::table('employees')
            ->where(function ($q) {
                $q->where('is_dead_file', false)->orWhereNull('is_dead_file');
            })
            ->where('status', '!=', 'dead_file')
            ->get();

        $groupedByUser = $rawPunches->groupBy('device_user_id');

        $synced  = 0;
        $updated = 0;
        $skipped = 0;
        $unlinkedUsers = [];

        foreach ($groupedByUser as $deviceUserId => $punches) {
            $cleanId     = trim((string)$deviceUserId);
            $cleanNoZero = ltrim($cleanId, '0');

            // Match employee: by device_user_id, or employee_code, or full_name
            $employee = $employees->first(function ($emp) use ($cleanId, $cleanNoZero, $punches) {
                $empDeviceId = trim((string)($emp->device_user_id ?? ''));
                $empCode     = trim((string)($emp->employee_code ?? ''));
                $empName     = strtolower(trim((string)($emp->full_name ?? '')));

                if (!empty($empDeviceId)) {
                    if ($empDeviceId === $cleanId || ltrim($empDeviceId, '0') === $cleanNoZero) {
                        return true;
                    }
                }

                if (!empty($empCode)) {
                    if ($empCode === $cleanId || $empCode === 'EMP-' . $cleanId || $empCode === 'EMP-' . str_pad($cleanId, 3, '0', STR_PAD_LEFT)) {
                        return true;
                    }
                }

                $punchName = strtolower(trim((string)($punches->first()->full_name ?? '')));
                if (!empty($punchName) && !empty($empName) && $empName === $punchName) {
                    return true;
                }

                return false;
            });

            if (!$employee) {
                $unlinkedUsers[] = $cleanId;
                continue;
            }

            // Automatically link device_user_id on employee if not set
            if (empty($employee->device_user_id)) {
                DB::table('employees')->where('id', $employee->id)->update(['device_user_id' => $cleanId]);
                $employee->device_user_id = $cleanId;
            }

            try {
                $empModel = Employee::find($employee->id);
                if (!$empModel || $empModel->is_dead_file || $empModel->status === 'dead_file') {
                    continue;
                }

                $devSn = $punches->first()->device_sn ?? $deviceSn;
                $existedBefore = DB::table('attendance')
                    ->where('employee_id', $empModel->id)
                    ->where('attendance_date', $date)
                    ->exists();

                $syncedRecord = \App\Services\BiometricPunchService::syncEmployeeDatePunches($empModel, $date, $devSn);

                if ($existedBefore) {
                    $updated++;
                } else {
                    $synced++;
                }

                $this->line("  ✓ {$employee->full_name} [PIN: {$cleanId}]: In=" . ($syncedRecord->check_in ?? '—') . ", Out=" . ($syncedRecord->check_out ?? '—') . " [{$syncedRecord->status}]");

            } catch (\Exception $e) {
                $this->error("  ✗ Employee {$employee->full_name} (ID {$employee->id}): " . $e->getMessage());
                Log::error('ZKTeco sync error', ['employee_id' => $employee->id, 'date' => $date, 'error' => $e->getMessage()]);
            }
        }

        if (!empty($unlinkedUsers)) {
            $unlinkedList = implode(', ', array_unique($unlinkedUsers));
            $this->warn("  ⚠️ Unlinked Device PINs on {$date}: [{$unlinkedList}] — No employee has this Device User ID!");
        }

        $this->info("  → {$date} result: {$synced} created, {$updated} updated" . ($skipped ? ", {$skipped} already synced" : "") . ".");
    }
}
