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

        // Department-wise summary
        $departmentSummary = DB::table('attendances')
            ->join('employees', 'attendances.employee_id', '=', 'employees.id')
            ->whereBetween('attendances.attendance_date', [$fromDate, $toDate])
            ->select(
                DB::raw("COALESCE(NULLIF(employees.department, ''), 'General') as name"),
                DB::raw("SUM(CASE WHEN attendances.status = 'present' THEN 1 ELSE 0 END) as present"),
                DB::raw("SUM(CASE WHEN attendances.status = 'absent' THEN 1 ELSE 0 END) as absent"),
                DB::raw("SUM(CASE WHEN attendances.status = 'leave' THEN 1 ELSE 0 END) as leave"),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy(DB::raw("COALESCE(NULLIF(employees.department, ''), 'General')"))
            ->get();

        $stats = [
            'total_employees' => $employees->count(),
            'avg_attendance' => collect($attendanceData)->avg('attendance_percentage'),
            'total_working_days' => $totalWorkingDays,
        ];

        return view('hr-manager.reports.attendance', compact(
            'attendanceData', 'departmentSummary', 'stats', 'fromDate', 'toDate'
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

        $totalGross = $payrolls->sum('gross_salary') ?? 0;
        $totalNet = $payrolls->sum('net_salary') ?? 0;
        $totalDeductions = $payrolls->sum('deductions') ?? 0;
        $totalTax = $payrolls->sum('tax') ?? 0;

        // Cost per employee
        $avgCost = $payrolls->count() > 0 ? $totalGross / $payrolls->count() : 0;

        // Department-wise cost breakdown
        $departmentCosts = DB::table('payrolls')
            ->join('employees', 'payrolls.employee_id', '=', 'employees.id')
            ->where('payrolls.year', $year)
            ->where('payrolls.month', $month)
            ->select(
                DB::raw("COALESCE(NULLIF(employees.department, ''), 'General') as name"),
                DB::raw('COUNT(*) as employee_count'),
                DB::raw('SUM(payrolls.gross_salary) as total_gross'),
                DB::raw('SUM(payrolls.net_salary) as total_net'),
                DB::raw('SUM(payrolls.deductions) as total_deductions'),
                DB::raw('SUM(payrolls.tax) as total_tax')
            )
            ->groupBy(DB::raw("COALESCE(NULLIF(employees.department, ''), 'General')"))
            ->get();

        $designationCosts = collect();

        // Year-to-date costs
        $ytdPayrolls = Payroll::where('year', $year)
            ->where('month', '<=', $month)
            ->get();
        
        $ytdTotal = $ytdPayrolls->sum('gross_salary') ?? 0;

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

        // Leave requests by type
        $leaveByType = [];
        try {
            $leaveByType = DB::table('leave_requests')
                ->join('leave_types', 'leave_requests.leave_type_id', '=', 'leave_types.id')
                ->where('leave_requests.status', 'approved')
                ->whereYear('leave_requests.created_at', $year)
                ->select(
                    'leave_types.name',
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(DATEDIFF(leave_requests.end_date, leave_requests.start_date) + 1) as days')
                )
                ->groupBy('leave_types.id', 'leave_types.name')
                ->get();
        } catch (\Throwable $e) {
            $leaveByType = collect();
        }

        // Department-wise leave
        $departmentLeave = [];
        try {
            $departmentLeave = DB::table('leave_requests')
                ->join('employees', 'leave_requests.employee_id', '=', 'employees.id')
                ->where('leave_requests.status', 'approved')
                ->whereYear('leave_requests.created_at', $year)
                ->select(
                    DB::raw("COALESCE(NULLIF(employees.department, ''), 'General') as name"),
                    DB::raw('COUNT(*) as requests'),
                    DB::raw('SUM(DATEDIFF(leave_requests.end_date, leave_requests.start_date) + 1) as days')
                )
                ->groupBy(DB::raw("COALESCE(NULLIF(employees.department, ''), 'General')"))
                ->get();
        } catch (\Throwable $e) {
            $departmentLeave = collect();
        }

        $monthlyTrend = collect();

        $stats = [
            'total_requests' => LeaveRequest::whereYear('created_at', $year)->count(),
            'approved' => LeaveRequest::where('status', 'approved')->whereYear('created_at', $year)->count(),
            'rejected' => LeaveRequest::where('status', 'rejected')->whereYear('created_at', $year)->count(),
            'total_days' => LeaveRequest::where('status', 'approved')->whereYear('created_at', $year)->count(),
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
        $from = Carbon::parse($fromDate);
        $to = Carbon::parse($toDate);
        $monthCount = $from->diffInMonths($to) + 1;

        $totalHeadcount = 0;
        for ($i = 0; $i < $monthCount; $i++) {
            $count = Employee::where('is_active', true)
                ->where('date_of_joining', '<=', $from->copy()->endOfMonth())
                ->where(function ($q) use ($from) {
                    $q->whereNull('termination_date')
                      ->orWhere('termination_date', '>=', $from->copy()->startOfMonth());
                })
                ->count();
            $totalHeadcount += $count;
            $from->addMonth();
        }

        return $monthCount > 0 ? $totalHeadcount / $monthCount : 0;
    }
}
