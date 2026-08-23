<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Attendance;
use App\Models\Payroll;
use App\Models\LeaveRequest;
use App\Models\EmployeeContract;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HRReportsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Attendance Report Dashboard
     */
    public function attendanceReport(Request $request)
    {
        $this->authorize('viewAny', Attendance::class);

        $fromDate = $request->filled('from_date') 
            ? $request->from_date 
            : Carbon::now()->subMonths(1)->toDateString();
        $toDate = $request->filled('to_date') 
            ? $request->to_date 
            : Carbon::now()->toDateString();

        $totalWorkingDays = $this->getWorkingDays($fromDate, $toDate);
        $employees = Employee::where(function($q) {
            $q->where('status', 'active')->orWhereNull('status');
        })->get();

        $attendanceData = [];
        foreach ($employees as $emp) {
            $present = Attendance::where('employee_id', $emp->id)
                ->whereBetween('attendance_date', [$fromDate, $toDate])
                ->where('status', 'present')
                ->count();

            $absent = Attendance::where('employee_id', $emp->id)
                ->whereBetween('attendance_date', [$fromDate, $toDate])
                ->where('status', 'absent')
                ->count();

            $leave = Attendance::where('employee_id', $emp->id)
                ->whereBetween('attendance_date', [$fromDate, $toDate])
                ->where('status', 'leave')
                ->count();

            $attendanceData[] = [
                'employee' => $emp,
                'present' => $present,
                'absent' => $absent,
                'leave' => $leave,
                'attendance_percentage' => $totalWorkingDays > 0 ? ($present / $totalWorkingDays) * 100 : 0,
            ];
        }

        // Department-wise summary from computed attendance records
        $departmentSummary = collect($attendanceData)->groupBy(function($item) {
            return $item['employee']->department ?: 'General';
        })->map(function($rows, $deptName) {
            $present = $rows->sum('present');
            $absent = $rows->sum('absent');
            $leave = $rows->sum('leave');
            $total = $present + $absent + $leave;
            return (object)[
                'name'        => $deptName,
                'present'     => $present,
                'absent'      => $absent,
                'leave_count' => $leave,
                'leave'       => $leave,
                'total'       => $total,
            ];
        })->values();

        $stats = [
            'total_employees' => $employees->count(),
            'avg_attendance' => collect($attendanceData)->avg('attendance_percentage'),
            'total_working_days' => $totalWorkingDays,
        ];

        $recentSubmissions = [];
        try {
            $recentSubmissions = \App\Models\HrReportSubmission::with(['submitter', 'reviewer'])->latest()->take(5)->get();
        } catch (\Throwable $e) {}

        return view('hr-manager.reports.attendance', compact(
            'attendanceData', 'departmentSummary', 'stats', 'fromDate', 'toDate', 'recentSubmissions'
        ));
    }

    /**
     * Turnover Report
     */
    public function turnoverReport(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $year = $request->get('year', Carbon::now()->year);
        $fromDate = Carbon::now()->subYear()->startOfYear()->toDateString();
        $toDate = Carbon::now()->toDateString();

        // Employee separations
        $separations = EmployeeContract::where('status', 'terminated')
            ->whereBetween('end_date', [$fromDate, $toDate])
            ->with('employee')
            ->get();

        // New joinings
        $joinings = EmployeeContract::where('status', 'active')
            ->whereBetween('start_date', [$fromDate, $toDate])
            ->with('employee')
            ->get();

        // Turnover rate calculation
        $avgHeadcount = ($this->getAverageHeadcount($fromDate, $toDate)) ?: 1;
        $turnoverRate = ($separations->count() / $avgHeadcount) * 100;

        // Department-wise turnover
        $departmentTurnover = [];
        $departments = Employee::select(DB::raw("DISTINCT COALESCE(NULLIF(department, ''), 'General') as name"))->pluck('name');
        foreach ($departments as $deptName) {
            $deptSeparations = $separations->filter(fn($e) => ($e->employee->department ?? 'General') == $deptName)->count();
            $deptJoinings = $joinings->filter(fn($e) => ($e->employee->department ?? 'General') == $deptName)->count();
            
            $departmentTurnover[] = [
                'department' => $deptName,
                'separations' => $deptSeparations,
                'joinings' => $deptJoinings,
                'net_change' => $deptJoinings - $deptSeparations,
            ];
        }

        $stats = [
            'total_separations' => $separations->count(),
            'total_joinings' => $joinings->count(),
            'turnover_rate' => $turnoverRate,
            'net_change' => $joinings->count() - $separations->count(),
        ];

        return view('hr-manager.reports.turnover', compact(
            'separations', 'joinings', 'departmentTurnover', 'stats', 'year'
        ));
    }

    /**
     * Cost Analysis Report
     */
    public function costAnalysisReport(Request $request)
    {
        $this->authorize('viewAny', Payroll::class);

        $month = $request->get('month', Carbon::now()->month);
        $year = $request->get('year', Carbon::now()->year);

        // Total payroll cost
        $payrolls = Payroll::where('year', $year)
            ->where('month', $month)
            ->with('employee')
            ->get();

        $totalGross = (float)$payrolls->sum('gross_salary');
        $totalNet = (float)$payrolls->sum('net_salary');
        $totalDeductions = (float)$payrolls->sum('deductions');
        $totalTax = (float)$payrolls->sum('tax');

        // Cost per employee
        $avgCost = $payrolls->count() > 0 ? $totalGross / $payrolls->count() : 0;

        // Department-wise cost breakdown via Collection
        $departmentCosts = $payrolls->groupBy(function($p) {
            return $p->employee?->department ?: 'General';
        })->map(function($group, $deptName) {
            return (object)[
                'name'             => $deptName,
                'employee_count'   => $group->count(),
                'total_gross'      => (float)$group->sum('gross_salary'),
                'total_net'        => (float)$group->sum('net_salary'),
                'total_deductions' => (float)$group->sum('deductions'),
                'total_tax'        => (float)$group->sum('tax'),
            ];
        })->values();

        $designationCosts = collect();

        // Year-to-date costs
        $ytdPayrolls = Payroll::where('year', $year)
            ->where('month', '<=', $month)
            ->get();
        
        $ytdTotal = (float)$ytdPayrolls->sum('gross_salary');

        $stats = [
            'total_employees' => $payrolls->count(),
            'total_gross' => $totalGross,
            'total_net' => $totalNet,
            'total_deductions' => $totalDeductions,
            'total_tax' => $totalTax,
            'avg_cost' => $avgCost,
            'ytd_total' => $ytdTotal,
        ];

        return view('hr-manager.reports.cost-analysis', compact(
            'payrolls', 'departmentCosts', 'designationCosts', 'stats', 'month', 'year'
        ));
    }

    /**
     * Leave Analysis Report
     */
    public function leaveAnalysisReport(Request $request)
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $year = $request->get('year', Carbon::now()->year);

        $approvedLeaves = LeaveRequest::where('status', 'approved')
            ->whereYear('created_at', $year)
            ->with(['leaveType', 'employee'])
            ->get();

        $leaveByType = $approvedLeaves->groupBy(function($lr) {
            return $lr->leaveType?->name ?? 'Standard Leave';
        })->map(function($group, $typeName) {
            $days = $group->sum(function($lr) {
                if ($lr->start_date && $lr->end_date) {
                    return Carbon::parse($lr->start_date)->diffInDays(Carbon::parse($lr->end_date)) + 1;
                }
                return 1;
            });
            return (object)[
                'name'  => $typeName,
                'count' => $group->count(),
                'days'  => $days,
            ];
        })->values();

        $departmentLeave = $approvedLeaves->groupBy(function($lr) {
            return $lr->employee?->department ?: 'General';
        })->map(function($group, $deptName) {
            $days = $group->sum(function($lr) {
                if ($lr->start_date && $lr->end_date) {
                    return Carbon::parse($lr->start_date)->diffInDays(Carbon::parse($lr->end_date)) + 1;
                }
                return 1;
            });
            return (object)[
                'name'     => $deptName,
                'requests' => $group->count(),
                'days'     => $days,
            ];
        })->values();

        $monthlyTrend = collect();

        $stats = [
            'total_requests' => LeaveRequest::whereYear('created_at', $year)->count(),
            'approved' => $approvedLeaves->count(),
            'rejected' => LeaveRequest::where('status', 'rejected')->whereYear('created_at', $year)->count(),
            'total_days' => $leaveByType->sum('days'),
        ];

        return view('hr-manager.reports.leave-analysis', compact(
            'leaveByType', 'departmentLeave', 'monthlyTrend', 'stats', 'year'
        ));
    }

    /**
     * Employee Cost Report
     */
    public function employeeCostReport(Request $request)
    {
        $this->authorize('viewAny', Payroll::class);

        $month = $request->get('month', Carbon::now()->month);
        $year = $request->get('year', Carbon::now()->year);

        $employees = Employee::where(function($q) {
                $q->where('status', 'active')->orWhereNull('status');
            })
            ->orderBy('full_name')
            ->get();

        $employeeCosts = [];
        foreach ($employees as $emp) {
            $payroll = Payroll::where('employee_id', $emp->id)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            $employeeCosts[] = [
                'employee' => $emp,
                'basic_salary' => $payroll->basic_salary ?? $emp->basic_salary ?? 0,
                'allowances' => $payroll->allowances ?? ($emp->transport_allowance + $emp->house_allowance + $emp->position_allowance),
                'gross_salary' => $payroll->gross_salary ?? ($emp->basic_salary + $emp->transport_allowance + $emp->house_allowance + $emp->position_allowance),
                'deductions' => $payroll->deductions ?? 0,
                'tax' => $payroll->tax ?? 0,
                'net_salary' => $payroll->net_salary ?? ($emp->basic_salary + $emp->transport_allowance + $emp->house_allowance + $emp->position_allowance),
            ];
        }

        return view('hr-manager.reports.employee-cost', compact('employeeCosts', 'month', 'year'));
    }

    /**
     * Export reports to CSV
     */
    public function exportAttendanceCSV(Request $request)
    {
        $fromDate = $request->get('from_date', Carbon::now()->subMonths(1)->toDateString());
        $toDate = $request->get('to_date', Carbon::now()->toDateString());

        $fileName = 'attendance-report-' . now()->format('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"$fileName\"",
        ];

        $callback = function () use ($fromDate, $toDate) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Employee', 'Present', 'Absent', 'Leave', 'Attendance %']);

            $employees = Employee::where(function($q) {
                $q->where('status', 'active')->orWhereNull('status');
            })->get();
            $totalWorkingDays = $this->getWorkingDays($fromDate, $toDate);

            foreach ($employees as $emp) {
                $present = Attendance::where('employee_id', $emp->id)
                    ->whereBetween('attendance_date', [$fromDate, $toDate])
                    ->where('status', 'present')
                    ->count();

                $absent = Attendance::where('employee_id', $emp->id)
                    ->whereBetween('attendance_date', [$fromDate, $toDate])
                    ->where('status', 'absent')
                    ->count();

                $leave = Attendance::where('employee_id', $emp->id)
                    ->whereBetween('attendance_date', [$fromDate, $toDate])
                    ->where('status', 'leave')
                    ->count();

                $percentage = $totalWorkingDays > 0 ? ($present / $totalWorkingDays) * 100 : 0;

                fputcsv($file, [
                    $emp->name,
                    $present,
                    $absent,
                    $leave,
                    number_format($percentage, 2) . '%'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Send HR Report to General Manager (GM)
     */
    public function sendToGM(Request $request)
    {
        $request->validate([
            'report_type' => 'required|string',
            'from_date'   => 'nullable|date',
            'to_date'     => 'nullable|date',
            'notes'       => 'nullable|string|max:2000',
        ]);

        $fromDate = $request->input('from_date', Carbon::now()->subMonth()->toDateString());
        $toDate   = $request->input('to_date', Carbon::now()->toDateString());
        $totalWorkingDays = $this->getWorkingDays($fromDate, $toDate);

        $employees = Employee::where(function($q) {
            $q->where('status', 'active')->orWhereNull('status');
        })->get();

        $presentTotal = 0;
        $absentTotal = 0;
        $leaveTotal = 0;
        foreach ($employees as $emp) {
            $presentTotal += Attendance::where('employee_id', $emp->id)->whereBetween('attendance_date', [$fromDate, $toDate])->where('status', 'present')->count();
            $absentTotal += Attendance::where('employee_id', $emp->id)->whereBetween('attendance_date', [$fromDate, $toDate])->where('status', 'absent')->count();
            $leaveTotal += Attendance::where('employee_id', $emp->id)->whereBetween('attendance_date', [$fromDate, $toDate])->where('status', 'leave')->count();
        }

        $grandTotal = $presentTotal + $absentTotal + $leaveTotal;
        $avgRate = $grandTotal > 0 ? ($presentTotal / $grandTotal) * 100 : 0.00;

        $submission = \App\Models\HrReportSubmission::create([
            'report_type'         => $request->input('report_type', 'Attendance Report'),
            'from_date'           => $fromDate,
            'to_date'             => $toDate,
            'total_employees'     => $employees->count(),
            'avg_attendance_rate' => $avgRate,
            'total_working_days'  => $totalWorkingDays,
            'notes'               => $request->input('notes'),
            'summary_data'        => [
                'present_days' => $presentTotal,
                'absent_days'  => $absentTotal,
                'leave_days'   => $leaveTotal,
                'from_date'    => $fromDate,
                'to_date'      => $toDate,
                'submitted_by' => Auth::user()->name ?? 'HR Officer',
            ],
            'submitted_by'        => Auth::id(),
            'status'              => 'submitted',
        ]);

        // Notify GM users
        try {
            $gmUsers = \App\Models\User::whereHas('roles', function($r) {
                $r->whereIn('name', ['gm', 'general_manager', 'admin', 'global_admin']);
            })->get();

            foreach ($gmUsers as $gm) {
                // If notification system exists or send internal message
                if (class_exists('\App\Models\Notification')) {
                    \App\Models\Notification::create([
                        'user_id' => $gm->id,
                        'title'   => 'New HR Report Submitted to GM',
                        'message' => (Auth::user()->name ?? 'HR Officer') . ' submitted the ' . $submission->report_type . ' (' . Carbon::parse($fromDate)->format('d M') . ' – ' . Carbon::parse($toDate)->format('d M Y') . '). Notes: ' . ($request->notes ?: 'None'),
                        'type'    => 'hr_report',
                        'data'    => ['submission_id' => $submission->id, 'url' => route('reports.attendance')],
                    ]);
                }
            }
        } catch (\Throwable $e) {}

        return redirect()->back()->with('success', 'HR Report (' . $submission->report_type . ') has been successfully submitted to the General Manager (GM)!');
    }

    /**
     * GM Review / Acknowledge HR Report Submission
     */
    public function gmReview(Request $request, \App\Models\HrReportSubmission $submission)
    {
        $request->validate([
            'status'     => 'required|in:reviewed,acknowledged,rejected',
            'gm_remarks' => 'nullable|string|max:1000',
        ]);

        $submission->update([
            'status'      => $request->status,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'gm_remarks'  => $request->gm_remarks,
        ]);

        return redirect()->back()->with('success', 'Report status updated to: ' . ucfirst($request->status));
    }

    /**
     * GM Dedicated HR Reports View
     */
    public function gmIndex(Request $request)
    {
        $submissions = \App\Models\HrReportSubmission::with(['submitter', 'reviewer'])->latest()->paginate(15);
        return view('dashboard.gm-hr-reports', compact('submissions'));
    }

    /**
     * Helper: Calculate working days
     */
    private function getWorkingDays($fromDate, $toDate)
    {
        $from = Carbon::parse($fromDate);
        $to = Carbon::parse($toDate);
        
        $count = 0;
        while ($from->lte($to)) {
            if ($from->isWeekday()) {
                $count++;
            }
            $from->addDay();
        }

        return $count;
    }

    /**
     * Helper: Calculate average headcount
     */
    private function getAverageHeadcount($fromDate, $toDate)
    {
        $count = Employee::where(function ($q) {
            $q->where('status', 'active')->orWhereNull('status');
        })->count();

        return $count > 0 ? $count : 1;
    }
}
