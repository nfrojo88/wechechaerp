<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\DeviceAttendanceLog;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function index()
    {
        $query = Attendance::with('employee')->whereHas('employee')->latest('attendance_date');

        // Filter by specific single date, month, or date range
        $selectedDate = request('date');
        $selectedMonth = request('month');

        if ($selectedDate) {
            $query->whereDate('attendance_date', $selectedDate);
        } elseif ($selectedMonth) {
            $parts = explode('-', $selectedMonth);
            if (count($parts) === 2) {
                $query->whereYear('attendance_date', (int)$parts[0])
                      ->whereMonth('attendance_date', (int)$parts[1]);
            }
        } else {
            if (request('date_from')) {
                $query->whereDate('attendance_date', '>=', request('date_from'));
            }
            if (request('date_to')) {
                $query->whereDate('attendance_date', '<=', request('date_to'));
            }
        }

        // Filter by employee (name, code, or ZKTeco device ID)
        if (request('employee')) {
            $search = request('employee');
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('full_name', 'like', "%$search%")
                  ->orWhere('employee_code', 'like', "%$search%")
                  ->orWhere('device_user_id', 'like', "%$search%");
            });
        }

        // Filter by status or only records with punches added
        if (request('status')) {
            $query->where('status', request('status'));
        } elseif (!request()->has('show_all')) {
            // "if not added don't show in attendance section": only show records with actual clock times
            $query->where(function ($q) {
                $q->whereNotNull('morning_in')
                  ->orWhereNotNull('morning_out')
                  ->orWhereNotNull('afternoon_in')
                  ->orWhereNotNull('afternoon_out')
                  ->orWhereNotNull('check_in')
                  ->orWhereNotNull('check_out');
            });
        }

        // ── Auto-heal & Normalize Saturday Attendance Records ─────────────────
        // Under company policy, Saturday operates strictly on a Morning Session (4.0 hrs).
        // Employees who attended the morning session or worked their hours (>= 2.0h or had morning punches)
        // are recognized as FULLY 'present', NOT 'half_day'.
        try {
            $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
            if ($driver === 'sqlite') {
                Attendance::whereRaw("strftime('%w', attendance_date) = '6'")
                    ->where('status', 'half_day')
                    ->where(function ($q) {
                        $q->whereNotNull('morning_in')
                          ->orWhereNotNull('morning_out')
                          ->orWhereNotNull('check_in')
                          ->orWhere('hours_worked', '>=', 2.0);
                    })
                    ->update(['status' => 'present']);
            } else {
                Attendance::whereRaw('DAYOFWEEK(attendance_date) = 7')
                    ->where('status', 'half_day')
                    ->where(function ($q) {
                        $q->whereNotNull('morning_in')
                          ->orWhereNotNull('morning_out')
                          ->orWhereNotNull('check_in')
                          ->orWhere('hours_worked', '>=', 2.0);
                    })
                    ->update(['status' => 'present']);
            }
        } catch (\Throwable $e) {
            $targetDate = $selectedDate ?: (request('date_from') ?: today()->toDateString());
            if (\Carbon\Carbon::parse($targetDate)->isSaturday()) {
                Attendance::whereDate('attendance_date', $targetDate)
                    ->where('status', 'half_day')
                    ->where(function ($q) {
                        $q->whereNotNull('morning_in')
                          ->orWhereNotNull('morning_out')
                          ->orWhereNotNull('check_in')
                          ->orWhere('hours_worked', '>=', 2.0);
                    })
                    ->update(['status' => 'present']);
            }
        }

        $attendances = $query->paginate(30)->withQueryString();

        // Fetch distinct available dates from attendance records for navigation
        $availableDates = Attendance::select('attendance_date')
            ->distinct()
            ->whereNotNull('attendance_date')
            ->orderBy('attendance_date', 'desc')
            ->get()
            ->map(fn($a) => $a->attendance_date ? $a->attendance_date->format('Y-m-d') : null)
            ->filter()
            ->values();

        // Build list of distinct available months with English & Ethiopian labels
        $availableMonths = $availableDates->map(function ($dt) {
            $c = \Carbon\Carbon::parse($dt);
            $et = \App\Helpers\EthiopianCalendar::toEthiopian($dt);
            $etMonthName = $et['month_am'] ?? '';
            return [
                'value'    => $c->format('Y-m'),
                'label_en' => $c->format('F Y'),
                'label_et' => $etMonthName ? ($etMonthName . ' ' . ($et['year'] ?? '')) : '',
            ];
        })->unique('value')->values();

        // Build rich labels for each date
        $availableDatesWithLabels = $availableDates->map(function ($dt) {
            $c = \Carbon\Carbon::parse($dt);
            $et = \App\Helpers\EthiopianCalendar::toEthiopian($dt);
            $etBadge = $et['short_am'] ?? '';
            return [
                'date'       => $dt,
                'month'      => $c->format('Y-m'),
                'label_en'   => $c->format('M d, Y (D)'),
                'label_et'   => $etBadge,
                'full_label' => $c->format('M d, Y (D)') . ($etBadge ? ' — ' . $etBadge : ''),
            ];
        });

        // Determine date/period for statistics cards
        $statsQuery = Attendance::query();
        if ($selectedDate) {
            $statsTitle = \Carbon\Carbon::parse($selectedDate)->format('M d, Y');
            $statsEt = \App\Helpers\EthiopianCalendar::format($selectedDate, 'am');
            $statsQuery->whereDate('attendance_date', $selectedDate);
            $statsDate = $selectedDate;
        } elseif ($selectedMonth) {
            $mParts = explode('-', $selectedMonth);
            $mYear = (int)$mParts[0];
            $mNum = (int)$mParts[1];
            $statsTitle = \Carbon\Carbon::createFromFormat('Y-m', $selectedMonth)->format('F Y');
            $etM = \App\Helpers\EthiopianCalendar::toEthiopian($selectedMonth . '-01');
            $statsEt = ($etM['month_am'] ?? '') . ' ' . ($etM['year'] ?? '');
            $statsQuery->whereYear('attendance_date', $mYear)->whereMonth('attendance_date', $mNum);
            $statsDate = $selectedMonth;
        } elseif (request('date_from') || request('date_to')) {
            $statsTitle = 'Selected Date Range';
            $statsEt = (request('date_from') ?: '—') . ' to ' . (request('date_to') ?: '—');
            if (request('date_from')) $statsQuery->whereDate('attendance_date', '>=', request('date_from'));
            if (request('date_to')) $statsQuery->whereDate('attendance_date', '<=', request('date_to'));
            $statsDate = request('date_from') ?: today()->toDateString();
        } else {
            $fallbackDate = $availableDates->first() ?? today()->toDateString();
            $statsTitle = \Carbon\Carbon::parse($fallbackDate)->format('M d, Y');
            $statsEt = \App\Helpers\EthiopianCalendar::format($fallbackDate, 'am');
            $statsQuery->whereDate('attendance_date', $fallbackDate);
            $statsDate = $fallbackDate;
        }

        $stats = [
            'date'      => $statsDate,
            'title'     => $statsTitle,
            'et_title'  => $statsEt,
            'present'   => (clone $statsQuery)->where('status', 'present')->count(),
            'half_day'  => (clone $statsQuery)->where('status', 'half_day')->count(),
            'absent'    => (clone $statsQuery)->where('status', 'absent')->count(),
            'leave'     => (clone $statsQuery)->where('status', 'leave')->count(),
        ];

        $allEmployees = Employee::where('status', 'active')->orderBy('full_name')->get();
        $workSchedule = \App\Helpers\EthiopianCalendar::getWorkSchedule();
        $projects     = \App\Models\Project::orderBy('name')->get();

        return view('hr.attendance.index', compact(
            'attendances',
            'availableDates',
            'availableMonths',
            'availableDatesWithLabels',
            'stats',
            'selectedDate',
            'selectedMonth',
            'allEmployees',
            'workSchedule',
            'projects'
        ));
    }

    /**
     * Record Site Attendance when employee goes to a construction site.
     * (Employee is on-site so not captured by office biometric device).
     */
    public function recordSiteAttendance(Request $request)
    {
        $validated = $request->validate([
            'employee_id'    => 'required|exists:employees,id',
            'project_id'     => 'nullable|exists:projects,id',
            'site_name'      => 'nullable|string|max:255',
            'attendance_date'=> 'required|date',
            'task_notes'     => 'nullable|string|max:255',
            'hours_worked'   => 'nullable|numeric|min:1|max:24',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $date     = $validated['attendance_date'];
        $hours    = (float) ($validated['hours_worked'] ?? 8.0);

        $projectName = 'Job Site';
        if (!empty($validated['project_id'])) {
            $proj = \App\Models\Project::find($validated['project_id']);
            if ($proj) {
                $projectName = $proj->name;
            }
        } elseif (!empty($validated['site_name'])) {
            $projectName = trim($validated['site_name']);
        }

        $note = "On-Site: {$projectName}" . (!empty($validated['task_notes']) ? " ({$validated['task_notes']})" : "");

        $workSchedule = \App\Helpers\EthiopianCalendar::getWorkSchedule();
        $dateCarbon = \Carbon\Carbon::parse($date);
        $isSaturday = $dateCarbon->isSaturday();

        if ($isSaturday) {
            $mIn   = $workSchedule['sat_morning_in'] ?? '08:30';
            $mOut  = $workSchedule['sat_morning_out'] ?? '12:30';
            $aIn   = null;
            $aOut  = null;
            $cIn   = $mIn;
            $cOut  = $mOut;
            $hours = (float)($workSchedule['sat_total_hours'] ?? 4.0);
        } else {
            $mIn   = $workSchedule['morning_in'] ?? '08:30';
            $mOut  = $workSchedule['morning_out'] ?? '12:30';
            $aIn   = $workSchedule['afternoon_in'] ?? '13:30';
            $aOut  = $workSchedule['afternoon_out'] ?? '17:30';
            $cIn   = $mIn;
            $cOut  = $aOut;
            $hours = (float)($workSchedule['total_hours'] ?? 8.0);
        }

        Attendance::updateOrCreate(
            [
                'employee_id'     => $employee->id,
                'attendance_date' => $date,
            ],
            [
                'status'         => 'present',
                'source'         => 'manual',
                'morning_in'     => $mIn,
                'morning_out'    => $mOut,
                'afternoon_in'   => $aIn,
                'afternoon_out'  => $aOut,
                'check_in'       => $cIn,
                'check_out'      => $cOut,
                'hours_worked'   => $hours,
                'notes'          => $note,
                'is_approved'    => true,
                'approved_by'    => Auth::id(),
            ]
        );

        return redirect()->back()->with('success', "On-site attendance recorded for {$employee->full_name} on {$projectName}.");
    }

    /**
     * Update Company Work Schedule settings (working hours and break/non-work periods).
     */
    public function updateSchedule(Request $request)
    {
        $validated = $request->validate([
            'morning_in'      => 'required|date_format:H:i',
            'morning_out'     => 'required|date_format:H:i',
            'break_start'     => 'required|date_format:H:i',
            'break_end'       => 'required|date_format:H:i',
            'afternoon_in'    => 'required|date_format:H:i',
            'afternoon_out'   => 'required|date_format:H:i',
            'sat_morning_in'  => 'required|date_format:H:i',
            'sat_morning_out' => 'required|date_format:H:i',
            'sat_work_mode'   => 'nullable|string',
            'work_days'       => 'nullable|string',
            'title'           => 'nullable|string',
        ]);

        \App\Helpers\EthiopianCalendar::saveWorkSchedule($validated);

        return redirect()->back()->with('success', 'Work schedule & working hours policy updated successfully.');
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
        if ($request->input('action') === 'update_schedule') {
            return $this->updateSchedule($request);
        }

        if ($request->input('action') === 'record_site_attendance') {
            return $this->recordSiteAttendance($request);
        }

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

        // ── Load employees with assigned ZKTeco Device User ID ─────────────
        // STRICT RULE: Only employees who have an explicit device_user_id are eligible.
        // Absolutely NO guessing by employee code, ID numbers, or names.
        $employees = Employee::whereNotNull('device_user_id')
            ->where('device_user_id', '!=', '')
            ->select('id', 'employee_code', 'device_user_id', 'full_name', 'basic_salary')
            ->get();

        $empByDevice = [];
        foreach ($employees as $e) {
            $rawDev = trim((string)$e->device_user_id);
            if ($rawDev !== '') {
                $dUpper = strtoupper($rawDev);
                $empByDevice[$dUpper] = $e;
                if (is_numeric($rawDev)) {
                    $empByDevice[(int)$rawDev] = $e;
                    $empByDevice[(string)(int)$rawDev] = $e;
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

            if (!empty($late)) {
                if (preg_match('/^(\d+):(\d+)$/', $late, $lm)) {
                    $grouped[$userKey][$date]['late_mins'] += ((int)$lm[1] * 60) + (int)$lm[2];
                } elseif (is_numeric($late)) {
                    $grouped[$userKey][$date]['late_mins'] += (float) $late;
                }
            }

            if (!empty($otTime)) {
                if (preg_match('/^(\d+):(\d+)$/', $otTime, $om)) {
                    $otH = (int)$om[1] + ((int)$om[2] / 60);
                    $grouped[$userKey][$date]['ot_hours'] = max($grouped[$userKey][$date]['ot_hours'], round($otH, 2));
                } elseif (is_numeric($otTime)) {
                    $grouped[$userKey][$date]['ot_hours'] = max($grouped[$userKey][$date]['ot_hours'], (float) $otTime);
                }
            }

            if (!empty($workTime)) {
                if (preg_match('/^(\d+):(\d+)$/', $workTime, $wm)) {
                    $wH = (int)$wm[1] + ((int)$wm[2] / 60);
                    $grouped[$userKey][$date]['work_hours'] += round($wH, 2);
                } elseif (is_numeric($workTime)) {
                    $grouped[$userKey][$date]['work_hours'] += (float) $workTime;
                }
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

            // STRICT RULE: Only match by ZKTeco Device User ID (device_user_id).
            // Absolutely NO guessing by employee code, ID numbers, or employee names.
            // If the employee has not been assigned a ZKTeco Device User ID, do NOT import or show them.
            $searchId = $acNo !== '' ? $acNo : $empNo;

            if ($searchId !== '') {
                $sUpper = strtoupper(trim((string)$searchId));
                if (isset($empByDevice[$sUpper])) {
                    $employee = $empByDevice[$sUpper];
                } elseif (is_numeric($searchId)) {
                    $sInt = (int)$searchId;
                    if (isset($empByDevice[$sInt])) {
                        $employee = $empByDevice[$sInt];
                    } elseif (isset($empByDevice[(string)$sInt])) {
                        $employee = $empByDevice[(string)$sInt];
                    }
                }
            }

            if (!$employee) {
                $skipped++;
                $label = $acNo !== '' ? "Device ID #{$acNo}" : ($empNo !== '' ? "Emp No. '{$empNo}'" : "Unknown");
                if ($rawName !== '') {
                    $label .= " ({$rawName})";
                }
                $errors[] = "Skipped: {$label} - No employee is registered with this ZKTeco Device User ID.";
                continue;
            }

            foreach ($dates as $date => $info) {
                // "if not added don't show in attendance section" - skip days where no punch was recorded
                $hasAnyPunch = !empty($info['morning_in']) || !empty($info['morning_out'])
                            || !empty($info['afternoon_in']) || !empty($info['afternoon_out']);
                if (!$hasAnyPunch) {
                    continue;
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

                // Determine status (aware of Saturday morning-only work policy)
                $hasMorningIn   = !empty($info['morning_in']);
                $hasAfternoonIn = !empty($info['afternoon_in']);
                $dateCarbon     = Carbon::parse($date);
                $isSaturday     = $dateCarbon->isSaturday();

                if ($isSaturday) {
                    // On Saturday, official work is Morning Session Only (4.0 hrs).
                    // If employee clocked in or out in morning, or worked hours >= 2.0, they are FULLY PRESENT.
                    if ($hasMorningIn || !empty($info['morning_out']) || ($hours >= 2.0)) {
                        $status = 'present';
                    } elseif (!empty($info['absent_morning'])) {
                        $status = 'absent';
                    } else {
                        $status = 'present';
                    }
                } else {
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
                        'biometric_device_id' => $employee->device_user_id,
                        'notes'               => $info['late_mins'] > 0 ? "Late: {$info['late_mins']} min" : null,
                    ]
                );
                $saved++;
            }
        }

        $message = "✅ Import complete: {$saved} attendance records saved.";
        if ($skipped > 0) {
            $message .= " ⚠️ {$skipped} device user ID(s) skipped (employees must have 'ZKTeco Device User ID' assigned in their profile).";
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
     * Clear attendance records & raw device logs (Restricted strictly to Admin & Global Admin).
     */
    public function clearHistory(Request $request)
    {
        if (!auth()->check() || !auth()->user()->hasAnyRole(['admin', 'global_admin'])) {
            abort(403, 'Unauthorized access. Only Global Admin and Admin roles can clear attendance and device records.');
        }

        $clearType = $request->input('clear_type', 'all');
        $desc = '';
        $msg = '';

        if ($clearType === 'attendance') {
            Attendance::truncate();
            $desc = 'Employee Attendance records table';
            $msg = 'All employee attendance records have been cleared successfully. Raw device punch logs were preserved.';
        } elseif ($clearType === 'device_logs') {
            DeviceAttendanceLog::truncate();
            $desc = 'Raw Biometric Device punch logs table';
            $msg = 'All raw biometric device punch logs have been cleared successfully. Employee attendance records were preserved.';
        } else {
            Attendance::truncate();
            DeviceAttendanceLog::truncate();
            $desc = 'Complete Master Wipe: All Attendance records and Biometric Device punch logs';
            $msg = 'All attendance records and raw biometric logs have been completely wiped. You can start fresh.';
        }

        ActivityLog::log(
            'deleted',
            "Biometric/Attendance Reset: {$desc} wiped by " . (auth()->user()->name ?? 'Admin'),
            'Attendance & Biometrics'
        );

        return redirect()->route('admin.attendance.device-logs')->with('success', $msg);
    }

    /**
     * Legacy / HR redirect: Redirect admins to admin.attendance.device-logs, block non-admins.
     */
    public function deviceLogs()
    {
        if (auth()->check() && auth()->user()->hasAnyRole(['admin', 'global_admin'])) {
            return redirect()->route('admin.attendance.device-logs', request()->query());
        }

        abort(403, 'Unauthorized. Device punch logs and data maintenance is restricted to Admin & Global Admin.');
    }

    /**
     * Admin view for raw device logs and attendance reset maintenance.
     */
    public function adminDeviceLogs()
    {
        if (!auth()->check() || !auth()->user()->hasAnyRole(['admin', 'global_admin'])) {
            abort(403, 'Unauthorized. Device punch logs and data maintenance is restricted to Admin & Global Admin.');
        }

        $query = DeviceAttendanceLog::with('employee')->latest('punch_time');

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

        // Compute diagnostics about raw biometric punches and attendances in database safely
        try {
            $totalLogsCount       = DeviceAttendanceLog::count();
            $totalAttendanceCount = Attendance::count();
            $earliestPunch        = DeviceAttendanceLog::min('punch_time');
            $latestPunch          = DeviceAttendanceLog::max('punch_time');
            $unlinkedCount        = DeviceAttendanceLog::whereDoesntHave('employee')->count();
            $distinctDatesCount   = DB::table('device_attendance_logs')
                ->whereNotNull('punch_time')
                ->selectRaw('COUNT(DISTINCT DATE(punch_time)) as cnt')
                ->value('cnt') ?? 0;
        } catch (\Throwable $e) {
            $totalLogsCount       = 0;
            $totalAttendanceCount = 0;
            $earliestPunch        = null;
            $latestPunch          = null;
            $unlinkedCount        = 0;
            $distinctDatesCount   = 0;
        }

        return view('admin.attendance.device_logs', compact(
            'logs',
            'totalLogsCount',
            'totalAttendanceCount',
            'earliestPunch',
            'latestPunch',
            'unlinkedCount',
            'distinctDatesCount'
        ));
    }

    /**
     * Manually trigger ZKTeco punch → attendance sync via Artisan command.
     */
    public function syncZkteco(Request $request)
    {
        $syncAll = $request->boolean('sync_all', false);
        $force   = $request->boolean('force', false);

        if ($syncAll) {
            $args = ['--all' => true];
            if ($force) {
                $args['--force'] = true;
            }
            $label = "all available dates in system";
            $redirectParams = [];
        } else {
            $startDateInput = $request->input('start_date') ?: $request->input('date', now()->format('Y-m-d'));
            $endDateInput   = $request->input('end_date') ?: $startDateInput;

            try {
                $start = \Carbon\Carbon::parse($startDateInput)->format('Y-m-d');
                $end   = \Carbon\Carbon::parse($endDateInput)->format('Y-m-d');
            } catch (\Throwable $e) {
                $start = now()->format('Y-m-d');
                $end   = now()->format('Y-m-d');
            }

            if ($start > $end) {
                $temp = $start;
                $start = $end;
                $end = $temp;
            }

            $args = [
                '--from' => $start,
                '--to'   => $end,
            ];
            if ($force) {
                $args['--force'] = true;
            }

            $label = ($start === $end) ? $start : "{$start} to {$end}";
            $redirectParams = ['date_from' => $start, 'date_to' => $end];
        }

        $targetRoute = (auth()->check() && auth()->user()->hasAnyRole(['admin', 'global_admin']))
            ? 'admin.attendance.device-logs'
            : 'attendance.index';

        try {
            Artisan::call('zkteco:sync', $args);
            $output = trim(Artisan::output());

            return redirect()
                ->route($targetRoute, $redirectParams)
                ->with('success', "ZKTeco device punch sync completed for {$label}. " . ($output ? strip_tags($output) : ''));

        } catch (\Exception $e) {
            return redirect()
                ->route($targetRoute)
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
