<?php
/**
 * ZKTeco ADMS Receiver — iclock/cdata.php
 *
 * This file handles all communication from the ZKTeco fingerprint/face
 * attendance device using the ADMS (Attendance Data Management System) protocol.
 *
 * ⚠️  Place this file at: public/iclock/cdata.php
 *      Device should be configured to push to: http(s)://yourdomain.com/iclock/cdata.php
 *
 * Handles:
 *   - GET  ?options=all          → Device handshake — sends back configuration
 *   - GET  ?table=ATTLOG         → Heartbeat (keep-alive ping)
 *   - POST ?table=ATTLOG         → Attendance punch push (tab-separated body)
 */

// ── 1. Bootstrap Laravel ────────────────────────────────────────────────────
// We load Laravel's bootstrap so we can use the DB and models cleanly.
$laravelBase = dirname(__DIR__, 2); // two levels up: public/ → project root
require $laravelBase . '/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once $laravelBase . '/bootstrap/app.php';

// We need the console kernel to boot services (especially DB and config) without triggering HTTP routes.
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// ── 2. Logging helper ────────────────────────────────────────────────────────
$logFile = __DIR__ . '/adms.log';

function adms_log(string $message): void
{
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

// ── 3. Request info ──────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$body   = file_get_contents('php://input');

$sn = '';
$table = '';
$options = '';

// Check $_GET
foreach ($_GET as $k => $v) {
    $lk = strtolower($k);
    if ($lk === 'sn') $sn = trim($v);
    if ($lk === 'table') $table = trim($v);
    if ($lk === 'options') $options = trim($v);
}

// Fallback: parse raw QUERY_STRING if SN was not found in $_GET
if (empty($sn) && !empty($_SERVER['QUERY_STRING'])) {
    parse_str($_SERVER['QUERY_STRING'], $parsedQs);
    foreach ($parsedQs as $k => $v) {
        $lk = strtolower($k);
        if ($lk === 'sn') $sn = trim($v);
        if ($lk === 'table' && empty($table)) $table = trim($v);
        if ($lk === 'options' && empty($options)) $options = trim($v);
    }
}

// Fallback: check headers or User-Agent for SN
if (empty($sn)) {
    if (isset($_SERVER['HTTP_SN'])) {
        $sn = trim($_SERVER['HTTP_SN']);
    } elseif (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/SN=([A-Za-z0-9]+)/i', $_SERVER['HTTP_USER_AGENT'], $m)) {
        $sn = $m[1];
    }
}

adms_log("METHOD: {$method} | SN: {$sn} | TABLE: {$table} | PARAMS: " . json_encode($_GET));

if (!empty($body)) {
    adms_log("BODY: " . substr($body, 0, 500));
}

// Always respond as plain text
header('Content-Type: text/plain');

// ── 4. HANDSHAKE — Device boot / reconnect ───────────────────────────────────
// The device sends: GET /iclock/cdata.php?SN=XXXX&options=all
if ($method === 'GET' && $options === 'all') {
    adms_log("HANDSHAKE from SN: {$sn}");

    // Update device last-seen timestamp
    _updateDeviceSeen($sn);

    echo "GET OPTION FROM: {$sn}\r\n";
    echo "Stamp=9999\r\n";
    echo "OpStamp=9999\r\n";
    echo "ErrorDelay=60\r\n";
    echo "Delay=30\r\n";
    echo "TransTimes=00:00;14:05\r\n";
    echo "TransInterval=1\r\n";
    echo "TransFlag=1111000000\r\n";
    echo "Realtime=1\r\n";
    echo "Encrypt=0\r\n";
    echo "ServerVersion=2.4.1\r\n";
    echo "PushProtVer=2.4.1\r\n";
    echo "TransTables=User Transaction\r\n";
    echo "SessionID=" . substr(md5($sn . time()), 0, 16) . "\r\n";
    exit;
}

// ── 5. HEARTBEAT — Keep-alive ping ───────────────────────────────────────────
if ($method === 'GET' && empty($body)) {
    adms_log("HEARTBEAT from SN: {$sn}");
    _updateDeviceSeen($sn);
    echo "OK\r\n";
    exit;
}

// ── 6. ATTENDANCE PUSH — Tab-separated punch records ─────────────────────────
// Device sends: POST /iclock/cdata.php?SN=XXXX&table=ATTLOG
// Body format (tab-separated, one record per line):
//   user_id  punch_time           status  verify_mode  ...
//   1        2026-07-20 09:05:30  0       1            0  0  0  0  0  0
if ($method === 'POST' && strtoupper($table) === 'ATTLOG') {
    adms_log("ATTLOG PUSH from SN: {$sn} — parsing body...");

    $saved   = 0;
    $skipped = 0;
    $errors  = 0;

    $lines = explode("\n", trim($body));

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }

        // Split by tab; fall back to multiple spaces
        $parts = preg_split('/\t+/', $line);
        if (count($parts) < 2) {
            $parts = preg_split('/\s{2,}/', $line);
        }

        $userId     = isset($parts[0]) ? trim($parts[0]) : '';
        $punchTime  = isset($parts[1]) ? trim($parts[1]) : '';
        $status     = isset($parts[2]) ? trim($parts[2]) : '0';
        $verifyMode = isset($parts[3]) ? trim($parts[3]) : '';

        if (empty($userId) || empty($punchTime)) {
            adms_log("SKIP — missing user_id or punch_time in line: {$line}");
            $skipped++;
            continue;
        }

        // Validate punch_time format roughly
        if (!preg_match('/\d{4}-\d{2}-\d{2}/', $punchTime)) {
            adms_log("SKIP — invalid punch_time format: {$punchTime}");
            $skipped++;
            continue;
        }

        try {
            // INSERT IGNORE — device resends same records; UNIQUE KEY prevents duplicates
            DB::table('device_attendance_logs')->insertOrIgnore([
                'device_sn'      => $sn ?: null,
                'device_user_id' => $userId,
                'punch_time'     => $punchTime,
                'status'         => $status,
                'verify_mode'    => $verifyMode ?: null,
                'full_name'      => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            // Also auto-sync to attendance table if employee is mapped
            _autoSyncPunch($sn, $userId, $punchTime, $status);

            $saved++;
        } catch (\Exception $e) {
            adms_log("ERROR inserting punch — user_id={$userId} punch_time={$punchTime}: " . $e->getMessage());
            $errors++;
        }
    }

    adms_log("ATTLOG DONE — saved={$saved} skipped={$skipped} errors={$errors}");

    // MUST respond "OK" within 3 seconds or device retries
    echo "OK\n";
    exit;
}

// ── 7. Default fallback ───────────────────────────────────────────────────────
adms_log("UNKNOWN REQUEST — responding OK");
echo "OK\n";
exit;

// ── Helper: Update device last-seen ──────────────────────────────────────────
function _updateDeviceSeen(string $sn): void
{
    if (empty($sn)) return;

    try {
        DB::table('zk_devices')->updateOrInsert(
            ['serial_number' => $sn],
            ['last_seen_at' => now(), 'updated_at' => now()]
        );
    } catch (\Exception $e) {
        // Table may not exist yet — just log, don't break
        adms_log("NOTE: Could not update zk_devices (table may not exist yet): " . $e->getMessage());
    }
}

// ── Helper: Auto-sync a single punch to the attendance table ─────────────────
function _autoSyncPunch(string $sn, string $userId, string $punchTime, string $status): void
{
    try {
        // Find employee by device_user_id, stripped zero, or employee_code
        $cleanId = trim($userId);
        $employee = DB::table('employees')
            ->where('device_user_id', $cleanId)
            ->orWhere('device_user_id', ltrim($cleanId, '0'))
            ->orWhere('employee_code', $cleanId)
            ->orWhere('employee_code', 'EMP-' . $cleanId)
            ->orWhere('employee_code', 'EMP-' . str_pad($cleanId, 2, '0', STR_PAD_LEFT))
            ->first();

        if (!$employee) {
            adms_log("UNMATCHED PUNCH: user_id={$userId} (no employee mapped)");
            return; // No employee mapped to this device user ID yet
        }

        $dateStr = substr($punchTime, 0, 10); // Y-m-d
        $timeStr = strlen($punchTime) > 10 ? substr($punchTime, 11, 8) : '00:00:00'; // H:i:s

        // ── Determine OT type from day of week & punch time ───────────────────
        $punchHour  = (int) substr($timeStr, 0, 2);
        $dayOfWeek  = date('N', strtotime($dateStr)); // 1=Monday … 7=Sunday
        $isHoliday  = ($status === '4'); // Some devices flag 4 = holiday
        $isSunday   = ($dayOfWeek === 7);
        $isSaturday = ($dayOfWeek === 6);
        $isNight1   = ($punchHour >= 0 && $punchHour < 4);   // 00:00–04:00 → ×1.5
        $isNight2   = ($punchHour >= 16);                     // 16:00–00:00 → ×1.75

        $otType = 'none';
        if ($isHoliday) {
            $otType = 'holiday';
        } elseif ($isSunday) {
            $otType = 'rest_day';
        } elseif ($isSaturday) {
            $otType = 'rest_day';
        } elseif ($isNight1) {
            $otType = 'night_12_4';
        } elseif ($isNight2) {
            $otType = 'night_4_12';
        }

        // ── Calculate OT pay ─────────────────────────────────────────────────
        // OT hours are only set when check-out is available, so we default to 0 now
        // The HR manager can update OT hours manually; or it will be recalculated on sync
        $otPay = 0;

        // Get existing attendance record for this employee on this date
        $existing = DB::table('attendance')
            ->where('employee_id', $employee->id)
            ->where('attendance_date', $dateStr)
            ->first();

        if (!$existing) {
            // First punch of the day → check-in
            DB::table('attendance')->insert([
                'employee_id'         => $employee->id,
                'attendance_date'     => $dateStr,
                'check_in'            => $timeStr,
                'check_out'           => null,
                'hours_worked'        => null,
                'status'              => 'present',
                'source'              => 'device',
                'biometric_device_id' => $sn ?: null,
                'is_approved'         => false,
                'overtime_hours'      => 0,
                'overtime_type'       => $otType,
                'overtime_pay'        => 0,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        } else {
            // Subsequent punch — update check_out if this punch is later than check_in
            $checkIn = $existing->check_in;
            if ($checkIn && $timeStr > $checkIn) {
                $inSecs  = strtotime($dateStr . ' ' . $checkIn);
                $outSecs = strtotime($dateStr . ' ' . $timeStr);
                $hours   = $outSecs > $inSecs ? round(($outSecs - $inSecs) / 3600, 2) : null;

                // Calculate OT hours = total hours worked beyond 8 standard hours
                $regularHours = 8;
                $otHours      = $hours !== null && $hours > $regularHours
                                ? round($hours - $regularHours, 2)
                                : ($existing->overtime_hours ?? 0);

                // Calculate OT pay: basic / 30 / 8 × coefficient × OT hours
                $coefficients = [
                    'holiday'    => 2.5,
                    'rest_day'   => 2.0,
                    'night_12_4' => 1.5,
                    'night_4_12' => 1.75,
                    'none'       => 0,
                ];
                $coeff = $coefficients[$otType] ?? 0;

                // Use the OT type already saved (check-in) if current punch is normal
                $finalOtType = $existing->overtime_type ?? $otType;
                if ($finalOtType === 'none') $finalOtType = $otType;
                $finalCoeff  = $coefficients[$finalOtType] ?? 0;

                $basicSalary = (float) ($employee->basic_salary ?? 0);
                $otPay = $basicSalary > 0 && $otHours > 0
                    ? round(($basicSalary / 30 / 8) * $finalCoeff * $otHours, 2)
                    : 0;

                DB::table('attendance')
                    ->where('employee_id', $employee->id)
                    ->where('attendance_date', $dateStr)
                    ->update([
                        'check_out'      => $timeStr,
                        'hours_worked'   => $hours,
                        'overtime_hours' => $otHours,
                        'overtime_type'  => $finalOtType,
                        'overtime_pay'   => $otPay,
                        'updated_at'     => now(),
                    ]);
            }
        }
    } catch (\Exception $e) {
        adms_log("ERROR in auto-sync for user_id={$userId}: " . $e->getMessage());
    }
}

