<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Employee;
use App\Models\DailyReport;
use App\Models\Attendance;
use App\Models\ManpowerRequest;
use App\Models\SubconAgreement;
use App\Models\ActivityTimeLog;
use App\Models\Payroll;
use App\Models\LeaveRequest;
use Carbon\Carbon;

class HRManagerController extends Controller
{
    /**
     * Safely execute a callback to avoid crashes on missing tables
     */
    private function safe(callable $fn, $default = 0)
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * HR Manager Dashboard
     * Displays comprehensive HR metrics and pending items
     */
    public function dashboard()
    {
        // Gather all statistics
        $statistics = $this->getStatistics();

        // Get pending daily reports awaiting approval
        $pendingDailyReports = $this->safe(
            fn() => DailyReport::with(['project', 'createdBy'])
                ->where('status', 'pending')
                ->orWhere('status', 'submitted')
                ->latest('report_date')
                ->take(10)
                ->get(),
            collect()
        );

        // Get pending attendance records
        $pendingAttendance = $this->safe(
            fn() => Attendance::with(['employee', 'approvedBy'])
                ->where('is_approved', false)
                ->latest('attendance_date')
                ->take(10)
                ->get(),
            collect()
        );

        // Get current week manpower summary (employees present this week)
        $currentWeekManpower = $this->getWeeklyManpowerSummary();

        // Get subcon agreements status (active and upcoming)
        $subconAgreements = $this->safe(
            fn() => SubconAgreement::with(['project'])
                ->where(function ($query) {
                    $query->where('status', 'active')
                        ->orWhere('status', 'pending')
                        ->orWhere('status', 'approved');
                })
                ->where('end_date', '>=', now()->toDateString())
                ->latest('created_at')
                ->take(8)
                ->get(),
            collect()
        );

        // Get recent activities
        $recentActivities = $this->safe(
            fn() => ActivityTimeLog::with('user')
                ->latest('entered_at')
                ->take(15)
                ->get(),
            collect()
        );

        // Get pending manpower requests
        $pendingManpowerRequests = $this->safe(
            fn() => ManpowerRequest::with(['project', 'requestedBy'])
                ->where('status', 'pending')
                ->orWhere('status', 'submitted')
                ->latest('required_date')
                ->take(8)
                ->get(),
            collect()
        );

        // Get pending leave requests for approval
        $pendingLeaveRequests = $this->safe(
            fn() => LeaveRequest::with(['employee', 'leaveType'])
                ->where('status', 'pending')
                ->latest('created_at')
                ->take(10)
                ->get(),
            collect()
        );

        // KPI data (from the former HR Officer dashboard)
        $kpi = [
            'total_employees'       => $statistics['total_active_employees'] ?? 0,
            'present_today'         => $statistics['present_today'] ?? 0,
            'pending_payroll'       => $this->safe(fn() => Payroll::where('status', 'pending')->count()),
            'open_requests'         => $statistics['pending_manpower_requests'] ?? 0,
            'pending_leave_count'   => $this->safe(fn() => LeaveRequest::where('status', 'pending')->count()),
        ];

        $recentPayrolls = $this->safe(
            fn() => Payroll::with('employee')->latest()->take(5)->get(),
            collect()
        );

        // Get recent official employee letters
        $recentEmployeeLetters = $this->safe(
            fn() => \App\Models\EmployeeLetter::with(['employee', 'issuer'])->latest('issued_date')->take(8)->get(),
            collect()
        );

        return view('dashboard.hr-manager', compact(
            'statistics',
            'kpi',
            'recentPayrolls',
            'pendingDailyReports',
            'pendingAttendance',
            'currentWeekManpower',
            'subconAgreements',
            'recentActivities',
            'pendingManpowerRequests',
            'pendingLeaveRequests',
            'recentEmployeeLetters'
        ));
    }

    /**
     * Get all HR statistics
     * Returns comprehensive KPI data for dashboard cards
     */
    public function getStatistics()
    {
        $statistics = [
            // Total active employees count
            'total_active_employees' => $this->safe(
                fn() => Employee::where('status', 'active')->count(),
                0
            ),

            // Pending daily reports count
            'pending_daily_reports' => $this->safe(
                fn() => DailyReport::where(function ($query) {
                    $query->where('status', 'pending')
                        ->orWhere('status', 'submitted');
                })->count(),
                0
            ),

            // Pending attendance records count
            'pending_attendance' => $this->safe(
                fn() => Attendance::where('is_approved', false)->count(),
                0
            ),

            // Present today
            'present_today' => $this->safe(
                fn() => Attendance::whereDate('attendance_date', now())
                    ->where('status', 'present')
                    ->count(),
                0
            ),

            // Pending manpower requests
            'pending_manpower_requests' => $this->safe(
                fn() => ManpowerRequest::where(function ($query) {
                    $query->where('status', 'pending')
                        ->orWhere('status', 'submitted');
                })->count(),
                0
            ),

            // Active subcon agreements
            'active_subcon_agreements' => $this->safe(
                fn() => SubconAgreement::where('status', 'active')
                    ->where('end_date', '>=', now()->toDateString())
                    ->count(),
                0
            ),

            // Total daily reports this month
            'daily_reports_this_month' => $this->safe(
                fn() => DailyReport::whereMonth('report_date', now()->month)
                    ->whereYear('report_date', now()->year)
                    ->count(),
                0
            ),

            // Attendance rate this month (approved records)
            'attendance_rate_this_month' => $this->getMonthlyAttendanceRate(),

            // Employee Letters & Records
            'total_employee_letters'     => $this->safe(fn() => \App\Models\EmployeeLetter::count(), 0),
            'warning_letters'            => $this->safe(fn() => \App\Models\EmployeeLetter::whereIn('letter_type', ['first_warning', 'second_warning', 'final_warning', 'show_cause'])->count(), 0),
            'appreciation_letters'       => $this->safe(fn() => \App\Models\EmployeeLetter::whereIn('letter_type', ['thanks_letter', 'appreciation', 'promotion'])->count(), 0),

            // Employees absent today
            'absent_today' => $this->safe(
                fn() => Attendance::whereDate('attendance_date', now())
                    ->where('status', 'absent')
                    ->count(),
                0
            ),

            // On leave today
            'on_leave_today' => $this->safe(
                fn() => Attendance::whereDate('attendance_date', now())
                    ->where('status', 'leave')
                    ->count(),
                0
            ),

            // Approved subcon agreements
            'approved_subcon_agreements' => $this->safe(
                fn() => SubconAgreement::where('status', 'approved')
                    ->where('end_date', '>=', now()->toDateString())
                    ->count(),
                0
            ),
        ];

        return $statistics;
    }

