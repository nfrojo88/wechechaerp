<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\LeaveBalance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class LeaveRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private function ensureDefaultLeaveTypes(): void
    {
        try {
            if (LeaveType::count() === 0) {
                LeaveType::create(['name' => 'Annual Leave (ዓመታዊ ፈቃድ)', 'code' => 'ANNUAL', 'days_allowed' => 16, 'is_paid' => true, 'requires_documentation' => false, 'is_active' => true]);
                LeaveType::create(['name' => 'Sick Leave (የህመም ፈቃድ)', 'code' => 'SICK', 'days_allowed' => 30, 'is_paid' => true, 'requires_documentation' => true, 'is_active' => true]);
                LeaveType::create(['name' => 'Maternity Leave (የወሊድ ፈቃድ)', 'code' => 'MATERNITY', 'days_allowed' => 90, 'is_paid' => true, 'requires_documentation' => true, 'is_active' => true]);
                LeaveType::create(['name' => 'Paternity Leave (የአባትነት ፈቃድ)', 'code' => 'PATERNITY', 'days_allowed' => 5, 'is_paid' => true, 'requires_documentation' => false, 'is_active' => true]);
                LeaveType::create(['name' => 'Compassionate / Emergency (የሐዘን ፈቃድ)', 'code' => 'COMPASSIONATE', 'days_allowed' => 5, 'is_paid' => true, 'requires_documentation' => false, 'is_active' => true]);
                LeaveType::create(['name' => 'Unpaid Leave (ያለ ክፍያ ፈቃድ)', 'code' => 'UNPAID', 'days_allowed' => 30, 'is_paid' => false, 'requires_documentation' => false, 'is_active' => true]);
            }
        } catch (\Throwable $e) {}
    }

    protected function resolveEmployee(?int $employeeId = null): ?Employee
    {
        $user = Auth::user();
        if ($employeeId) {
            return Employee::find($employeeId);
        }
        if ($user) {
            $emp = Employee::where('user_id', $user->id)->first();
            if (!$emp && $user->email) {
                $emp = Employee::where('email', $user->email)->first();
            }
            if (!$emp && $user->phone) {
                $emp = Employee::where('phone', $user->phone)->first();
            }
            return $emp;
        }
        return null;
    }

    /**
     * Display all leave requests (HR & GM view)
     */
    public function index(Request $request)
    {
        $this->ensureDefaultLeaveTypes();
        $this->authorize('viewAny', LeaveRequest::class);

        $query = LeaveRequest::with(['employee.project', 'leaveType', 'approvedByUser']);

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('start_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('end_date', '<=', $request->to_date);
        }

        $leaveRequests = $query->orderBy('created_at', 'desc')->paginate(15);

        // Attach balance info to each leave request for GM & HR quick view
        $currentYear = Carbon::now()->year;
        foreach ($leaveRequests as $lr) {
            if ($lr->employee_id && $lr->leave_type_id) {
                $lr->balance = LeaveBalance::getOrCreateBalance($lr->employee_id, $lr->leave_type_id, $lr->start_date ? $lr->start_date->year : $currentYear);
            }
        }

        $leaveTypes = LeaveType::where('is_active', true)->get();
        $employees = Employee::where('status', 'active')->orderBy('full_name')->get();

        return view('hr-manager.leave-requests.index', compact(
            'leaveRequests',
            'leaveTypes',
            'employees'
        ));
    }

    /**
     * Show employee's own leave requests
     */
    public function myRequests(Request $request)
    {
        $this->ensureDefaultLeaveTypes();
        $employee = $this->resolveEmployee();

        if (!$employee) {
            return redirect()->route('dashboard')->with('warning', 'No employee profile linked to your user account.');
        }

        $query = LeaveRequest::where('employee_id', $employee->id)
            ->with(['leaveType', 'approvedByUser']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $leaveRequests = $query->orderBy('start_date', 'desc')->paginate(10);
        $currentYear = Carbon::now()->year;
        $balances = LeaveBalance::where('employee_id', $employee->id)
            ->where('year', $currentYear)
            ->with('leaveType')
            ->get();

        return view('hr-manager.leave-requests.my-requests', compact('leaveRequests', 'employee', 'balances'));
    }

    /**
     * Create leave request form
     */
    public function create(Request $request)
    {
        $this->ensureDefaultLeaveTypes();
        $user = Auth::user();
        $isHrOrGm = $user && ($user->hasAnyRole(['hr', 'hr_manager', 'hr_officer', 'admin', 'global_admin', 'gm', 'general_manager']) || str_contains(strtolower(implode(' ', $user->getRoleNames()->toArray())), 'gm'));
        
        $employee = $this->resolveEmployee($request->input('employee_id'));
        $allEmployees = $isHrOrGm ? Employee::where('status', 'active')->orderBy('full_name')->get() : collect();

        if (!$employee && $allEmployees->isNotEmpty()) {
            $employee = $allEmployees->first();
        }

        if (!$employee) {
            return redirect()->route('employees.index')->with('warning', 'Please link your account to an employee profile or select an employee.');
        }

        $leaveTypes = LeaveType::where('is_active', true)->get();
        
        // Get current year balances
        $currentYear = Carbon::now()->year;
        $balances = collect();
        foreach ($leaveTypes as $lt) {
            $balances->push(LeaveBalance::getOrCreateBalance($employee->id, $lt->id, $currentYear));
        }

        return view('hr-manager.leave-requests.create', compact('employee', 'leaveTypes', 'balances', 'allEmployees', 'isHrOrGm'));
    }

    /**
     * Store leave request
     */
    public function store(Request $request)
    {
        $this->ensureDefaultLeaveTypes();
        $user = Auth::user();
        $isHrOrGm = $user && ($user->hasAnyRole(['hr', 'hr_manager', 'hr_officer', 'admin', 'global_admin', 'gm', 'general_manager']) || str_contains(strtolower(implode(' ', $user->getRoleNames()->toArray())), 'gm'));

        $employeeId = $isHrOrGm && $request->filled('employee_id') ? (int)$request->employee_id : null;
        $employee = $this->resolveEmployee($employeeId);

        if (!$employee) {
            return back()->withErrors(['employee_id' => 'Employee profile not found.']);
        }

        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|min:5|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
        ]);

        $leaveType = LeaveType::findOrFail($validated['leave_type_id']);
        $daysRequested = Carbon::parse($validated['start_date'])
            ->diffInDays(Carbon::parse($validated['end_date'])) + 1;

        // Check for overlapping leave
        $overlap = LeaveRequest::where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($validated) {
                $q->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
                  ->orWhereBetween('end_date', [$validated['start_date'], $validated['end_date']])
                  ->orWhere(function ($q2) use ($validated) {
                      $q2->where('start_date', '<=', $validated['start_date'])
                         ->where('end_date', '>=', $validated['end_date']);
                  });
            })
            ->exists();

        if ($overlap) {
            return back()->withInput()->withErrors(['start_date' => 'An active or pending leave request already overlaps with these selected dates.']);
        }

        // Check balance against employee's Statutory Annual Leave Balance
        $year = Carbon::parse($validated['start_date'])->year;
        $annualType = LeaveType::where('code', 'ANNUAL')->orWhere('name', 'like', '%Annual%')->first();
        $annualTypeId = $annualType ? $annualType->id : $leaveType->id;
        $balance = LeaveBalance::getOrCreateBalance($employee->id, $annualTypeId, $year);

        if ($leaveType->code === 'ANNUAL' || str_contains(strtolower($leaveType->name), 'annual')) {
            if (!$balance || !$balance->hasEnoughBalance($daysRequested)) {
                $available = $balance ? $balance->remaining_days : 0;
                return back()->withInput()->withErrors(['leave_type_id' => "Insufficient annual leave balance. You requested {$daysRequested} day(s), but only {$available} day(s) remain available."]);
            }
        }

        // Handle attachment
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('leave_attachments', 'public');
        }

        // Create leave request
        $leaveRequest = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $validated['leave_type_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'],
            'attachment' => $attachmentPath,
            'status' => 'pending',
        ]);

        return redirect()->route('leave-requests.index')
            ->with('success', 'Leave request submitted successfully. It is now awaiting General Manager / HR review.');
    }

    /**
     * Show leave request details
     */
    public function show(LeaveRequest $leaveRequest)
    {
        $this->authorize('view', $leaveRequest);
        $leaveRequest->load(['employee.project', 'leaveType', 'approvedByUser']);

        $year = $leaveRequest->start_date ? $leaveRequest->start_date->year : Carbon::now()->year;
        $annualType = LeaveType::where('code', 'ANNUAL')->orWhere('name', 'like', '%Annual%')->first();
        $annualTypeId = $annualType ? $annualType->id : $leaveRequest->leave_type_id;
        $currentBalance = LeaveBalance::getOrCreateBalance($leaveRequest->employee_id, $annualTypeId, $year);

        return view('hr-manager.leave-requests.show', compact('leaveRequest', 'currentBalance'));
    }

    /**
     * Approve leave request
     */
    public function approve(LeaveRequest $leaveRequest)
    {
        $this->authorize('approve', $leaveRequest);

        if (!$leaveRequest->isPending()) {
            return back()->withErrors(['status' => 'Only pending requests can be approved']);
        }

        $daysRequested = $leaveRequest->days_requested;

        // Deduct from employee's statutory annual leave balance
        $year = $leaveRequest->start_date ? $leaveRequest->start_date->year : Carbon::now()->year;
        $annualType = LeaveType::where('code', 'ANNUAL')->orWhere('name', 'like', '%Annual%')->first();
        $annualTypeId = $annualType ? $annualType->id : $leaveRequest->leave_type_id;
        $balance = LeaveBalance::getOrCreateBalance($leaveRequest->employee_id, $annualTypeId, $year);

        if ($balance) {
            $balance->updateBalance($daysRequested);
        }

        // Update leave request
        $leaveRequest->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Leave request approved and granted successfully.');
    }


    /**
     * Reject leave request
     */
    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        $this->authorize('reject', $leaveRequest);

        if (!$leaveRequest->isPending()) {
            return back()->withErrors(['status' => 'Only pending requests can be rejected']);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|min:10|max:500',
        ]);

        $leaveRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Leave request rejected');
    }

    /**
     * Bulk approve leave requests
     */
    public function bulkApprove(Request $request)
    {
        $this->authorize('approve', LeaveRequest::class);

        $validated = $request->validate([
            'request_ids' => 'required|array|min:1',
            'request_ids.*' => 'integer|exists:leave_requests,id',
        ]);

        $leaveRequests = LeaveRequest::whereIn('id', $validated['request_ids'])
            ->where('status', 'pending')
            ->get();

        $approved = 0;
        foreach ($leaveRequests as $leaveRequest) {
            $daysRequested = $leaveRequest->days_requested;

            $balance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
                ->where('leave_type_id', $leaveRequest->leave_type_id)
                ->where('year', $leaveRequest->start_date->year)
                ->first();

            if ($balance && $balance->hasEnoughBalance($daysRequested)) {
                $balance->updateBalance($daysRequested);

                $leaveRequest->update([
                    'status' => 'approved',
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                ]);

                $approved++;
            }
        }

        return back()->with('success', "Approved $approved leave requests");
    }

    /**
     * Get leave balance for employee
     */
    public function getBalance(Employee $employee)
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $balances = LeaveBalance::where('employee_id', $employee->id)
            ->where('year', Carbon::now()->year)
            ->with('leaveType')
            ->get();

        return response()->json($balances);
    }

    /**
     * Export leave report
     */
    public function exportReport(Request $request)
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $query = LeaveRequest::with(['employee', 'leaveType']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('start_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('end_date', '<=', $request->to_date);
        }

        $leaveRequests = $query->get();

        $fileName = 'leave-requests-' . now()->format('Y-m-d-His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"$fileName\"",
        ];

        $callback = function () use ($leaveRequests) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Employee', 'Leave Type', 'Start Date', 'End Date', 'Days', 'Status', 'Reason']);

            foreach ($leaveRequests as $lr) {
                fputcsv($file, [
                    $lr->employee->name,
                    $lr->leaveType->name,
                    $lr->start_date->format('Y-m-d'),
                    $lr->end_date->format('Y-m-d'),
                    $lr->days_requested,
                    $lr->status,
                    $lr->reason,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
