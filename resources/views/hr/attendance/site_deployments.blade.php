@extends('layouts.app')
@section('title', 'On-Site Employee Deployments (ወደ ሳይት የተላኩ ሠራተኞች)')

@section('content')
<div class="container-fluid py-3">

    {{-- Page Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h3 mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-person-digging text-primary me-2"></i>On-Site Employee Deployments (ወደ ሳይት የተላኩ ሠራተኞች)
                </h1>
                <span class="badge bg-primary-subtle text-primary border border-primary fw-semibold px-2 py-1">
                    <span class="badge bg-primary text-white me-1">S</span> Status S &bull; Full Pay
                </span>
            </div>
            <p class="text-muted mb-0 small">
                Direct site dispatch decisions by <strong>Planning Manager, Coordinator, Finance Head, HR, and GM</strong>. Marked as <strong>S</strong> in attendance and protected from payroll deductions.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary btn-sm shadow-xs fw-bold px-3" data-bs-toggle="modal" data-bs-target="#newDeploymentModal">
                <i class="fa-solid fa-plus me-1"></i>Send Employee to Site (ወደ ሳይት ላክ)
            </button>
            <a href="{{ route('attendance.index') }}" class="btn btn-outline-secondary btn-sm shadow-xs">
                <i class="fa-solid fa-calendar-check me-1"></i>Attendance Table
            </a>
            @role('global_admin|admin')
            <a href="{{ route('admin.attendance.device-logs') }}" class="btn btn-outline-info btn-sm shadow-xs">
                <i class="fa-solid fa-fingerprint me-1"></i>Device Logs &amp; Reset
            </a>
            @endrole
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 border-start border-4 border-success">
        <div class="d-flex align-items-center">
            <i class="fa-solid fa-circle-check text-success fs-5 me-2"></i>
            <div>{{ session('success') }}</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 border-start border-4 border-danger">
        <div class="d-flex align-items-center">
            <i class="fa-solid fa-triangle-exclamation text-danger fs-5 me-2"></i>
            <div>{{ session('error') }}</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Policy Notice --}}
    <div class="alert alert-light border border-primary-subtle shadow-xs mb-4 py-2 px-3 rounded-3" style="background: linear-gradient(135deg, #f0fdf4 0%, #eff6ff 100%);">
        <div class="d-flex align-items-center gap-2">
            <i class="fa-solid fa-shield-halved text-primary fs-5"></i>
            <div class="small text-dark">
                <strong>Non-Deductible Policy:</strong> Employees sent to site do not clock in at the head office fingerprint machine. Their attendance is officially flagged with code <strong>S (On Site)</strong>. System rules treat status <strong>S</strong> as full working presence and <strong>strictly prohibit unexcused absence deductions from monthly payroll</strong>.
            </div>
        </div>
    </div>

    {{-- KPI Cards Row --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm border-start border-4 border-primary h-100">
                <div class="card-body p-3">
                    <span class="text-xs fw-bold text-muted text-uppercase d-block mb-1">Today On Site</span>
                    <div class="h3 fw-bold text-primary mb-0">{{ number_format($stats['today_count'] ?? 0) }}</div>
                    <small class="text-muted">{{ today()->format('M d, Y') }}</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm border-start border-4 border-success h-100">
                <div class="card-body p-3">
                    <span class="text-xs fw-bold text-muted text-uppercase d-block mb-1">This Month On Site</span>
                    <div class="h3 fw-bold text-success mb-0">{{ number_format($stats['month_count'] ?? 0) }}</div>
                    <small class="text-muted">{{ today()->format('F Y') }}</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm border-start border-4 border-indigo h-100" style="border-left-color: #6366f1 !important;">
                <div class="card-body p-3">
                    <span class="text-xs fw-bold text-muted text-uppercase d-block mb-1">Unique Staff Dispatched</span>
                    <div class="h3 fw-bold text-indigo mb-0" style="color: #6366f1;">{{ number_format($stats['distinct_employees'] ?? 0) }}</div>
                    <small class="text-muted">Distinct employees</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm border-start border-4 border-secondary h-100">
                <div class="card-body p-3">
                    <span class="text-xs fw-bold text-muted text-uppercase d-block mb-1">Total Record Count</span>
                    <div class="h3 fw-bold text-dark mb-0">{{ number_format($stats['total_count'] ?? 0) }}</div>
                    <small class="text-muted">All-time site attendance</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Form --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('attendance.site-deployments') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Employee</label>
                    <select name="employee_id" class="form-select form-select-sm">
                        <option value="">All Employees</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}" {{ request('employee_id') == $emp->id ? 'selected' : '' }}>
                                {{ $emp->full_name }} ({{ $emp->employee_code ?: 'EMP-'.$emp->id }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Project / Site</label>
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">All Projects</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" {{ request('project_id') == $p->id ? 'selected' : '' }}>
                                {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Decided By</label>
                    <select name="decided_by" class="form-select form-select-sm">
                        <option value="">All Decision Makers</option>
                        @foreach($decidedUsers as $u)
                            <option value="{{ $u->id }}" {{ request('decided_by') == $u->id ? 'selected' : '' }}>
                                {{ $u->name }} ({{ $u->roles->first()?->name ? ucwords(str_replace('_', ' ', $u->roles->first()->name)) : 'Manager' }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-1">From Date</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill shadow-xs">
                        <i class="fa-solid fa-filter me-1"></i>Filter
                    </button>
                    <a href="{{ route('attendance.site-deployments') }}" class="btn btn-outline-secondary btn-sm shadow-xs" title="Reset Filters">
                        <i class="fa-solid fa-rotate-right"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Deployments Table Card --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between py-3 border-bottom gap-2">
            <h6 class="mb-0 fw-bold text-dark">
                <i class="fa-solid fa-list-check me-2 text-primary"></i>Site Deployment Log &amp; Direct Report to HR
            </h6>
            <span class="badge bg-primary-subtle text-primary border border-primary px-2 py-1">
                {{ $deployments->total() }} records found
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width:50px;">#</th>
                            <th>Date (ቀን)</th>
                            <th>Employee (ሠራተኛ)</th>
                            <th>Destination Project / Site (ሳይት)</th>
                            <th>Decided By (ማን እንደወሰነ)</th>
                            <th>Assignment / Notes (የሥራ ዝርዝር)</th>
                            <th class="text-center">Session &amp; Clocking (ክፍለ ጊዜ)</th>
                            <th class="text-center">Status (ሁኔታ)</th>
                            <th class="text-center">Hours</th>
                            <th class="pe-3 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deployments as $d)
                        @php
                            $etDate = \App\Helpers\EthiopianCalendar::toEthiopian($d->attendance_date);
                            $etMonthName = $etDate['month_am'] ?? '';
                            $etDay = $etDate['day'] ?? '';
                            $etYear = $etDate['year'] ?? '';
                        @endphp
                        <tr>
                            <td class="ps-3 text-muted small">{{ $d->id }}</td>
                            <td>
                                <strong class="text-dark">{{ $d->attendance_date?->format('d M Y (D)') }}</strong>
                                <br><small class="text-primary font-monospace">🇪🇹 {{ $etMonthName }} {{ $etDay }}, {{ $etYear }} ዓ.ም.</small>
                            </td>
                            <td>
                                @if($d->employee)
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle-sm bg-primary-subtle text-primary fw-bold me-2" style="width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px;">
                                            {{ strtoupper(substr($d->employee->full_name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <a href="{{ route('employees.show', $d->employee) }}" class="fw-bold text-dark text-decoration-none">
                                                {{ $d->employee->full_name }}
                                            </a>
                                            <div class="small text-muted font-monospace" style="font-size:0.75rem;">
                                                {{ $d->employee->employee_code ?: 'EMP-'.$d->employee->id }} &bull; {{ $d->employee->department ?: 'Site Operations' }}
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-muted">Employee #{{ $d->employee_id }}</span>
                                @endif
                            </td>
                            <td>
                                @if($d->siteProject)
                                    <span class="badge bg-light text-dark border px-2 py-1">
                                        <i class="fa-solid fa-building me-1 text-primary"></i>{{ $d->siteProject->name }}
                                    </span>
                                @elseif(!empty($d->site_name))
                                    <span class="badge bg-light text-dark border px-2 py-1">
                                        <i class="fa-solid fa-location-dot me-1 text-danger"></i>{{ $d->site_name }}
                                    </span>
                                @else
                                    @php
                                        // Extract site name from note if possible
                                        $siteDisplay = 'Job Site';
                                        if (preg_match('/On-Site\s*\[?S?\]?:\s*([^\|]+)/i', $d->notes ?? '', $matches)) {
                                            $siteDisplay = trim($matches[1]);
                                        }
                                    @endphp
                                    <span class="badge bg-light text-dark border px-2 py-1">
                                        <i class="fa-solid fa-helmet-safety me-1 text-warning"></i>{{ $siteDisplay }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $decidedName = $d->decidedBy?->name;
                                    $decidedRole = $d->decided_by_role;

                                    // Extract from note if decidedBy relationship is null
                                    if (!$decidedName && preg_match('/Decided by:\s*([^\(]+)\s*\(([^\)]+)\)/i', $d->notes ?? '', $m)) {
                                        $decidedName = trim($m[1]);
                                        $decidedRole = trim($m[2]);
                                    } elseif (!$decidedName && $d->approvedBy) {
                                        $decidedName = $d->approvedBy->name;
                                        $decidedRole = $d->approvedBy->roles->first()?->name ? ucwords(str_replace('_', ' ', $d->approvedBy->roles->first()->name)) : 'Manager';
                                    }
                                @endphp
                                @if($decidedName)
                                    <div class="fw-semibold text-dark">{{ $decidedName }}</div>
                                    <span class="badge bg-secondary-subtle text-dark border" style="font-size:0.7rem;">
                                        <i class="fa-solid fa-user-check me-1 text-success"></i>{{ $decidedRole ?: 'Authorized Head' }}
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted border">Authorized Manager</span>
                                @endif
                            </td>
                            <td style="max-width: 230px;">
                                <div class="text-truncate text-muted small" title="{{ $d->notes }}">
                                    {{ $d->site_task ?: ($d->notes ?: 'General On-Site Duties') }}
                                </div>
                            </td>
                            <td class="text-center">
                                @php
                                    $hasMIn  = !empty($d->morning_in);
                                    $hasMOut = !empty($d->morning_out);
                                    $hasAIn  = !empty($d->afternoon_in);
                                    $hasAOut = !empty($d->afternoon_out);
                                @endphp
                                @if($hasMIn && $hasAOut)
                                    <span class="badge bg-primary-subtle text-primary border border-primary px-2 py-0.5 small mb-1 d-inline-block">
                                        <i class="fa-solid fa-sun text-warning me-1"></i>Full Day
                                    </span>
                                @elseif($hasMIn && !$hasAIn)
                                    <span class="badge bg-warning-subtle text-dark border border-warning px-2 py-0.5 small mb-1 d-inline-block">
                                        <i class="fa-solid fa-cloud-sun text-warning me-1"></i>Morning Only
                                    </span>
                                @elseif($hasAIn && !$hasMIn)
                                    <span class="badge bg-info-subtle text-dark border border-info px-2 py-0.5 small mb-1 d-inline-block">
                                        <i class="fa-solid fa-cloud-moon text-info me-1"></i>Afternoon Only
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted border px-2 py-0.5 small mb-1 d-inline-block">
                                        Custom
                                    </span>
                                @endif
                                <div class="small font-monospace text-muted d-flex flex-wrap justify-content-center gap-1" style="font-size:0.7rem;">
                                    @if($hasMIn)<span class="badge bg-light text-dark border" title="Morning Clock In">M-In: {{ substr($d->morning_in, 0, 5) }}</span>@endif
                                    @if($hasMOut)<span class="badge bg-light text-dark border" title="Morning Clock Out">M-Out: {{ substr($d->morning_out, 0, 5) }}</span>@endif
                                    @if($hasAIn)<span class="badge bg-light text-dark border" title="Afternoon Clock In">A-In: {{ substr($d->afternoon_in, 0, 5) }}</span>@endif
                                    @if($hasAOut)<span class="badge bg-light text-dark border" title="Afternoon Clock Out">A-Out: {{ substr($d->afternoon_out, 0, 5) }}</span>@endif
                                    @if(!$hasMIn && !$hasMOut && !$hasAIn && !$hasAOut)
                                        <span class="text-muted">Standard 8h</span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge text-white px-2 py-1 shadow-xs fw-bold" style="background-color: #6366f1;">
                                    <span class="badge bg-white text-dark me-1" style="font-size:0.75rem;">S</span> On Site (ሳይት)
                                </span>
                            </td>
                            <td class="text-center">
                                <strong class="text-dark">{{ number_format($d->hours_worked, 1) }}h</strong>
                                <br><small class="text-success fw-semibold">Non-deductible</small>
                            </td>
                            <td class="pe-3 text-end">
                                @if($d->employee)
                                <a href="{{ route('employees.show', $d->employee) }}" class="btn btn-outline-secondary btn-sm" title="View Employee Profile">
                                    <i class="fa-solid fa-user"></i>
                                </a>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-person-digging fa-3x mb-3 d-block opacity-25"></i>
                                <strong class="fs-6">No on-site deployments found for the selected filter.</strong>
                                <br><small class="text-muted">Use the "Send Employee to Site" button above to register an employee working on site.</small>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($deployments->hasPages())
            <div class="p-3 border-top">
                {{ $deployments->appends(request()->all())->links() }}
            </div>
            @endif
        </div>
    </div>

</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- MODAL: SEND EMPLOYEE TO SITE (ወደ ሳይት መላክ)                  --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="newDeploymentModal" tabindex="-1" aria-labelledby="newDeploymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-gradient text-white py-3 px-4" style="background: linear-gradient(135deg, #4f46e5, #0d6efd);">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-white bg-opacity-20 rounded-circle">
                        <i class="fa-solid fa-person-digging fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="newDeploymentModalLabel">Send Employee to Site (ወደ ሳይት መላክ)</h5>
                        <small class="text-white text-opacity-75">Dispatch staff to construction sites &bull; Status marked S &bull; Full payroll credit</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="{{ route('attendance.record-site') }}" method="POST" id="siteDeploymentForm">
                @csrf
                <div class="modal-body p-4">

                    {{-- Decision Maker Banner --}}
                    <div class="alert alert-light border border-primary-subtle d-flex align-items-center justify-content-between mb-4 py-2 px-3 rounded-3 shadow-xs">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-user-shield text-primary fs-5"></i>
                            <div>
                                <small class="text-muted d-block" style="font-size:0.75rem;">Authorized Decision Maker (ማን እንደወሰነ):</small>
                                <strong class="text-dark">{{ auth()->user()->name }}</strong>
                            </div>
                        </div>
                        <span class="badge bg-primary text-white px-2 py-1">
                            {{ auth()->user()->roles->first()?->name ? ucwords(str_replace('_', ' ', auth()->user()->roles->first()->name)) : 'Manager' }}
                        </span>
                    </div>

                    {{-- Employee Selection --}}
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">
                            Select Employee(s) to Send (ወደ ሳይት የሚላኩ ሠራተኞች) <span class="text-danger">*</span>
                        </label>
                        <select name="employee_ids[]" id="deploy_employee_ids" class="form-select select2" multiple required style="min-height: 90px;">
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}">
                                    {{ $emp->full_name }} ({{ $emp->employee_code ?: 'EMP-'.$emp->id }}) &bull; {{ $emp->department ?: 'Site Staff' }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">You can select multiple employees at once to deploy as a group.</small>
                    </div>

                    {{-- Project and Location --}}
                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label class="form-label fw-bold small text-muted text-uppercase">
                                Construction Project (የግንባታ ፕሮጀክት) <span class="text-danger">*</span>
                            </label>
                            <select name="project_id" class="form-select" required>
                                <option value="">-- Select Project --</option>
                                @foreach($projects as $proj)
                                    <option value="{{ $proj->id }}">
                                        🏗️ {{ $proj->name }} @if(!empty($proj->code))({{ $proj->code }})@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold small text-muted text-uppercase">
                                Specific Site Location (ካለ)
                            </label>
                            <input type="text" name="site_name" class="form-control" placeholder="e.g. Block B, Substation, Quarry...">
                        </div>
                    </div>

                    {{-- Duration Selection: Single Day vs Date Range --}}
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase d-flex justify-content-between align-items-center mb-1">
                            <span>Deployment Duration Option (የቆይታ አማራጭ) <span class="text-danger">*</span></span>
                            <span class="badge bg-primary-subtle text-primary border border-primary px-2" id="durationModeBadge">Single Day</span>
                        </label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="duration_type" id="dur_single_day" value="single_day" checked onchange="toggleDurationMode('single_day')">
                                <label class="btn btn-outline-primary w-100 py-2 fw-semibold text-center" for="dur_single_day">
                                    <i class="fa-solid fa-calendar-day me-1"></i> Single Day (አንድ ቀን ብቻ)
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="duration_type" id="dur_date_range" value="date_range" onchange="toggleDurationMode('date_range')">
                                <label class="btn btn-outline-primary w-100 py-2 fw-semibold text-center" for="dur_date_range">
                                    <i class="fa-solid fa-calendar-week me-1"></i> Date Range (የቀናት ክልል)
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Single Day Container --}}
                    <div id="singleDateContainer" class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">
                            Deployment Date (የሚላኩበት ቀን) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-calendar-day text-primary"></i></span>
                            <input type="date" name="single_date" id="modal_single_date" class="form-control" value="{{ today()->toDateString() }}" onchange="syncSingleDateToRange(this.value)">
                            <span class="input-group-text bg-white small font-monospace">
                                🇪🇹 {{ \App\Helpers\EthiopianCalendar::format(today(), 'am') }}
                            </span>
                        </div>
                    </div>

                    {{-- Date Range Container (Starts Hidden) --}}
                    <div id="dateRangeContainer" class="row g-3 mb-3" style="display: none;">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">
                                Start Date (የመነሻ ቀን) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="date" name="start_date" id="modal_deploy_start" class="form-control" value="{{ today()->toDateString() }}" onchange="syncEndDate(this.value)">
                                <span class="input-group-text bg-white small font-monospace">
                                    🇪🇹 {{ \App\Helpers\EthiopianCalendar::format(today(), 'am') }}
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">
                                End Date (የመጨረሻ ቀን) <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="end_date" id="modal_deploy_end" class="form-control" value="{{ today()->toDateString() }}">
                            <small class="text-muted">Same as start date for a single day deployment.</small>
                        </div>
                    </div>

                    {{-- Work Session & Clock Punches Selection --}}
                    <div class="card border border-primary-subtle rounded-3 shadow-xs mb-3 overflow-hidden">
                        <div class="card-header bg-light py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                            <span class="fw-bold small text-dark">
                                <i class="fa-solid fa-clock me-1 text-primary"></i> Work Session &amp; Clock Punches (ክፍለ ጊዜ እና ሰዓት) <span class="text-danger">*</span>
                            </span>
                            <span class="badge bg-primary text-white" id="sessionSummaryBadge">
                                Full Day &bull; 8.0h
                            </span>
                        </div>
                        <div class="card-body p-3 bg-white">
                            {{-- Session Radio Options --}}
                            <div class="row g-2 mb-3">
                                <div class="col-6 col-md-3">
                                    <input type="radio" class="btn-check" name="session_type" id="sess_full_day" value="full_day" checked onchange="handleSessionChange('full_day')">
                                    <label class="btn btn-outline-secondary w-100 py-2 text-start small d-flex flex-column h-100" for="sess_full_day">
                                        <span class="fw-bold text-dark"><i class="fa-solid fa-sun text-warning me-1"></i> Full Day</span>
                                        <span class="text-muted text-xs">Morning + Afternoon (8h)</span>
                                    </label>
                                </div>
                                <div class="col-6 col-md-3">
                                    <input type="radio" class="btn-check" name="session_type" id="sess_morning" value="morning" onchange="handleSessionChange('morning')">
                                    <label class="btn btn-outline-secondary w-100 py-2 text-start small d-flex flex-column h-100" for="sess_morning">
                                        <span class="fw-bold text-dark"><i class="fa-solid fa-cloud-sun text-warning me-1"></i> Morning Only</span>
                                        <span class="text-muted text-xs">In: 08:30 &bull; Out: 12:30 (4h)</span>
                                    </label>
                                </div>
                                <div class="col-6 col-md-3">
                                    <input type="radio" class="btn-check" name="session_type" id="sess_afternoon" value="afternoon" onchange="handleSessionChange('afternoon')">
                                    <label class="btn btn-outline-secondary w-100 py-2 text-start small d-flex flex-column h-100" for="sess_afternoon">
                                        <span class="fw-bold text-dark"><i class="fa-solid fa-cloud-moon text-info me-1"></i> Afternoon Only</span>
                                        <span class="text-muted text-xs">In: 13:30 &bull; Out: 17:30 (4h)</span>
                                    </label>
                                </div>
                                <div class="col-6 col-md-3">
                                    <input type="radio" class="btn-check" name="session_type" id="sess_custom" value="custom" onchange="handleSessionChange('custom')">
                                    <label class="btn btn-outline-secondary w-100 py-2 text-start small d-flex flex-column h-100" for="sess_custom">
                                        <span class="fw-bold text-dark"><i class="fa-solid fa-sliders text-primary me-1"></i> Custom Punches</span>
                                        <span class="text-muted text-xs">Pick specific punch times</span>
                                    </label>
                                </div>
                            </div>

                            {{-- 4-Punch Visual Grid --}}
                            <div class="border rounded-2 p-2 bg-light-subtle">
                                <div class="row g-2">
                                    {{-- Morning In --}}
                                    <div class="col-6 col-md-3">
                                        <div class="p-2 border rounded-2 bg-white h-100 transition-all" id="card_morning_in">
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input punch-toggle" type="checkbox" name="include_morning_in" id="chk_morning_in" value="1" checked onchange="onPunchToggleChanged()">
                                                <label class="form-check-label small fw-bold text-dark" for="chk_morning_in">
                                                    🌅 Morning In
                                                </label>
                                            </div>
                                            <input type="time" name="morning_in" id="time_morning_in" class="form-control form-control-sm font-monospace" value="{{ $workSchedule['morning_in'] ?? '08:30' }}">
                                            <small class="text-muted text-xs d-block mt-1">ጧት መግቢያ (08:30)</small>
                                        </div>
                                    </div>

                                    {{-- Morning Out --}}
                                    <div class="col-6 col-md-3">
                                        <div class="p-2 border rounded-2 bg-white h-100 transition-all" id="card_morning_out">
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input punch-toggle" type="checkbox" name="include_morning_out" id="chk_morning_out" value="1" checked onchange="onPunchToggleChanged()">
                                                <label class="form-check-label small fw-bold text-dark" for="chk_morning_out">
                                                    🥪 Morning Out
                                                </label>
                                            </div>
                                            <input type="time" name="morning_out" id="time_morning_out" class="form-control form-control-sm font-monospace" value="{{ $workSchedule['morning_out'] ?? '12:30' }}">
                                            <small class="text-muted text-xs d-block mt-1">ለምሳ መውጫ (12:30)</small>
                                        </div>
                                    </div>

                                    {{-- Afternoon In --}}
                                    <div class="col-6 col-md-3">
                                        <div class="p-2 border rounded-2 bg-white h-100 transition-all" id="card_afternoon_in">
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input punch-toggle" type="checkbox" name="include_afternoon_in" id="chk_afternoon_in" value="1" checked onchange="onPunchToggleChanged()">
                                                <label class="form-check-label small fw-bold text-dark" for="chk_afternoon_in">
                                                    🍱 Afternoon In
                                                </label>
                                            </div>
                                            <input type="time" name="afternoon_in" id="time_afternoon_in" class="form-control form-control-sm font-monospace" value="{{ $workSchedule['afternoon_in'] ?? '13:30' }}">
                                            <small class="text-muted text-xs d-block mt-1">ከምሳ መግቢያ (13:30)</small>
                                        </div>
                                    </div>

                                    {{-- Afternoon Out --}}
                                    <div class="col-6 col-md-3">
                                        <div class="p-2 border rounded-2 bg-white h-100 transition-all" id="card_afternoon_out">
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input punch-toggle" type="checkbox" name="include_afternoon_out" id="chk_afternoon_out" value="1" checked onchange="onPunchToggleChanged()">
                                                <label class="form-check-label small fw-bold text-dark" for="chk_afternoon_out">
                                                    🌇 Afternoon Out
                                                </label>
                                            </div>
                                            <input type="time" name="afternoon_out" id="time_afternoon_out" class="form-control form-control-sm font-monospace" value="{{ $workSchedule['afternoon_out'] ?? '17:30' }}">
                                            <small class="text-muted text-xs d-block mt-1">ከሰዓት መውጫ (17:30)</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Hours and Task --}}
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted text-uppercase">Credited Hours / Day</label>
                            <div class="input-group">
                                <input type="number" step="0.5" min="0.5" max="24" name="hours_worked" id="modal_hours_worked" class="form-control" value="8.0" required>
                                <span class="input-group-text">Hrs</span>
                            </div>
                            <small class="text-muted">Full day: 8.0h &bull; Half day: 4.0h</small>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold small text-muted text-uppercase">Assignment / Purpose (የሥራው ዝርዝር / ዓላማ)</label>
                            <input type="text" name="task_notes" class="form-control" placeholder="e.g. Concrete casting supervision, excavation survey, quality check...">
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-light py-3 px-4">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold shadow-xs">
                        <i class="fa-solid fa-check me-1"></i>Dispatch to Site (ይመዝገቡ)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function toggleDurationMode(mode) {
    const singleContainer = document.getElementById('singleDateContainer');
    const rangeContainer  = document.getElementById('dateRangeContainer');
    const modeBadge       = document.getElementById('durationModeBadge');
    const singleInput     = document.getElementById('modal_single_date');
    const startInput      = document.getElementById('modal_deploy_start');
    const endInput        = document.getElementById('modal_deploy_end');

    if (mode === 'single_day') {
        if (singleContainer) singleContainer.style.display = 'block';
        if (rangeContainer)  rangeContainer.style.display  = 'none';
        if (modeBadge) {
            modeBadge.textContent = 'Single Day';
            modeBadge.className = 'badge bg-primary-subtle text-primary border border-primary px-2';
        }
        if (singleInput && startInput && endInput) {
            startInput.value = singleInput.value;
            endInput.value   = singleInput.value;
        }
    } else {
        if (singleContainer) singleContainer.style.display = 'none';
        if (rangeContainer)  rangeContainer.style.display  = 'flex';
        if (modeBadge) {
            modeBadge.textContent = 'Date Range';
            modeBadge.className = 'badge bg-info-subtle text-info border border-info px-2';
        }
    }
}

function syncSingleDateToRange(val) {
    const startInput = document.getElementById('modal_deploy_start');
    const endInput   = document.getElementById('modal_deploy_end');
    if (startInput) startInput.value = val;
    if (endInput) endInput.value = val;
}

function syncEndDate(startVal) {
    const endInput = document.getElementById('modal_deploy_end');
    if (endInput && !endInput.value) {
        endInput.value = startVal;
    }
}

function handleSessionChange(type) {
    const chkMIn  = document.getElementById('chk_morning_in');
    const chkMOut = document.getElementById('chk_morning_out');
    const chkAIn  = document.getElementById('chk_afternoon_in');
    const chkAOut = document.getElementById('chk_afternoon_out');

    const hoursInput = document.getElementById('modal_hours_worked');
    const badge      = document.getElementById('sessionSummaryBadge');

    if (type === 'full_day') {
        if (chkMIn)  chkMIn.checked  = true;
        if (chkMOut) chkMOut.checked = true;
        if (chkAIn)  chkAIn.checked  = true;
        if (chkAOut) chkAOut.checked = true;
        if (hoursInput) hoursInput.value = '8.0';
        if (badge) badge.innerHTML = '<i class="fa-solid fa-sun text-warning me-1"></i> Full Day &bull; 8.0h';
    } else if (type === 'morning') {
        if (chkMIn)  chkMIn.checked  = true;
        if (chkMOut) chkMOut.checked = true;
        if (chkAIn)  chkAIn.checked  = false;
        if (chkAOut) chkAOut.checked = false;
        if (hoursInput) hoursInput.value = '4.0';
        if (badge) badge.innerHTML = '<i class="fa-solid fa-cloud-sun text-warning me-1"></i> Morning Session &bull; 4.0h';
    } else if (type === 'afternoon') {
        if (chkMIn)  chkMIn.checked  = false;
        if (chkMOut) chkMOut.checked = false;
        if (chkAIn)  chkAIn.checked  = true;
        if (chkAOut) chkAOut.checked = true;
        if (hoursInput) hoursInput.value = '4.0';
        if (badge) badge.innerHTML = '<i class="fa-solid fa-cloud-moon text-info me-1"></i> Afternoon Session &bull; 4.0h';
    } else if (type === 'custom') {
        if (badge) badge.innerHTML = '<i class="fa-solid fa-sliders text-white me-1"></i> Custom Punches';
    }
    updatePunchInputsVisual();
}

function updatePunchInputsVisual() {
    ['morning_in', 'morning_out', 'afternoon_in', 'afternoon_out'].forEach(function(id) {
        const chk  = document.getElementById('chk_' + id);
        const time = document.getElementById('time_' + id);
        const card = document.getElementById('card_' + id);
        if (chk && time) {
            time.disabled = !chk.checked;
            if (card) {
                if (chk.checked) {
                    card.classList.remove('opacity-50', 'bg-light');
                    card.classList.add('bg-white');
                } else {
                    card.classList.add('opacity-50', 'bg-light');
                    card.classList.remove('bg-white');
                }
            }
        }
    });
}

function onPunchToggleChanged() {
    const customRadio = document.getElementById('sess_custom');
    if (customRadio) customRadio.checked = true;

    updatePunchInputsVisual();

    const chkMIn  = document.getElementById('chk_morning_in')?.checked;
    const chkMOut = document.getElementById('chk_morning_out')?.checked;
    const chkAIn  = document.getElementById('chk_afternoon_in')?.checked;
    const chkAOut = document.getElementById('chk_afternoon_out')?.checked;

    let computed = 0;
    if (chkMIn && chkMOut) computed += 4.0;
    else if (chkMIn || chkMOut) computed += 2.0;

    if (chkAIn && chkAOut) computed += 4.0;
    else if (chkAIn || chkAOut) computed += 2.0;

    const hoursInput = document.getElementById('modal_hours_worked');
    if (hoursInput && computed > 0) {
        hoursInput.value = computed.toFixed(1);
    }

    const badge = document.getElementById('sessionSummaryBadge');
    if (badge) {
        badge.innerHTML = `<i class="fa-solid fa-sliders text-white me-1"></i> Custom &bull; ${computed.toFixed(1)}h`;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updatePunchInputsVisual();
});
</script>
@endpush
