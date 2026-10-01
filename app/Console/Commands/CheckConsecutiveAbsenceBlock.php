<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\SiteDeploymentRequest;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserAccessAudit;
use App\Models\ActivityLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckConsecutiveAbsenceBlock extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'attendance:check-absence-blocks
                            {--days=5 : Consecutive absent days threshold}
                            {--dry-run : Check streaks without blocking access}';

    /**
     * The console command description.
     */
    protected $description = 'Detect 5 consecutive absent working days and block system/API access automatically';

    public function handle(): int
    {
        $threshold = (int) $this->option('days') ?: 5;
        $dryRun    = $this->option('dry-run');

        $this->info("🔍 Running consecutive absence check (threshold: {$threshold} days)...");

        // 1. Check if biometric devices were offline or sync failed today
        if ($this->wereDevicesOfflineToday()) {
            $this->warn("⚠️ Biometric devices appear offline or sync failed today. Skipping absence auto-block to prevent false suspensions.");
            return self::SUCCESS;
        }

        // 2. Fetch all active employees who have linked user accounts
        // Strict Rule: Skip employees in Dead File
        $employees = Employee::activeRoster()
            ->whereNotNull('user_id')
            ->with(['user', 'user.roles'])
            ->get();

        $today = today();
        $blockedCount = 0;
        $warning3Count = 0;
        $warning4Count = 0;
        $missingDeviceIds = [];

        foreach ($employees as $employee) {
            $user = $employee->user;
            if (!$user) {
                continue;
            }

            // Exemption 1: Never auto-block Admin or Global Admin accounts
            if ($user->isGlobalAdmin() || $user->hasAnyRole(['admin', 'global_admin', 'super_admin'])) {
                continue;
            }

            // Exemption 2: Skip employees with no Device ID registered (they cannot punch)
            $devId = trim((string)($employee->device_user_id ?? ''));
            if ($devId === '') {
                $missingDeviceIds[] = [
                    'employee' => $employee->full_name,
                    'code'     => $employee->employee_code,
                    'dept'     => $employee->department,
                ];
                continue;
            }

            // Already blocked
            if ($user->access_blocked_at) {
                continue;
            }

            // Calculate consecutive absent expected working days backwards from yesterday / today
            $streak = $this->calculateConsecutiveAbsentWorkingDays($employee, $today);

            if ($streak >= $threshold) {
                $reason = "Access suspended automatically due to {$streak} consecutive expected working days without attendance. Contact HR.";
                
                $this->error("🚨 Blocking access for {$user->name} ({$employee->full_name}): {$streak} consecutive absent days.");

                if (!$dryRun) {
                    $user->blockAccess($reason, $streak);
                    $this->notifyHrAndEmployee($user, $employee, $streak, true);
                }
                $blockedCount++;

            } elseif ($streak === 4) {
                $this->warn("⚠️ Warning 4-Day: {$user->name} ({$employee->full_name}) has 4 consecutive absent days.");
                if (!$dryRun) {
                    $this->notifyHrAndEmployee($user, $employee, 4, false);
                }
                $warning4Count++;

            } elseif ($streak === 3) {
                $this->line("ℹ️ Warning 3-Day: {$user->name} ({$employee->full_name}) has 3 consecutive absent days.");
                if (!$dryRun) {
                    $this->notifyHrAndEmployee($user, $employee, 3, false);
                }
                $warning3Count++;
            }
        }

        // Persist missing device ID diagnostics for HR attendance dashboard panel
        if (!empty($missingDeviceIds)) {
            try {
                SystemSetting::set(
                    'attendance_missing_device_ids',
                    $missingDeviceIds,
                    'json',
                    'hr_attendance',
                    'Employees without registered biometric Device ID who cannot punch'
                );
            } catch (\Throwable $e) {}
        }

        $this->info("✅ Absence check complete: {$blockedCount} blocked, {$warning4Count} 4-day warnings, {$warning3Count} 3-day warnings, " . count($missingDeviceIds) . " employees missing Device ID.");

        return self::SUCCESS;
    }

    /**
     * Check if all devices were completely offline or no punches were logged today
     */
    private function wereDevicesOfflineToday(): bool
    {
        $todayStr = today()->toDateString();
        $isSunday = today()->isSunday();

        // Sunday is rest day anyway
        if ($isSunday) {
            return false;
        }

        $todayPunchesCount = DB::table('device_attendance_logs')
            ->whereDate('punch_time', $todayStr)
            ->count();

        // If today has 0 punches across the entire company during business hours, check device heartbeats
        if ($todayPunchesCount === 0 && now()->hour >= 10) {
            $latestSeen = DB::table('zk_devices')->max('last_seen_at');
            if ($latestSeen && Carbon::parse($latestSeen)->lt(today()->subDay())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate consecutive absent expected working days backwards.
     * Skips:
     * - Sundays (rest days)
     * - Public holidays
     * - Approved leaves
     * - Approved Site Deployments (S days)
     * These neither add to nor break the streak.
     * Any punch (P) breaks the streak completely and resets to 0.
     */
    public function calculateConsecutiveAbsentWorkingDays(Employee $employee, Carbon $referenceDate, int $lookbackDays = 30): int
    {
        $streak = 0;
        $currentDate = $referenceDate->copy();

        // If checking in the middle of today and today is not over, start evaluating from yesterday
        // unless today already has punches recorded
        if ($currentDate->isToday() && now()->hour < 18) {
            $hasPunchesToday = Attendance::where('employee_id', $employee->id)
                ->whereDate('attendance_date', $currentDate->toDateString())
                ->where(function($q) {
                    $q->whereNotNull('morning_in')
                      ->orWhereNotNull('afternoon_in')
                      ->orWhereNotNull('check_in')
                      ->orWhere('hours_worked', '>', 0);
                })
                ->exists();

            if (!$hasPunchesToday) {
                // Today isn't finished yet, step back to evaluate completed days
                $currentDate->subDay();
            }
        }

        for ($i = 0; $i < $lookbackDays; $i++) {
            $dateStr = $currentDate->toDateString();

            // 1. Sunday: Rest day — does NOT add to or break the streak
            if ($currentDate->isSunday()) {
                $currentDate->subDay();
                continue;
            }

            // 2. Public Holiday: Does NOT add to or break the streak
            if ($this->isPublicHoliday($dateStr)) {
                $currentDate->subDay();
                continue;
            }

            // 3. Approved Leave: Does NOT add to or break the streak
            if ($this->hasApprovedLeave($employee->id, $dateStr)) {
                $currentDate->subDay();
                continue;
            }

            // 4. Approved Site Deployment ('S'): Does NOT add to or break the streak
            if ($this->hasApprovedSiteDeployment($employee->id, $dateStr)) {
                $currentDate->subDay();
                continue;
            }

            // Check attendance punch on this expected working day
            $attendance = Attendance::where('employee_id', $employee->id)
                ->whereDate('attendance_date', $dateStr)
                ->first();

            $hasPunch = $attendance && (
                !empty($attendance->morning_in) ||
                !empty($attendance->morning_out) ||
                !empty($attendance->afternoon_in) ||
                !empty($attendance->afternoon_out) ||
                !empty($attendance->check_in) ||
                !empty($attendance->check_out) ||
                (float)$attendance->hours_worked > 0
            );

            // Also check raw biometric logs directly in case sync hasn't run yet
            if (!$hasPunch) {
                $hasPunch = $this->hasRawPunchOnDate($employee, $dateStr);
            }

            if ($hasPunch) {
                // Punch found! Streak is broken!
                break;
            }

            // Expected working day with NO punch -> increment streak
            $streak++;

            $currentDate->subDay();
        }

        return $streak;
    }

    /**
     * Check if a date is an official public holiday
     */
    private function isPublicHoliday(string $dateStr): bool
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('holidays')) {
                return Holiday::whereDate('holiday_date', $dateStr)->exists()
                    || Holiday::whereDate('from_date', '<=', $dateStr)
                              ->whereDate('to_date', '>=', $dateStr)
                              ->exists();
            }
        } catch (\Throwable $e) {}
        return false;
    }

    /**
     * Check if an employee is on approved leave on a given date
     */
    private function hasApprovedLeave(int $employeeId, string $dateStr): bool
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('leave_requests')) {
                return LeaveRequest::where('employee_id', $employeeId)
                    ->where('status', 'approved')
                    ->whereDate('start_date', '<=', $dateStr)
                    ->whereDate('end_date', '>=', $dateStr)
                    ->exists();
            }
        } catch (\Throwable $e) {}
        return false;
    }

    /**
     * Check if employee has an approved site deployment on a given date
     */
    private function hasApprovedSiteDeployment(int $employeeId, string $dateStr): bool
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('site_deployment_requests')) {
                return SiteDeploymentRequest::where('employee_id', $employeeId)
                    ->where('status', 'approved')
                    ->whereDate('start_date', '<=', $dateStr)
                    ->whereDate('end_date', '>=', $dateStr)
                    ->exists();
            }
        } catch (\Throwable $e) {}
        return false;
    }

    /**
     * Check if raw biometric punch exists on date for employee
     */
    private function hasRawPunchOnDate(Employee $employee, string $dateStr): bool
    {
        $devId = trim((string)($employee->device_user_id ?? ''));
        $code  = trim((string)($employee->employee_code ?? ''));

        if ($devId === '' && $code === '') {
            return false;
        }

        return DB::table('device_attendance_logs')
            ->whereDate('punch_time', $dateStr)
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
            ->exists();
    }

    /**
     * Send warning notification to employee and HR team
     */
    private function notifyHrAndEmployee(User $user, Employee $employee, int $streak, bool $isBlocked): void
    {
        $subject = $isBlocked
            ? "🚨 Account Access Suspended: 5 Consecutive Days Without Attendance"
            : "⚠️ Attendance Alert: {$streak} Consecutive Days Without Attendance";

        $message = $isBlocked
            ? "Employee {$employee->full_name} ({$employee->employee_code}) has been absent for 5 consecutive expected working days without approved leave or site deployment. System access has been suspended."
            : "Employee {$employee->full_name} ({$employee->employee_code}) has been absent for {$streak} consecutive working days without attendance. If 5 consecutive days are reached, system access will be automatically suspended.";

        try {
            // Log to Activity Log for HR view
            ActivityLog::log(
                $isBlocked ? 'suspended' : 'warning',
                $message,
                'HR Attendance Security',
                $user
            );

            // Record warning audit entry
            if (!$isBlocked) {
                UserAccessAudit::create([
                    'user_id'            => $user->id,
                    'employee_id'        => $employee->id,
                    'action'             => 'warning_sent',
                    'performed_by'       => null,
                    'reason'             => "Warning sent at {$streak} consecutive missed days.",
                    'missed_streak_days' => $streak,
                    'ip_address'         => '127.0.0.1',
                ]);
            }
        } catch (\Throwable $e) {}
    }
}
