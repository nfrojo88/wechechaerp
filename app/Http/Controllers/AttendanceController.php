<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
                    'status'      => $rec['status'],
                    'source'      => 'bulk_upload',
                    'is_approved' => true,
                    'approved_by' => Auth::id(),
                ]
            );
            $count++;
        }

        return back()->with('success', "$count attendance records saved successfully.");
    }

    /**
     * Import attendance from a biometric machine XLS export (BIFF2 format)
     * or a CSV file in the same column layout.
     *
     * Expected XLS columns:
     *   Emp No. | AC-No. | Name | Date | Timetable | On duty | Off duty |
     *   Clock In | Clock Out | Normal | Late | Early | Absent | OT Time | Work Time | Department
     */
    public function importXls(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240',
                function ($attribute, $value, $fail) {
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (!in_array($ext, ['xls', 'xlsx', 'csv', 'txt'])) {
                        $fail('The uploaded file must be an XLS, XLSX, or CSV file.');
                    }
                },
            ],
        ]);

        $file     = $request->file('file');
        $ext      = strtolower($file->getClientOriginalExtension());
        $tmpPath  = $file->getRealPath();

        // ── Clear previous attendance history if requested ────────────────
        if ($request->boolean('clear_before_import')) {
            Attendance::truncate();
            \App\Models\DeviceAttendanceLog::truncate();
        }

        // ── Parse the file ────────────────────────────────────────────────
        $records = [];

        if ($ext === 'csv') {
            $records = $this->parseCsvAttendance($tmpPath);
        } elseif ($ext === 'xls') {
            $parser  = new \App\Services\AttendanceXlsParser();
            $records = $parser->parse($tmpPath);
        } elseif ($ext === 'xlsx') {
            // openspout is installed for xlsx support
            $records = $this->parseXlsxAttendance($tmpPath);
        }

        if (empty($records)) {
            return back()->with('error', 'No records found in the uploaded file. Please check the file format.');
        }

        // ── Load all employees for matching ───────────────────────────────
        $employees = Employee::select('id', 'employee_code', 'device_user_id', 'full_name', 'basic_salary')->get();

        $normalizeName = function($name) {
            $name = preg_replace('/[._\s]+/', ' ', strtolower(trim((string)$name)));
            return preg_replace('/[^a-z0-9 ]/', '', $name);
        };

        // Multi-index mapping
        $empByCode     = [];
        $empByDevice   = [];
        $empById       = [];
        $empByName     = [];
        $empByCodeNum  = [];

        foreach ($employees as $e) {
            $empById[$e->id] = $e;

            if (!empty($e->employee_code)) {
                $cUpper = strtoupper(trim($e->employee_code));
                $empByCode[$cUpper] = $e;
                if (preg_match('/(\d+)/', $cUpper, $m)) {
                    $empByCodeNum[(int)$m[1]] = $e;
                }
            }

            if (!empty($e->device_user_id)) {
                $dUpper = strtoupper(trim((string)$e->device_user_id));
                $empByDevice[$dUpper] = $e;
                if (is_numeric($dUpper)) {
                    $empByDevice[(int)$dUpper] = $e;
                }
            }

            if (!empty($e->full_name)) {
                $norm = $normalizeName($e->full_name);
                if ($norm) {
                    $empByName[$norm] = $e;
                }
            }
        }

        // ── Group records by (employee, date) and merge Morning+Afternoon ─
        $grouped = []; // [userKey][date] => merged record

        foreach ($records as $rec) {
            $empNo    = trim($rec['Emp No.']  ?? $rec['emp_no']  ?? '');
            $acNo     = trim($rec['AC-No.']   ?? $rec['ac_no']   ?? '');
            $empName  = trim($rec['Name']     ?? $rec['name']    ?? '');
            $dateRaw  = trim($rec['Date']     ?? $rec['date']    ?? '');
            $session  = trim($rec['Timetable'] ?? $rec['timetable'] ?? '');
            $clockIn  = trim($rec['Clock In']  ?? $rec['clock_in']  ?? '');
            $clockOut = trim($rec['Clock Out'] ?? $rec['clock_out'] ?? '');
            $absent   = trim($rec['Absent']    ?? $rec['absent']   ?? '');
            $late     = trim($rec['Late']      ?? $rec['late']     ?? '');
            $otTime   = trim($rec['OT Time']   ?? $rec['ot_time']  ?? '');
            $workTime = trim($rec['Work Time'] ?? $rec['work_time'] ?? '');

            if (empty($dateRaw) || ($acNo === '' && $empNo === '' && $empName === '')) {
                continue;
            }

            // Extract Attendance Date from 'Date' column
            $date = null;
            try {
                $date = Carbon::parse($dateRaw)->format('Y-m-d');
            } catch (\Exception $e) {
                if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $dateRaw, $m)) {
                    try {
                        $date = Carbon::createFromDate($m[3], $m[1], $m[2])->format('Y-m-d');
                    } catch (\Exception $e2) {}
                }
            }

            if (!$date) {
                continue;
            }

            // Primary unique identifier in device export is AC-No. (ZKTeco Device User ID)
            $userKey = $acNo !== '' ? "AC:{$acNo}" : ($empNo !== '' ? "EMP:{$empNo}" : "NAME:{$empName}");

            if (!isset($grouped[$userKey][$date])) {
                $grouped[$userKey][$date] = [
                    'emp_no'        => $empNo,
                    'ac_no'         => $acNo,
                    'emp_name'      => $empName,
                    'morning_in'    => null,
                    'morning_out'   => null,
                    'afternoon_in'  => null,
                    'afternoon_out' => null,
                    'absent'        => false,
                    'late_mins'     => 0,
                    'ot_hours'      => 0,
                    'work_hours'    => 0,
                ];
            }

            $isMorning   = stripos($session, 'morning') !== false;
            $isAfternoon = stripos($session, 'afternoon') !== false || stripos($session, 'evening') !== false;

            // Map clock times
            if ($isMorning) {
                if (!empty($clockIn))  $grouped[$userKey][$date]['morning_in']  = $clockIn;
                if (!empty($clockOut)) $grouped[$userKey][$date]['morning_out'] = $clockOut;
            } elseif ($isAfternoon) {
                if (!empty($clockIn))  $grouped[$userKey][$date]['afternoon_in']  = $clockIn;
                if (!empty($clockOut)) $grouped[$userKey][$date]['afternoon_out'] = $clockOut;
            } else {
                // Single session or unknown — treat as clock_in/out
                if (!empty($clockIn))  $grouped[$userKey][$date]['morning_in']  = $clockIn;
                if (!empty($clockOut)) $grouped[$userKey][$date]['afternoon_out'] = $clockOut;
            }

            // Accumulate absent/late/OT
            if (strtolower($absent) === 'true' || $absent === '1') {
                if ($isMorning && empty($clockIn)) {
                    $grouped[$userKey][$date]['absent_morning'] = true;
                }
                if ($isAfternoon && empty($clockIn)) {
                    $grouped[$userKey][$date]['absent_afternoon'] = true;
                }
            }

            if (!empty($late) && is_numeric($late)) {
                $grouped[$userKey][$date]['late_mins'] += (float) $late;
            }

            if (!empty($otTime) && is_numeric($otTime)) {
                $grouped[$userKey][$date]['ot_hours'] = max($grouped[$userKey][$date]['ot_hours'], (float) $otTime);
            }

            if (!empty($workTime) && is_numeric($workTime)) {
                $grouped[$userKey][$date]['work_hours'] += (float) $workTime;
            }
        }

        // ── Upsert attendance records ─────────────────────────────────────
        $saved   = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($grouped as $userKey => $dates) {
            $firstEntry = array_values($dates)[0] ?? [];
            $acNo       = trim($firstEntry['ac_no'] ?? '');
            $empNo      = trim($firstEntry['emp_no'] ?? '');
            $rawName    = trim($firstEntry['emp_name'] ?? '');

            $employee = null;

            // 1. PRIMARY: Match AC-No. directly with Employee's ZKTeco Device User ID (device_user_id)
            if ($acNo !== '') {
                $acUpper = strtoupper($acNo);
                if (isset($empByDevice[$acUpper])) {
                    $employee = $empByDevice[$acUpper];
                } elseif (is_numeric($acNo) && isset($empByDevice[(int)$acNo])) {
                    $employee = $empByDevice[(int)$acNo];
                } elseif (is_numeric($acNo) && isset($empByDevice[(string)(int)$acNo])) {
                    $employee = $empByDevice[(string)(int)$acNo];
                }
            }

            // 2. SECONDARY: Match AC-No. against employee_code numeric part or internal ID
            if (!$employee && $acNo !== '' && is_numeric($acNo)) {
                $acInt = (int)$acNo;
                if (isset($empByCodeNum[$acInt])) {
                    $employee = $empByCodeNum[$acInt];
                } elseif (isset($empById[$acInt])) {
                    $employee = $empById[$acInt];
                }
            }

            // 3. TERTIARY: Match Emp No. against device_user_id or employee_code
            if (!$employee && $empNo !== '') {
                $empUpper = strtoupper($empNo);
                if (isset($empByDevice[$empUpper])) {
                    $employee = $empByDevice[$empUpper];
                } elseif (isset($empByCode[$empUpper])) {
                    $employee = $empByCode[$empUpper];
                } elseif (is_numeric($empNo) && isset($empByCodeNum[(int)$empNo])) {
                    $employee = $empByCodeNum[(int)$empNo];
                } elseif (is_numeric($empNo) && isset($empById[(int)$empNo])) {
                    $employee = $empById[(int)$empNo];
                }
            }

            // 4. FALLBACK: Match normalized Name against employee full_name
            if (!$employee && $rawName !== '') {
                $normName = $normalizeName($rawName);
                if ($normName !== '' && isset($empByName[$normName])) {
                    $employee = $empByName[$normName];
                }
            }

            if (!$employee) {
                $skipped++;
                $label = $acNo !== '' ? "AC-No. (Device ID: {$acNo})" : "Emp No. '{$empNo}'";
                if ($rawName !== '') {
                    $label .= " [Name: {$rawName}]";
                }
                $errors[] = "Employee not matched: {$label}. Set 'ZKTeco Device User ID' to '{$acNo}' on the employee form.";
                continue;
            }

            foreach ($dates as $date => $info) {
                // Determine status
                $hasMorningIn  = !empty($info['morning_in']);
                $hasAfternoonIn = !empty($info['afternoon_in']);

                if ($hasMorningIn && $hasAfternoonIn) {
                    $status = 'present';
                } elseif ($hasMorningIn || $hasAfternoonIn) {
                    $status = 'half_day';
                } elseif (!empty($info['absent_morning']) && !empty($info['absent_afternoon'])) {
                    $status = 'absent';
                } elseif (!empty($info['absent_morning']) || !empty($info['absent_afternoon'])) {
                    $status = 'half_day';
                } else {
                    $status = 'absent';
                }

                // Calculate hours worked
                $hours = (float) $info['work_hours'];
                if ($hours == 0) {
                    if ($info['morning_in'] && $info['morning_out']) {
                        try {
                            $mIn  = Carbon::createFromFormat('H:i', $info['morning_in']);
                            $mOut = Carbon::createFromFormat('H:i', $info['morning_out']);
                            $hours += max(0, round($mOut->diffInMinutes($mIn) / 60, 2));
                        } catch (\Exception $e) {}
                    }
                    if ($info['afternoon_in'] && $info['afternoon_out']) {
                        try {
                            $aIn  = Carbon::createFromFormat('H:i', $info['afternoon_in']);
                            $aOut = Carbon::createFromFormat('H:i', $info['afternoon_out']);
                            $hours += max(0, round($aOut->diffInMinutes($aIn) / 60, 2));
                        } catch (\Exception $e) {}
                    }
                }

                $checkIn  = $info['morning_in']    ?: ($info['afternoon_in']  ?: null);
                $checkOut = $info['afternoon_out']  ?: ($info['morning_out']  ?: null);

                // OT calculation
                $otHours = (float) ($info['ot_hours'] ?? 0);
                $otType  = 'none';
                if ($otHours > 0) {
                    $dayOfWeek = Carbon::parse($date)->dayOfWeek;
                    if ($dayOfWeek === 0 || $dayOfWeek === 6) {
                        $otType = 'rest_day';
                    }
                }

                $basic = (float) ($employee->basic_salary ?? 0);
                $otPay = \App\Models\Payroll::calculateOvertimePay($basic, $otHours, $otType);

                $toTime = fn($val) => (!empty($val) && trim((string)$val) !== '') ? trim((string)$val) : null;

                Attendance::updateOrCreate(
                    [
                        'employee_id'     => $employee->id,
                        'attendance_date' => $date,
                    ],
                    [
                        'morning_in'     => $toTime($info['morning_in']),
                        'morning_out'    => $toTime($info['morning_out']),
                        'afternoon_in'   => $toTime($info['afternoon_in']),
                        'afternoon_out'  => $toTime($info['afternoon_out']),
                        'check_in'       => $toTime($checkIn),
                        'check_out'      => $toTime($checkOut),
                        'hours_worked'   => $hours,
                        'status'         => $status,
                        'source'         => 'bulk_upload',
                        'is_approved'    => true,
                        'approved_by'    => Auth::id(),
                        'overtime_hours' => $otHours,
                        'overtime_type'       => $otType,
                        'overtime_pay'        => $otPay,
                        'biometric_device_id' => $acNo ?: ($employee->device_user_id ?: null),
                        'notes'               => $info['late_mins'] > 0 ? "Late: {$info['late_mins']} min" : null,
                    ]
                );
                $saved++;
            }
        }

        $message = "✅ Import complete: {$saved} attendance records saved.";
        if ($skipped > 0) {
            $message .= " ⚠️ {$skipped} employees not matched (check employee codes).";
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'saved'   => $saved,
                'skipped' => $skipped,
                'errors'  => $errors,
                'message' => $message,
            ]);
        }

        $status = $skipped > 0 ? 'warning' : 'success';
        return redirect()->route('attendance.index')->with($status, $message);
    }

    /**
     * Parse a CSV file in the biometric machine export format.
     */
    private function parseCsvAttendance(string $filePath): array
    {
        $rows    = [];
        $headers = null;

        if (($handle = fopen($filePath, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if ($headers === null) {
                    $headers = array_map('trim', $row);
                    continue;
                }
                if (count($row) < 2) {
                    continue;
                }
                $rows[] = array_combine($headers, array_pad($row, count($headers), null));
            }
            fclose($handle);
        }

        return $rows;
    }

    /**
     * Parse an XLSX file using openspout.
     */
    private function parseXlsxAttendance(string $filePath): array
    {
        $rows    = [];
        $headers = null;

        try {
            $reader = \OpenSpout\Reader\XLSX\Reader::create();
            $reader->open($filePath);

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = $row->getCells();
                    $values = [];
                    foreach ($cells as $cell) {
                        $values[] = $cell->getValue();
                    }

                    if ($headers === null) {
                        $headers = array_map(fn($v) => trim((string) $v), $values);
                        continue;
                    }

                    if (empty(array_filter($values))) {
                        continue;
                    }

                    $rows[] = array_combine($headers, array_pad($values, count($headers), null));
                }
                break; // First sheet only
            }

            $reader->close();
        } catch (\Exception $e) {
            // Return empty if XLSX reading fails
        }

        return $rows;
    }

    /**
     * Download a sample CSV template matching the biometric machine export format.
     */
    public function downloadTemplate()
    {
        $headers = [
            'Emp No.', 'AC-No.', 'No.', 'Name', 'Auto-Assign', 'Date',
            'Timetable', 'On duty', 'Off duty', 'Clock In', 'Clock Out',
            'Normal', 'Real time', 'Late', 'Early', 'Absent', 'OT Time',
            'Work Time', 'Exception', 'Must C/In', 'Must C/Out', 'Department',
        ];

        $sample = [
            ['EMP001', '1', '', 'John Doe', '', date('n/j/Y'), 'Morning', '08:30', '12:30', '08:25', '12:35', '0.5', '0.5', '', '', '', '', '4', '', 'True', 'True', 'Office'],
            ['EMP001', '1', '', 'John Doe', '', date('n/j/Y'), 'Afternoon', '13:30', '17:30', '13:28', '17:35', '0.5', '0.5', '', '', '', '0.1', '4.1', '', 'True', 'True', 'Office'],
        ];

        $csv = implode(',', $headers) . "\r\n";
        foreach ($sample as $row) {
            $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $row)) . "\r\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="attendance-import-template.csv"',
        ]);
    }

    /**
     * Clear all attendance records & raw device logs to start from scratch.
     */
    public function clearHistory(Request $request)
    {
        Attendance::truncate();
        \App\Models\DeviceAttendanceLog::truncate();

        return redirect()->route('attendance.index')->with('success', 'All previous attendance history has been cleared successfully. You can now start uploading from scratch.');
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
        $deviceUserId = (string)$request->input('device_user_id');
        $punchTime = now()->format('Y-m-d H:i:s');
        $punchState = (string)$request->input('punch_state', '0');

        $emp = Employee::where('device_user_id', $deviceUserId)
            ->orWhere('id', $deviceUserId)
            ->first();

        if ($emp && empty($emp->device_user_id)) {
            $emp->device_user_id = $deviceUserId;
            $emp->save();
        }

        $fullName = $emp ? $emp->full_name : null;

        // Insert into device_attendance_logs matching exact schema
        $logId = DB::table('device_attendance_logs')->insertGetId([
            'device_sn'       => $deviceSn,
            'device_user_id'  => $deviceUserId,
            'punch_time'      => $punchTime,
            'status'          => $punchState,
            'verify_mode'     => '1',
            'full_name'       => $fullName,
            'synced_at'       => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // Auto trigger sync
        try {
            Artisan::call('zkteco:sync', ['--date' => now()->format('Y-m-d'), '--force' => true]);
        } catch (\Throwable $e) {}

        return redirect()->back()->with('success', "Test punch successfully received for " . ($fullName ? "{$fullName} (PIN: {$deviceUserId})" : "User PIN #{$deviceUserId}") . " [Punch Log #{$logId}] at {$punchTime}! Attendance synced.");
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

        // 2. Delete test entries
        if ($request->has('delete_all_logs')) {
            DB::table('device_attendance_logs')->where('device_sn', 'TEST-DEVICE-01')->delete();
        }

        return redirect()->back()->with('success', 'Test log entries and machine diagnostics log have been cleanly reset!');
    }
}
