<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Attendance;
use App\Models\Payroll;
use App\Models\Holiday;
use App\Models\SiteDeploymentRequest;
use App\Models\EmployeeContract;
use App\Models\PerformanceReview;
use App\Models\EmployeeAchievement;
use App\Helpers\EthiopianCalendar;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmployeeSelfServiceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Self-service dashboard
     */
    public function dashboard()
    {
        $employee = Employee::where('user_id', Auth::id())->firstOrFail();
        $employee->load(['leaveRequests', 'salaryStructure', 'contracts', 'performanceReviews', 'achievements']);

        // Pending leaves
        $pendingLeaves = $employee->leaveRequests()
            ->where('status', 'pending')
            ->count();

        // Approved leaves
        $approvedLeaves = $employee->leaveRequests()
            ->where('status', 'approved')
            ->count();

        // This month attendance
        $thisMonthAttendance = Attendance::where('employee_id', $employee->id)
            ->whereMonth('attendance_date', Carbon::now()->month)
            ->whereYear('attendance_date', Carbon::now()->year)
            ->get();

        $presentDays = $thisMonthAttendance->where('status', 'present')->count();
        $absentDays = $thisMonthAttendance->where('status', 'absent')->count();
        $leaveDays = $thisMonthAttendance->where('status', 'leave')->count();

        // Latest payroll
        $latestPayroll = $employee->payrolls()
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->first();

        // Current contract
        $currentContract = $employee->contracts()
            ->where('status', 'active')
            ->orderBy('start_date', 'desc')
            ->first();

        // Latest performance review
        $latestReview = $employee->performanceReviews()
            ->where('status', 'approved')
            ->orderBy('review_period', 'desc')
            ->first();

        // Recent achievements
        $recentAchievements = $employee->achievements()
            ->orderBy('achievement_date', 'desc')
            ->limit(3)
            ->get();

        return view('employee.self-service.dashboard', compact(
            'employee',
            'pendingLeaves',
            'approvedLeaves',
            'presentDays',
            'absentDays',
            'leaveDays',
            'latestPayroll',
            'currentContract',
            'latestReview',
            'recentAchievements'
        ));
    }

    /**
     * View attendance records
     */
    public function viewAttendance(Request $request)
    {
        $employee = Employee::where('user_id', Auth::id())->firstOrFail();

        // 1. Available Ethiopian payroll periods & current period
        $availablePeriods = EthiopianCalendar::getAvailablePayrollPeriods(12, 1);
        $currentPeriod = EthiopianCalendar::getCurrentPayrollPeriod();

        $selectedPeriodKey = $request->input('period');
        $selectedPeriod = null;

        if ($request->filled('period')) {
            if ($selectedPeriodKey && str_contains($selectedPeriodKey, '-')) {
                [$ey, $em] = explode('-', $selectedPeriodKey);
                $selectedPeriod = EthiopianCalendar::getPayrollPeriod((int)$ey, (int)$em);
            }
        } elseif ($request->filled('month')) {
            $m = (int)$request->month;
            $y = (int)($request->year ?? Carbon::now()->year);
            $startCarbon = Carbon::createFromDate($y, $m, 1)->startOfMonth();
            $endCarbon   = Carbon::createFromDate($y, $m, 1)->endOfMonth();

            $curr = $startCarbon->copy();
            $periodDays = [];
            while ($curr->lte($endCarbon)) {
                $greg = $curr->toDateString();
                $et = EthiopianCalendar::toEthiopian($curr);
                $periodDays[] = [
                    'greg_date'    => $greg,
                    'greg_day'     => $curr->format('d'),
                    'greg_month'   => $curr->format('M'),
                    'greg_label'   => $curr->format('M d'),
                    'day_of_week'  => $curr->dayOfWeek,
                    'day_name_en'  => $curr->format('D'),
                    'is_sunday'    => $curr->isSunday(),
                    'is_saturday'  => $curr->isSaturday(),
                    'eth_year'     => $et['year'] ?? null,
                    'eth_month'    => $et['month'] ?? null,
                    'eth_day'      => $et['day'] ?? null,
                    'eth_label_am' => $et['short_am'] ?? '',
                    'eth_label_en' => $et['short_en'] ?? '',
                    'display_label'=> ($et['day'] ?? '') . ' ' . ($et['month_en'] ?? ''),
                ];
                $curr->addDay();
            }

            $selectedPeriod = [
                'eth_year'       => null,
                'eth_month'      => null,
                'month_am'       => $startCarbon->format('F Y'),
                'month_en'       => $startCarbon->format('F Y'),
                'period_key'     => 'greg-' . $y . '-' . $m,
                'label_am'       => $startCarbon->format('F Y'),
                'label_en'       => $startCarbon->format('F Y'),
                'full_label'     => $startCarbon->format('F Y'),
                'start_greg'     => $startCarbon->toDateString(),
                'end_greg'       => $endCarbon->toDateString(),
                'total_days'     => count($periodDays),
                'days'           => $periodDays,
            ];
            $selectedPeriodKey = 'greg-' . $y . '-' . $m;
        }

        if (!$selectedPeriod) {
            $selectedPeriod = $currentPeriod;
            $selectedPeriodKey = $currentPeriod['period_key'];
        }

        $startDate  = $selectedPeriod['start_greg'];
        $endDate    = $selectedPeriod['end_greg'];
        $periodDays = $selectedPeriod['days'];

        // 2. Fetch all attendance records in this period for the employee
        $periodRecords = Attendance::where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->orderBy('attendance_date', 'asc')
            ->get();

        $recordsByDate = $periodRecords->keyBy(function($item) {
            return $item->attendance_date ? $item->attendance_date->toDateString() : '';
        });

        // 3. Approved Leaves for this employee in period
        $leaves = collect();
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('leave_requests')) {
                $leaves = LeaveRequest::where('employee_id', $employee->id)
                    ->where('status', 'approved')
                    ->where(function($q) use ($startDate, $endDate) {
                        $q->whereBetween('start_date', [$startDate, $endDate])
                          ->orWhereBetween('end_date', [$startDate, $endDate])
                          ->orWhere(function($sq) use ($startDate, $endDate) {
                              $sq->where('start_date', '<=', $startDate)->where('end_date', '>=', $endDate);
                          });
                    })
                    ->get();
            }
        } catch (\Throwable $e) {}

        // 4. Public Holidays in period
        $holidays = collect();
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('holidays')) {
                $holidays = Holiday::where(function($q) use ($startDate, $endDate) {
                        $q->whereBetween('holiday_date', [$startDate, $endDate])
                          ->orWhere(function($sq) use ($startDate, $endDate) {
                              $sq->whereBetween('from_date', [$startDate, $endDate])
                                ->orWhereBetween('to_date', [$startDate, $endDate]);
                          });
                    })
                    ->get();
            }
        } catch (\Throwable $e) {}

        // 5. Approved site deployment
        $approvedSiteDeployments = collect();
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('site_deployment_requests')) {
                $approvedSiteDeployments = SiteDeploymentRequest::where('employee_id', $employee->id)
                    ->where('status', 'approved')
                    ->where(function($q) use ($startDate, $endDate) {
                        $q->whereBetween('start_date', [$startDate, $endDate])
                          ->orWhereBetween('end_date', [$startDate, $endDate])
                          ->orWhere(function($sq) use ($startDate, $endDate) {
                              $sq->where('start_date', '<=', $startDate)->where('end_date', '>=', $endDate);
                          });
                    })
                    ->get();
            }
        } catch (\Throwable $e) {}

        // 6. Build Daily Sheet data and summary statistics
        $todayDateStr = today()->toDateString();
        $sheetDays = [];
        $summary = [
            'present'      => 0,
            'absent'       => 0,
            'leave'        => 0,
            'holiday'      => 0,
            'site'         => 0,
            'half_day'     => 0,
            'late_days'    => 0,
            'penalty_days' => 0,
            'hours'        => 0.0,
        ];

        foreach ($periodDays as $dayItem) {
            $greg = $dayItem['greg_date'];
            $isSunday = $dayItem['is_sunday'];
            $att = $recordsByDate->get($greg);

            $hasPunch = $att && (
                !empty($att->morning_in) ||
                !empty($att->morning_out) ||
                !empty($att->afternoon_in) ||
                !empty($att->afternoon_out) ||
                !empty($att->check_in) ||
                !empty($att->check_out) ||
                (float)$att->hours_worked > 0
            );

            $isLate = false;
            $lateMinutes = 0;
            if ($hasPunch) {
                $inPunch = $att->morning_in ?: $att->check_in;
                $lateMinutes = $att->late_minutes ?: (\class_exists(\App\Services\BiometricPunchService::class) ? \App\Services\BiometricPunchService::calculateLateMinutes($inPunch) : 0);
                $isLate = $lateMinutes > 0 || ($att->morning_in && $att->morning_in > '08:40:59');
            }

            // Site deployment
            $siteDep = $approvedSiteDeployments->first(function($sd) use ($greg) {
                $sdStart = $sd->start_date ? Carbon::parse($sd->start_date)->toDateString() : null;
                $sdEnd   = $sd->end_date ? Carbon::parse($sd->end_date)->toDateString() : null;
                return $sdStart && $sdEnd && $sdStart <= $greg && $sdEnd >= $greg;
            });
            $hasSite = ($att && ($att->status === 'S' || $att->source === 'site_dispatch' || (method_exists($att, 'isOnSite') && $att->isOnSite()))) ?: $siteDep;

            // Leave
            $leaveObj = $leaves->first(function($lv) use ($greg) {
                $lvStart = $lv->start_date ? Carbon::parse($lv->start_date)->toDateString() : null;
                $lvEnd   = $lv->end_date ? Carbon::parse($lv->end_date)->toDateString() : null;
                return $lvStart && $lvEnd && $lvStart <= $greg && $lvEnd >= $greg;
            });

            // Holiday
            $holidayObj = $holidays->first(function($h) use ($greg) {
                $hd = $h->holiday_date ? Carbon::parse($h->holiday_date)->toDateString() : null;
                if ($hd && $hd === $greg) return true;
                if ($h->from_date && $h->to_date) {
                    return Carbon::parse($h->from_date)->toDateString() <= $greg 
                        && Carbon::parse($h->to_date)->toDateString() >= $greg;
                }
                return false;
            });

            // Determine status code
            if ($isSunday) {
                if ($hasPunch) {
                    $code = 'P';
                    $cellClass = 'cell-sunday-ot';
                    $label = 'Sunday Overtime (P)';
                    $summary['present']++;
                } else {
                    $code = 'SUN';
                    $cellClass = 'cell-sunday';
                    $label = 'Sunday (Rest Day)';
                }
            } elseif ($hasSite) {
                $code = 'S';
                $cellClass = 'cell-site';
                $label = 'On-Site Deployment (S)';
                $summary['site']++;
                $summary['present']++;
            } elseif ($hasPunch) {
                $code = 'P';
                $cellClass = $isLate ? 'cell-present-late' : 'cell-present-ontime';
                $label = $isLate ? "Present (Late {$lateMinutes}m)" : 'Present (P)';
                $summary['present']++;
                if ($isLate) {
                    $summary['late_days']++;
                }
            } elseif ($leaveObj) {
                $code = 'L';
                $cellClass = 'cell-leave';
                $label = 'Approved Leave: ' . ($leaveObj->leave_type ?? 'Leave');
                $summary['leave']++;
            } elseif ($holidayObj) {
                $code = 'H';
                $cellClass = 'cell-holiday';
                $label = 'Public Holiday: ' . ($holidayObj->title ?? $holidayObj->name ?? 'Holiday');
                $summary['holiday']++;
            } else {
                if ($greg > $todayDateStr) {
                    $code = '—';
                    $cellClass = 'cell-upcoming';
                    $label = 'Upcoming Day';
                } else {
                    $code = 'A';
                    $cellClass = 'cell-absent';
                    $label = 'Absent (A)';
                    $summary['absent']++;
                }
            }

            // Punch strings
            $rawIn  = $att?->morning_in ?: ($att?->afternoon_in ?: $att?->check_in);
            $rawOut = $att?->afternoon_out ?: ($att?->morning_out ?: $att?->check_out);
            $punchIn  = $rawIn ? Carbon::parse($rawIn)->format('h:i A') : null;
            $punchOut = $rawOut ? Carbon::parse($rawOut)->format('h:i A') : null;

            if ($att && (float)$att->hours_worked > 0) {
                $summary['hours'] += (float)$att->hours_worked;
            }

            if ($att && in_array(strtolower($att->status ?? ''), ['half-day', 'half_day'])) {
                $summary['half_day']++;
            }

            $sheetDays[] = [
                'day'         => $dayItem,
                'record'      => $att,
                'code'        => $code,
                'class'       => $cellClass,
                'label'       => $label,
                'punch_in'    => $punchIn,
                'punch_out'   => $punchOut,
                'is_late'     => $isLate,
                'late_min'    => $lateMinutes,
            ];
        }

        $summary['penalty_days'] = intdiv($summary['late_days'], 3);

        // Paginated attendance records for table (showing newest first)
        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->orderBy('attendance_date', 'desc')
            ->paginate(35);

        return view('employee.self-service.attendance', compact(
            'employee',
            'attendance',
            'availablePeriods',
            'currentPeriod',
            'selectedPeriod',
            'selectedPeriodKey',
            'sheetDays',
            'summary'
        ));
    }

    /**
     * View payroll records
     */
    public function viewPayroll(Request $request)
    {
        $employee = Employee::where('user_id', Auth::id())->firstOrFail();

        $payrolls = $employee->payrolls()
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->paginate(12);

        // Calculate YTD
        $ytdTotal = Payroll::where('employee_id', $employee->id)
            ->where('year', Carbon::now()->year)
            ->sum('net_salary');

        return view('employee.self-service.payroll', compact('employee', 'payrolls', 'ytdTotal'));
    }

    /**
     * View contract details
     */
    public function viewContract()
    {
        $employee = Employee::where('user_id', Auth::id())->firstOrFail();

        $contracts = $employee->contracts()->orderBy('start_date', 'desc')->get();

        return view('employee.self-service.contract', compact('employee', 'contracts'));
    }

    /**
     * View leave history
     */
    public function viewLeaveHistory(Request $request)
    {
        $employee = Employee::where('user_id', Auth::id())->firstOrFail();

        $query = $employee->leaveRequests();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $leaves = $query->orderBy('created_at', 'desc')->paginate(15);

        return view('employee.self-service.leave-history', compact('employee', 'leaves'));
    }

    /**
     * View performance reviews
     */
    public function viewPerformance()
    {
        $employee = Employee::where('user_id', Auth::id())->firstOrFail();

        $reviews = $employee->performanceReviews()
            ->where('status', 'approved')
            ->orderBy('review_period', 'desc')
            ->paginate(10);

        return view('employee.self-service.performance', compact('employee', 'reviews'));
    }

    /**
     * View achievements/recognition
     */
    public function viewAchievements()
    {
        $employee = Employee::where('user_id', Auth::id())->firstOrFail();

        $achievements = $employee->achievements()
            ->orderBy('achievement_date', 'desc')
            ->paginate(15);

        return view('employee.self-service.achievements', compact('employee', 'achievements'));
    }

    /**
     * View leave balance
     */
    public function viewLeaveBalance()
    {
        $employee = Employee::where('user_id', Auth::id())->firstOrFail();

        $balances = $employee->leaveBalances()
            ->where('year', Carbon::now()->year)
            ->with('leaveType')
            ->get();

        return view('employee.self-service.leave-balance', compact('employee', 'balances'));
    }

    /**
     * Download payroll slip
     */
    public function downloadPayrollSlip(Payroll $payroll)
    {
        $employee = Employee::where('user_id', Auth::id())->firstOrFail();

        if ($payroll->employee_id !== $employee->id) {
            abort(403, 'Unauthorized');
        }

        $pdf = \PDF::loadView('payroll.slip', ['payroll' => $payroll]);

        return $pdf->download('payroll-slip-' . $payroll->period . '.pdf');
    }

    /**
     * Download contract
     */
    public function downloadContract(EmployeeContract $contract)
    {
        $employee = Employee::where('user_id', Auth::id())->firstOrFail();

        if ($contract->employee_id !== $employee->id) {
            abort(403, 'Unauthorized');
        }

        if ($contract->contract_file) {
            return \Storage::download($contract->contract_file, 'contract-' . $contract->contract_number . '.pdf');
        }

        abort(404, 'Contract file not found');
    }

    /**
     * Update personal profile
     */
    public function updateProfile(Request $request)
    {
        $employee = Employee::where('user_id', Auth::id())->firstOrFail();

        $validated = $request->validate([
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
        ]);

        $employee->update($validated);

        return back()->with('success', 'Profile updated successfully');
    }
}
