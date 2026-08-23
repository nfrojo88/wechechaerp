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
     * Supports: GET /iclock/cdata and POST /iclock/cdata
     */
    public function cdata(Request $request)
    {
        $sn      = $this->extractParam($request, 'SN');
        $table   = strtoupper($this->extractParam($request, 'table'));
        $options = strtolower($this->extractParam($request, 'options'));
        $method  = strtoupper($request->method());
        $body    = $request->getContent();

        $this->logAdms("METHOD: {$method} | SN: {$sn} | TABLE: {$table} | OPTIONS: {$options} | QUERY: " . $request->getQueryString());

        if (!empty($sn)) {
            $this->updateDeviceSeen($sn, $request->ip());
        }

        // ── 1. INITIAL HANDSHAKE (GET /iclock/cdata?SN=...&options=all) ──────────
        // ZKTeco requests server configuration upon startup/reconnection
        if ($method === 'GET' && ($options === 'all' || empty($table))) {
            $this->logAdms("HANDSHAKE accepted from device SN: {$sn}");

            // Standard ZKTeco ADMS Handshake response using \r\n
            $response = "GET OPTION FROM: {$sn}\r\n"
                      . "Stamp=9999\r\n"
                      . "OpStamp=9999\r\n"
                      . "ErrorDelay=60\r\n"
                      . "Delay=30\r\n"
                      . "TransTimes=00:00;14:05\r\n"
                      . "TransInterval=1\r\n"
                      . "TransFlag=1111000000\r\n"
                      . "Realtime=1\r\n"
                      . "Encrypt=0\r\n"
                      . "ServerVersion=2.4.1\r\n"
                      . "PushProtVer=2.4.1\r\n"
                      . "TransTables=User Transaction\r\n"
                      . "SessionID=" . substr(md5($sn . time()), 0, 16) . "\r\n";

            return response($response, 200, [
                'Content-Type'   => 'text/plain; charset=utf-8',
                'Content-Length' => strlen($response),
                'Connection'     => 'close',
            ]);
        }

        // ── 2. HEARTBEAT / GET request ─────────────────────────────────────────────
        if ($method === 'GET') {
            $response = "OK\r\n";
            return response($response, 200, [
                'Content-Type'   => 'text/plain; charset=utf-8',
                'Content-Length' => strlen($response),
                'Connection'     => 'close',
            ]);
        }

        // ── 3. ATTENDANCE PUNCH PUSH (POST /iclock/cdata?table=ATTLOG) ────────────
        if ($method === 'POST') {
            $this->logAdms("POST RECEIVED from SN: [{$sn}] TABLE: [{$table}] — Payload Length: " . strlen($body));

            $lines = explode("\n", trim($body));
            $saved = 0;
            $skipped = 0;

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                $userId = '';
                $punchTime = '';
                $status = '0';
                $verifyMode = '1';

                // Format A: Tab or multiple spaces separated (Standard ZKTeco: "PIN\tTIME\tSTATUS\tVERIFY")
                if (str_contains($line, "\t") || preg_match('/\s{2,}/', $line)) {
                    $parts = preg_split('/\t+|\s{2,}/', $line);
                    $userId     = isset($parts[0]) ? trim($parts[0]) : '';
                    $punchTime  = isset($parts[1]) ? trim($parts[1]) : '';
                    $status     = isset($parts[2]) ? trim($parts[2]) : '0';
                    $verifyMode = isset($parts[3]) ? trim($parts[3]) : '1';
                }
                // Format B: Key=Value format (e.g. "PIN=1\tTIME=2026-08-23 08:30:00")
                elseif (str_contains($line, '=')) {
                    parse_str(str_replace("\t", '&', $line), $kv);
                    $userId    = $kv['PIN'] ?? $kv['pin'] ?? $kv['USERID'] ?? $kv['userId'] ?? '';
                    $punchTime = $kv['TIME'] ?? $kv['time'] ?? $kv['CHECKTIME'] ?? '';
                    $status    = $kv['STATUS'] ?? $kv['status'] ?? '0';
                    $verifyMode= $kv['VERIFY'] ?? $kv['verify'] ?? '1';
                }

                if (empty($userId) || empty($punchTime) || !preg_match('/\d{4}-\d{2}-\d{2}/', $punchTime)) {
                    $skipped++;
                    continue;
                }

                try {
                    // Match employee by device_user_id or employee_code
                    $cleanId = trim($userId);
                    $employee = DB::table('employees')
                        ->where('device_user_id', $cleanId)
                        ->orWhere('device_user_id', ltrim($cleanId, '0'))
                        ->orWhere('employee_code', $cleanId)
                        ->orWhere('employee_code', 'EMP-' . $cleanId)
                        ->first();

                    $fullName = $employee ? $employee->full_name : null;

                    // 1. Insert into raw device attendance logs
                    DB::table('device_attendance_logs')->insertOrIgnore([
                        'device_sn'      => $sn ?: 'AF6P230860018',
                        'device_user_id' => $cleanId,
                        'punch_time'     => $punchTime,
                        'status'         => (string)$status,
                        'verify_mode'    => (string)$verifyMode,
                        'full_name'      => $fullName,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ]);

                    // 2. Real-time auto-sync to employee attendance table
                    if ($employee) {
                        $this->autoSyncPunch($sn, $employee, $punchTime, (string)$status);
                    }

                    $saved++;
                } catch (\Throwable $e) {
                    $this->logAdms("ERROR processing punch [{$line}]: " . $e->getMessage());
                }
            }

            $this->logAdms("PUNCH PROCESS RESULT: Saved={$saved}, Skipped={$skipped}");

            // ZKTeco expects "OK: {count}\r\n" or "OK\r\n"
            $response = ($saved > 0) ? "OK: {$saved}\r\n" : "OK\r\n";
            return response($response, 200, [
                'Content-Type'   => 'text/plain; charset=utf-8',
                'Content-Length' => strlen($response),
                'Connection'     => 'close',
            ]);
        }

        // ── 4. Fallback ───────────────────────────────────────────────────────────
        $response = "OK\r\n";
        return response($response, 200, [
            'Content-Type'   => 'text/plain; charset=utf-8',
            'Content-Length' => strlen($response),
            'Connection'     => 'close',
        ]);
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

        $response = "OK\r\n";
        return response($response, 200, [
            'Content-Type'   => 'text/plain; charset=utf-8',
            'Content-Length' => strlen($response),
            'Connection'     => 'close',
        ]);
    }

    /**
     * Handle devicecmd / fdata / push endpoints
     */
    public function devicecmd(Request $request)
    {
        $response = "OK\r\n";
        return response($response, 200, [
            'Content-Type'   => 'text/plain; charset=utf-8',
            'Content-Length' => strlen($response),
            'Connection'     => 'close',
        ]);
    }

    public function fdata(Request $request)
    {
        $response = "OK\r\n";
        return response($response, 200, [
            'Content-Type'   => 'text/plain; charset=utf-8',
            'Content-Length' => strlen($response),
            'Connection'     => 'close',
        ]);
    }

    public function push(Request $request)
    {
        $response = "OK\r\n";
        return response($response, 200, [
            'Content-Type'   => 'text/plain; charset=utf-8',
            'Content-Length' => strlen($response),
            'Connection'     => 'close',
        ]);
    }

    /**
     * Automatically sync a single punch in real-time to the main attendance table.
     */
    private function autoSyncPunch(?string $sn, $employee, string $punchTime, string $status): void
    {
        try {
            $dateStr = substr($punchTime, 0, 10);
            $timeStr = strlen($punchTime) > 10 ? substr($punchTime, 11, 8) : '00:00:00';

            $existing = DB::table('attendance')
                ->where('employee_id', $employee->id)
                ->where('attendance_date', $dateStr)
                ->first();

            if (!$existing) {
                // First punch: Check-In
                DB::table('attendance')->insert([
                    'employee_id'         => $employee->id,
                    'attendance_date'     => $dateStr,
                    'check_in'            => $timeStr,
                    'check_out'           => null,
                    'hours_worked'        => null,
                    'status'              => 'present',
                    'source'              => 'biometric',
                    'biometric_device_id' => $sn ?: 'AF6P230860018',
                    'is_approved'         => true,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);

                $this->logAdms("CHECK-IN RECORDED: {$employee->full_name} at {$timeStr} on {$dateStr}");
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

                    $this->logAdms("CHECK-OUT UPDATED: {$employee->full_name} at {$timeStr} (Hours: {$hours})");
                }
            }

            // Mark punch log as synced
            DB::table('device_attendance_logs')
                ->where('device_user_id', $employee->device_user_id)
                ->where('punch_time', $punchTime)
                ->update(['synced_at' => now()]);

        } catch (\Throwable $e) {
            $this->logAdms("ERROR in autoSyncPunch: " . $e->getMessage());
        }
    }

    /**
     * Update device heartbeat status in zk_devices table
     */
    private function updateDeviceSeen(string $sn, ?string $ip = null): void
    {
        try {
            DB::table('zk_devices')->updateOrInsert(
                ['serial_number' => $sn],
                [
                    'name'         => 'ZKTeco ' . $sn,
                    'location'     => 'Main Office Gate',
                    'is_active'    => true,
                    'last_seen_at' => now(),
                    'updated_at'   => now(),
                ]
            );
        } catch (\Throwable $e) {
            $this->logAdms("NOTE: zk_devices update note: " . $e->getMessage());
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
        return trim((string)$val);
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

