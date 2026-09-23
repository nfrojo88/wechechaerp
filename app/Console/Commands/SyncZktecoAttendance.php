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
                            {--force : Re-sync records that were already synced}
                            {--all : Sync ALL dates (use carefully)}';

    protected $description = 'Sync ZKTeco raw punch logs → HR Attendance table';

    public function handle(): int
    {
        $forceResync = $this->option('force');
        $syncAll     = $this->option('all');
        $from        = $this->option('from');
        $to          = $this->option('to');

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

            $this->info("Syncing " . count($dates) . " dates...");
            foreach ($dates as $date) {
                $this->syncDate($date, $forceResync);
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

            $current = $startDate->copy();
            $this->info("Syncing date range: {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");
            while ($current->lte($endDate)) {
                $this->syncDate($current->format('Y-m-d'), $forceResync);
                $current->addDay();
            }
        } else {
            $date = $this->option('date') ?? now()->format('Y-m-d');

            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $this->error("Invalid date format. Use Y-m-d e.g. 2026-07-20");
                return self::FAILURE;
            }

            $this->syncDate($date, $forceResync);
        }

        return self::SUCCESS;
    }

    /**
     * Sync all punch records for a given date into the attendance table.
     */
    private function syncDate(string $date, bool $force): void
    {
        $this->info("📅 Syncing attendance for: {$date}");

        // Get all punch records for this date, grouped by employee
        $query = DB::table('device_attendance_logs as d')
            ->join('employees as e', 'e.device_user_id', '=', 'd.device_user_id')
            ->whereDate('d.punch_time', $date)
            ->select(
                'e.id as employee_id',
                'e.full_name',
                'd.device_sn',
                DB::raw("MIN(CASE WHEN d.status = '0' OR d.status IS NULL THEN TIME(d.punch_time) END) as first_checkin"),
                DB::raw("MAX(TIME(d.punch_time)) as last_punch"),
                DB::raw("MIN(TIME(d.punch_time)) as first_punch"),
                DB::raw("COUNT(*) as punch_count")
            )
            ->groupBy('e.id', 'e.full_name', 'd.device_sn');

        // If not forcing, only sync records not yet synced
        if (!$force) {
            $alreadySyncedEmployeeIds = DB::table('attendance')
                ->where('attendance_date', $date)
                ->where('source', 'device')
                ->pluck('employee_id')
                ->toArray();

            if (!empty($alreadySyncedEmployeeIds)) {
                $query->whereNotIn('e.id', $alreadySyncedEmployeeIds);
            }
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->line("  → No new records to sync for {$date}.");
            return;
        }

        $synced  = 0;
        $updated = 0;
        $errors  = 0;

        foreach ($rows as $row) {
            try {
                // Use first punch as check-in, last punch as check-out (if they differ)
                $checkIn  = $row->first_punch;
                $checkOut = $row->last_punch !== $row->first_punch ? $row->last_punch : null;

                // Calculate hours worked
                $hoursWorked = null;
                if ($checkIn && $checkOut) {
                    $inDt  = Carbon::parse("{$date} {$checkIn}");
                    $outDt = Carbon::parse("{$date} {$checkOut}");
                    $hoursWorked = round($outDt->diffInMinutes($inDt) / 60, 2);
                }

                // Determine status
                $status = 'present';

                // Check for lateness (after 09:15 AM)
                $workStartTime = '09:15:00';
                if ($checkIn && $checkIn > $workStartTime) {
                    $status = 'late';
                }

                $attendanceData = [
                    'check_in'            => $checkIn,
                    'check_out'           => $checkOut,
                    'hours_worked'        => $hoursWorked,
                    'status'              => $status,
                    'source'              => 'device',
                    'biometric_device_id' => $row->device_sn ?? null,
                    'is_approved'         => false,
                    'updated_at'          => now(),
                ];

                $existing = DB::table('attendance')
                    ->where('employee_id', $row->employee_id)
                    ->where('attendance_date', $date)
                    ->first();

                if ($existing) {
                    DB::table('attendance')
                        ->where('id', $existing->id)
                        ->update($attendanceData);
                    $updated++;
                } else {
                    DB::table('attendance')->insert(array_merge($attendanceData, [
                        'employee_id'     => $row->employee_id,
                        'attendance_date' => $date,
                        'created_at'      => now(),
                    ]));
                    $synced++;
                }

                // Mark device logs as synced
                DB::table('device_attendance_logs')
                    ->whereDate('punch_time', $date)
                    ->where('device_user_id', function ($q) use ($row) {
                        $q->select('device_user_id')
                          ->from('employees')
                          ->where('id', $row->employee_id)
                          ->limit(1);
                    })
                    ->update(['synced_at' => now()]);

                $this->line("  ✓ {$row->full_name}: in={$checkIn} out=" . ($checkOut ?? 'N/A') . " [{$status}]");

            } catch (\Exception $e) {
                $this->error("  ✗ Employee ID {$row->employee_id}: " . $e->getMessage());
                Log::error('ZKTeco sync error', ['employee_id' => $row->employee_id, 'date' => $date, 'error' => $e->getMessage()]);
                $errors++;
            }
        }

        $this->info("  → Done: {$synced} created, {$updated} updated, {$errors} errors.");
    }
}
