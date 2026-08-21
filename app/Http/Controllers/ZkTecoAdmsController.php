<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\DeviceAttendanceLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ZkTecoAdmsController extends Controller
{
    /**
     * Handle ZKTeco ADMS cdata endpoint (Handshake, Heartbeat & ATTLOG punch push)
     */
    public function cdata(Request $request)
    {
        $sn      = $this->extractParam($request, 'SN');
        $table   = strtoupper($this->extractParam($request, 'table'));
        $options = $this->extractParam($request, 'options');
        $method  = $request->method();
        $body    = $request->getContent();

        $this->logAdms("METHOD: {$method} | SN: {$sn} | TABLE: {$table} | OPTIONS: {$options} | URL: " . $request->fullUrl());

        if (!empty($sn)) {
            $this->updateDeviceSeen($sn, $request->ip());
        }

        // ── 1. HANDSHAKE (GET /iclock/cdata?SN=...&options=all) ───────────────────
        if ($method === 'GET' && $options === 'all') {
            $this->logAdms("HANDSHAKE accepted from device SN: {$sn}");

            $response = "GET OPTION FROM: {$sn}\n"
                      . "Stamp=9999\n"
                      . "OpStamp=9999\n"
                      . "ErrorDelay=60\n"
                      . "Delay=30\n"
                      . "TransFlag=1111000000\n"
                      . "TransInterval=1\n"
                      . "TransTables=ATTLOG\n"
                      . "ServerVersion=2.4.1\n"
                      . "PushProtVer=2.4.1\n";

            return response($response, 200)->header('Content-Type', 'text/plain');
        }

        // ── 2. HEARTBEAT / GET request ─────────────────────────────────────────────
        if ($method === 'GET') {
            return response("OK\n", 200)->header('Content-Type', 'text/plain');
        }

        // ── 3. ATTENDANCE PUNCH PUSH (POST /iclock/cdata?table=ATTLOG) ────────────
        if ($method === 'POST' && ($table === 'ATTLOG' || empty($table))) {
            $this->logAdms("ATTLOG POST from SN: {$sn} — Raw Body length: " . strlen($body));

            $lines = explode("\n", trim($body));
            $saved = 0;
            $skipped = 0;

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                // Split by tab or multiple spaces
                $parts = preg_split('/\t+/', $line);
                if (count($parts) < 2) {
                    $parts = preg_split('/\s{2,}/', $line);
                }

                $userId     = isset($parts[0]) ? trim($parts[0]) : '';
                $punchTime  = isset($parts[1]) ? trim($parts[1]) : '';
                $status     = isset($parts[2]) ? trim($parts[2]) : '0';
                $verifyMode = isset($parts[3]) ? trim($parts[3]) : '';

                if (empty($userId) || empty($punchTime) || !preg_match('/\d{4}-\d{2}-\d{2}/', $punchTime)) {
                    $skipped++;
                    continue;
                }

                try {
                    // 1. Insert into raw device attendance logs
                    DB::table('device_attendance_logs')->insertOrIgnore([
                        'device_sn'      => $sn ?: 'AF6P230860018',
                        'device_user_id' => $userId,
                        'punch_time'     => $punchTime,
                        'status'         => $status,
                        'verify_mode'    => $verifyMode ?: null,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ]);

                    // 2. Real-time auto-sync to employee attendance table
                    $this->autoSyncPunch($sn, $userId, $punchTime, $status);
                    $saved++;
                } catch (\Throwable $e) {
                    $this->logAdms("ERROR processing punch line [{$line}]: " . $e->getMessage());
                }
            }

            $this->logAdms("PUNCH PROCESS RESULT: Saved/Synced={$saved}, Skipped={$skipped}");
            return response("OK: {$saved}\n", 200)->header('Content-Type', 'text/plain');
        }

        // ── 4. Fallback for other tables (OPERLOG, OPTIONS, etc.) ──────────────────
        return response("OK\n", 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Handle ZKTeco ADMS getrequest endpoint (Heartbeat / Command polling)
     */
    public function getrequest(Request $request)
    {
        $sn = $this->extractParam($request, 'SN');
        if (!empty($sn)) {
            $this->updateDeviceSeen($sn, $request->ip());
        }
        return response("OK\n", 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Handle devicecmd / fdata / push
     */
    public function devicecmd(Request $request)
    {
        return response("OK\n", 200)->header('Content-Type', 'text/plain');
    }

    public function fdata(Request $request)
    {
        return response("OK\n", 200)->header('Content-Type', 'text/plain');
    }

    public function push(Request $request)
    {
        return response("OK\n", 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Automatically sync a single punch in real-time to the main attendance table.
     */
    private function autoSyncPunch(?string $sn, string $userId, string $punchTime, string $status): void
    {
        try {
            // Find employee by device_user_id or employee_code
            $cleanId = trim($userId);
            $employee = DB::table('employees')
                ->where('device_user_id', $cleanId)
                ->orWhere('device_user_id', ltrim($cleanId, '0'))
                ->orWhere('employee_code', $cleanId)
                ->orWhere('employee_code', 'EMP-' . $cleanId)
                ->orWhere('employee_code', 'EMP-' . str_pad($cleanId, 2, '0', STR_PAD_LEFT))
                ->first();

            if (!$employee) {
                $this->logAdms("UNMATCHED PUNCH: device_user_id={$userId} (No employee linked yet)");
                return;
            }

            $dateStr = substr($punchTime, 0, 10);
            $timeStr = strlen($punchTime) > 10 ? substr($punchTime, 11, 8) : '00:00:00';

            $existing = DB::table('attendance')
                ->where('employee_id', $employee->id)
                ->where('attendance_date', $dateStr)
                ->first();

            if (!$existing) {
                // First punch of the day: Check-In
                $workStartTime = '09:15:00';
                $attStatus = ($timeStr > $workStartTime) ? 'present' : 'present';

                DB::table('attendance')->insert([
                    'employee_id'         => $employee->id,
                    'attendance_date'     => $dateStr,
                    'check_in'            => $timeStr,
                    'check_out'           => null,
                    'hours_worked'        => null,
                    'status'              => $attStatus,
                    'source'              => 'biometric',
                    'biometric_device_id' => $sn ?: 'AF6P230860018',
                    'is_approved'         => true,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);

                $this->logAdms("CHECK-IN RECORDED: Employee {$employee->full_name} (ID: {$employee->id}, Device ID: {$userId}) at {$timeStr} on {$dateStr}");
            } else {
                // Subsequent punch: Check-Out
                $checkIn = $existing->check_in;
                if ($checkIn && $timeStr > $checkIn) {
                    $inSecs  = strtotime($dateStr . ' ' . $checkIn);
                    $outSecs = strtotime($dateStr . ' ' . $timeStr);
                    $hours   = $outSecs > $inSecs ? round(($outSecs - $inSecs) / 3600, 2) : null;

                    DB::table('attendance')
                        ->where('employee_id', $employee->id)
                        ->where('attendance_date', $dateStr)
                        ->update([
                            'check_out'           => $timeStr,
                            'hours_worked'        => $hours,
                            'source'              => 'biometric',
                            'biometric_device_id' => $sn ?: ($existing->biometric_device_id ?? 'AF6P230860018'),
                            'updated_at'          => now(),
                        ]);

                    $this->logAdms("CHECK-OUT UPDATED: Employee {$employee->full_name} at {$timeStr} (Total Hours: {$hours} hrs)");
                }
            }

            // Mark punch log as synced
            DB::table('device_attendance_logs')
                ->where('device_user_id', $userId)
                ->where('punch_time', $punchTime)
                ->update(['synced_at' => now()]);

        } catch (\Throwable $e) {
            $this->logAdms("ERROR in autoSyncPunch: " . $e->getMessage());
        }
    }

    /**
     * Update device heartbeat status
     */
    private function updateDeviceSeen(string $sn, ?string $ip = null): void
    {
        try {
            DB::table('zk_devices')->updateOrInsert(
                ['serial_number' => $sn],
                [
                    'device_name'   => 'ZKTeco MB460 / Face & Fingerprint',
                    'ip_address'    => $ip,
                    'port'          => 4370,
                    'status'        => 'online',
                    'last_seen_at'  => now(),
                    'updated_at'    => now(),
                ]
            );
        } catch (\Throwable $e) {
            // Log without failing
        }
    }

    private function extractParam(Request $request, string $key): string
    {
        $val = $request->query($key) ?? $request->input($key) ?? '';
        if (empty($val) && !empty($request->server('QUERY_STRING'))) {
            parse_str($request->server('QUERY_STRING'), $qs);
            foreach ($qs as $k => $v) {
                if (strtolower($k) === strtolower($key)) {
                    return trim($v);
                }
            }
        }
        return trim($val);
    }

    private function logAdms(string $msg): void
    {
        try {
            $logPath = public_path('iclock/adms.log');
            $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
            @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            Log::info("ADMS: {$msg}");
        }
    }
}
