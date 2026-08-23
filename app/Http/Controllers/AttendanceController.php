<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function index()
    {
        $query = Attendance::with('employee')->latest('attendance_date');

        // Filter by date range
        if (request('date_from')) {
            $query->whereDate('attendance_date', '>=', request('date_from'));
        }
        if (request('date_to')) {
            $query->whereDate('attendance_date', '<=', request('date_to'));
        }

        // Filter by employee
        if (request('employee')) {
            $search = request('employee');
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('full_name', 'like', "%$search%")
                  ->orWhere('employee_code', 'like', "%$search%")
                  ->orWhere('first_name', 'like', "%$search%")
                  ->orWhere('last_name', 'like', "%$search%");
            });
        }

        // Filter by status
        if (request('status')) {
            $query->where('status', request('status'));
        }

        $attendances = $query->paginate(30);

        return view('hr.attendance.index', compact('attendances'));
    }

    public function create(Request $request)
    {
        $selectedDate = $request->input('date', today()->toDateString());
        $selectedEmployeeId = $request->input('employee_id');

        $employees = Employee::where('status', 'active')->orderBy('full_name')->get();

        // Fetch all existing attendance records for the selected date keyed by employee_id
        $attendances = Attendance::whereDate('attendance_date', $selectedDate)
            ->get()
            ->keyBy('employee_id');

        return view('hr.attendance.create', compact('employees', 'attendances', 'selectedDate', 'selectedEmployeeId'));
    }

    public function quickClock(Request $request)
    {
        $request->validate([
            'employee_id'     => 'required|exists:employees,id',
            'attendance_date' => 'required|date',
            'action'          => 'required|in:morning_in,morning_out,afternoon_in,afternoon_out,clock_in,clock_out,absent',
        ]);

        $employee = Employee::findOrFail($request->employee_id);
        $date = $request->attendance_date;
        $nowTime = now()->format('H:i');

        $attendance = Attendance::firstOrNew([
            'employee_id'     => $employee->id,
            'attendance_date' => $date,
        ]);

        $action = $request->action;
        if ($action === 'morning_in') {
            $attendance->morning_in = $nowTime;
            $attendance->status = 'present';
        } elseif ($action === 'morning_out') {
            $attendance->morning_out = $nowTime;
            $attendance->status = 'present';
        } elseif ($action === 'afternoon_in') {
            $attendance->afternoon_in = $nowTime;
            $attendance->status = 'present';
        } elseif ($action === 'afternoon_out') {
            $attendance->afternoon_out = $nowTime;
            $attendance->status = 'present';
        } elseif ($action === 'clock_in') {
            $attendance->morning_in = $attendance->morning_in ?? $nowTime;
            $attendance->status = 'present';
        } elseif ($action === 'clock_out') {
            $attendance->afternoon_out = $nowTime;
            $attendance->status = 'present';
        } elseif ($action === 'absent') {
            $attendance->status = 'absent';
            $attendance->morning_in = null;
            $attendance->morning_out = null;
            $attendance->afternoon_in = null;
            $attendance->afternoon_out = null;
            $attendance->check_in = null;
            $attendance->check_out = null;
            $attendance->hours_worked = 0;
        }

        $attendance->check_in = $attendance->morning_in ?? ($attendance->afternoon_in ?? $attendance->check_in);
        $attendance->check_out = $attendance->afternoon_out ?? ($attendance->morning_out ?? $attendance->check_out);

        // Recalculate total hours worked across both sessions
        $hours = 0;
        if ($attendance->morning_in && $attendance->morning_out) {
            $mIn  = \Carbon\Carbon::createFromFormat('H:i', $attendance->morning_in);
            $mOut = \Carbon\Carbon::createFromFormat('H:i', $attendance->morning_out);
            $hours += max(0, round($mOut->diffInMinutes($mIn) / 60, 2));
        }
        if ($attendance->afternoon_in && $attendance->afternoon_out) {
            $aIn  = \Carbon\Carbon::createFromFormat('H:i', $attendance->afternoon_in);
            $aOut = \Carbon\Carbon::createFromFormat('H:i', $attendance->afternoon_out);
            $hours += max(0, round($aOut->diffInMinutes($aIn) / 60, 2));
        }
        if ($hours == 0 && $attendance->check_in && $attendance->check_out) {
            $in    = \Carbon\Carbon::createFromFormat('H:i', $attendance->check_in);
            $out   = \Carbon\Carbon::createFromFormat('H:i', $attendance->check_out);
            $hours = round($out->diffInMinutes($in) / 60, 2);
        }

        $attendance->hours_worked = $hours;
        $attendance->source = 'manual_quick';
        $attendance->is_approved = true;
        $attendance->approved_by = Auth::id();
        $attendance->save();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Attendance updated for {$employee->full_name}",
                'attendance' => $attendance,
            ]);
        }

        return redirect()->route('attendance.create', ['date' => $date, 'employee_id' => $employee->id])
            ->with('success', "Updated " . str_replace('_', ' ', $action) . " for {$employee->full_name} ({$nowTime}).");
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id'     => 'required|exists:employees,id',
            'attendance_date' => 'required|date',
            'status'          => 'required|in:present,absent,half_day,leave,holiday,weekend',
            'morning_in'      => 'nullable|date_format:H:i',
            'morning_out'     => 'nullable|date_format:H:i',
            'afternoon_in'    => 'nullable|date_format:H:i',
            'afternoon_out'   => 'nullable|date_format:H:i',
            'check_in'        => 'nullable|date_format:H:i',
            'check_out'       => 'nullable|date_format:H:i',
            'overtime_hours'  => 'nullable|numeric|min:0|max:24',
            'overtime_type'   => 'nullable|in:none,holiday,rest_day,night_12_4,night_4_12',
            'notes'           => 'nullable|string',
        ]);

        $morningIn = $request->morning_in ?: null;
        $morningOut = $request->morning_out ?: null;
        $afternoonIn = $request->afternoon_in ?: null;
        $afternoonOut = $request->afternoon_out ?: null;

        $checkIn = $morningIn ?: ($afternoonIn ?: ($request->check_in ?: null));
        $checkOut = $afternoonOut ?: ($morningOut ?: ($request->check_out ?: null));

        $hours = 0;
        if ($morningIn && $morningOut) {
            $mIn  = \Carbon\Carbon::createFromFormat('H:i', $morningIn);
            $mOut = \Carbon\Carbon::createFromFormat('H:i', $morningOut);
            $hours += max(0, round($mOut->diffInMinutes($mIn) / 60, 2));
        }
        if ($afternoonIn && $afternoonOut) {
            $aIn  = \Carbon\Carbon::createFromFormat('H:i', $afternoonIn);
            $aOut = \Carbon\Carbon::createFromFormat('H:i', $afternoonOut);
            $hours += max(0, round($aOut->diffInMinutes($aIn) / 60, 2));
        }
        if ($hours == 0 && $checkIn && $checkOut) {
            $in    = \Carbon\Carbon::createFromFormat('H:i', $checkIn);
            $out   = \Carbon\Carbon::createFromFormat('H:i', $checkOut);
            $hours = round($out->diffInMinutes($in) / 60, 2);
        }

        // ── Auto-detect OT type if not explicitly set ────────────────────────
        $otHours = (float) ($request->overtime_hours ?? 0);
        $otType  = $request->overtime_type ?? 'none';

        if ($otHours > 0 && $otType === 'none') {
            $date    = \Carbon\Carbon::parse($request->attendance_date);
            $cIn = $checkIn ? \Carbon\Carbon::createFromFormat('H:i', $checkIn) : null;

            if ($request->status === 'holiday') {
                $otType = 'holiday';
            } elseif ($request->status === 'weekend' || $date->isSunday()) {
                $otType = 'rest_day';
            } elseif ($date->isSaturday()) {
                $otType = 'rest_day';
            } elseif ($cIn) {
                $hour = (int) $cIn->format('H');
                if ($hour >= 0 && $hour < 4) {
                    $otType = 'night_12_4';
                } elseif ($hour >= 16) {
                    $otType = 'night_4_12';
                }
            }
        }

        // ── Calculate OT pay ─────────────────────────────────────────────────
        $employee = \App\Models\Employee::find($request->employee_id);
        $basic    = (float) ($employee->basic_salary ?? 0);
        $otPay    = \App\Models\Payroll::calculateOvertimePay($basic, $otHours, $otType);

        Attendance::updateOrCreate(
            ['employee_id' => $request->employee_id, 'attendance_date' => $request->attendance_date],
            [
                'morning_in'     => $morningIn,
                'morning_out'    => $morningOut,
                'afternoon_in'   => $afternoonIn,
                'afternoon_out'  => $afternoonOut,
                'check_in'       => $checkIn,
                'check_out'      => $checkOut,
                'hours_worked'   => $hours,
                'status'         => $request->status,
                'source'         => 'manual',
                'notes'          => $request->notes,
                'is_approved'    => true,
                'approved_by'    => Auth::id(),
                'overtime_hours' => $otHours,
                'overtime_type'  => $otType,
                'overtime_pay'   => $otPay,
            ]
        );

        return redirect()->route('attendance.create', ['date' => $request->attendance_date, 'employee_id' => $request->employee_id])
                         ->with('success', 'Attendance record saved for ' . ($employee->full_name ?? 'Employee'));
    }

    public function bulkStore(Request $request)
    {
        $request->validate([
            'attendance_date'              => 'required|date',
            'records'                      => 'required|array',
            'records.*.employee_id'        => 'required|exists:employees,id',
            'records.*.status'             => 'required|in:present,absent,half_day,leave,holiday,weekend',
        ]);

        $count = 0;
        foreach ($request->records as $rec) {
            Attendance::updateOrCreate(
                ['employee_id' => $rec['employee_id'], 'attendance_date' => $request->attendance_date],
                [
                    'status' => $rec['status'],
                    'source' => 'bulk_upload',
                    'is_approved' => true,
                    'approved_by' => Auth::id(),
                ]
            );
            $count++;
        }

        return back()->with('success', "$count attendance records saved successfully.");
    }

    public function deviceLogs()
    {
        $query = \App\Models\DeviceAttendanceLog::with('employee')->latest('punch_time');

        if (request('date_from')) {
            $query->whereDate('punch_time', '>=', request('date_from'));
        }
        if (request('date_to')) {
            $query->whereDate('punch_time', '<=', request('date_to'));
        }
        if (request('linked') === 'linked') {
            $query->whereHas('employee');
        } elseif (request('linked') === 'unlinked') {
            $query->whereDoesntHave('employee');
        }

        $logs = $query->paginate(50);
        return view('hr.attendance.device_logs', compact('logs'));
    }

    /**
     * Manually trigger ZKTeco punch → attendance sync via Artisan command.
     */
    public function syncZkteco(Request $request)
    {
        $date  = $request->input('date', now()->format('Y-m-d'));
        $force = $request->boolean('force', false);

        try {
            $args = ['--date' => $date];
            if ($force) {
                $args['--force'] = true;
            }

            Artisan::call('zkteco:sync', $args);
            $output = trim(Artisan::output());

            return redirect()
                ->route('attendance.deviceLogs')
                ->with('success', "ZKTeco sync completed for {$date}. " . ($output ? strip_tags($output) : ''));

        } catch (\Exception $e) {
            return redirect()
                ->route('attendance.deviceLogs')
                ->with('error', 'Sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Show device status page — last heartbeat per device.
     */
    public function zktecoStatus()
    {
        $devices = DB::table('zk_devices')->orderBy('last_seen_at', 'desc')->get();

        // Count unsynced punches per device
        $unsyncedCounts = DB::table('device_attendance_logs')
            ->whereNull('synced_at')
            ->selectRaw('device_sn, COUNT(*) as cnt')
            ->groupBy('device_sn')
            ->pluck('cnt', 'device_sn');

        // Total device logs today
        $todayPunches = DB::table('device_attendance_logs')
            ->whereDate('punch_time', now()->format('Y-m-d'))
            ->count();

        // Unmatched user IDs (device_user_id not in any employee)
        $unmatchedIds = DB::table('device_attendance_logs')
            ->leftJoin('employees', 'employees.device_user_id', '=', 'device_attendance_logs.device_user_id')
            ->whereNull('employees.id')
            ->distinct()
            ->pluck('device_attendance_logs.device_user_id');

        return view('hr.attendance.zkteco_status', compact(
            'devices', 'unsyncedCounts', 'todayPunches', 'unmatchedIds'
        ));
    }

    /**
     * Interactive Machine & Biometric Connection Test Page
     */
    public function machineTest()
    {
        $devices = DB::table('zk_devices')->orderBy('last_seen_at', 'desc')->get();
        $recentLogs = DB::table('device_attendance_logs')
            ->leftJoin('employees', 'employees.device_user_id', '=', 'device_attendance_logs.device_user_id')
            ->select('device_attendance_logs.*', 'employees.full_name as employee_name', 'employees.department as employee_department')
            ->orderBy('device_attendance_logs.created_at', 'desc')
            ->take(30)
            ->get();

        $employees = Employee::where(function($q) {
            $q->where('status', 'active')->orWhereNull('status');
        })->orderBy('full_name')->get();

        $admsLogFile = public_path('iclock/adms.log');
        $rawAdmsLogs = file_exists($admsLogFile) ? file_get_contents($admsLogFile) : 'No incoming device requests recorded yet.';
        $rawAdmsLogLines = array_filter(explode("\n", $rawAdmsLogs));
        $latestRawLogs = implode("\n", array_slice($rawAdmsLogLines, -50));

        return view('hr.attendance.machine_test', compact('devices', 'recentLogs', 'employees', 'latestRawLogs'));
    }

    /**
     * Simulate a Live Device Punch to verify database sync
     */
    public function simulateTestPunch(Request $request)
    {
        $request->validate([
            'device_user_id' => 'required|string',
            'punch_state'    => 'required|in:0,1,4,5', // 0: In, 1: Out
            'device_sn'      => 'nullable|string',
        ]);

        $deviceSn = $request->input('device_sn', 'TEST-DEVICE-01');
        $deviceUserId = $request->input('device_user_id');
        $punchTime = now()->format('Y-m-d H:i:s');
        $punchState = (int)$request->input('punch_state');

        // Insert into device_attendance_logs
        $logId = DB::table('device_attendance_logs')->insertGetId([
            'device_sn'       => $deviceSn,
            'device_user_id'  => $deviceUserId,
            'punch_time'      => $punchTime,
            'punch_state'     => $punchState,
            'verify_type'     => 1, // Fingerprint/Face
            'work_code'       => '0',
            'raw_payload'     => "TEST PUNCH SIMULATION: PIN={$deviceUserId}\tTIME={$punchTime}\tSTATE={$punchState}",
            'synced_at'       => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // Auto trigger sync
        try {
            Artisan::call('zkteco:sync', ['--date' => now()->format('Y-m-d'), '--force' => true]);
        } catch (\Throwable $e) {}

        return redirect()->back()->with('success', "Test punch successfully received for User ID: {$deviceUserId} (Punch ID #{$logId}) at {$punchTime}! Attendance sync completed.");
    }

    /**
     * Clear and delete all test logs & raw machine logs
     */
    public function clearTestLogs(Request $request)
    {
        // 1. Wipe adms.log
        $admsLogFile = public_path('iclock/adms.log');
        if (file_exists($admsLogFile)) {
            file_put_contents($admsLogFile, "[".date('Y-m-d H:i:s')."] Cleaned test logs.\n");
        }

        // 2. Optionally delete test entries
        if ($request->has('delete_all_logs')) {
            DB::table('device_attendance_logs')->where('device_sn', 'TEST-DEVICE-01')->orWhere('raw_payload', 'LIKE', '%TEST%')->delete();
        }

        return redirect()->back()->with('success', 'Test log entries and machine diagnostics log have been cleanly reset!');
    }
}
