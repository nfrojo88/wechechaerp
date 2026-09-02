@extends('layouts.app')
@section('title', 'HR Dashboard')
@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-users me-2 text-primary"></i>HR Dashboard
            </h1>
            <p class="text-muted mt-1">{{ now()->format('l, F j Y') }}</p>
        </div>
    </div>

    <!-- ── KPI Cards Row 1 — Employee & Payroll Stats ── -->
    <div class="row mb-3">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Active Employees</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $kpi['total_employees'] ?? $statistics['total_active_employees'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-id-badge fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Present Today</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $kpi['present_today'] ?? $statistics['present_today'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-calendar-check fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Payrolls</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $kpi['pending_payroll'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-money-bill-wave fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Open Manpower Requests</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $kpi['open_requests'] ?? $statistics['pending_manpower_requests'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-person-circle-plus fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── KPI Cards Row 2 — HR Manager Stats ── -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                <i class="fas fa-clipboard-list me-1"></i>Pending Daily Reports
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $statistics['pending_daily_reports'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                <i class="fas fa-person-dots-from-line me-1"></i>Pending Attendance
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $statistics['pending_attendance'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                <i class="fas fa-handshake me-1"></i>Active Subcon
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $statistics['active_subcon_agreements'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-briefcase fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                <i class="fas fa-user-times me-1"></i>Absent Today
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $statistics['absent_today'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-slash fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Main Content: Recent Payrolls + Quick Actions ── -->
    <div class="row mb-4">
        <!-- Recent Payrolls -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-file-invoice-dollar me-2"></i>Recent Payrolls
                    </h6>
                    <a href="{{ route('payrolls.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Employee</th>
                                    <th>Period</th>
                                    <th>Net Pay</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentPayrolls ?? [] as $payroll)
                                @php $net = $payroll->basic_salary + $payroll->allowances + $payroll->overtime_pay - $payroll->deductions - $payroll->tax; @endphp
                                <tr>
                                    <td>{{ $payroll->employee->first_name ?? 'N/A' }} {{ $payroll->employee->last_name ?? '' }}</td>
                                    <td>{{ date('M', mktime(0,0,0,$payroll->month,1)) }} {{ $payroll->year }}</td>
                                    <td><strong>${{ number_format($net, 2) }}</strong></td>
                                    <td>
                                        <span class="badge bg-{{ $payroll->status == 'paid' ? 'success' : 'warning' }}">
                                            {{ ucfirst($payroll->status) }}
                                        </span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">No payroll records found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                    <span class="badge bg-primary bg-opacity-10 text-primary small">HR Operations</span>
                </div>
                <div class="card-body d-flex flex-column gap-2">
                    <a href="{{ route('employee-letters.create') }}" class="btn btn-primary w-100 shadow-sm fw-bold py-2">
                        <i class="fas fa-file-signature me-2"></i>Record Official Letter
                    </a>
                    <a href="{{ route('employee-letters.index') }}" class="btn btn-outline-primary w-100 py-1.5">
                        <i class="fas fa-folder-open me-2"></i>Employee Letter Archive
                    </a>
                    <hr class="my-1">
                    <a href="{{ route('payrolls.create') }}" class="btn btn-outline-secondary w-100 text-start">
                        <i class="fas fa-plus me-2 text-primary"></i>Generate Payroll
                    </a>
                    <a href="{{ route('employees.create') }}" class="btn btn-outline-success w-100 text-start">
                        <i class="fas fa-user-plus me-2"></i>Add Employee
                    </a>
                    <a href="{{ route('attendance.create') }}" class="btn btn-outline-info w-100 text-start text-dark">
                        <i class="fas fa-clipboard-user me-2 text-info"></i>Mark Attendance
                    </a>
                    <a href="{{ route('manpower-requests.create') }}" class="btn btn-outline-warning text-dark w-100 text-start">
                        <i class="fas fa-person-circle-plus me-2 text-warning"></i>Manpower Request
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Pending Daily Reports ── -->
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-file-invoice me-2"></i>Pending Daily Reports
                    </h6>
                    <a href="#" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#dailyReportsModal">
                        View All
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Project</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pendingDailyReports as $report)
                                <tr>
                                    <td>
                                        <small>{{ $report->project->project_name ?? 'N/A' }}</small>
                                    </td>
                                    <td>{{ $report->report_date->format('M d, Y') }}</td>
                                    <td>
                                        <span class="badge bg-warning">{{ ucfirst($report->status) }}</span>
                                    </td>
                                    <td>
                                        <a href="{{ route('daily-reports.show', $report) }}" class="btn btn-xs btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">No pending reports</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Manpower Requests -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-person-circle-plus me-2"></i>Pending Manpower Requests
                    </h6>
                    <a href="{{ route('manpower-requests.index') }}" class="btn btn-sm btn-outline-primary">
                        All
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Project</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pendingManpowerRequests as $request)
                                <tr>
                                    <td>
                                        <small>{{ $request->project->project_name ?? 'N/A' }}</small>
                                    </td>
                                    <td>{{ $request->required_date->format('M d, Y') }}</td>
                                    <td>
                                        <span class="badge bg-warning">{{ ucfirst($request->status) }}</span>
                                    </td>
                                    <td>
                                        <a href="{{ route('manpower-requests.show', $request) }}" class="btn btn-xs btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">No pending requests</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Pending Leave Requests (Approve / Reject) ── -->
    <div class="row mb-4">
        <div class="col-lg-12">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-calendar-minus me-2"></i>Pending Leave Requests
                    </h6>
                    <a href="{{ route('leave-requests.index') }}" class="btn btn-sm btn-outline-primary">
                        View All Leaves
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Employee</th>
                                    <th>Leave Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Days</th>
                                    <th>Reason</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pendingLeaveRequests ?? [] as $leave)
                                <tr>
                                    <td>
                                        <div class="fw-bold">{{ $leave->employee->full_name ?? ($leave->employee->first_name ?? 'N/A') . ' ' . ($leave->employee->last_name ?? '') }}</div>
                                        <small class="text-muted">{{ $leave->employee->employee_id ?? '' }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark">{{ $leave->leaveType->name ?? 'Leave' }}</span>
                                    </td>
                                    <td>{{ \Carbon\Carbon::parse($leave->start_date)->format('M d, Y') }}</td>
                                    <td>{{ \Carbon\Carbon::parse($leave->end_date)->format('M d, Y') }}</td>
                                    <td><strong>{{ $leave->days_requested ?? \Carbon\Carbon::parse($leave->start_date)->diffInDays(\Carbon\Carbon::parse($leave->end_date)) + 1 }}</strong></td>
                                    <td><small class="text-muted">{{ Str::limit($leave->reason, 40) }}</small></td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            <form action="{{ route('leave-requests.approve', $leave) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success px-2 py-1" onclick="return confirm('Approve this leave request?')">
                                                    <i class="fas fa-check me-1"></i>Approve
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-danger px-2 py-1" data-bs-toggle="modal" data-bs-target="#rejectLeaveModal{{ $leave->id }}">
                                                <i class="fas fa-times me-1"></i>Reject
                                            </button>
                                        </div>

                                        <!-- Reject Modal -->
                                        <div class="modal fade" id="rejectLeaveModal{{ $leave->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content text-start">
                                                    <form action="{{ route('leave-requests.reject', $leave) }}" method="POST">
                                                        @csrf
                                                        <div class="modal-header">
                                                            <h5 class="modal-title text-danger"><i class="fas fa-times-circle me-2"></i>Reject Leave Request</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p class="mb-2">Employee: <strong>{{ $leave->employee->full_name ?? 'N/A' }}</strong></p>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-semibold">Rejection Reason <span class="text-danger">*</span></label>
                                                                <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="Provide reason for rejection (min 10 characters)..."></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-3">No pending leave requests to review.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Active Subcontractor Agreements ── -->
    <div class="row mb-4">
        <div class="col-lg-12">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-handshake me-2"></i>Active Subcontractor Agreements
                    </h6>
                    <a href="{{ route('subcon-agreements.index') }}" class="btn btn-sm btn-outline-primary">
                        All
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Subcontractor</th>
                                    <th>Project</th>
                                    <th>Status</th>
                                    <th>End Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($subconAgreements as $agreement)
                                <tr>
                                    <td>
                                        <small>{{ $agreement->subcontractor->name ?? $agreement->project->project_name ?? 'N/A' }}</small>
                                    </td>
                                    <td>
                                        <small>{{ $agreement->project->project_name ?? 'N/A' }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $agreement->status === 'active' ? 'success' : 'info' }}">
                                            {{ ucfirst($agreement->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <small>{{ $agreement->end_date->format('M d, Y') }}</small>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">No active agreements</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Quick Access Modules ── -->
    <div class="row mb-4">
        <div class="col-12 mb-3">
            <div class="d-flex align-items-center gap-2">
                <div class="rounded-3 p-2 text-white d-flex align-items-center justify-content-center" style="background: linear-gradient(135deg, var(--brand-700), var(--brand-500)); width:36px; height:36px;">
                    <i class="fa-solid fa-grid-2 fa-sm"></i>
                </div>
                <h5 class="fw-bold mb-0 text-gray-800">Quick Access Modules</h5>
                <span class="badge bg-primary bg-opacity-10 text-primary fw-normal ms-1 px-2">HR Officer Tools</span>
            </div>
        </div>

        <!-- Record Attendance -->
        <div class="col-lg-3 col-md-6 mb-3">
            <a href="{{ route('attendance.create') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden quick-module-card">
                    <div class="card-body p-4 d-flex align-items-center gap-3">
                        <div class="rounded-3 p-3 flex-shrink-0" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);">
                            <i class="fa-solid fa-calendar-check fa-lg text-white"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-gray-900 fs-6 mb-0">Record Attendance</div>
                            <small class="text-muted">Mark &amp; record attendance</small>
                        </div>
                        <i class="fa-solid fa-arrow-right ms-auto text-muted small"></i>
                    </div>
                    <div style="height: 4px; background: linear-gradient(90deg, #3b82f6, #1d4ed8);"></div>
                </div>
            </a>
        </div>

        <!-- Leave Requests -->
        <div class="col-lg-3 col-md-6 mb-3">
            <a href="{{ route('leave-requests.index') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden quick-module-card">
                    <div class="card-body p-4 d-flex align-items-center gap-3">
                        <div class="rounded-3 p-3 flex-shrink-0" style="background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);">
                            <i class="fa-solid fa-calendar-xmark fa-lg text-white"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-gray-900 fs-6 mb-0">Leave Requests</div>
                            <small class="text-muted">Approve / review leaves</small>
                        </div>
                        <i class="fa-solid fa-arrow-right ms-auto text-muted small"></i>
                    </div>
                    <div style="height: 4px; background: linear-gradient(90deg, #ef4444, #b91c1c);"></div>
                </div>
            </a>
        </div>

        <!-- Approve Daily Reports -->
        <div class="col-lg-3 col-md-6 mb-3">
            <a href="{{ route('daily-reports.approval') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden quick-module-card">
                    <div class="card-body p-4 d-flex align-items-center gap-3">
                        <div class="rounded-3 p-3 flex-shrink-0" style="background: linear-gradient(135deg, #f59e0b 0%, #b45309 100%);">
                            <i class="fa-solid fa-file-circle-check fa-lg text-white"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-gray-900 fs-6 mb-0">Approve Daily Reports</div>
                            <small class="text-muted">Review &amp; approve reports</small>
                        </div>
                        <i class="fa-solid fa-arrow-right ms-auto text-muted small"></i>
                    </div>
                    <div style="height: 4px; background: linear-gradient(90deg, #f59e0b, #b45309);"></div>
                </div>
            </a>
        </div>

        <!-- Subcon Agreements -->
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <a href="{{ route('subcon-agreements.index') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden quick-module-card">
                    <div class="card-body p-3 d-flex align-items-center gap-2">
                        <div class="rounded-3 p-2 flex-shrink-0" style="background: linear-gradient(135deg, #8b5cf6 0%, #5b21b6 100%);">
                            <i class="fa-solid fa-handshake fa-lg text-white"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-gray-900 small mb-0">Subcon</div>
                            <small class="text-muted" style="font-size: 11px;">Agreements</small>
                        </div>
                    </div>
                    <div style="height: 4px; background: linear-gradient(90deg, #8b5cf6, #5b21b6);"></div>
                </div>
            </a>
        </div>

        <!-- Employee Letters -->
        <div class="col-lg-4 col-md-4 col-sm-6 mb-3">
            <a href="{{ route('employee-letters.index') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden quick-module-card">
                    <div class="card-body p-3 d-flex align-items-center gap-3">
                        <div class="rounded-3 p-2 flex-shrink-0" style="background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);">
                            <i class="fa-solid fa-file-signature fa-lg text-white"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-gray-900 fs-6 mb-0">Official Letters &amp; Notices</div>
                            <small class="text-muted">Issue &amp; record warnings, appreciations, guarantees</small>
                        </div>
                        <span class="badge bg-indigo text-white ms-auto">{{ $statistics['total_employee_letters'] ?? 0 }}</span>
                    </div>
                    <div style="height: 4px; background: linear-gradient(90deg, #6366f1, #4338ca);"></div>
                </div>
            </a>
        </div>
    </div>

    <style>
    .quick-module-card {
        transition: transform 0.2s, box-shadow 0.2s;
        cursor: pointer;
    }
    .quick-module-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12) !important;
    }
    .bg-indigo {
        background-color: #6366f1 !important;
    }
    </style>

    <!-- ── Official Employee Letters & Disciplinary Records ── -->
    <div class="row mb-4">
        <div class="col-lg-12">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-file-contract me-2"></i>Official Employee Letters &amp; Notices
                        </h6>
                        <small class="text-muted">Recorded disciplinary warnings, appreciations, promotions, and guarantee letters</small>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('employee-letters.create') }}" class="btn btn-sm btn-primary shadow-sm fw-bold">
                            <i class="fas fa-plus me-1"></i> Issue / Record Letter
                        </a>
                        <a href="{{ route('employee-letters.index') }}" class="btn btn-sm btn-outline-primary">
                            View All Letters ({{ $statistics['total_employee_letters'] ?? 0 }})
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Ref Number &amp; Date</th>
                                    <th>Employee</th>
                                    <th>Letter Type</th>
                                    <th>Title / Subject</th>
                                    <th>Issuer (HR)</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentEmployeeLetters ?? [] as $ltr)
                                <tr>
                                    <td>
                                        <div class="font-monospace fw-bold text-primary small">{{ $ltr->reference_number ?: 'LTR-#'.$ltr->id }}</div>
                                        <small class="text-muted">{{ optional($ltr->issued_date)->format('M d, Y') }}</small>
                                    </td>
                                    <td>
                                        <div class="fw-bold">{{ $ltr->employee->full_name ?? 'N/A' }}</div>
                                        <small class="text-muted font-monospace">{{ $ltr->employee->employee_code ?? '' }}</small>
                                        @if($ltr->employee->role_title)
                                            <span class="text-muted small">&bull; {{ $ltr->employee->role_title }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $ltr->badge_class }} px-2 py-1">
                                            <i class="{{ $ltr->icon }} me-1"></i>{{ $ltr->type_label }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark text-truncate d-inline-block" style="max-width: 260px;" title="{{ $ltr->title }}">
                                            {{ $ltr->title }}
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">{{ $ltr->issuer->name ?? 'HR Officer' }}</small>
                                    </td>
                                    <td>
                                        @if($ltr->attachment_path)
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success small">
                                                <i class="fas fa-paperclip me-1"></i>Attachment
                                            </span>
                                        @else
                                            <span class="badge bg-light text-muted border small">Generated</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('employee-letters.show', $ltr) }}" class="btn btn-outline-primary" title="View Full Letter">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('employee-letters.print', $ltr) }}" target="_blank" class="btn btn-outline-secondary" title="Print Letterhead">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            <a href="{{ route('employee-letters.edit', $ltr) }}" class="btn btn-outline-warning" title="Edit Letter">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="fas fa-folder-open fa-2x mb-2 d-block opacity-25"></i>
                                        No official employee letters recorded yet. Click <strong>"Issue / Record Letter"</strong> to record warnings, appreciations, or agreements.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Recent Activities ── -->
    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-history me-2"></i>Recent Activities
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th>Activity</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentActivities as $activity)
                                <tr>
                                    <td>{{ $activity->user->name ?? 'System' }}</td>
                                    <td>{{ $activity->activity ?? 'Activity' }}</td>
                                    <td>
                                        <small class="text-muted">{{ $activity->entered_at->diffForHumans() ?? 'N/A' }}</small>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-3">No recent activities</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Action Buttons (Floating) -->
<div class="position-fixed bottom-0 end-0 p-3">
    <div class="d-flex flex-column gap-2">
        <a href="{{ route('employees.create') }}" class="btn btn-primary btn-lg rounded-circle" title="Add Employee">
            <i class="fas fa-user-plus"></i>
        </a>
        <a href="{{ route('attendance.create') }}" class="btn btn-info btn-lg rounded-circle text-white" title="Mark Attendance">
            <i class="fas fa-clipboard-list"></i>
        </a>
    </div>
</div>

@endsection