    /**
     * Get weekly manpower summary
     * Returns attendance breakdown by status for current week
     */
    private function getWeeklyManpowerSummary()
    {
        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();

        return $this->safe(function () use ($startOfWeek, $endOfWeek) {
            // Group attendance records by status for this week
            $weeklyAttendance = Attendance::whereBetween('attendance_date', [$startOfWeek, $endOfWeek])
                ->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status');

            // Get total employee count for comparison
            $totalEmployees = Employee::where('status', 'active')->count();
            
            // Ensure we have at least 1 to prevent division by zero
            if ($totalEmployees <= 0) {
                $totalEmployees = 1;
            }

            // Calculate daily breakdown
            $dailyBreakdown = [];
            for ($i = 0; $i < 7; $i++) {
                $date = $startOfWeek->clone()->addDays($i);
                $dayName = $date->format('D');

                $dayAttendance = Attendance::whereDate('attendance_date', $date)
                    ->select('status', DB::raw('COUNT(*) as count'))
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->toArray();

                $dailyBreakdown[$dayName] = [
                    'date' => $date->format('Y-m-d'),
                    'present' => $dayAttendance['present'] ?? 0,
                    'absent' => $dayAttendance['absent'] ?? 0,
                    'leave' => $dayAttendance['leave'] ?? 0,
                    'total_expected' => $totalEmployees,
                ];
            }

            return [
                'total_employees' => $totalEmployees,
                'present_total' => $weeklyAttendance['present'] ?? 0,
                'absent_total' => $weeklyAttendance['absent'] ?? 0,
                'leave_total' => $weeklyAttendance['leave'] ?? 0,
                'daily_breakdown' => $dailyBreakdown,
            ];
        }, [
            'total_employees' => 1,
            'present_total' => 0,
            'absent_total' => 0,
            'leave_total' => 0,
            'daily_breakdown' => [],
        ]);
    }

    /**
     * Calculate monthly attendance rate
     * Returns percentage of employees present vs total
     */
    private function getMonthlyAttendanceRate()
    {
        return $this->safe(function () {
            $now = now();
            $daysInMonth = $now->daysInMonth;

            // Count approved attendance records
            $presentDays = Attendance::whereMonth('attendance_date', $now->month)
                ->whereYear('attendance_date', $now->year)
                ->where('is_approved', true)
                ->where('status', 'present')
                ->distinct('employee_id')
                ->count('employee_id');

            // Total active employees
            $totalEmployees = Employee::where('status', 'active')->count();

            if ($totalEmployees === 0) {
                return 0;
            }

            // Calculate rate (max 100%)
            $rate = ($presentDays / ($totalEmployees * $daysInMonth)) * 100;
            return round(min($rate, 100), 1);
        }, 0);
    }

    /**
     * Get detailed employee list with status
     */
    public function employees(Request $request)
    {
        $query = Employee::query();

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filter by department
        if ($request->has('department') && $request->department) {
            $query->where('department', $request->department);
        }

        // Search by name or employee code
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $employees = $query->with('user', 'project')
            ->paginate(25);

        $departments = Employee::distinct()
            ->pluck('department')
            ->filter()
            ->values();

        return view('hr-manager.employees.index', compact('employees', 'departments'));
    }

    /**
     * Get API endpoint for dashboard statistics (JSON response)
     */
    public function getStatisticsApi()
    {
        $statistics = $this->getStatistics();
        return response()->json($statistics);
    }

    /**
     * Get pending approvals summary
     */
    public function getPendingApprovals()
    {
        $approvals = [
            'daily_reports' => $this->safe(
                fn() => DailyReport::where('status', 'pending')
                    ->orWhere('status', 'submitted')
                    ->count(),
                0
            ),
            'attendance_records' => $this->safe(
                fn() => Attendance::where('is_approved', false)->count(),
                0
            ),
            'manpower_requests' => $this->safe(
                fn() => ManpowerRequest::where('status', 'pending')
                    ->orWhere('status', 'submitted')
                    ->count(),
                0
            ),
        ];

        return response()->json($approvals);
    }
}
