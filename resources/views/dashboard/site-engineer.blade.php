@extends('layouts.app')
@section('title', 'Site Engineer Dashboard')

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- Top Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold mb-1" style="color: #1e293b;">
                <i class="fa-solid fa-hard-hat text-warning me-2"></i>Site Engineer Dashboard
            </h3>
            <p class="text-muted small mb-0">Site operations, morning workforce reporting, schedules, and material requests</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-white text-secondary border px-3 py-2 shadow-sm rounded-pill">
                <i class="fa-solid fa-calendar-day text-primary me-1"></i>{{ now()->format('l, F j, Y') }}
            </span>
            <a href="{{ route('manpower-daily-report.create') }}" class="btn btn-primary btn-sm shadow-sm">
                <i class="fa-solid fa-users-line me-1"></i> Submit Manpower Report
            </a>
            <a href="{{ route('material-requests.create', ['source' => 'Emergency']) }}" class="btn btn-danger btn-sm shadow-sm">
                <i class="fa-solid fa-bolt me-1"></i> Emergency MR
            </a>
        </div>
    </div>

    {{-- Morning Manpower Report Status Hero Banner --}}
    @if($todayManpowerReport)
        @if($todayManpowerReport->status === 'approved')
            <div class="card border-0 shadow-sm rounded-3 mb-4 text-white" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%);">
                <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                            <i class="fa-solid fa-circle-check fa-2x text-white"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Today's Morning Manpower Report Approved</h5>
                            <p class="mb-0 small text-white text-opacity-90">
                                <strong>{{ $todayManpowerReport->total_present }}</strong> workers logged for <strong>{{ $todayManpowerReport->project->name ?? 'Site' }}</strong> &bull; Reviewed by <strong>{{ $todayManpowerReport->reviewer->name ?? 'Planning Manager' }}</strong>
                                @if($todayManpowerReport->review_notes) — <em>"{{ $todayManpowerReport->review_notes }}"</em>@endif
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('manpower-daily-report.show', $todayManpowerReport) }}" class="btn btn-light btn-sm text-success fw-bold shadow-sm px-3">
                        <i class="fa-solid fa-eye me-1"></i> View Report
                    </a>
                </div>
            </div>
        @elseif($todayManpowerReport->status === 'rejected')
            <div class="card border-0 shadow-sm rounded-3 mb-4 text-white" style="background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);">
                <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                            <i class="fa-solid fa-triangle-exclamation fa-2x text-white"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Manpower Report Needs Revision</h5>
                            <p class="mb-0 small text-white text-opacity-90">
                                Planning Manager rejected today's submission.
                                @if($todayManpowerReport->review_notes) Reason: <strong>"{{ $todayManpowerReport->review_notes }}"</strong>@endif
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('manpower-daily-report.create') }}" class="btn btn-light btn-sm text-danger fw-bold shadow-sm px-3">
                        <i class="fa-solid fa-pen-to-square me-1"></i> Resubmit Report
                    </a>
                </div>
            </div>
        @else
            <div class="card border-0 shadow-sm rounded-3 mb-4 text-dark" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-left: 5px solid #f59e0b !important;">
                <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-warning bg-opacity-25 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                            <i class="fa-solid fa-hourglass-half fa-2x text-warning"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1 text-dark">Today's Manpower Report Submitted — Awaiting Review</h5>
                            <p class="mb-0 small text-dark text-opacity-75">
                                Submitted at {{ $todayManpowerReport->created_at->format('h:i A') }} with <strong>{{ $todayManpowerReport->total_present }} workers</strong> for <strong>{{ $todayManpowerReport->project->name ?? 'Site' }}</strong>. Sent to Planning Manager.
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('manpower-daily-report.show', $todayManpowerReport) }}" class="btn btn-warning btn-sm text-dark fw-bold shadow-sm px-3">
                        <i class="fa-solid fa-eye me-1"></i> View Submission
                    </a>
                </div>
            </div>
        @endif
    @else
        <div class="card border-0 shadow-sm rounded-3 mb-4 text-white" style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);">
            <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white bg-opacity-20 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                        <i class="fa-solid fa-users-rays fa-2x text-warning"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1">Morning Manpower Report Not Submitted Yet</h5>
                        <p class="mb-0 small text-white text-opacity-90">
                            Please log today's site workforce count (skilled, unskilled, operators, subcontractors) for Planning Manager visibility.
                        </p>
                    </div>
                </div>
                <a href="{{ route('manpower-daily-report.create') }}" class="btn btn-warning btn-sm text-dark fw-bold shadow-sm px-4 py-2">
                    <i class="fa-solid fa-paper-plane me-1"></i> Submit Morning Report Now
                </a>
            </div>
        </div>
    @endif

    {{-- KPI Cards Row --}}
    <div class="row g-3 mb-4">
        <!-- Today Workforce -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white border-start border-4 border-primary">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Site Workforce Today</div>
                            <div class="h3 mb-0 fw-bold text-dark">{{ $kpi['today_manpower_count'] ?? 0 }}</div>
                            <small class="text-muted">{{ $todayManpowerReport ? 'Logged in Morning Report' : 'Site Attendance' }}</small>
                        </div>
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                            <i class="fa-solid fa-users fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned Work Orders -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white border-start border-4 border-info">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs fw-bold text-info text-uppercase mb-1">Active Work Orders</div>
                            <div class="h3 mb-0 fw-bold text-dark">{{ $kpi['assigned_work_orders_count'] ?? 0 }}</div>
                            <small class="text-muted">Assigned from Planning</small>
                        </div>
                        <div class="rounded-circle bg-info bg-opacity-10 p-3 text-info">
                            <i class="fa-solid fa-calendar-check fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Material Requests -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white border-start border-4 border-warning">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs fw-bold text-warning text-uppercase mb-1">Pending Material Requests</div>
                            <div class="h3 mb-0 fw-bold text-dark">{{ $kpi['pending_mr_count'] ?? 0 }} <span class="fs-6 text-muted fw-normal">/ {{ $kpi['my_material_requests'] ?? 0 }}</span></div>
                            <small class="text-muted">Awaiting store/approval</small>
                        </div>
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning">
                            <i class="fa-solid fa-cart-flatbed fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Open Site Issues -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white border-start border-4 border-danger">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs fw-bold text-danger text-uppercase mb-1">Open Site Issues</div>
                            <div class="h3 mb-0 fw-bold text-dark">{{ $kpi['open_issues_count'] ?? 0 }}</div>
                            <small class="text-muted">Delays & site obstacles</small>
                        </div>
                        <div class="rounded-circle bg-danger bg-opacity-10 p-3 text-danger">
                            <i class="fa-solid fa-triangle-exclamation fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Content Grid --}}
    <div class="row g-4">
        {{-- Left Column (8 cols) --}}
        <div class="col-lg-8">

            {{-- Assigned Work Orders / Schedule --}}
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark">
                        <i class="fa-solid fa-calendar-days text-info me-2"></i>Assigned Work Orders & Dispatches
                    </h6>
                    <a href="{{ route('eng-schedule.my') }}" class="btn btn-sm btn-outline-primary">
                        View Schedule <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Work Order</th>
                                    <th>Project / Location</th>
                                    <th>Schedule</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($assignedWorkOrders as $order)
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $order->title }}</div>
                                        <small class="text-muted">{{ $order->category ?? 'General' }}</small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold small">{{ $order->project->name ?? 'N/A' }}</div>
                                        <small class="text-muted">{{ $order->location ?? 'Site' }}</small>
                                    </td>
                                    <td class="small">
                                        <div><i class="fa-regular fa-clock text-muted me-1"></i>{{ $order->start_datetime->format('M d, H:i') }}</div>
                                        <div class="text-muted">to {{ $order->end_datetime->format('M d, H:i') }}</div>
                                    </td>
                                    <td>
                                        @php
                                            $pColors = [
                                                'urgent' => 'bg-danger',
                                                'high'   => 'bg-warning text-dark',
                                                'medium' => 'bg-info text-dark',
                                                'low'    => 'bg-secondary',
                                            ];
                                        @endphp
                                        <span class="badge {{ $pColors[$order->priority] ?? 'bg-secondary' }} text-uppercase">{{ $order->priority }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">{{ ucfirst($order->status) }}</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('eng-schedule.show', $order->id) }}" class="btn btn-sm btn-light border">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fa-solid fa-calendar-xmark fa-2x mb-2 d-block text-muted"></i>
                                        No active work orders assigned to you today.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Recent Morning Manpower Reports --}}
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark">
                        <i class="fa-solid fa-users-line text-primary me-2"></i>Recent Morning Manpower Reports
                    </h6>
                    <a href="{{ route('manpower-daily-report.index') }}" class="btn btn-sm btn-outline-primary">
                        View All History <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Project</th>
                                    <th class="text-center">Total Present</th>
                                    <th>Work Area</th>
                                    <th class="text-center">Review Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentManpowerReports as $mp)
                                <tr>
                                    <td class="fw-semibold small">
                                        {{ $mp->report_date->format('D, M d, Y') }}
                                        @if($mp->report_date->isToday())
                                            <span class="badge bg-primary ms-1">Today</span>
                                        @endif
                                    </td>
                                    <td class="small">{{ $mp->project->name ?? 'N/A' }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 fw-bold rounded-pill">
                                            <i class="fa-solid fa-users me-1"></i>{{ $mp->total_present }}
                                        </span>
                                    </td>
                                    <td class="small text-muted">{{ $mp->work_area ?: '—' }}</td>
                                    <td class="text-center">
                                        <span class="badge {{ $mp->status_badge_class }}">{{ $mp->status_label }}</span>
                                        @if($mp->review_notes)
                                            <div class="text-muted small mt-1" style="font-size: 0.75rem;"><i class="fa-solid fa-comment me-1"></i>{{ \Illuminate\Support\Str::limit($mp->review_notes, 30) }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('manpower-daily-report.show', $mp) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fa-solid fa-inbox fa-2x mb-2 d-block text-muted"></i>
                                        No manpower reports submitted yet.
                                        <div class="mt-2">
                                            <a href="{{ route('manpower-daily-report.create') }}" class="btn btn-sm btn-primary">Submit Morning Report</a>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Recent Material Requests --}}
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark">
                        <i class="fa-solid fa-cart-flatbed text-warning me-2"></i>My Recent Material Requests
                    </h6>
                    <a href="{{ route('material-requests.index') }}" class="btn btn-sm btn-outline-primary">
                        All Requests <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Request #</th>
                                    <th>Project</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentMR as $mr)
                                <tr>
                                    <td class="fw-bold">#{{ $mr->id }}</td>
                                    <td>{{ $mr->project->name ?? 'N/A' }}</td>
                                    <td class="small text-muted">{{ $mr->created_at->format('M d, Y') }}</td>
                                    <td>
                                        @php
                                            $stMap = [
                                                'approved' => 'bg-success',
                                                'rejected' => 'bg-danger',
                                                'pending'  => 'bg-warning text-dark',
                                                'issued'   => 'bg-info text-dark',
                                            ];
                                        @endphp
                                        <span class="badge {{ $stMap[$mr->status] ?? 'bg-secondary' }}">{{ ucfirst($mr->status) }}</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('material-requests.show', $mr->id) }}" class="btn btn-sm btn-light border">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No recent material requests found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        {{-- Right Column (4 cols) --}}
        <div class="col-lg-4">

            {{-- Quick Site Actions --}}
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="m-0 fw-bold text-dark">
                        <i class="fa-solid fa-bolt text-warning me-2"></i>Quick Site Actions
                    </h6>
                </div>
                <div class="card-body d-flex flex-column gap-2 p-3">
                    <a href="{{ route('manpower-daily-report.create') }}" class="btn btn-primary text-start fw-semibold py-2">
                        <i class="fa-solid fa-users-line me-2"></i> Submit Morning Manpower Report
                    </a>
                    <a href="{{ route('material-requests.create', ['source' => 'Emergency']) }}" class="btn btn-danger text-start fw-semibold py-2">
                        <i class="fa-solid fa-bolt me-2"></i> Ask Emergency Material Request
                    </a>
                    <a href="{{ route('eng-schedule.my') }}" class="btn btn-info text-dark text-start fw-semibold py-2">
                        <i class="fa-solid fa-calendar-check me-2"></i> My Assigned Work Orders
                    </a>
                    <a href="{{ route('daily-reports.create') }}" class="btn btn-success text-start fw-semibold py-2">
                        <i class="fa-solid fa-file-signature me-2"></i> Create Daily Site Log
                    </a>
                    <a href="{{ route('issues.create') }}" class="btn btn-outline-danger text-start fw-semibold py-2">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i> Report Site Issue / Obstacle
                    </a>
                    <a href="{{ route('attendance.index') }}" class="btn btn-outline-secondary text-start fw-semibold py-2">
                        <i class="fa-solid fa-user-check me-2"></i> Mark Site Attendance
                    </a>
                    <a href="{{ route('manpower-daily-report.index') }}" class="btn btn-outline-primary text-start fw-semibold py-2">
                        <i class="fa-solid fa-clock-rotate-left me-2"></i> My Manpower History
                    </a>
                </div>
            </div>

            {{-- Today's Manpower Breakdown Card (if submitted) --}}
            @if($todayManpowerReport)
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark">
                        <i class="fa-solid fa-chart-pie text-success me-2"></i>Today's Site Workforce
                    </h6>
                    <span class="badge bg-success">{{ $todayManpowerReport->total_present }} Total</span>
                </div>
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2 small">
                        <span><i class="fa-solid fa-helmet-safety text-primary me-2"></i>Skilled Workers</span>
                        <strong class="badge bg-light text-dark border">{{ $todayManpowerReport->skilled_workers }}</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 small">
                        <span><i class="fa-solid fa-person-digging text-warning me-2"></i>Unskilled Workers</span>
                        <strong class="badge bg-light text-dark border">{{ $todayManpowerReport->unskilled_workers }}</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 small">
                        <span><i class="fa-solid fa-user-tie text-success me-2"></i>Supervisors</span>
                        <strong class="badge bg-light text-dark border">{{ $todayManpowerReport->supervisors }}</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 small">
                        <span><i class="fa-solid fa-screwdriver-wrench text-info me-2"></i>Engineers</span>
                        <strong class="badge bg-light text-dark border">{{ $todayManpowerReport->engineers }}</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 small">
                        <span><i class="fa-solid fa-truck-monster text-danger me-2"></i>Equipment Operators</span>
                        <strong class="badge bg-light text-dark border">{{ $todayManpowerReport->operators }}</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 small">
                        <span><i class="fa-solid fa-hammer text-secondary me-2"></i>Daily Laborers</span>
                        <strong class="badge bg-light text-dark border">{{ $todayManpowerReport->daily_laborers }}</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center small">
                        <span><i class="fa-solid fa-handshake text-purple me-2" style="color: #8b5cf6;"></i>Subcontractor Workers</span>
                        <strong class="badge bg-light text-dark border">{{ $todayManpowerReport->subcontractor_workers }}</strong>
                    </div>
                </div>
            </div>
            @endif

            {{-- Recent Site Issues --}}
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark">
                        <i class="fa-solid fa-triangle-exclamation text-danger me-2"></i>Site Issues & Delays
                    </h6>
                    <a href="{{ route('issues.index') }}" class="btn btn-sm btn-outline-danger">All Issues</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($recentIssues as $issue)
                        <li class="list-group-item p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold small text-dark">{{ $issue->title ?? 'Site Issue #'.$issue->id }}</div>
                                    <small class="text-muted">{{ $issue->project->name ?? 'Site' }} &bull; {{ $issue->created_at->diffForHumans() }}</small>
                                </div>
                                <span class="badge {{ $issue->status === 'open' ? 'bg-danger' : 'bg-secondary' }}">{{ ucfirst($issue->status) }}</span>
                            </div>
                        </li>
                        @empty
                        <li class="list-group-item p-3 text-center text-muted small">
                            <i class="fa-solid fa-circle-check text-success me-1"></i> No active site issues reported.
                        </li>
                        @endforelse
                    </ul>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
