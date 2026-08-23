@extends('layouts.app')
@section('title', 'HR Attendance Report - Construct-Pro ERP')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="h3 mb-1 fw-bold">
                <i class="fa-solid fa-calendar-check text-primary me-2"></i>HR Attendance Report
            </h2>
            <p class="text-muted small mb-0">Overview of staff attendance rate, presence, absences, and leaves across departments</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#sendToGmModal">
                <i class="fa-solid fa-paper-plane me-1"></i>Send to GM
            </button>
            <a href="{{ route('reports.attendance.export', ['from_date' => $fromDate, 'to_date' => $toDate]) }}" class="btn btn-outline-success btn-sm rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-file-csv me-1"></i>Export CSV
            </a>
            <a href="{{ route('attendance.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i>Attendance Logs
            </a>
        </div>
    </div>

    <!-- Navigation Report Tabs -->
    <div class="mb-4">
        <div class="nav nav-pills flex-column flex-sm-row gap-2 bg-light p-2 rounded-4 border">
            <a href="{{ route('reports.attendance') }}" class="nav-link active rounded-pill px-3 fw-semibold">
                <i class="fa-solid fa-calendar-check me-1"></i>Attendance Report
            </a>
            <a href="{{ route('reports.turnover') }}" class="nav-link rounded-pill px-3 fw-semibold text-secondary">
                <i class="fa-solid fa-user-slash me-1"></i>Turnover &amp; Retention
            </a>
            <a href="{{ route('reports.cost-analysis') }}" class="nav-link rounded-pill px-3 fw-semibold text-secondary">
                <i class="fa-solid fa-money-bill-trend-up me-1"></i>Payroll Cost Analysis
            </a>
            <a href="{{ route('reports.leave-analysis') }}" class="nav-link rounded-pill px-3 fw-semibold text-secondary">
                <i class="fa-solid fa-plane-departure me-1"></i>Leave Analysis
            </a>
            <a href="{{ route('reports.employee-cost') }}" class="nav-link rounded-pill px-3 fw-semibold text-secondary">
                <i class="fa-solid fa-id-card-clip me-1"></i>Employee Cost Breakdown
            </a>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #3b82f6 !important;">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold">Tracked Employees</div>
                            <div class="h3 mb-0 fw-bold text-primary">{{ $stats['total_employees'] }}</div>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:#dbeafe;">
                            <i class="fa-solid fa-users text-primary fa-lg"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">Active non-remote personnel</div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #10b981 !important;">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold">Average Attendance Rate</div>
                            <div class="h3 mb-0 fw-bold text-success">{{ number_format($stats['avg_attendance'] ?? 0, 1) }}%</div>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:#d1fae5;">
                            <i class="fa-solid fa-chart-pie text-success fa-lg"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">Across all recorded working days</div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #6366f1 !important;">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold">Total Working Days</div>
                            <div class="h3 mb-0 fw-bold text-indigo">{{ $stats['total_working_days'] }}</div>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:#e0e7ff;">
                            <i class="fa-solid fa-calendar-days text-indigo fa-lg"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">Within selected date range</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('reports.attendance') }}" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-secondary mb-1">From Date</label>
                    <input type="date" name="from_date" class="form-control form-control-sm rounded-3" value="{{ $fromDate }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-secondary mb-1">To Date</label>
                    <input type="date" name="to_date" class="form-control form-control-sm rounded-3" value="{{ $toDate }}">
                </div>
                <div class="col-md-4 d-flex gap-2 pt-3">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill flex-grow-1">
                        <i class="fa-solid fa-filter me-1"></i>Filter Period
                    </button>
                    <a href="{{ route('reports.attendance') }}" class="btn btn-outline-secondary btn-sm rounded-pill">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Department Summary Table -->
    @if(count($departmentSummary) > 0)
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-light py-3 border-0 rounded-top-4">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-building me-2 text-primary"></i>Department Attendance Breakdown
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Department</th>
                        <th class="text-center text-success">Present Days</th>
                        <th class="text-center text-danger">Absent Days</th>
                        <th class="text-center text-warning">Leave Days</th>
                        <th class="text-center">Total Logs</th>
                        <th class="text-center">Attendance %</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($departmentSummary as $dept)
                    @php
                        $deptRate = $dept->total > 0 ? ($dept->present / $dept->total) * 100 : 0;
                    @endphp
                    <tr>
                        <td class="fw-bold text-dark">{{ $dept->name }}</td>
                        <td class="text-center fw-semibold text-success">{{ $dept->present }}</td>
                        <td class="text-center fw-semibold text-danger">{{ $dept->absent }}</td>
                        <td class="text-center fw-semibold text-warning">{{ $dept->leave_count ?? $dept->leave ?? 0 }}</td>
                        <td class="text-center text-muted">{{ $dept->total }}</td>
                        <td class="text-center">
                            <span class="badge {{ $deptRate >= 85 ? 'bg-success' : ($deptRate >= 70 ? 'bg-warning text-dark' : 'bg-danger') }} rounded-pill px-3 py-1">
                                {{ number_format($deptRate, 1) }}%
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- Employee Detailed Attendance List -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-light py-3 border-0 rounded-top-4">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-user-check me-2 text-primary"></i>Employee Attendance Performance
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th class="text-center text-success">Present</th>
                        <th class="text-center text-danger">Absent</th>
                        <th class="text-center text-warning">Leave</th>
                        <th class="text-center">Rate (%)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attendanceData as $row)
                    <tr>
                        <td>
                            <div class="fw-bold text-dark">{{ $row['employee']->name ?? $row['employee']->full_name }}</div>
                            <small class="text-muted">{{ $row['employee']->employee_id ?? 'EMP-' . $row['employee']->id }}</small>
                        </td>
                        <td>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border">
                                {{ $row['employee']->department->name ?? ($row['employee']->department_name ?? 'General') }}
                            </span>
                        </td>
                        <td class="text-center fw-bold text-success">{{ $row['present'] }}</td>
                        <td class="text-center fw-bold text-danger">{{ $row['absent'] }}</td>
                        <td class="text-center fw-bold text-warning">{{ $row['leave'] }}</td>
                        <td class="text-center">
                            <div class="d-flex align-items-center justify-content-center gap-2">
                                <div class="progress flex-grow-1" style="height: 6px; max-width: 80px;">
                                    <div class="progress-bar {{ $row['attendance_percentage'] >= 85 ? 'bg-success' : ($row['attendance_percentage'] >= 70 ? 'bg-warning' : 'bg-danger') }}" 
                                         style="width: {{ min(100, $row['attendance_percentage']) }}%"></div>
                                </div>
                                <span class="fw-bold {{ $row['attendance_percentage'] >= 85 ? 'text-success' : ($row['attendance_percentage'] >= 70 ? 'text-warning' : 'text-danger') }}">
                                    {{ number_format($row['attendance_percentage'], 1) }}%
                                </span>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-clipboard-question fa-3x mb-2 opacity-50"></i>
                            <p class="mb-0">No attendance data found for the selected period.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- GM Submissions History -->
    @if(isset($recentSubmissions) && count($recentSubmissions) > 0)
    <div class="card border-0 shadow-sm rounded-4 mt-4">
        <div class="card-header bg-light py-3 border-0 rounded-top-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-paper-plane me-2 text-primary"></i>Recent Submissions to General Manager (GM)
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Report Type</th>
                        <th>Period</th>
                        <th>Submitted By</th>
                        <th>Submitted At</th>
                        <th>Status</th>
                        <th>GM Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentSubmissions as $sub)
                    <tr>
                        <td class="fw-bold text-dark">{{ $sub->report_type }}</td>
                        <td>{{ $sub->from_date ? $sub->from_date->format('d M') : '-' }} to {{ $sub->to_date ? $sub->to_date->format('d M Y') : '-' }}</td>
                        <td>{{ $sub->submitter->name ?? 'HR Officer' }}</td>
                        <td>{{ $sub->created_at ? $sub->created_at->format('d M Y, h:i A') : '-' }}</td>
                        <td>
                            @if($sub->status === 'reviewed' || $sub->status === 'acknowledged')
                                <span class="badge bg-success rounded-pill px-3"><i class="fa-solid fa-check me-1"></i>{{ ucfirst($sub->status) }}</span>
                            @else
                                <span class="badge bg-warning text-dark rounded-pill px-3"><i class="fa-solid fa-clock me-1"></i>Sent / Pending GM</span>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $sub->gm_remarks ?? ($sub->notes ?? '—') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>

