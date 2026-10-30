@extends('layouts.app')
@section('title', 'HR Attendance Report — Wechecha ERP')

@section('content')
<div class="container-fluid px-0">
    <x-print-header title="HR Attendance & Workforce Report" :dateRange="\Carbon\Carbon::parse($fromDate)->format('M d, Y') . ' to ' . \Carbon\Carbon::parse($toDate)->format('M d, Y')" />

    {{-- Page Header --}}
    <x-page-header
        title="HR Attendance & Workforce Report"
        subtitle="Staff attendance rate, presence, unexcused absences, biometric late penalties, and approved leaves across departments."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'HR Management', 'url' => route('dashboard.hr')],
            ['label' => 'HR Reports', 'url' => route('reports.attendance')],
            ['label' => 'Attendance']
        ]">
        <x-slot:actions>
            <a href="{{ route('attendance.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-fingerprint me-1.5 text-primary"></i>Attendance Logs
            </a>
            <x-export-menu :csvUrl="route('reports.attendance.export', ['from_date' => $fromDate, 'to_date' => $toDate])" :print="true" />
            <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold" data-bs-toggle="modal" data-bs-target="#sendToGmModal">
                <i class="fa-solid fa-paper-plane me-1.5"></i>Send to GM
            </button>
        </x-slot:actions>
    </x-page-header>

    {{-- Unified HR Reports Navigation Pill Bar --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-2">
            <div class="nav nav-pills flex-column flex-sm-row gap-2">
                <a href="{{ route('reports.attendance') }}" class="nav-link active rounded-pill px-3 py-2 fw-semibold text-center">
                    <i class="fa-solid fa-calendar-check me-1.5"></i>Attendance Report
                </a>
                <a href="{{ route('reports.turnover') }}" class="nav-link rounded-pill px-3 py-2 fw-semibold text-secondary text-center">
                    <i class="fa-solid fa-user-slash me-1.5"></i>Turnover &amp; Retention
                </a>
                <a href="{{ route('reports.cost-analysis') }}" class="nav-link rounded-pill px-3 py-2 fw-semibold text-secondary text-center">
                    <i class="fa-solid fa-money-bill-trend-up me-1.5"></i>Payroll Cost Analysis
                </a>
                <a href="{{ route('reports.leave-analysis') }}" class="nav-link rounded-pill px-3 py-2 fw-semibold text-secondary text-center">
                    <i class="fa-solid fa-plane-departure me-1.5"></i>Leave Analysis
                </a>
                <a href="{{ route('reports.employee-cost') }}" class="nav-link rounded-pill px-3 py-2 fw-semibold text-secondary text-center">
                    <i class="fa-solid fa-id-card-clip me-1.5"></i>Employee Cost Breakdown
                </a>
            </div>
        </div>
    </div>

    {{-- Executive Summary KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <x-kpi-card
                title="Tracked Employees"
                :value="number_format($stats['total_employees'])"
                changeLabel="Active non-remote personnel"
                icon="fa-solid fa-users"
                color="primary" />
        </div>
        <div class="col-12 col-md-4">
            <x-kpi-card
                title="Average Attendance Rate"
                :value="number_format($stats['avg_attendance'] ?? 0, 1) . '%'"
                changeLabel="Across recorded working days"
                icon="fa-solid fa-chart-pie"
                color="success" />
        </div>
        <div class="col-12 col-md-4">
            <x-kpi-card
                title="Total Working Days"
                :value="number_format($stats['total_working_days'])"
                changeLabel="Within selected date range"
                icon="fa-solid fa-calendar-days"
                color="info" />
        </div>
    </div>

    {{-- Date Range Filter --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('reports.attendance') }}" class="row g-2 align-items-end">
                <div class="col-12 col-sm-6 col-md-4">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">From Date</label>
                    <input type="date" name="from_date" class="form-control form-control-sm" value="{{ $fromDate }}" required>
                </div>
                <div class="col-12 col-sm-6 col-md-4">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">To Date</label>
                    <input type="date" name="to_date" class="form-control form-control-sm" value="{{ $toDate }}" required>
                </div>
                <div class="col-12 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 py-2 w-100 fw-semibold shadow-xs">
                        <i class="fa-solid fa-filter me-1.5"></i>Filter Period
                    </button>
                    <a href="{{ route('reports.attendance') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs" title="Reset filter">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Department Summary Table --}}
    @if(count($departmentSummary) > 0)
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2.5 py-1 fw-bold small">
                        <i class="fa-solid fa-building me-1"></i>Departments
                    </span>
                    <h6 class="fw-bold mb-0 text-dark">Department Attendance Breakdown</h6>
                </div>
                <span class="text-muted small">{{ count($departmentSummary) }} Departments</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 text-secondary text-uppercase small fw-bold">Department</th>
                            <th class="text-center text-secondary text-uppercase small fw-bold text-success">Present Days</th>
                            <th class="text-center text-secondary text-uppercase small fw-bold text-danger">Absent Days</th>
                            <th class="text-center text-secondary text-uppercase small fw-bold text-warning">Leave Days</th>
                            <th class="text-center text-secondary text-uppercase small fw-bold">Total Logs</th>
                            <th class="pe-4 text-center text-secondary text-uppercase small fw-bold">Attendance Rate (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($departmentSummary as $dept)
                            @php
                                $deptRate = $dept->total > 0 ? ($dept->present / $dept->total) * 100 : 0;
                            @endphp
                            <tr>
                                <td class="ps-4 fw-bold text-dark" data-label="Department">{{ $dept->name }}</td>
                                <td class="text-center font-tabular fw-semibold text-success" data-label="Present Days">{{ $dept->present }}</td>
                                <td class="text-center font-tabular fw-semibold text-danger" data-label="Absent Days">{{ $dept->absent }}</td>
                                <td class="text-center font-tabular fw-semibold text-warning" data-label="Leave Days">{{ $dept->leave_count ?? $dept->leave ?? 0 }}</td>
                                <td class="text-center font-tabular text-muted" data-label="Total Logs">{{ $dept->total }}</td>
                                <td class="pe-4 text-center" data-label="Attendance Rate">
                                    <span class="badge {{ $deptRate >= 85 ? 'bg-success' : ($deptRate >= 70 ? 'bg-warning text-dark' : 'bg-danger') }} rounded-pill px-3 py-1 font-tabular">
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

    {{-- Employee Detailed Attendance List --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2.5 py-1 fw-bold small">
                    <i class="fa-solid fa-user-check me-1"></i>Staff Performance
                </span>
                <h6 class="fw-bold mb-0 text-dark">Employee Attendance Records</h6>
            </div>
            <span class="text-muted small">{{ count($attendanceData) }} Employees Recorded</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 text-secondary text-uppercase small fw-bold">Employee</th>
                        <th class="text-secondary text-uppercase small fw-bold">Department</th>
                        <th class="text-center text-secondary text-uppercase small fw-bold text-success">Present</th>
                        <th class="text-center text-secondary text-uppercase small fw-bold text-danger">Absent</th>
                        <th class="text-center text-secondary text-uppercase small fw-bold text-warning">Leave</th>
                        <th class="pe-4 text-center text-secondary text-uppercase small fw-bold">Rate (%)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attendanceData as $row)
                        <tr>
                            <td class="ps-4" data-label="Employee">
                                <div class="fw-bold text-dark">{{ $row['employee']->name ?? $row['employee']->full_name }}</div>
                                <span class="badge bg-light text-muted border font-monospace" style="font-size: 0.7rem;">
                                    {{ $row['employee']->employee_id ?? 'EMP-' . $row['employee']->id }}
                                </span>
                            </td>
                            <td data-label="Department">
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border">
                                    {{ $row['employee']->department->name ?? ($row['employee']->department_name ?? 'General') }}
                                </span>
                            </td>
                            <td class="text-center font-tabular fw-bold text-success" data-label="Present">{{ $row['present'] }}</td>
                            <td class="text-center font-tabular fw-bold text-danger" data-label="Absent">{{ $row['absent'] }}</td>
                            <td class="text-center font-tabular fw-bold text-warning" data-label="Leave">{{ $row['leave'] }}</td>
                            <td class="pe-4 text-center" data-label="Rate (%)">
                                <div class="d-flex align-items-center justify-content-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 6px; max-width: 80px; background-color: #f1f5f9;">
                                        <div class="progress-bar {{ $row['attendance_percentage'] >= 85 ? 'bg-success' : ($row['attendance_percentage'] >= 70 ? 'bg-warning' : 'bg-danger') }}" 
                                             style="width: {{ min(100, $row['attendance_percentage']) }}%"></div>
                                    </div>
                                    <span class="font-tabular fw-bold {{ $row['attendance_percentage'] >= 85 ? 'text-success' : ($row['attendance_percentage'] >= 70 ? 'text-warning' : 'text-danger') }}" style="font-size: 0.85rem;">
                                        {{ number_format($row['attendance_percentage'], 1) }}%
                                    </span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <x-empty-state
                                    title="No Attendance Data Found"
                                    description="No attendance punches or manual timesheets exist for the selected date range."
                                    icon="fa-solid fa-calendar-xmark" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- GM Submissions History --}}
    @if(isset($recentSubmissions) && count($recentSubmissions) > 0)
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2.5 py-1 fw-bold small">
                        <i class="fa-solid fa-paper-plane me-1"></i>GM Submissions
                    </span>
                    <h6 class="fw-bold mb-0 text-dark">Recent Submissions to General Manager</h6>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 text-secondary text-uppercase small fw-bold">Report Type</th>
                            <th class="text-secondary text-uppercase small fw-bold">Period</th>
                            <th class="text-secondary text-uppercase small fw-bold">Submitted By</th>
                            <th class="text-secondary text-uppercase small fw-bold">Submitted At</th>
                            <th class="text-secondary text-uppercase small fw-bold">Status</th>
                            <th class="pe-4 text-secondary text-uppercase small fw-bold">GM Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentSubmissions as $sub)
                            <tr>
                                <td class="ps-4 fw-bold text-dark" data-label="Report Type">{{ $sub->report_type }}</td>
                                <td data-label="Period">
                                    {{ $sub->from_date ? $sub->from_date->format('d M') : '-' }} to {{ $sub->to_date ? $sub->to_date->format('d M Y') : '-' }}
                                </td>
                                <td data-label="Submitted By">{{ $sub->submitter->name ?? 'HR Officer' }}</td>
                                <td class="font-monospace text-muted small" data-label="Submitted At">
                                    {{ $sub->created_at ? $sub->created_at->format('d M Y, h:i A') : '-' }}
                                </td>
                                <td data-label="Status">
                                    @if($sub->status === 'reviewed' || $sub->status === 'acknowledged')
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 fw-bold">
                                            <i class="fa-solid fa-check me-1"></i>{{ ucfirst($sub->status) }}
                                        </span>
                                    @else
                                        <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-1 fw-bold">
                                            <i class="fa-solid fa-clock me-1"></i>Sent / Pending GM
                                        </span>
                                    @endif
                                </td>
                                <td class="pe-4 text-muted small" data-label="GM Remarks">
                                    {{ $sub->gm_remarks ?? ($sub->notes ?? '—') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Official Print Signature Block --}}
    <x-print-signatures 
        preparedTitle="Prepared By" 
        preparedRole="HR & Attendance Officer" 
        checkedTitle="Verified By" 
        checkedRole="HR Operations Manager" 
        approvedTitle="Approved By" 
        approvedRole="General Manager" />
</div>

{{-- Modal: Send Report to GM --}}
<div class="modal fade" id="sendToGmModal" tabindex="-1" aria-labelledby="sendToGmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <form action="{{ Route::has('reports.attendance.send-gm') ? route('reports.attendance.send-gm') : url('/reports/attendance/send-gm') }}" method="POST">
                @csrf
                <div class="modal-header border-bottom py-3 px-4">
                    <h5 class="modal-title fw-bold text-dark" id="sendToGmModalLabel">
                        <i class="fa-solid fa-paper-plane text-primary me-2"></i>Send Report to General Manager
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Report Type</label>
                        <input type="text" name="report_type" class="form-control" value="HR Attendance &amp; Manpower Report" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Period From</label>
                            <input type="date" name="from_date" class="form-control" value="{{ $fromDate }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Period To</label>
                            <input type="date" name="to_date" class="form-control" value="{{ $toDate }}" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Notes / Summary Remarks for GM (Optional)</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="e.g. Overall attendance rate is {{ number_format($stats['avg_attendance'] ?? 0, 1) }}%."></textarea>
                    </div>
                    <div class="alert alert-info py-2 small rounded-3 border-0 mb-0">
                        <i class="fa-solid fa-circle-info me-1"></i>This report will be delivered directly to the General Manager's executive inbox.
                    </div>
                </div>
                <div class="modal-footer border-top py-2 px-4">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 shadow-sm fw-semibold">
                        <i class="fa-solid fa-paper-plane me-1"></i>Confirm &amp; Send to GM
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
