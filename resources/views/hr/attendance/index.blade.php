@extends('layouts.app')
@section('title', 'Attendance Management')
@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-3 border-bottom gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h4 class="fw-bold text-dark mb-0">
                    <i class="fa-solid fa-fingerprint text-primary me-1"></i> Biometric Attendance Management
                </h4>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 small">
                    <i class="far fa-calendar-day me-1"></i>Today: {{ now()->format('M d, Y') }} ({{ now()->format('l') }})
                </span>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 small">
                    <i class="fas fa-landmark me-1"></i>🇪🇹 {{ \App\Helpers\EthiopianCalendar::format(today(), 'am') }}
                </span>
                <span class="badge bg-dark-subtle text-dark border px-2 py-1 small">
                    <i class="fa-solid fa-calendar-week me-1"></i>Period: <strong>{{ $period['full_label'] }}</strong> ({{ $period['label_en'] }})
                </span>
            </div>
            <p class="text-muted small mb-0 mt-1">
                Biometric punch verification, Ethiopian calendar payroll period (26th–25th), on-site deployments, and attendance compliance.
            </p>
        </div>

        {{-- Actions --}}
        <div class="d-flex align-items-center gap-2 flex-wrap">
            {{-- Biometric Sync --}}
            <form action="{{ route('attendance.zkteco-sync') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-primary shadow-xs" title="Synchronize raw punches from ZKTeco biometric machines">
                    <i class="fa-solid fa-rotate me-1"></i>Sync Biometrics
                </button>
            </form>

            {{-- Site Deployment Dispatch --}}
            <button type="button" class="btn btn-sm btn-primary shadow-xs" data-bs-toggle="modal" data-bs-target="#siteAttendanceModal" title="Dispatch employee to site project">
                <i class="fa-solid fa-person-digging me-1"></i>Record Site Deployment
            </button>

            {{-- Site Deployments Report --}}
            <a href="{{ route('attendance.site-deployments') }}" class="btn btn-sm btn-outline-secondary shadow-xs">
                <i class="fa-solid fa-list-check text-primary me-1"></i>Deployments
            </a>

            {{-- Export as PDF Button --}}
            <button type="button" class="btn btn-sm btn-outline-danger shadow-xs" data-bs-toggle="modal" data-bs-target="#exportAttendancePdfModal" title="Export monthly clock in / out attendance matrix as PDF">
                <i class="fa-solid fa-file-pdf me-1"></i>Export as PDF
            </button>

            {{-- More Actions Dropdown --}}
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle shadow-xs" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-ellipsis-vertical me-1"></i>Settings
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                    <li>
                        <a class="dropdown-item py-2 small" href="#" data-bs-toggle="modal" data-bs-target="#workScheduleModal">
                            <i class="fa-solid fa-business-time text-info me-2"></i>Configure Shift Schedule
                        </a>
                    </li>
                    @if(auth()->user() && auth()->user()->hasAnyRole(['admin', 'global_admin']))
                    <li>
                        <a class="dropdown-item py-2 small" href="{{ route('admin.attendance.device-logs') }}">
                            <i class="fa-solid fa-microchip text-secondary me-2"></i>Device Logs &amp; Machines
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item py-2 small text-danger" href="#" data-bs-toggle="modal" data-bs-target="#clearResyncModal">
                            <i class="fa-solid fa-broom me-2"></i>Clear &amp; Resync Fresh
                        </a>
                    </li>
                    @endif
                </ul>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-xs rounded-3 py-2 px-3 mb-3 d-flex align-items-center gap-2">
        <i class="fa-solid fa-circle-check text-success fs-5"></i>
        <div class="small flex-grow-1">{{ session('success') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-xs rounded-3 py-2 px-3 mb-3 d-flex align-items-center gap-2">
        <i class="fa-solid fa-triangle-exclamation text-danger fs-5"></i>
        <div class="small flex-grow-1">{{ session('error') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- WARNING 1: Suspended Access Accounts (5 Consecutive Absent Days) --}}
    @if(!empty($blockedUsers) && $blockedUsers->isNotEmpty())
    <div class="card border-danger border-opacity-50 shadow-xs rounded-3 mb-3 bg-danger bg-opacity-10">
        <div class="card-header bg-danger bg-opacity-25 py-2 px-3 border-danger border-opacity-25 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-user-lock text-danger fs-5"></i>
                <div>
                    <strong class="text-danger">Suspended User Access Alert (5 Consecutive Absent Days)</strong>
                    <span class="badge bg-danger ms-2 font-monospace">{{ $blockedUsers->count() }} User(s) Blocked</span>
                </div>
            </div>
            <small class="text-muted">Strict Compliance: System &amp; API access blocked after 5 consecutive expected working days without attendance</small>
        </div>
        <div class="card-body p-3 bg-white">
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>User Name</th>
                            <th>Employee Code &amp; Dept</th>
                            <th>Blocked Date &amp; Time</th>
                            <th>Reason</th>
                            <th class="text-center">Action (Restore)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($blockedUsers as $blocked)
                        <tr>
                            <td>
                                <strong>{{ $blocked->name }}</strong>
                                <div class="text-muted" style="font-size: 0.72rem;">{{ $blocked->email }}</div>
                            </td>
                            <td>
                                {{ $blocked->employee?->employee_code ?? 'No Code' }} &bull; {{ $blocked->employee?->department ?? 'General' }}
                            </td>
                            <td>
                                <span class="font-monospace text-danger">{{ optional($blocked->access_blocked_at)->format('M d, Y H:i') }}</span>
                            </td>
                            <td>
                                <span class="text-muted">{{ $blocked->access_block_reason ?: '5 consecutive days without attendance' }}</span>
                            </td>
                            <td class="text-center">
                                @if(\App\Http\Controllers\AttendanceController::isHrOrAdmin())
                                <button type="button" class="btn btn-xs btn-outline-success py-1 px-2 shadow-xs" data-bs-toggle="modal" data-bs-target="#unblockUserModal{{ $blocked->id }}">
                                    <i class="fa-solid fa-lock-open me-1"></i>Restore Access
                                </button>

                                {{-- Unblock Reason Modal --}}
                                <div class="modal fade text-start" id="unblockUserModal{{ $blocked->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content rounded-3 border-0 shadow">
                                            <form action="{{ route('admin.attendance.restore-access', $blocked->id) }}" method="POST">
                                                @csrf
                                                <div class="modal-header bg-success text-white py-2 px-3">
                                                    <h6 class="modal-title fw-bold">
                                                        <i class="fa-solid fa-shield-halved me-1"></i>Restore System Access: {{ $blocked->name }}
                                                    </h6>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-3">
                                                    <div class="alert alert-warning py-2 small mb-3">
                                                        <i class="fa-solid fa-circle-info me-1"></i>
                                                        You are about to restore system and API access for <strong>{{ $blocked->name }}</strong>. This action will be permanently recorded in the HR security audit trail.
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold small">Reason for Restoration <span class="text-danger">*</span></label>
                                                        <textarea name="reason" rows="3" class="form-control form-control-sm" placeholder="e.g. Employee provided verified medical certificate or emergency deployment confirmation approved by HR..." required minlength="5"></textarea>
                                                        <small class="text-muted">Enter a detailed justification for the audit log.</small>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light py-2 px-3">
                                                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-sm btn-success">
                                                        <i class="fa-solid fa-check me-1"></i>Confirm &amp; Restore Access
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                @else
                                <span class="text-muted small">HR / Admin Only</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- WARNING 2: Missing Biometric Device ID Panel --}}
    @if(!empty($missingDeviceEmployees) && $missingDeviceEmployees->isNotEmpty())
    <div class="alert alert-warning border-0 shadow-xs rounded-3 p-3 mb-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation text-warning fs-4"></i>
                <div>
                    <strong class="text-dark">Missing Device ID Notice:</strong>
                    <span class="text-dark small ms-1">
                        <strong>{{ $missingDeviceEmployees->count() }}</strong> active employee(s) have no registered biometric Device PIN. They cannot punch on biometric machines.
                    </span>
                </div>
            </div>
            <button class="btn btn-xs btn-outline-dark py-1 px-2" type="button" data-bs-toggle="collapse" data-bs-target="#missingDeviceCollapse" aria-expanded="false">
                <i class="fa-solid fa-list me-1"></i>View Affected Staff
            </button>
        </div>
        <div class="collapse mt-2 pt-2 border-top border-warning border-opacity-50" id="missingDeviceCollapse">
            <div class="d-flex flex-wrap gap-2">
                @foreach($missingDeviceEmployees as $missing)
                <span class="badge bg-white text-dark border p-2 shadow-xs">
                    <i class="fa-solid fa-user-xmark text-danger me-1"></i>
                    <strong>{{ $missing->full_name }}</strong> ({{ $missing->employee_code ?? 'EMP' }}) &bull; {{ $missing->department ?? 'General' }}
                </span>
                @endforeach
            </div>
            <small class="text-muted d-block mt-2">
                <i class="fa-solid fa-circle-info me-1"></i>To fix: Open the employee profile in HR Roster and enter their numeric PIN corresponding to the ZKTeco device user ID.
            </small>
        </div>
    </div>
    @endif

    {{-- Dashboard Period Statistics Cards --}}
    <div class="row g-2 mb-3">
        {{-- Card 1: Active Staff --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-xs rounded-3 bg-white h-100 p-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem;">Active Staff</span>
                    <i class="fa-solid fa-users text-primary"></i>
                </div>
                <div class="fs-4 fw-bold text-dark font-monospace">{{ number_format($stats['total_staff']) }}</div>
                <div class="text-muted small" style="font-size: 0.72rem;">Active Roster (Excl. Dead File)</div>
            </div>
        </div>

        {{-- Card 2: Present Days (P) --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-xs rounded-3 bg-white h-100 p-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-success small fw-bold text-uppercase" style="font-size: 0.7rem;">Present Days (P)</span>
                    <i class="fa-solid fa-fingerprint text-success"></i>
                </div>
                <div class="fs-4 fw-bold text-success font-monospace">{{ number_format($stats['total_present']) }}</div>
                <div class="text-muted small" style="font-size: 0.72rem;">Verified Biometric Punches</div>
            </div>
        </div>

        {{-- Card 3: Site Deployments (S) --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-xs rounded-3 bg-white h-100 p-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-info small fw-bold text-uppercase" style="font-size: 0.7rem;">Site Deploy (S)</span>
                    <i class="fa-solid fa-person-digging text-info"></i>
                </div>
                <div class="fs-4 fw-bold text-info font-monospace">{{ number_format($stats['total_site']) }}</div>
                <div class="text-muted small" style="font-size: 0.72rem;">Approved Site Work (Credited)</div>
            </div>
        </div>

        {{-- Card 4: Late Days --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-xs rounded-3 bg-white h-100 p-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-warning small fw-bold text-uppercase" style="font-size: 0.7rem;">Late Days</span>
                    <i class="fa-solid fa-clock-rotate-left text-warning"></i>
                </div>
                <div class="fs-4 fw-bold text-warning font-monospace">{{ number_format($stats['total_late']) }}</div>
                <div class="text-muted small" style="font-size: 0.72rem;">Check-in after 08:40 AM cutoff</div>
            </div>
        </div>

        {{-- Card 5: Late Penalty Days (3 Lates = 1 Absent) --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-xs rounded-3 bg-white h-100 p-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-danger small fw-bold text-uppercase" style="font-size: 0.7rem;">Penalty Days</span>
                    <span class="badge bg-danger-subtle text-danger font-monospace px-1.5" style="font-size: 0.65rem;">3:1 Rule</span>
                </div>
                <div class="fs-4 fw-bold text-danger font-monospace">{{ number_format($stats['total_penalty_days']) }}</div>
                <div class="text-muted small" style="font-size: 0.72rem;">{{ $stats['penalized_employees_count'] }} staff penalized (&ge;3 lates)</div>
            </div>
        </div>

        {{-- Card 6: Effective Absent Days --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-xs rounded-3 bg-danger bg-opacity-10 border-danger border-opacity-25 h-100 p-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-danger small fw-bold text-uppercase" style="font-size: 0.7rem;">Effective Absent</span>
                    <i class="fa-solid fa-user-xmark text-danger"></i>
                </div>
                <div class="fs-4 fw-bold text-danger font-monospace">{{ number_format($stats['total_effective_absent']) }}</div>
                <div class="text-danger text-opacity-75 small" style="font-size: 0.72rem;">Base ({{ $stats['total_absent'] }}) + Penalty ({{ $stats['total_penalty_days'] }})</div>
            </div>
        </div>
    </div>

    {{-- Separation of Head Office vs Site vs Driver Attendance --}}
    <div class="card border-0 shadow-xs rounded-3 mb-3 bg-white p-2">
        <div class="row g-2" role="tablist">
            {{-- Tab 1: Head Office Attendance --}}
            <div class="col-md-3 col-6">
                <a class="nav-link py-2 px-3 rounded-3 d-flex align-items-center gap-2 {{ $staffType === 'office' ? 'active bg-primary text-white shadow-xs' : 'bg-light text-dark' }}" 
                   href="{{ route('attendance.index', array_merge(request()->except(['staff_type', 'page']), ['staff_type' => 'office'])) }}">
                    <i class="fa-solid fa-building {{ $staffType === 'office' ? 'text-white' : 'text-primary' }} fs-5"></i>
                    <div class="flex-grow-1 text-truncate">
                        <div class="fw-bold text-truncate" style="font-size: 0.82rem;">Head Office (ዋና መስሪያ ቤት)</div>
                        <div class="{{ $staffType === 'office' ? 'text-white-50' : 'text-muted' }}" style="font-size: 0.65rem;">HQ Biometric Machine</div>
                    </div>
                    <span class="badge {{ $staffType === 'office' ? 'bg-white text-primary' : 'bg-primary text-white' }} rounded-pill font-monospace">{{ $officeStaffCount }}</span>
                </a>
            </div>

            {{-- Tab 2: Site & Project Attendance --}}
            <div class="col-md-3 col-6">
                <a class="nav-link py-2 px-3 rounded-3 d-flex align-items-center gap-2 {{ in_array($staffType, ['site', 'site_driver_remote']) ? 'active bg-warning text-dark shadow-xs border border-warning' : 'bg-light text-dark' }}" 
                   href="{{ route('attendance.index', array_merge(request()->except(['staff_type', 'page']), ['staff_type' => 'site'])) }}">
                    <i class="fa-solid fa-person-digging {{ in_array($staffType, ['site', 'site_driver_remote']) ? 'text-dark' : 'text-warning' }} fs-5"></i>
                    <div class="flex-grow-1 text-truncate">
                        <div class="fw-bold text-truncate" style="font-size: 0.82rem;">Site &amp; Project (ሳይትና ፕሮጀክት)</div>
                        <div class="text-muted" style="font-size: 0.65rem;">Engineers, Foremen &amp; Field</div>
                    </div>
                    <span class="badge {{ in_array($staffType, ['site', 'site_driver_remote']) ? 'bg-dark text-white' : 'bg-warning text-dark' }} rounded-pill font-monospace">{{ $siteStaffCount }}</span>
                </a>
            </div>

            {{-- Tab 3: Driver Department Attendance (General Service) --}}
            <div class="col-md-3 col-6">
                <a class="nav-link py-2 px-3 rounded-3 d-flex align-items-center gap-2 {{ $staffType === 'driver' ? 'active bg-success text-white shadow-xs' : 'bg-light text-dark' }}" 
                   href="{{ route('attendance.index', array_merge(request()->except(['staff_type', 'page']), ['staff_type' => 'driver'])) }}">
                    <i class="fa-solid fa-truck {{ $staffType === 'driver' ? 'text-white' : 'text-success' }} fs-5"></i>
                    <div class="flex-grow-1 text-truncate">
                        <div class="fw-bold text-truncate" style="font-size: 0.82rem;">Driver Dept. (ሾፌሮች)</div>
                        <div class="{{ $staffType === 'driver' ? 'text-white-50' : 'text-muted' }}" style="font-size: 0.65rem;">Added by General Service (GS)</div>
                    </div>
                    <span class="badge {{ $staffType === 'driver' ? 'bg-white text-success' : 'bg-success text-white' }} rounded-pill font-monospace">{{ $driverStaffCount }}</span>
                </a>
            </div>

            {{-- Tab 4: All Employees Combined --}}
            <div class="col-md-3 col-6">
                <a class="nav-link py-2 px-3 rounded-3 d-flex align-items-center gap-2 {{ $staffType === 'all' ? 'active bg-dark text-white shadow-xs' : 'bg-light text-dark' }}" 
                   href="{{ route('attendance.index', array_merge(request()->except(['staff_type', 'page']), ['staff_type' => 'all'])) }}">
                    <i class="fa-solid fa-users {{ $staffType === 'all' ? 'text-white' : 'text-secondary' }} fs-5"></i>
                    <div class="flex-grow-1 text-truncate">
                        <div class="fw-bold text-truncate" style="font-size: 0.82rem;">All Staff (ሁሉም ሰራተኞች)</div>
                        <div class="{{ $staffType === 'all' ? 'text-white-50' : 'text-muted' }}" style="font-size: 0.65rem;">Company-wide Audit</div>
                    </div>
                    <span class="badge {{ $staffType === 'all' ? 'bg-white text-dark' : 'bg-secondary text-white' }} rounded-pill font-monospace">{{ $allStaffCount }}</span>
                </a>
            </div>
        </div>
    </div>

    {{-- General Service Driver Management Banner --}}
    @if($staffType === 'driver' || (auth()->check() && auth()->user()->hasAnyRole(['general_service', 'general_services', 'admin', 'global_admin'])))
    <div class="card border-0 shadow-xs rounded-3 mb-3 bg-success bg-opacity-10 border-success border-opacity-25">
        <div class="card-body py-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-truck text-success fs-4"></i>
                <div>
                    <strong class="text-dark small">Driver Department Attendance Management</strong>
                    <div class="text-muted" style="font-size: 0.72rem;">
                        Drivers travel on fleet &amp; site transport trips. Their daily duty, destinations, and hours are managed and logged by <strong>General Service (GS)</strong>.
                    </div>
                </div>
            </div>
            @if(auth()->check() && auth()->user()->hasAnyRole(['general_service', 'general_services', 'admin', 'global_admin', 'hr', 'hr_manager', 'hr_officer']))
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-success shadow-xs" data-bs-toggle="modal" data-bs-target="#recordDriverModal">
                    <i class="fa-solid fa-plus-circle me-1"></i>Record Single Driver
                </button>
                <button type="button" class="btn btn-sm btn-success shadow-xs" data-bs-toggle="modal" data-bs-target="#recordDailyDriverSheetModal">
                    <i class="fa-solid fa-clipboard-list me-1"></i>Daily Driver Sheet (የዕለት ሉህ)
                </button>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Filter Bar & Ethiopian Period Selector --}}
    <div class="card border-0 shadow-xs rounded-3 mb-3 bg-white">
        <div class="card-body p-3">
            <form action="{{ route('attendance.index') }}" method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="staff_type" value="{{ $staffType }}">

                {{-- Ethiopian Period Selector --}}
                <div class="col-md-4 col-lg-3">
                    <label class="form-label small fw-bold text-dark mb-1">
                        <i class="fa-solid fa-calendar-days text-primary me-1"></i>Ethiopian Payroll Period (26th–25th)
                    </label>
                    <select name="period" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach($availablePeriods as $p)
                        <option value="{{ $p['period_key'] }}" {{ $p['period_key'] === $selectedPeriodKey ? 'selected' : '' }}>
                            {{ $p['full_label'] }} ({{ $p['label_en'] }})
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Project Filter for Site Staff --}}
                @if(in_array($staffType, ['site', 'site_driver_remote', 'all']))
                <div class="col-md-3 col-lg-2">
                    <label class="form-label small fw-bold text-dark mb-1">
                        <i class="fa-solid fa-location-dot text-danger me-1"></i>Project / Site
                    </label>
                    <select name="project_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Project Sites</option>
                        @foreach($projects as $p)
                        <option value="{{ $p->id }}" {{ request('project_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                {{-- Department Filter --}}
                <div class="col-md-3 col-lg-2">
                    <label class="form-label small fw-bold text-dark mb-1">
                        <i class="fa-solid fa-building text-secondary me-1"></i>Department
                    </label>
                    <select name="department" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Departments</option>
                        @foreach($departments as $dept)
                        <option value="{{ $dept }}" {{ request('department') === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Search Box --}}
                <div class="col-md-4 col-lg-3">
                    <label class="form-label small fw-bold text-dark mb-1">
                        <i class="fa-solid fa-magnifying-glass text-secondary me-1"></i>Search Employee
                    </label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Name, Code, Role...">
                </div>

                {{-- Filter Action Buttons --}}
                <div class="col-md-2 col-lg-2 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100 shadow-xs">
                        <i class="fa-solid fa-filter me-1"></i>Filter
                    </button>
                    @if(request()->hasAny(['search', 'department', 'project_id', 'period']))
                    <a href="{{ route('attendance.index', ['staff_type' => $staffType]) }}" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Legend & View Switcher Bar --}}
    <div class="card border-0 shadow-xs rounded-3 mb-3 bg-light">
        <div class="card-body py-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-3 flex-wrap small">
                <span class="fw-bold text-dark"><i class="fa-solid fa-tags me-1 text-primary"></i>Legend:</span>
                <span class="d-flex align-items-center gap-1">
                    <span class="badge border bg-white text-success font-monospace px-1.5 py-0.5 border-success">
                        <i class="fa-solid fa-arrow-right-to-bracket me-0.5"></i>In / Out
                    </span>
                    <span class="text-dark">Present (Biometric Punch)</span>
                </span>
                <span class="d-flex align-items-center gap-1">
                    <span class="badge bg-warning text-dark font-monospace px-1 py-0.5 border border-dark border-opacity-25">+Late</span>
                    <span class="text-dark">&gt; 08:40 AM Cutoff (3 Lates = 1 Absent)</span>
                </span>
                <span class="d-flex align-items-center gap-1">
                    <span class="badge bg-info text-white font-monospace px-1.5 py-0.5">S</span>
                    <span class="text-dark">Site Deployment (HR Approved)</span>
                </span>
                <span class="d-flex align-items-center gap-1">
                    <span class="badge bg-primary text-white font-monospace px-1.5 py-0.5">L</span>
                    <span class="text-dark">Approved Leave</span>
                </span>
                <span class="d-flex align-items-center gap-1">
                    <span class="badge bg-purple text-white px-1.5 py-0.5 font-monospace">H</span>
                    <span class="text-dark">Holiday</span>
                </span>
                <span class="d-flex align-items-center gap-1">
                    <span class="badge bg-danger text-white font-monospace px-1.5 py-0.5">A</span>
                    <span class="text-dark">Absent</span>
                </span>
                <span class="d-flex align-items-center gap-1">
                    <span class="badge bg-secondary bg-opacity-25 text-muted px-1.5 py-0.5 font-monospace">SUN</span>
                    <span class="text-muted">Sunday Rest</span>
                </span>
            </div>

            {{-- Mode Switcher Buttons & PDF Export --}}
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted d-none d-md-inline">View Mode:</span>
                <div class="btn-group btn-group-sm shadow-xs" role="group">
                    <button type="button" class="btn btn-primary active" id="btnModeTimes" onclick="switchMatrixView('times')">
                        <i class="fa-solid fa-clock me-1"></i>Clock In / Out Times
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btnModeCompact" onclick="switchMatrixView('compact')">
                        <i class="fa-solid fa-table-cells me-1"></i>Compact Status
                    </button>
                </div>
                <a href="{{ route('attendance.export-pdf', request()->all()) }}" target="_blank" class="btn btn-sm btn-outline-danger shadow-xs" title="Export this month's Clock In / Out Times matrix as PDF">
                    <i class="fa-solid fa-file-pdf me-1"></i>PDF
                </a>
            </div>
        </div>
    </div>

    {{-- Main Ethiopian Payroll Period Matrix Table --}}
    <div class="card border-0 shadow-xs rounded-3 overflow-hidden bg-white mb-4">
        <div class="card-header bg-white py-3 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="fw-bold text-dark mb-0">
                    @if($staffType === 'driver')
                        <i class="fa-solid fa-truck me-1 text-success"></i>
                        Driver Department Attendance Matrix &bull; {{ $period['full_label'] }} ({{ $period['start_greg'] }} &rarr; {{ $period['end_greg'] }})
                    @elseif(in_array($staffType, ['site', 'site_driver_remote']))
                        <i class="fa-solid fa-person-digging me-1 text-warning"></i>
                        Site &amp; Project Attendance Matrix &bull; {{ $period['full_label'] }} ({{ $period['start_greg'] }} &rarr; {{ $period['end_greg'] }})
                    @elseif($staffType === 'office')
                        <i class="fa-solid fa-building me-1 text-primary"></i>
                        Head Office Biometric Attendance Matrix &bull; {{ $period['full_label'] }} ({{ $period['start_greg'] }} &rarr; {{ $period['end_greg'] }})
                    @else
                        <i class="fa-solid fa-table-cells me-1 text-primary"></i>
                        Company-Wide Attendance Matrix &bull; {{ $period['full_label'] }} ({{ $period['start_greg'] }} &rarr; {{ $period['end_greg'] }})
                    @endif
                </h6>
                <small class="text-muted">
                    @if($staffType === 'driver')
                        Showing {{ count($matrix) }} active drivers &bull; Attendance managed and logged by General Service Department
                    @elseif(in_array($staffType, ['site', 'site_driver_remote']))
                        Showing {{ count($matrix) }} active site &amp; field staff &bull; Evaluated by on-site project duty and deployments (Status S)
                    @elseif($staffType === 'office')
                        Showing {{ count($matrix) }} head office staff &bull; Evaluated by head office biometric machine logs
                    @else
                        Showing {{ count($matrix) }} active staff &bull; {{ count($periodDays) }} calendar days
                    @endif
                </small>
            </div>
            <div class="small text-muted font-monospace">
                Ethiopian Period: {{ $period['label_am'] }}
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 740px;">
                <table class="table table-bordered align-middle mb-0 text-center small attendance-matrix-table mode-times" id="attendanceMatrixTable">
                    <thead class="table-light sticky-top" style="z-index: 5;">
                        {{-- Top Header Row: Ethiopian Dates --}}
                        <tr>
                            <th class="sticky-col-header text-start align-middle" rowspan="2" style="min-width: 210px; z-index: 6; left: 0;">
                                <div class="fw-bold text-dark">Employee Information</div>
                                <div class="text-muted" style="font-size: 0.68rem;">Code &bull; Dept &bull; Device PIN</div>
                            </th>

                            @foreach($periodDays as $day)
                            <th class="p-1 date-col-header {{ $day['is_sunday'] ? 'bg-secondary bg-opacity-10 text-muted' : ($day['is_saturday'] ? 'bg-warning bg-opacity-10 text-warning-emphasis' : '') }}">
                                <div class="fw-bold text-dark" style="font-size: 0.75rem;">{{ $day['eth_day'] }}</div>
                                <div class="text-muted text-uppercase" style="font-size: 0.62rem;">{{ substr($day['eth_label_en'], 0, 4) }}</div>
                            </th>
                            @endforeach

                            {{-- Summary Header Group --}}
                            <th class="bg-success-subtle text-success fw-bold align-middle" rowspan="2" style="min-width: 44px;" title="Total Present Days (P)">P</th>
                            <th class="bg-info-subtle text-info fw-bold align-middle" rowspan="2" style="min-width: 44px;" title="Total Site Days (S)">S</th>
                            <th class="bg-primary-subtle text-primary fw-bold align-middle" rowspan="2" style="min-width: 44px;" title="Approved Leave (L)">L</th>
                            <th class="bg-purple text-white bg-opacity-25 fw-bold align-middle" rowspan="2" style="min-width: 44px;" title="Public Holidays (H)">H</th>
                            <th class="bg-danger-subtle text-danger fw-bold align-middle" rowspan="2" style="min-width: 44px;" title="Base Absent Days (A)">A</th>
                            <th class="bg-warning-subtle text-warning fw-bold align-middle" rowspan="2" style="min-width: 44px;" title="Late Punches (&gt; 08:40 AM)">Late</th>
                            <th class="bg-danger text-white fw-bold align-middle" rowspan="2" style="min-width: 48px;" title="Penalty Absent Days (floor(Late / 3))">Penalty</th>
                            <th class="bg-danger text-white fw-bold align-middle" rowspan="2" style="min-width: 52px;" title="Effective Absent = A + Penalty Days">Eff. Abs</th>
                        </tr>

                        {{-- Second Header Row: Gregorian Dates & Day of Week --}}
                        <tr>
                            @foreach($periodDays as $day)
                            <th class="p-1 date-col-header text-muted {{ $day['is_sunday'] ? 'bg-secondary bg-opacity-10' : ($day['is_saturday'] ? 'bg-warning bg-opacity-10' : '') }}" style="font-size: 0.65rem;">
                                <div>{{ $day['greg_day'] }} {{ $day['greg_month'] }}</div>
                                <div class="fw-semibold text-dark">{{ $day['day_name_en'] }}</div>
                            </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($matrix as $empId => $row)
                        @php
                            $emp = $row['employee'];
                            $summary = $row['summary'];
                            $days = $row['days'];
                        @endphp
                        <tr>
                            {{-- Sticky Employee Info Column --}}
                            <td class="text-start sticky-col-cell bg-white px-2 py-1.5" style="left: 0; z-index: 4;">
                                <div class="d-flex align-items-center justify-content-between gap-1">
                                    <div class="text-truncate" style="max-width: 155px;">
                                        <strong class="text-dark d-block text-truncate" title="{{ $emp->full_name }}">{{ $emp->full_name }}</strong>
                                        <div class="text-muted text-truncate" style="font-size: 0.67rem;">
                                            <span class="font-monospace text-primary fw-semibold">{{ $emp->employee_code ?? 'EMP' }}</span>
                                            &bull; {{ $emp->role_title ?: ($emp->department ?? 'General') }}
                                        </div>
                                        @if($emp->project)
                                        <div class="text-truncate text-info" style="font-size: 0.65rem;" title="{{ $emp->project->name }}">
                                            <i class="fa-solid fa-location-dot me-0.5"></i>{{ $emp->project->name }}
                                        </div>
                                        @endif
                                    </div>
                                    @if(!empty($emp->device_user_id))
                                        <span class="badge bg-light text-secondary border font-monospace flex-shrink-0" style="font-size: 0.62rem;" title="Machine PIN: {{ $emp->device_user_id }}">
                                            {{ $emp->device_user_id }}
                                        </span>
                                    @elseif($emp->isDriver())
                                        <span class="badge bg-success text-white font-monospace flex-shrink-0" style="font-size: 0.58rem;" title="Driver Department (Logged by General Service)">
                                            DRIVER
                                        </span>
                                    @elseif($emp->isSiteDriverOrRemote())
                                        <span class="badge bg-info text-white font-monospace flex-shrink-0" style="font-size: 0.58rem;" title="Site &amp; Field Staff (No Head Office PIN required)">
                                            SITE
                                        </span>
                                    @else
                                        <span class="badge bg-danger text-white font-monospace flex-shrink-0" style="font-size: 0.58rem;" title="Missing Head Office Device PIN">
                                            NO PIN
                                        </span>
                                    @endif
                                </div>
                            </td>

                            {{-- Daily Matrix Cells --}}
                            @foreach($periodDays as $day)
                            @php
                                $dItem = $days[$day['greg_date']] ?? null;
                                $code = $dItem['code'] ?? '—';
                                $cellClass = $dItem['class'] ?? 'cell-upcoming';
                                $isLate = $dItem['is_late'] ?? false;
                                $lateMin = $dItem['late_minutes'] ?? 0;
                                $punchIn = $dItem['punch_in'] ?? null;
                                $punchOut = $dItem['punch_out'] ?? null;

                                $tooltip = $dItem['label'] ?? '';
                                if ($punchIn || $punchOut) {
                                    $tooltip .= " (In: " . ($punchIn ?? '—') . " | Out: " . ($punchOut ?? '—') . ")";
                                }
                            @endphp
                            <td class="p-0 position-relative cell-container {{ $cellClass }}"
                                onclick="openDayDetailModal(this)"
                                data-emp-name="{{ $emp->full_name }}"
                                data-emp-code="{{ $emp->employee_code ?? 'EMP' }}"
                                data-emp-dept="{{ $emp->department ?? 'General' }}"
                                data-emp-role="{{ $emp->role_title ?? '' }}"
                                data-date-greg="{{ $day['greg_date'] }} ({{ $day['day_name_en'] }})"
                                data-date-eth="{{ $day['eth_day'] }} {{ $day['eth_label_am'] }} ({{ $day['eth_year'] }})"
                                data-code="{{ $code }}"
                                data-label="{{ $dItem['label'] ?? '' }}"
                                data-punch-in="{{ $punchIn ?? '—' }}"
                                data-punch-out="{{ $punchOut ?? '—' }}"
                                data-morning-in="{{ $dItem['morning_in'] ?? '—' }}"
                                data-morning-out="{{ $dItem['morning_out'] ?? '—' }}"
                                data-afternoon-in="{{ $dItem['afternoon_in'] ?? '—' }}"
                                data-afternoon-out="{{ $dItem['afternoon_out'] ?? '—' }}"
                                data-hours="{{ $dItem['hours'] ?? '0' }}"
                                data-is-late="{{ $isLate ? '1' : '0' }}"
                                data-late-min="{{ $lateMin }}"
                                data-device="{{ $emp->device_user_id ?? 'None' }}"
                                data-site="{{ $dItem['site_name'] ?? '' }}"
                                data-leave="{{ $dItem['leave_title'] ?? '' }}"
                                data-holiday="{{ $dItem['holiday_name'] ?? '' }}"
                                data-notes="{{ $dItem['notes'] ?? '' }}"
                                title="{{ $tooltip }}">

                                {{-- DETAILED VIEW (Clock In & Clock Out) --}}
                                <div class="view-times-box d-flex flex-column align-items-center justify-content-center p-1 w-100 h-100">
                                    @if($code === 'P')
                                        {{-- Clock In (Green or Warning if late) --}}
                                        <div class="fw-bold font-monospace text-truncate w-100 text-center {{ $isLate ? 'text-warning-emphasis' : 'text-success' }}" style="font-size: 0.73rem; line-height: 1.15;">
                                            <i class="fa-solid fa-arrow-right-to-bracket me-0.5 opacity-75" style="font-size: 0.58rem;"></i>{{ $punchIn ?? '—' }}
                                        </div>
                                        {{-- Clock Out --}}
                                        <div class="font-monospace text-truncate w-100 text-center text-secondary" style="font-size: 0.68rem; line-height: 1.15; opacity: 0.85;">
                                            <i class="fa-solid fa-arrow-right-from-bracket me-0.5 opacity-75" style="font-size: 0.58rem;"></i>{{ $punchOut ?? '—' }}
                                        </div>
                                        @if($isLate)
                                        <span class="badge bg-warning text-dark border border-warning position-absolute top-0 end-0 px-1 py-0 shadow-xs" style="font-size: 0.52rem; transform: scale(0.85); transform-origin: top right;" title="Late by {{ $lateMin }}m">
                                            +{{ $lateMin }}m
                                        </span>
                                        @endif
                                    @elseif($code === 'S')
                                        {{-- On-Site Credited Hours --}}
                                        <div class="fw-bold font-monospace text-truncate w-100 text-center text-info" style="font-size: 0.70rem; line-height: 1.2;">
                                            <i class="fa-solid fa-person-digging me-0.5 opacity-75" style="font-size: 0.55rem;"></i>{{ $punchIn ?? '08:40 AM' }}
                                        </div>
                                        <div class="font-monospace text-truncate w-100 text-center text-secondary" style="font-size: 0.67rem; line-height: 1.2; opacity: 0.85;">
                                            <i class="fa-solid fa-arrow-right-from-bracket me-0.5 opacity-75" style="font-size: 0.55rem;"></i>{{ $punchOut ?? '05:30 PM' }}
                                        </div>
                                        <span class="badge bg-info text-white position-absolute top-0 end-0 px-1 py-0 shadow-xs" style="font-size: 0.52rem; transform: scale(0.85); transform-origin: top right;" title="On-Site Deployment">
                                            S
                                        </span>
                                    @elseif($code === 'L')
                                        <span class="badge bg-primary text-white font-monospace px-1.5 py-0.5" style="font-size: 0.68rem;">L</span>
                                        <div class="text-primary fw-bold" style="font-size: 0.58rem; line-height: 1;">LEAVE</div>
                                    @elseif($code === 'H')
                                        <span class="badge bg-purple text-white font-monospace px-1.5 py-0.5" style="font-size: 0.68rem;">H</span>
                                        <div class="text-purple fw-bold" style="font-size: 0.58rem; line-height: 1;">HOLIDAY</div>
                                    @elseif($code === 'SUN')
                                        @if(!empty($punchIn))
                                            <div class="fw-bold font-monospace text-success" style="font-size: 0.72rem; line-height: 1.1;">{{ $punchIn }}</div>
                                            <div class="font-monospace text-secondary" style="font-size: 0.66rem; line-height: 1.1;">{{ $punchOut ?: '—' }}</div>
                                            <span class="badge bg-warning text-dark position-absolute top-0 end-0 px-1 py-0" style="font-size: 0.52rem;">OT</span>
                                        @else
                                            <span class="text-muted fw-bold font-monospace" style="font-size: 0.68rem;">SUN</span>
                                        @endif
                                    @elseif($code === 'A')
                                        <span class="badge bg-danger text-white font-monospace px-1.5 py-0.5" style="font-size: 0.68rem;">A</span>
                                        <div class="text-danger fw-bold opacity-75" style="font-size: 0.58rem; line-height: 1;">ABSENT</div>
                                    @else
                                        <span class="text-muted" style="font-size: 0.8rem;">—</span>
                                    @endif
                                </div>

                                {{-- COMPACT VIEW (Large letter badge only) --}}
                                <div class="view-compact-box d-none align-items-center justify-content-center w-100 h-100">
                                    <span class="fw-bold font-monospace badge-letter" style="font-size: 0.85rem;">{{ $code }}</span>
                                    @if($isLate)
                                    <span class="position-absolute top-0 end-0 p-1">
                                        <span class="badge rounded-circle p-1 bg-danger"></span>
                                    </span>
                                    @endif
                                </div>
                            </td>
                            @endforeach

                            {{-- Summary Columns --}}
                            <td class="fw-bold font-monospace bg-success-subtle text-success">{{ $summary['present_days'] }}</td>
                            <td class="fw-bold font-monospace bg-info-subtle text-info">{{ $summary['site_days'] }}</td>
                            <td class="fw-bold font-monospace bg-primary-subtle text-primary">{{ $summary['leave_days'] }}</td>
                            <td class="fw-bold font-monospace bg-purple text-white bg-opacity-25">{{ $summary['holiday_days'] }}</td>
                            <td class="fw-bold font-monospace bg-danger-subtle text-danger">{{ $summary['absent_days'] }}</td>
                            <td class="fw-bold font-monospace bg-warning-subtle text-dark">{{ $summary['late_days'] }}</td>
                            <td class="fw-bold font-monospace bg-danger text-white">{{ $summary['penalty_days'] }}</td>
                            <td class="fw-bold font-monospace bg-danger text-white fs-6">{{ $summary['effective_absent'] }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ count($periodDays) + 9 }}" class="py-5 text-center text-muted">
                                <i class="fa-solid fa-users-slash fs-2 mb-2 d-block text-secondary"></i>
                                No active employees found matching the current search &amp; filter criteria.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- MODAL: Interactive Day Punch Detail Modal --}}
<div class="modal fade" id="dayPunchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header py-3 px-4 border-bottom bg-light">
                <div>
                    <h6 class="modal-title fw-bold text-dark mb-0" id="dpEmpName">Employee Name</h6>
                    <div class="text-muted small" id="dpEmpMeta">Code &bull; Department</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                {{-- Date & Status Header --}}
                <div class="d-flex align-items-center justify-content-between p-3 rounded-3 mb-3 bg-light border">
                    <div>
                        <div class="text-muted small">Ethiopian Calendar Date:</div>
                        <strong class="text-dark fs-6" id="dpDateEth">—</strong>
                        <div class="text-muted small mt-0.5" id="dpDateGreg">—</div>
                    </div>
                    <div class="text-end">
                        <span class="badge fs-6 px-3 py-1.5" id="dpStatusBadge">Status</span>
                    </div>
                </div>

                {{-- Time Clock Cards --}}
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="p-3 rounded-3 border bg-white text-center">
                            <span class="text-muted small text-uppercase fw-semibold d-block mb-1">
                                <i class="fa-solid fa-arrow-right-to-bracket text-success me-1"></i>Clock In
                            </span>
                            <div class="fs-4 fw-bold font-monospace text-dark" id="dpPunchIn">—</div>
                            <div class="small text-muted" id="dpLateTag">Official Cutoff: 08:40 AM</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 border bg-white text-center">
                            <span class="text-muted small text-uppercase fw-semibold d-block mb-1">
                                <i class="fa-solid fa-arrow-right-from-bracket text-secondary me-1"></i>Clock Out
                            </span>
                            <div class="fs-4 fw-bold font-monospace text-dark" id="dpPunchOut">—</div>
                            <div class="small text-muted" id="dpHoursTag">Hours Worked</div>
                        </div>
                    </div>
                </div>

                {{-- Shift Sessions Breakdown --}}
                <div class="border rounded-3 p-3 bg-light mb-3">
                    <div class="fw-bold small text-dark mb-2">
                        <i class="fa-solid fa-clock-rotate-left text-primary me-1"></i>Daily Shift Sessions Breakdown
                    </div>
                    <div class="row g-2 small font-monospace">
                        <div class="col-6">
                            <span class="text-muted">Morning In:</span>
                            <strong class="text-dark ms-1" id="dpMorningIn">—</strong>
                        </div>
                        <div class="col-6">
                            <span class="text-muted">Morning Out (Lunch):</span>
                            <strong class="text-dark ms-1" id="dpMorningOut">—</strong>
                        </div>
                        <div class="col-6">
                            <span class="text-muted">Afternoon In:</span>
                            <strong class="text-dark ms-1" id="dpAfternoonIn">—</strong>
                        </div>
                        <div class="col-6">
                            <span class="text-muted">Day End Out:</span>
                            <strong class="text-dark ms-1" id="dpAfternoonOut">—</strong>
                        </div>
                    </div>
                </div>

                {{-- Metadata / Site / Leave / Notes --}}
                <div class="small text-muted" id="dpExtraInfo"></div>
            </div>
            <div class="modal-footer bg-light py-2 px-3">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- MODAL 1: On-Site Deployment Request --}}
<div class="modal fade" id="siteAttendanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-3 border-0 shadow">
            <form action="{{ route('attendance.record-site') }}" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white py-2 px-3">
                    <h6 class="modal-title fw-bold">
                        <i class="fa-solid fa-person-digging me-1"></i>Record Site Deployment Request
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Site deployments are credited as <strong>On-Site ('S')</strong> and are non-deductible only after <strong>HR approval</strong>. Pending requests will not show 'S'.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Employee <span class="text-danger">*</span></label>
                        <select name="employee_id" class="form-select form-select-sm" required>
                            <option value="">Select Employee...</option>
                            @foreach($allActiveEmployees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_code ?? 'EMP' }}) &bull; {{ $emp->department ?? 'General' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control form-control-sm" value="{{ today()->toDateString() }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small">End Date <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" class="form-control form-control-sm" value="{{ today()->toDateString() }}" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Destination Project / Site <span class="text-danger">*</span></label>
                        <select name="project_id" class="form-select form-select-sm" required>
                            <option value="">Select Project...</option>
                            @foreach($projects as $proj)
                            <option value="{{ $proj->id }}">{{ $proj->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Task / Mission Details</label>
                        <textarea name="task_description" rows="2" class="form-control form-control-sm" placeholder="Describe the on-site activity, supervisor, or field mission..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-3">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fa-solid fa-paper-plane me-1"></i>Submit for HR Approval
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL 2: Shift Schedule Configuration --}}
<div class="modal fade" id="workScheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-3 border-0 shadow">
            <form action="{{ route('attendance.updateSchedule') }}" method="POST">
                @csrf
                <div class="modal-header bg-dark text-white py-2 px-3">
                    <h6 class="modal-title fw-bold">
                        <i class="fa-solid fa-business-time me-1 text-info"></i>Configure Official Work Schedule
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3 small">
                    <div class="mb-3">
                        <strong class="text-dark d-block mb-1">Monday – Friday Schedule (8.0 Hours Total):</strong>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="text-muted">Morning Check-In:</label>
                                <input type="time" name="morning_in" class="form-control form-control-sm" value="{{ $workSchedule['morning_in'] ?? '08:40' }}" required>
                            </div>
                            <div class="col-6">
                                <label class="text-muted">Morning Check-Out (Lunch):</label>
                                <input type="time" name="morning_out" class="form-control form-control-sm" value="{{ $workSchedule['morning_out'] ?? '12:30' }}" required>
                            </div>
                            <div class="col-6">
                                <label class="text-muted">Lunch Break Start:</label>
                                <input type="time" name="break_start" class="form-control form-control-sm" value="{{ $workSchedule['break_start'] ?? '12:30' }}" required>
                            </div>
                            <div class="col-6">
                                <label class="text-muted">Lunch Break End:</label>
                                <input type="time" name="break_end" class="form-control form-control-sm" value="{{ $workSchedule['break_end'] ?? '13:35' }}" required>
                            </div>
                            <div class="col-6">
                                <label class="text-muted">Afternoon Return:</label>
                                <input type="time" name="afternoon_in" class="form-control form-control-sm" value="{{ $workSchedule['afternoon_in'] ?? '13:35' }}" required>
                            </div>
                            <div class="col-6">
                                <label class="text-muted">Day End Check-Out:</label>
                                <input type="time" name="afternoon_out" class="form-control form-control-sm" value="{{ $workSchedule['afternoon_out'] ?? '17:30' }}" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3 pt-2 border-top">
                        <strong class="text-dark d-block mb-1">Saturday Schedule (Morning Session Only - 4.0 Hours):</strong>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="text-muted">Saturday In:</label>
                                <input type="time" name="sat_morning_in" class="form-control form-control-sm" value="{{ $workSchedule['sat_morning_in'] ?? '08:40' }}" required>
                            </div>
                            <div class="col-6">
                                <label class="text-muted">Saturday Out:</label>
                                <input type="time" name="sat_morning_out" class="form-control form-control-sm" value="{{ $workSchedule['sat_morning_out'] ?? '12:30' }}" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-3">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-sm btn-dark">
                        <i class="fa-solid fa-save me-1"></i>Save Schedule Policy
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL 3: Clear and Resync (Admin Only) --}}
@if(auth()->user() && auth()->user()->hasAnyRole(['admin', 'global_admin']))
<div class="modal fade" id="clearResyncModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-3 border-0 shadow">
            <form action="{{ route('attendance.clearHistory') }}" method="POST">
                @csrf
                <div class="modal-header bg-danger text-white py-2 px-3">
                    <h6 class="modal-title fw-bold">
                        <i class="fa-solid fa-broom me-1"></i>Clear &amp; Resync Attendance from Biometrics
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3 small">
                    <div class="alert alert-danger py-2 mb-3">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        This will delete cached attendance rows and rebuild everything strictly from raw biometric machine push logs.
                    </div>
                    <input type="hidden" name="clear_type" value="reset_and_resync">
                    <p class="text-muted mb-0">Are you sure you want to proceed?</p>
                </div>
                <div class="modal-footer bg-light py-2 px-3">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger">
                        <i class="fa-solid fa-rotate me-1"></i>Rebuild from Machines
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<style>
/* Sticky first column for employee identification */
.attendance-matrix-table .sticky-col-header {
    position: sticky;
    left: 0;
    z-index: 6;
    background-color: #f8f9fa;
}
.attendance-matrix-table .sticky-col-cell {
    position: sticky;
    left: 0;
    z-index: 4;
    background-color: #ffffff;
    box-shadow: 2px 0 5px rgba(0, 0, 0, 0.06);
}

/* Date column sizing depending on mode */
.attendance-matrix-table.mode-times .date-col-header,
.attendance-matrix-table.mode-times .cell-container {
    min-width: 82px;
    height: 50px;
}
.attendance-matrix-table.mode-compact .date-col-header,
.attendance-matrix-table.mode-compact .cell-container {
    min-width: 40px;
    height: 38px;
}

/* Cell hover interaction */
.cell-container {
    cursor: pointer;
    transition: all 0.15s ease-in-out;
}
.cell-container:hover {
    transform: scale(1.06);
    z-index: 10 !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Modern, clean semantic cell color themes */
.cell-present-ontime {
    background-color: #f0fdf4 !important;
    border: 1px solid #bbf7d0 !important;
}
.cell-present-late {
    background-color: #fffbeb !important;
    border: 1px solid #fde68a !important;
}
.cell-site {
    background-color: #f0f9ff !important;
    border: 1px solid #bae6fd !important;
}
.cell-leave {
    background-color: #eef2ff !important;
    border: 1px solid #c7d2fe !important;
}
.cell-holiday {
    background-color: #faf5ff !important;
    border: 1px solid #e9d5ff !important;
}
.cell-sunday {
    background-color: #f9fafb !important;
    border: 1px solid #e5e7eb !important;
}
.cell-sunday-ot {
    background-color: #ecfdf5 !important;
    border: 1px solid #6ee7b7 !important;
}
.cell-absent {
    background-color: #fff1f2 !important;
    border: 1px solid #fecdd3 !important;
}
.cell-upcoming {
    background-color: #ffffff !important;
    border: 1px solid #f3f4f6 !important;
}

/* Colors for letters */
.cell-present-ontime .badge-letter { color: #166534; }
.cell-present-late .badge-letter   { color: #b45309; }
.cell-site .badge-letter           { color: #0369a1; }
.cell-leave .badge-letter          { color: #4338ca; }
.cell-holiday .badge-letter        { color: #7e22ce; }
.cell-sunday .badge-letter         { color: #6b7280; }
.cell-absent .badge-letter         { color: #be123c; }
.cell-upcoming .badge-letter       { color: #9ca3af; }

.bg-purple {
    background-color: #7e22ce !important;
}
.text-purple {
    color: #7e22ce !important;
}
</style>

<script>
function switchMatrixView(mode) {
    const table = document.getElementById('attendanceMatrixTable');
    const btnTimes = document.getElementById('btnModeTimes');
    const btnCompact = document.getElementById('btnModeCompact');

    if (!table) return;

    if (mode === 'compact') {
        table.classList.remove('mode-times');
        table.classList.add('mode-compact');

        document.querySelectorAll('.view-times-box').forEach(el => el.classList.add('d-none'));
        document.querySelectorAll('.view-compact-box').forEach(el => {
            el.classList.remove('d-none');
            el.classList.add('d-flex');
        });

        btnCompact.classList.add('btn-primary', 'active');
        btnCompact.classList.remove('btn-outline-secondary');
        btnTimes.classList.remove('btn-primary', 'active');
        btnTimes.classList.add('btn-outline-secondary');
        try { localStorage.setItem('matrix_view_mode', 'compact'); } catch(e){}
    } else {
        table.classList.remove('mode-compact');
        table.classList.add('mode-times');

        document.querySelectorAll('.view-compact-box').forEach(el => {
            el.classList.remove('d-flex');
            el.classList.add('d-none');
        });
        document.querySelectorAll('.view-times-box').forEach(el => el.classList.remove('d-none'));

        btnTimes.classList.add('btn-primary', 'active');
        btnTimes.classList.remove('btn-outline-secondary');
        btnCompact.classList.remove('btn-primary', 'active');
        btnCompact.classList.add('btn-outline-secondary');
        try { localStorage.setItem('matrix_view_mode', 'times'); } catch(e){}
    }
}

// Restore saved preference on load
document.addEventListener('DOMContentLoaded', function() {
    try {
        const saved = localStorage.getItem('matrix_view_mode');
        if (saved === 'compact') {
            switchMatrixView('compact');
        }
    } catch(e){}
});

// Open Day Detail Modal with complete punch metadata
function openDayDetailModal(cell) {
    if (!cell) return;
    const empName = cell.getAttribute('data-emp-name') || 'Employee';
    const empCode = cell.getAttribute('data-emp-code') || '';
    const empDept = cell.getAttribute('data-emp-dept') || '';
    const empRole = cell.getAttribute('data-emp-role') || '';
    const dateEth = cell.getAttribute('data-date-eth') || '';
    const dateGreg = cell.getAttribute('data-date-greg') || '';
    const code = cell.getAttribute('data-code') || '—';
    const label = cell.getAttribute('data-label') || '';
    const punchIn = cell.getAttribute('data-punch-in') || '—';
    const punchOut = cell.getAttribute('data-punch-out') || '—';
    const mIn = cell.getAttribute('data-morning-in') || '—';
    const mOut = cell.getAttribute('data-morning-out') || '—';
    const aIn = cell.getAttribute('data-afternoon-in') || '—';
    const aOut = cell.getAttribute('data-afternoon-out') || '—';
    const hours = cell.getAttribute('data-hours') || '0';
    const isLate = cell.getAttribute('data-is-late') === '1';
    const lateMin = cell.getAttribute('data-late-min') || '0';
    const device = cell.getAttribute('data-device') || 'None';
    const site = cell.getAttribute('data-site') || '';
    const leave = cell.getAttribute('data-leave') || '';
    const holiday = cell.getAttribute('data-holiday') || '';
    const notes = cell.getAttribute('data-notes') || '';

    function to12H(timeStr) {
        if (!timeStr || timeStr === '—' || timeStr === '-' || timeStr.trim() === '') return '—';
        if (timeStr.includes('AM') || timeStr.includes('PM')) return timeStr;
        const parts = timeStr.trim().split(':');
        if (parts.length >= 2) {
            let h = parseInt(parts[0], 10);
            let m = parts[1].substring(0, 2);
            if (isNaN(h)) return timeStr;
            let ampm = h >= 12 ? 'PM' : 'AM';
            let h12 = h % 12;
            if (h12 === 0) h12 = 12;
            let hPad = String(h12).padStart(2, '0');
            return `${hPad}:${m} ${ampm}`;
        }
        return timeStr;
    }

    document.getElementById('dpEmpName').textContent = empName;
    document.getElementById('dpEmpMeta').textContent = `${empCode} • ${empDept} ${empRole ? '• ' + empRole : ''} (Device PIN: ${device})`;
    document.getElementById('dpDateEth').textContent = dateEth;
    document.getElementById('dpDateGreg').textContent = dateGreg;
    document.getElementById('dpPunchIn').textContent = to12H(punchIn);
    document.getElementById('dpPunchOut').textContent = to12H(punchOut);
    document.getElementById('dpMorningIn').textContent = to12H(mIn);
    document.getElementById('dpMorningOut').textContent = to12H(mOut);
    document.getElementById('dpAfternoonIn').textContent = to12H(aIn);
    document.getElementById('dpAfternoonOut').textContent = to12H(aOut);
    document.getElementById('dpHoursTag').textContent = `${hours} Hours Worked`;

    const statusBadge = document.getElementById('dpStatusBadge');
    statusBadge.className = 'badge fs-6 px-3 py-1.5';

    if (code === 'P') {
        if (isLate) {
            statusBadge.classList.add('bg-warning', 'text-dark');
            statusBadge.textContent = `Present • Late (${lateMin} min)`;
            document.getElementById('dpLateTag').innerHTML = `<span class="text-danger fw-bold"><i class="fa-solid fa-clock me-1"></i>Late by ${lateMin} min (Cutoff: 08:40 AM)</span>`;
        } else {
            statusBadge.classList.add('bg-success', 'text-white');
            statusBadge.textContent = 'Present (On-Time)';
            document.getElementById('dpLateTag').innerHTML = `<span class="text-success"><i class="fa-solid fa-check me-1"></i>On-Time Arrival (Before 08:40 AM)</span>`;
        }
    } else if (code === 'S') {
        statusBadge.classList.add('bg-info', 'text-white');
        statusBadge.textContent = 'On-Site Deployment (Credited)';
        document.getElementById('dpLateTag').textContent = site ? `Site: ${site}` : 'Field Deployment';
    } else if (code === 'L') {
        statusBadge.classList.add('bg-primary', 'text-white');
        statusBadge.textContent = 'Approved Leave';
        document.getElementById('dpLateTag').textContent = leave || 'Approved Leave';
    } else if (code === 'H') {
        statusBadge.classList.add('bg-purple', 'text-white');
        statusBadge.textContent = 'Public Holiday';
        document.getElementById('dpLateTag').textContent = holiday || 'Official Company Holiday';
    } else if (code === 'SUN') {
        statusBadge.classList.add('bg-secondary', 'text-white');
        statusBadge.textContent = punchIn !== '—' ? 'Sunday Overtime Work' : 'Sunday Rest Day';
        document.getElementById('dpLateTag').textContent = 'Weekly Rest Day';
    } else if (code === 'A') {
        statusBadge.classList.add('bg-danger', 'text-white');
        statusBadge.textContent = 'Absent (No Biometric Punches)';
        document.getElementById('dpLateTag').innerHTML = `<span class="text-danger fw-bold"><i class="fa-solid fa-circle-exclamation me-1"></i>Expected working day with no punch</span>`;
    } else {
        statusBadge.classList.add('bg-light', 'text-muted');
        statusBadge.textContent = 'Upcoming Calendar Day';
        document.getElementById('dpLateTag').textContent = 'Not yet reached';
    }

    let extraHtml = [];
    if (site) extraHtml.push(`<div><strong>Project Site:</strong> ${site}</div>`);
    if (leave) extraHtml.push(`<div><strong>Leave Type:</strong> ${leave}</div>`);
    if (holiday) extraHtml.push(`<div><strong>Holiday:</strong> ${holiday}</div>`);
    if (notes) extraHtml.push(`<div><strong>Notes:</strong> ${notes}</div>`);
    document.getElementById('dpExtraInfo').innerHTML = extraHtml.join('');

    const modal = new bootstrap.Modal(document.getElementById('dayPunchModal'));
    modal.show();
}
</script>

{{-- MODAL: Record Driver Attendance by General Service --}}
@if(auth()->check() && auth()->user()->hasAnyRole(['general_service', 'general_services', 'admin', 'global_admin', 'hr', 'hr_manager', 'hr_officer']))
<div class="modal fade text-start" id="recordDriverModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-3 border-0 shadow">
            <form action="{{ route('attendance.record-driver') }}" method="POST">
                @csrf
                <div class="modal-header bg-success text-white py-2 px-3">
                    <h6 class="modal-title fw-bold">
                        <i class="fa-solid fa-truck me-1"></i>Record Driver Attendance (General Service)
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Select Driver <span class="text-danger">*</span></label>
                        <select name="employee_id" class="form-select form-select-sm" required>
                            <option value="">Choose Driver...</option>
                            @foreach($activeDriversList as $drv)
                            <option value="{{ $drv->id }}">
                                {{ $drv->full_name }} ({{ $drv->employee_code ?? 'EMP' }} &bull; {{ $drv->role_title ?: 'Driver' }})
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Attendance Date <span class="text-danger">*</span></label>
                            <input type="date" name="attendance_date" class="form-control form-control-sm" value="{{ today()->toDateString() }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Duty Status <span class="text-danger">*</span></label>
                            <select name="duty_status" class="form-select form-select-sm" required>
                                <option value="present">Present (Standard Fleet Duty)</option>
                                <option value="trip" selected>On-Trip / Field Dispatch (S)</option>
                                <option value="leave">Approved Leave (L)</option>
                                <option value="absent">Absent (A)</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Morning Check-In</label>
                            <input type="time" name="morning_in" class="form-control form-control-sm" value="08:00">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Afternoon Check-Out</label>
                            <input type="time" name="afternoon_out" class="form-control form-control-sm" value="17:30">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Trip Destination / Route</label>
                        <input type="text" name="trip_destination" class="form-control form-control-sm" placeholder="e.g. Chafe Site material transport, Addis-Mojo run, Staff shuttle">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Vehicle Plate Number (Optional)</label>
                        <input type="text" name="vehicle_plate" class="form-control form-control-sm" placeholder="e.g. 3-45678 AA or Isuzu NPR">
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold">General Service Remarks / Notes</label>
                        <textarea name="notes" rows="2" class="form-control form-control-sm" placeholder="Fuel, cargo details, or route notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-3">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="fa-solid fa-check me-1"></i>Save Attendance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- MODAL: Daily Driver Attendance Sheet Entry (General Service) --}}
@if(auth()->check() && auth()->user()->hasAnyRole(['general_service', 'general_services', 'admin', 'global_admin', 'hr', 'hr_manager', 'hr_officer']))
<div class="modal fade text-start" id="recordDailyDriverSheetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content rounded-3 border-0 shadow">
            <form action="{{ route('attendance.record-driver-sheet') }}" method="POST">
                @csrf
                <div class="modal-header bg-success text-white py-2 px-3">
                    <h6 class="modal-title fw-bold">
                        <i class="fa-solid fa-clipboard-list me-2"></i>Daily Driver Attendance Sheet (በጄኔራል ሰርቪስ የዕለት አቴንዳንስ መመዝገቢያ)
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="row align-items-center mb-3 bg-light p-2 rounded-2">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold mb-1"><i class="fa-solid fa-calendar me-1 text-success"></i>Date (ቀን):</label>
                            <input type="date" name="sheet_date" class="form-control form-control-sm" value="{{ today()->toDateString() }}" required>
                        </div>
                        <div class="col-md-8 text-end small text-muted">
                            <i class="fa-solid fa-circle-info me-1"></i>Set duty status and route/trip for each driver. Unrecorded drivers set to "Skip" will stay blank.
                        </div>
                    </div>

                    <div class="table-responsive" style="max-height: 480px;">
                        <table class="table table-sm table-bordered align-middle mb-0 small">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="width: 22%;">Driver Name</th>
                                    <th style="width: 20%;">Duty Status</th>
                                    <th style="width: 15%;">Shift Times</th>
                                    <th style="width: 23%;">Trip Route / Destination</th>
                                    <th style="width: 20%;">Vehicle Plate / Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($activeDriversList as $idx => $drv)
                                <tr>
                                    <td>
                                        <input type="hidden" name="drivers[{{ $idx }}][employee_id]" value="{{ $drv->id }}">
                                        <strong class="d-block text-dark">{{ $drv->full_name }}</strong>
                                        <span class="text-muted" style="font-size: 0.7rem;">{{ $drv->employee_code ?? 'EMP' }}</span>
                                    </td>
                                    <td>
                                        <select name="drivers[{{ $idx }}][duty_status]" class="form-select form-select-sm">
                                            <option value="trip" selected>🚚 On-Trip / Dispatch (S)</option>
                                            <option value="present">✅ Present (Fleet Duty)</option>
                                            <option value="leave">🏖️ Approved Leave (L)</option>
                                            <option value="absent">❌ Absent (A)</option>
                                            <option value="skip">— Skip (Leave blank)</option>
                                        </select>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <input type="time" name="drivers[{{ $idx }}][morning_in]" class="form-control form-control-sm px-1" value="08:00">
                                            <input type="time" name="drivers[{{ $idx }}][afternoon_out]" class="form-control form-control-sm px-1" value="17:30">
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" name="drivers[{{ $idx }}][trip_destination]" class="form-control form-control-sm" placeholder="e.g. Chafe Site, Mojo, City transport">
                                    </td>
                                    <td>
                                        <input type="text" name="drivers[{{ $idx }}][vehicle_plate]" class="form-control form-control-sm" placeholder="e.g. Plate # or notes">
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-3">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="fa-solid fa-floppy-disk me-1"></i>Save All Driver Records
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- Modal: Export Attendance Matrix as PDF with Date Option --}}
<div class="modal fade" id="exportAttendancePdfModal" tabindex="-1" aria-labelledby="exportAttendancePdfModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <form action="{{ route('attendance.export-pdf') }}" method="GET" target="_blank">
                <div class="modal-header bg-danger text-white py-2 px-3">
                    <h6 class="modal-title fw-bold" id="exportAttendancePdfModalLabel">
                        <i class="fa-solid fa-file-pdf me-1.5"></i>Export Attendance Matrix as PDF (Clock In &amp; Out Times)
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Generates a comprehensive landscape executive PDF matrix showing <strong>every employee's daily Clock In and Clock Out times</strong>, verified biometric punches, late penalties, on-site deployments, and period summary totals.
                    </div>

                    <div class="row g-3 mb-2">
                        {{-- Date Option Choice: Month vs Custom Dates --}}
                        <div class="col-12">
                            <label class="form-label small fw-bold text-dark mb-1">Date Option / Range Mode:</label>
                            <div class="d-flex gap-3 align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="date_mode" id="dateModeMonth" value="month" checked onchange="togglePdfDateMode()">
                                    <label class="form-check-label small fw-semibold" for="dateModeMonth">
                                        Ethiopian Payroll Month (26th &ndash; 25th)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="date_mode" id="dateModeCustom" value="custom" onchange="togglePdfDateMode()">
                                    <label class="form-check-label small fw-semibold" for="dateModeCustom">
                                        Custom Date Option (Specific Date Range)
                                    </label>
                                </div>
                            </div>
                        </div>

                        {{-- Section A: Ethiopian Month / Period --}}
                        <div class="col-12" id="pdfMonthSelectionBox">
                            <label class="form-label small fw-bold text-dark mb-1">
                                <i class="fa-solid fa-calendar-days text-primary me-1"></i>Select Ethiopian Month (Payroll Period):
                            </label>
                            <select name="period" class="form-select form-select-sm" id="pdfPeriodInput">
                                @foreach($availablePeriods as $p)
                                <option value="{{ $p['period_key'] }}" {{ $p['period_key'] === $selectedPeriodKey ? 'selected' : '' }}>
                                    {{ $p['full_label'] }} ({{ $p['label_en'] }}) &bull; [{{ $p['start_greg'] }} &rarr; {{ $p['end_greg'] }}]
                                </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Section B: Custom Date Range Option --}}
                        <div class="col-12 d-none" id="pdfCustomDateSelectionBox">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-dark mb-1">
                                        <i class="fa-regular fa-calendar text-danger me-1"></i>From Date (Start):
                                    </label>
                                    <input type="date" name="start_date" id="pdfStartDateInput" value="{{ $period['start_greg'] }}" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-dark mb-1">
                                        <i class="fa-regular fa-calendar text-danger me-1"></i>To Date (End):
                                    </label>
                                    <input type="date" name="end_date" id="pdfEndDateInput" value="{{ $period['end_greg'] }}" class="form-control form-control-sm">
                                </div>
                            </div>
                            <small class="text-muted mt-1 d-block" style="font-size: 0.72rem;">
                                Select any date range within or across months. Both Gregorian and Ethiopian calendar headers will be displayed.
                            </small>
                        </div>

                        {{-- Report Format Option --}}
                        <div class="col-12">
                            <label class="form-label small fw-bold text-dark mb-1">
                                <i class="fa-solid fa-file-invoice text-danger me-1"></i>Report Format / Layout:
                            </label>
                            <select name="layout" class="form-select form-select-sm">
                                <option value="timesheet" selected>📄 A4 Detailed Timesheet (Morning In/Out &amp; Afternoon In/Out Sections)</option>
                                <option value="matrix">📊 Master Monthly Attendance Matrix (All Employees Grid)</option>
                            </select>
                        </div>

                        {{-- Specific Employee Filter (Optional) --}}
                        <div class="col-12">
                            <label class="form-label small fw-bold text-dark mb-1">
                                <i class="fa-solid fa-user-tag text-primary me-1"></i>Employee Selection:
                            </label>
                            <select name="employee_id" class="form-select form-select-sm">
                                <option value="">All Active Staff (Generates Individual A4 Sheets for All)</option>
                                @foreach($employees as $e)
                                <option value="{{ $e->id }}">{{ $e->full_name }} ({{ $e->employee_code ?? 'EMP' }}) &bull; {{ $e->role_title ?: ($e->department ?? 'General') }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Staff Category --}}
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark mb-1">
                                <i class="fa-solid fa-users text-secondary me-1"></i>Staff Category:
                            </label>
                            <select name="staff_type" class="form-select form-select-sm">
                                <option value="office" {{ $staffType === 'office' ? 'selected' : '' }}>Head Office Staff (HQ Biometrics)</option>
                                <option value="site" {{ $staffType === 'site' ? 'selected' : '' }}>Site &amp; Project Staff (Deployments)</option>
                                <option value="driver" {{ $staffType === 'driver' ? 'selected' : '' }}>Driver Dept (General Service)</option>
                                <option value="all" {{ $staffType === 'all' ? 'selected' : '' }}>All Employees Combined</option>
                            </select>
                        </div>

                        {{-- Department --}}
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark mb-1">
                                <i class="fa-solid fa-building text-secondary me-1"></i>Department:
                            </label>
                            <select name="department" class="form-select form-select-sm">
                                <option value="">All Departments</option>
                                @foreach($departments as $dept)
                                <option value="{{ $dept }}" {{ request('department') === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Project Site Filter --}}
                        @if($projects && $projects->isNotEmpty())
                        <div class="col-12">
                            <label class="form-label small fw-bold text-dark mb-1">
                                <i class="fa-solid fa-location-dot text-danger me-1"></i>Project Site Filter (Optional):
                            </label>
                            <select name="project_id" class="form-select form-select-sm">
                                <option value="">All Project Sites</option>
                                @foreach($projects as $p)
                                <option value="{{ $p->id }}" {{ request('project_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-3 justify-content-between">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger shadow-xs">
                        <i class="fa-solid fa-file-pdf me-1"></i>Generate PDF Report
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function togglePdfDateMode() {
    const isCustom = document.getElementById('dateModeCustom').checked;
    const monthBox = document.getElementById('pdfMonthSelectionBox');
    const customBox = document.getElementById('pdfCustomDateSelectionBox');
    const periodInput = document.getElementById('pdfPeriodInput');

    if (isCustom) {
        monthBox.classList.add('d-none');
        customBox.classList.remove('d-none');
        if (periodInput) periodInput.disabled = true;
    } else {
        monthBox.classList.remove('d-none');
        customBox.classList.add('d-none');
        if (periodInput) periodInput.disabled = false;
    }
}
</script>

@endsection