<!-- Modal: Send Report to GM -->
<div class="modal fade" id="sendToGmModal" tabindex="-1" aria-labelledby="sendToGmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <form action="{{ Route::has('reports.attendance.send-gm') ? route('reports.attendance.send-gm') : url('/reports/attendance/send-gm') }}" method="POST">
                @csrf
                <div class="modal-header border-0 bg-light rounded-top-4 py-3">
                    <h5 class="modal-title fw-bold text-dark" id="sendToGmModalLabel">
                        <i class="fa-solid fa-paper-plane text-primary me-2"></i>Send Report to General Manager
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Report Type</label>
                        <input type="text" name="report_type" class="form-control rounded-3" value="HR Attendance & Manpower Report" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Period From</label>
                            <input type="date" name="from_date" class="form-control rounded-3" value="{{ $fromDate }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Period To</label>
                            <input type="date" name="to_date" class="form-control rounded-3" value="{{ $toDate }}" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Notes / Summary Remarks for GM (Optional)</label>
                        <textarea name="notes" class="form-control rounded-3" rows="3" placeholder="e.g. Attendance summary across all departments for management review. Overall attendance rate is {{ number_format($stats['avg_attendance'] ?? 0, 1) }}%."></textarea>
                    </div>
                    <div class="alert alert-info py-2 small rounded-3 border-0 mb-0">
                        <i class="fa-solid fa-circle-info me-1"></i>This report will be sent directly to the General Manager's dashboard and alerts.
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4 py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 shadow-sm">
                        <i class="fa-solid fa-paper-plane me-1"></i>Confirm &amp; Send to GM
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
