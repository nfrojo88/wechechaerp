@extends('layouts.app')
@section('title', 'Attendance Management')
@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-0"><i class="fas fa-calendar-check me-2 text-primary"></i>Attendance Management</h1>
            <p class="text-muted mt-1 mb-1">Track and manage employee attendance records</p>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                    <i class="fas fa-calendar-day me-1"></i>Today: {{ now()->format('M d, Y') }} ({{ now()->format('l') }})
                </span>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                    <i class="fas fa-landmark me-1"></i>🇪🇹 {{ \App\Helpers\EthiopianCalendar::format(today(), 'am') }} ({{ \App\Helpers\EthiopianCalendar::format(today(), 'en') }})
                </span>
            </div>
        </div>
        <div class="btn-group flex-wrap" role="group">
            <form action="{{ route('attendance.zkteco-sync') }}" method="POST" class="d-inline">
                @csrf
                <input type="hidden" name="date" value="{{ request('date', today()->toDateString()) }}">
                <input type="hidden" name="redirect_to" value="attendance">
                <button type="submit" class="btn btn-info text-white fw-semibold" title="Synchronize biometric device punches">
                    <i class="fa-solid fa-rotate me-1"></i>Sync Biometrics
                </button>
            </form>
            <button type="button" class="btn btn-outline-danger fw-semibold" data-bs-toggle="modal" data-bs-target="#clearResyncModal" title="Clear processed attendance and freshly re-sync from raw biometric punch logs">
                <i class="fa-solid fa-broom me-1"></i>Clear &amp; Resync Fresh
            </button>
            <span class="badge bg-warning-subtle text-dark border border-warning-subtle d-inline-flex align-items-center gap-1 px-2 py-2" title="Attendance machine runs 5h ahead; ERP converts punches automatically (-5h)">
                <i class="fa-solid fa-clock-rotate-left text-warning-emphasis"></i><span class="fw-semibold">Device TZ: -5h</span>
            </span>
            <button type="button" class="btn btn-warning text-dark fw-bold" data-bs-toggle="modal" data-bs-target="#quickAttendanceModal" title="Mark or modify attendance for any employee">
                <i class="fa-solid fa-user-pen me-1"></i>Mark Attendance (መዝግብ)
            </button>
            <button type="button" class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#siteAttendanceModal">
                <i class="fa-solid fa-person-digging me-1"></i>Employee On Site (ወደ ሳይት የወጣ)
            </button>
            <a href="{{ route('attendance.site-deployments') }}" class="btn btn-outline-primary fw-semibold">
                <i class="fa-solid fa-list-check me-1"></i>Site Deployments Report (ሪፖርት)
            </a>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#workScheduleModal">
                <i class="fas fa-business-time me-1"></i>Work Schedule
            </button>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importDeviceModal">
                <i class="fas fa-file-excel me-1"></i>Bulk Upload (XLS)
            </button>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif



    <!-- Official Work Hours & Schedule Card (Working Time vs Non-Working/Break Time) -->
    <div class="card border-0 shadow-sm mb-3 bg-white rounded-3">
        <div class="card-body p-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary rounded-circle p-2"><i class="fas fa-business-time text-white"></i></span>
                    <div>
                        <h6 class="mb-0 fw-bold text-dark">
                            Work Hours &amp; Shift Schedule <span class="text-muted fw-normal small">(የሥራና የእረፍት ሰዓት ድልድል)</span>
                        </h6>
                        <small class="text-muted">
                            <span class="badge bg-primary-subtle text-primary border border-primary px-2 py-0.5 me-1">Mon – Fri: {{ $workSchedule['total_hours'] ?? '8.0' }} hrs/day</span>
                            <span class="badge bg-warning-subtle text-dark border border-warning px-2 py-0.5 me-1">Sat: {{ $workSchedule['sat_total_hours'] ?? '4.0' }} hrs (Morning Only)</span>
                            &bull; Linked with Ethiopian Time &amp; Calendar
                        </small>
                    </div>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#workScheduleModal">
                        <i class="fas fa-cog me-1"></i>Configure Schedule
                    </button>
                </div>
            </div>

            <!-- Schedule Badges / Panels -->
            <div class="row g-3">
                <!-- Left: Monday – Friday (Full Working Day) -->
                <div class="col-12 col-xl-8 border-end-xl">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge bg-dark text-white px-2.5 py-1 small fw-bold">
                            <i class="fa-solid fa-calendar-week me-1 text-info"></i> Monday – Friday (ሰኞ – ዓርብ - Full Day)
                        </span>
                        <span class="small text-muted fw-semibold">Expected: <strong>{{ $workSchedule['total_hours'] ?? '8.0' }} hrs</strong></span>
                    </div>
                    <div class="row g-2">
                        {{-- Morning Working Hours --}}
                        <div class="col-md-4">
                            <div class="p-2 rounded-3 border bg-success-subtle bg-opacity-50 border-success-subtle d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="badge bg-success text-white px-1 py-0 me-1" style="font-size: 0.65rem;">IN WORK</span>
                                        <span class="fw-bold small text-success-emphasis"><i class="fas fa-sun text-warning me-1"></i>Morning Shift</span>
                                    </div>
                                    <div class="small fw-bold text-dark mt-1 font-monospace">
                                        {{ \Carbon\Carbon::createFromFormat('H:i', $workSchedule['morning_in'])->format('h:i A') }} – {{ \Carbon\Carbon::createFromFormat('H:i', $workSchedule['morning_out'])->format('h:i A') }}
                                    </div>
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">
                                        🇪🇹 {{ \App\Helpers\EthiopianCalendar::toEthiopianTime($workSchedule['morning_in']) }}
                                    </small>
                                </div>
                            </div>
                        </div>

                        {{-- Lunch & Rest Break --}}
                        <div class="col-md-4">
                            <div class="p-2 rounded-3 border bg-warning-subtle bg-opacity-50 border-warning-subtle d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="badge bg-warning text-dark px-1 py-0 me-1" style="font-size: 0.65rem;">BREAK</span>
                                        <span class="fw-bold small text-warning-emphasis"><i class="fas fa-utensils text-warning me-1"></i>Lunch Break</span>
                                    </div>
                                    <div class="small fw-bold text-dark mt-1 font-monospace">
                                        {{ \Carbon\Carbon::createFromFormat('H:i', $workSchedule['break_start'])->format('h:i A') }} – {{ \Carbon\Carbon::createFromFormat('H:i', $workSchedule['break_end'])->format('h:i A') }}
                                    </div>
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">
                                        🇪🇹 {{ \App\Helpers\EthiopianCalendar::toEthiopianTime($workSchedule['break_start']) }}
                                    </small>
                                </div>
                            </div>
                        </div>

                        {{-- Afternoon Working Hours --}}
                        <div class="col-md-4">
                            <div class="p-2 rounded-3 border bg-primary-subtle bg-opacity-50 border-primary-subtle d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="badge bg-primary text-white px-1 py-0 me-1" style="font-size: 0.65rem;">IN WORK</span>
                                        <span class="fw-bold small text-primary-emphasis"><i class="fas fa-cloud-sun text-warning me-1"></i>Afternoon Shift</span>
                                    </div>
                                    <div class="small fw-bold text-dark mt-1 font-monospace">
                                        {{ \Carbon\Carbon::createFromFormat('H:i', $workSchedule['afternoon_in'])->format('h:i A') }} – {{ \Carbon\Carbon::createFromFormat('H:i', $workSchedule['afternoon_out'])->format('h:i A') }}
                                    </div>
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">
                                        🇪🇹 {{ \App\Helpers\EthiopianCalendar::toEthiopianTime($workSchedule['afternoon_in']) }}
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Saturday (Morning Session Only) -->
                <div class="col-12 col-xl-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge bg-warning text-dark px-2.5 py-1 small fw-bold border border-warning">
                            <i class="fa-solid fa-mug-saucer me-1"></i> Saturday (ቅዳሜ - Morning Only)
                        </span>
                        <span class="small text-muted fw-semibold">Expected: <strong>{{ $workSchedule['sat_total_hours'] ?? '4.0' }} hrs</strong></span>
                    </div>
                    <div class="row g-2">
                        {{-- Saturday Morning Shift --}}
                        <div class="col-12 col-sm-7">
                            <div class="p-2 rounded-3 border bg-success-subtle bg-opacity-50 border-success-subtle d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="badge bg-success text-white px-1 py-0 me-1" style="font-size: 0.65rem;">IN WORK</span>
                                        <span class="fw-bold small text-success-emphasis"><i class="fas fa-sun text-warning me-1"></i>Morning (ጠዋት)</span>
                                    </div>
                                    <div class="small fw-bold text-dark mt-1 font-monospace">
                                        {{ \Carbon\Carbon::createFromFormat('H:i', $workSchedule['sat_morning_in'] ?? '08:30')->format('h:i A') }} – {{ \Carbon\Carbon::createFromFormat('H:i', $workSchedule['sat_morning_out'] ?? '12:30')->format('h:i A') }}
                                    </div>
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">
                                        🇪🇹 {{ \App\Helpers\EthiopianCalendar::toEthiopianTime($workSchedule['sat_morning_in'] ?? '08:30') }}
                                    </small>
                                </div>
                            </div>
                        </div>

                        {{-- Saturday Afternoon Off --}}
                        <div class="col-12 col-sm-5">
                            <div class="p-2 rounded-3 border bg-light d-flex align-items-center justify-content-between h-100">
                                <div>
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="badge bg-secondary text-white px-1 py-0 me-1" style="font-size: 0.65rem;">OFF</span>
                                        <span class="fw-bold small text-muted"><i class="fa-solid fa-moon text-secondary me-1"></i>Afternoon</span>
                                    </div>
                                    <div class="small fw-bold text-muted mt-1">
                                        Off / Non-Working
                                    </div>
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">
                                        ከሰዓት እረፍት
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Clean Month, Date & Filter Toolbar --}}
    <div class="card border-0 shadow-sm mb-4 bg-white rounded-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('attendance.index') }}" id="attendanceFilterForm">
                <div class="row g-2 align-items-end">
                    
                    {{-- Select Month --}}
                    <div class="col-12 col-sm-6 col-lg-2">
                        <label class="form-label small fw-bold text-dark mb-1">
                            <i class="far fa-calendar text-primary me-1"></i>Select Month (ወር)
                        </label>
                        <select name="month" id="filterMonth" class="form-select" onchange="onMonthFilterChange(this.value)">
                            <option value="">All Months (ሁሉም ወራት)</option>
                            @foreach($availableMonths ?? [] as $m)
                                <option value="{{ $m['value'] }}" {{ request('month', $selectedMonth ?? '') === $m['value'] ? 'selected' : '' }}>
                                    {{ $m['label_en'] }} @if(!empty($m['label_et'])) ({{ $m['label_et'] }}) @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Select Specific Date --}}
                    <div class="col-12 col-sm-6 col-lg-2">
                        <label class="form-label small fw-bold text-dark mb-1">
                            <i class="far fa-calendar-check text-success me-1"></i>Select Date (ቀን)
                        </label>
                        <select name="date" id="filterDate" class="form-select">
                            <option value="">All Dates in Selected Period</option>
                            @foreach($availableDatesWithLabels ?? [] as $item)
                                <option value="{{ $item['date'] }}" 
                                        data-month="{{ $item['month'] }}"
                                        {{ request('date', $selectedDate ?? '') === $item['date'] ? 'selected' : '' }}>
                                    {{ $item['full_label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Staff Scope Filter --}}
                    <div class="col-6 col-sm-6 col-lg-2">
                        <label class="form-label small fw-bold text-dark mb-1">
                            <i class="fas fa-building text-primary me-1"></i>Staff Scope
                        </label>
                        <select name="staff_type" class="form-select" onchange="this.form.submit()">
                            <option value="office" {{ ($staffType ?? 'office') === 'office' ? 'selected' : '' }}>🏢 Head Office Only</option>
                            <option value="all" {{ ($staffType ?? '') === 'all' ? 'selected' : '' }}>👥 All Employees</option>
                            <option value="site_driver_remote" {{ ($staffType ?? '') === 'site_driver_remote' ? 'selected' : '' }}>🚜 Site, Driver &amp; Remote</option>
                        </select>
                    </div>

                    {{-- Source Filter --}}
                    <div class="col-6 col-sm-6 col-lg-2">
                        <label class="form-label small fw-bold text-dark mb-1">
                            <i class="fas fa-fingerprint text-primary me-1"></i>Source (ምንጭ)
                        </label>
                        <select name="source" class="form-select">
                            <option value="">All Sources</option>
                            <option value="biometric" {{ request('source') === 'biometric' ? 'selected' : '' }}>⚡ Biometric (Synced)</option>
                            <option value="site" {{ request('source') === 'site' ? 'selected' : '' }}>🏗️ On-Site (ሳይት ላይ)</option>
                            <option value="manual" {{ request('source') === 'manual' ? 'selected' : '' }}>✍️ Manual Entry</option>
                        </select>
                    </div>

                    {{-- Status Filter --}}
                    <div class="col-6 col-sm-6 col-lg-1">
                        <label class="form-label small fw-bold text-dark mb-1">
                            <i class="fas fa-tag text-secondary me-1"></i>Status
                        </label>
                        <select name="status" class="form-select">
                            <option value="">All</option>
                            <option value="present" {{ request('status') === 'present' ? 'selected' : '' }}>Present</option>
                            <option value="S" {{ in_array(request('status'), ['S', 'site', 's']) ? 'selected' : '' }}>S - On Site</option>
                            <option value="half_day" {{ request('status') === 'half_day' ? 'selected' : '' }}>Half Day</option>
                            <option value="absent" {{ request('status') === 'absent' ? 'selected' : '' }}>Absent</option>
                            <option value="leave" {{ request('status') === 'leave' ? 'selected' : '' }}>Leave</option>
                        </select>
                    </div>

                    {{-- Employee / Device ID Search --}}
                    <div class="col-6 col-sm-6 col-lg-2">
                        <label class="form-label small fw-bold text-dark mb-1">
                            <i class="fas fa-search text-secondary me-1"></i>Employee / ID
                        </label>
                        <input type="text" name="employee" class="form-control" 
                               placeholder="Name or Device ID..." value="{{ request('employee') }}">
                    </div>

                    {{-- Filter Buttons --}}
                    <div class="col-6 col-sm-6 col-lg-1 d-flex gap-1">
                        <button type="submit" class="btn btn-primary flex-grow-1 shadow-xs" title="Filter Records">
                            <i class="fas fa-filter"></i>
                        </button>
                        <a href="{{ route('attendance.index') }}" class="btn btn-outline-secondary" title="Reset all filters">
                            <i class="fas fa-redo"></i>
                        </a>
                        <button type="button" class="btn btn-outline-info" data-bs-toggle="collapse" 
                                data-bs-target="#customDateRangeCollapse" title="Custom Date Range">
                            <i class="fas fa-calendar-alt"></i>
                        </button>
                    </div>
                </div>

                {{-- Optional Expandable Custom Date Range (Date From & To) --}}
                <div class="collapse {{ (request('date_from') || request('date_to')) ? 'show' : '' }} mt-3 pt-2 border-top" id="customDateRangeCollapse">
                    <div class="row g-2 align-items-center">
                        <div class="col-12 col-md-auto text-muted small fw-semibold">
                            <i class="fas fa-calendar-week me-1 text-info"></i>Custom Date Range:
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">From</span>
                                <input type="date" name="date_from" id="dateFromInput" class="form-control" value="{{ request('date_from') }}">
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">To</span>
                                <input type="date" name="date_to" id="dateToInput" class="form-control" value="{{ request('date_to') }}">
                            </div>
                        </div>
                        <div class="col-12 col-md-auto">
                            <small class="text-muted">(Overrides single month/date when dates are set)</small>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Staff Scope Banner --}}
    @if(($staffType ?? 'office') === 'office')
        <div class="alert bg-primary-subtle border border-primary-subtle d-flex align-items-center justify-content-between flex-wrap gap-2 p-2 px-3 rounded-3 mb-2 shadow-xs">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary text-white px-2 py-1 small">
                    <i class="fa-solid fa-building me-1"></i>Head Office Staff Active
                </span>
                <span class="text-dark small">
                    <strong>Site workers, drivers, and remote workers are excluded</strong> from this attendance register and absent penalty calculation.
                </span>
            </div>
            <div>
                <a href="{{ route('attendance.index', array_merge(request()->except('page'), ['staff_type' => 'all'])) }}" class="btn btn-xs btn-outline-primary py-0 px-2 fw-semibold" style="font-size: 0.75rem;">
                    <i class="fa-solid fa-users me-1"></i>View All Employees
                </a>
            </div>
        </div>
    @elseif(($staffType ?? '') === 'site_driver_remote')
        <div class="alert bg-warning-subtle border border-warning d-flex align-items-center justify-content-between flex-wrap gap-2 p-2 px-3 rounded-3 mb-2 shadow-xs">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-warning text-dark px-2 py-1 small">
                    <i class="fa-solid fa-truck-pickup me-1"></i>Site, Drivers &amp; Remote Staff View
                </span>
                <span class="text-dark small">
                    Currently showing field personnel, project-assigned staff, drivers, and remote workers.
                </span>
            </div>
            <div>
                <a href="{{ route('attendance.index', array_merge(request()->except('page'), ['staff_type' => 'office'])) }}" class="btn btn-xs btn-outline-dark py-0 px-2 fw-semibold" style="font-size: 0.75rem;">
                    <i class="fa-solid fa-building me-1"></i>Switch to Head Office Staff Only
                </a>
            </div>
        </div>
    @endif

    {{-- Attendance Policy Rule Banner (3 Late Days = 1 Absent Day Penalty) --}}
    <div class="alert bg-warning-subtle border border-warning d-flex align-items-center justify-content-between flex-wrap gap-2 p-2.5 px-3 rounded-3 mb-3 shadow-xs">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-warning text-dark px-2.5 py-1.5 fw-bold font-monospace shadow-xs">
                <i class="fa-solid fa-scale-balanced me-1"></i>POLICY RULE
            </span>
            <div>
                <span class="fw-bold text-dark small">Attendance Penalty: 3 Late Days = 1 Absent Day Penalty</span>
                <span class="text-muted small ms-1 d-none d-md-inline">&bull; Official check-in cutoff is 08:40 AM. Every 3 late arrivals incur 1 full day absence penalty.</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-white text-dark border font-monospace px-2.5 py-1 small shadow-xs">
                <i class="fa-regular fa-clock text-warning me-1"></i>Cutoff: 08:40 AM
            </span>
            <span class="badge bg-danger text-white font-monospace px-2.5 py-1 small shadow-xs">
                3 Late Days = 1 Day Absent
            </span>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4 g-3">
        {{-- Present Card --}}
        <div class="col-6 col-lg">
            <a href="{{ route('attendance.index', array_merge(request()->except('page'), ['status' => 'present'])) }}" class="text-decoration-none">
                <div class="card border-0 border-start border-4 border-success shadow-sm h-100 py-2 bg-white">
                    <div class="card-body py-2 px-3">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Present &bull; {{ $stats['title'] ?? 'Selected Period' }}
                        </div>
                        @if(!empty($stats['et_title']))
                        <small class="text-muted d-block mb-1 font-monospace" style="font-size: 0.72rem;">
                            🇪🇹 {{ $stats['et_title'] }}
                        </small>
                        @endif
                        <div class="h4 mb-0 font-weight-bold text-gray-800">
                            {{ $stats['present'] ?? 0 }}
                        </div>
                    </div>
                </div>
            </a>
        </div>

        {{-- On Site (S) Card --}}
        <div class="col-6 col-lg">
            <a href="{{ route('attendance.index', array_merge(request()->except('page'), ['status' => 'S'])) }}" class="text-decoration-none">
                <div class="card border-0 border-start border-4 shadow-sm h-100 py-2 bg-white" style="border-left-color: #6366f1 !important;">
                    <div class="card-body py-2 px-3">
                        <div class="text-xs font-weight-bold text-uppercase mb-1" style="color: #6366f1;">
                            On Site (S) &bull; {{ $stats['title'] ?? 'Selected Period' }}
                        </div>
                        @if(!empty($stats['et_title']))
                        <small class="text-muted d-block mb-1 font-monospace" style="font-size: 0.72rem;">
                            🇪🇹 {{ $stats['et_title'] }}
                        </small>
                        @endif
                        <div class="h4 mb-0 font-weight-bold" style="color: #6366f1;">
                            {{ $stats['site'] ?? 0 }}
                        </div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Late Days & 3 Late = 1 Absent Penalty Card --}}
        <div class="col-6 col-lg">
            <div class="card border-0 border-start border-4 shadow-sm h-100 py-2 bg-white" style="border-left-color: #d97706 !important;">
                <div class="card-body py-2 px-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="text-xs font-weight-bold text-uppercase mb-1" style="color: #d97706;">
                            Late &amp; Penalty (3 Late = 1 Absent)
                        </div>
                    </div>
                    @if(!empty($stats['et_title']))
                    <small class="text-muted d-block mb-1 font-monospace" style="font-size: 0.72rem;">
                        🇪🇹 {{ $stats['et_title'] }}
                    </small>
                    @endif
                    <div class="d-flex align-items-baseline gap-2">
                        <div class="h4 mb-0 font-weight-bold" style="color: #d97706;">
                            {{ $stats['late_days'] ?? 0 }}
                        </div>
                        <small class="text-muted fw-semibold" style="font-size:0.75rem;">Late Days</small>
                    </div>
                    <div class="mt-1">
                        @if(($stats['late_penalty_absents'] ?? 0) > 0)
                            <span class="badge bg-danger text-white font-monospace px-1.5 py-0.5" style="font-size: 0.68rem;" title="3 Late Days = 1 Absent Day Penalty">
                                <i class="fa-solid fa-scale-balanced me-0.5"></i>Penalty: {{ $stats['late_penalty_absents'] }} Day(s) Absent
                            </span>
                        @else
                            <span class="badge bg-warning-subtle text-dark border border-warning-subtle px-1.5 py-0.5" style="font-size: 0.68rem;">
                                {{ ($stats['late_days'] ?? 0) % 3 }}/3 toward 1 absent
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Effective Absent Card (Base + Late Penalty) --}}
        <div class="col-6 col-lg">
            <a href="{{ route('attendance.index', array_merge(request()->except('page'), ['status' => 'absent'])) }}" class="text-decoration-none">
                <div class="card border-0 border-start border-4 border-danger shadow-sm h-100 py-2 bg-white">
                    <div class="card-body py-2 px-3">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            Absent &bull; {{ $stats['title'] ?? 'Selected Period' }}
                        </div>
                        @if(!empty($stats['et_title']))
                        <small class="text-muted d-block mb-1 font-monospace" style="font-size: 0.72rem;">
                            🇪🇹 {{ $stats['et_title'] }}
                        </small>
                        @endif
                        <div class="d-flex align-items-baseline gap-2">
                            <div class="h4 mb-0 font-weight-bold text-danger">
                                {{ $stats['effective_absent'] ?? $stats['absent'] }}
                            </div>
                            <small class="text-muted fw-semibold" style="font-size:0.75rem;">Effective</small>
                        </div>
                        <div class="mt-1">
                            @if(($stats['late_penalty_absents'] ?? 0) > 0)
                                <small class="text-danger fw-semibold font-monospace" style="font-size: 0.68rem;">
                                    {{ $stats['absent'] }} Base + {{ $stats['late_penalty_absents'] }} Late Pen.
                                </small>
                            @else
                                <small class="text-muted" style="font-size: 0.68rem;">
                                    {{ $stats['absent'] ?? 0 }} unexcused
                                </small>
                            @endif
                        </div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Half Day Card --}}
        <div class="col-6 col-lg">
            <a href="{{ route('attendance.index', array_merge(request()->except('page'), ['status' => 'half_day'])) }}" class="text-decoration-none">
                <div class="card border-0 border-start border-4 border-warning shadow-sm h-100 py-2 bg-white">
                    <div class="card-body py-2 px-3">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Half Day &bull; {{ $stats['title'] ?? 'Selected Period' }}
                        </div>
                        @if(!empty($stats['et_title']))
                        <small class="text-muted d-block mb-1 font-monospace" style="font-size: 0.72rem;">
                            🇪🇹 {{ $stats['et_title'] }}
                        </small>
                        @endif
                        <div class="h4 mb-0 font-weight-bold text-gray-800">
                            {{ $stats['half_day'] ?? 0 }}
                        </div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Leave Card --}}
        <div class="col-6 col-lg">
            <a href="{{ route('attendance.index', array_merge(request()->except('page'), ['status' => 'leave'])) }}" class="text-decoration-none">
                <div class="card border-0 border-start border-4 border-info shadow-sm h-100 py-2 bg-white">
                    <div class="card-body py-2 px-3">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Leave &bull; {{ $stats['title'] ?? 'Selected Period' }}
                        </div>
                        @if(!empty($stats['et_title']))
                        <small class="text-muted d-block mb-1 font-monospace" style="font-size: 0.72rem;">
                            🇪🇹 {{ $stats['et_title'] }}
                        </small>
                        @endif
                        <div class="h4 mb-0 font-weight-bold text-gray-800">
                            {{ $stats['leave'] ?? 0 }}
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Attendance Records Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h6 class="mb-0 font-weight-bold">
                <i class="fas fa-table me-2"></i>Attendance Records
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width: 190px;">Employee & Device ID</th>
                            <th>Date</th>
                            <th class="text-center" style="min-width: 160px;">
                                <div class="text-primary fw-bold"><i class="fas fa-sun me-1"></i>Morning Session</div>
                                <div class="small text-muted fw-normal">Clock In &bull; Clock Out</div>
                            </th>
                            <th class="text-center" style="min-width: 160px;">
                                <div class="text-warning fw-bold"><i class="fas fa-cloud-sun me-1"></i>Afternoon Session</div>
                                <div class="small text-muted fw-normal">Clock In &bull; Clock Out</div>
                            </th>
                            <th class="text-center">Hours</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">OT (Hrs / Pay)</th>
                            <th>Source</th>
                            <th class="text-center">Approved</th>
                            <th class="text-center" style="min-width: 100px;">Action (ማስተካከያ)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($attendances as $a)
                        <tr>
                            <td>
                                <strong class="text-dark">{{ $a->employee->full_name ?? 'N/A' }}</strong>
                                <div class="d-flex align-items-center gap-1 mt-1 flex-wrap">
                                    <small class="text-muted font-monospace">{{ $a->employee->employee_code ?? 'EMP' }}</small>
                                    @php
                                        $empDevId = trim((string)($a->employee?->device_user_id ?? ''));
                                        $bioDevId = trim((string)($a->biometric_device_id ?? ''));
                                        $empCode  = trim((string)($a->employee?->employee_code ?? ''));
                                        
                                        // Only show Device ID if explicitly added to employee; never fallback to emp ID/code
                                        $devId = $empDevId !== '' ? $empDevId : (($bioDevId !== '' && $bioDevId !== $empCode) ? $bioDevId : null);
                                    @endphp
                                    @if($devId)
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle" title="ZKTeco Biometric Device User ID">
                                            <i class="fa-solid fa-fingerprint me-1"></i>Device ID: {{ $devId }}
                                        </span>
                                    @else
                                        <span class="badge bg-light text-muted border" title="No ZKTeco Device ID assigned">
                                            <i class="fa-solid fa-fingerprint me-1"></i>No Device ID
                                        </span>
                                    @endif
                                </div>
                                @if(!empty($a->notes))
                                    <div class="small mt-1">
                                        @if(str_contains($a->notes, 'On-Site'))
                                            <span class="badge bg-purple-subtle text-purple border" style="background:#f5f3ff; color:#6d28d9; border-color:#ddd6fe !important;">
                                                <i class="fa-solid fa-location-dot me-1"></i>{{ $a->notes }}
                                            </span>
                                        @else
                                            <span class="text-muted"><i class="far fa-comment-dots me-1"></i>{{ $a->notes }}</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                <a href="{{ route('attendance.index', ['date' => $a->attendance_date->format('Y-m-d')]) }}" 
                                   class="fw-bold text-primary text-decoration-none" title="Click to view all records for {{ $a->attendance_date->format('M d, Y') }}">
                                    <i class="far fa-calendar-alt me-1"></i>{{ $a->attendance_date->format('M d, Y') }}
                                </a>
                                <br><small class="text-muted">{{ $a->attendance_date->format('l') }}</small>
                                <div class="mt-1">
                                    <span class="badge bg-light text-dark border font-monospace px-1 py-0" style="font-size: 0.72rem;" title="Ethiopian Calendar">
                                        🇪🇹 {{ \App\Helpers\EthiopianCalendar::format($a->attendance_date, 'am') }}
                                    </span>
                                </div>
                            </td>
                            @php
                                $sched = \App\Helpers\EthiopianCalendar::getWorkSchedule();
                                $defMIn  = $sched['morning_in'] ?? '08:30';
                                $defMOut = $sched['morning_out'] ?? '12:30';
                                $defAIn  = $sched['afternoon_in'] ?? '13:30';
                                $defAOut = $sched['afternoon_out'] ?? '17:30';

                                $mIn  = $a->morning_in;
                                $mOut = $a->morning_out;
                                $aIn  = $a->afternoon_in;
                                $aOut = $a->afternoon_out;
                                $cIn  = $a->check_in;
                                $cOut = $a->check_out;

                                // Fallback: if session columns were not filled, use the shared BiometricPunchService logic
                                if (empty($mIn) && empty($mOut) && empty($aIn) && empty($aOut) && (!empty($cIn) || !empty($cOut))) {
                                    $calc = \App\Services\BiometricPunchService::calculateAttendanceRecord(
                                        array_filter([$cIn, $cOut]),
                                        $a->attendance_date?->toDateString(),
                                        (bool)$isSat
                                    );
                                    $mIn  = $calc['morning_in'];
                                    $mOut = $calc['morning_out'];
                                    $aIn  = $calc['afternoon_in'];
                                    $aOut = $calc['afternoon_out'];
                                }

                                $isSat = $a->attendance_date && $a->attendance_date->isSaturday();
                                $lateStatus = \App\Services\BiometricPunchService::getLateStatus($mIn ?: $cIn);

                                $fmt12 = function($val) {
                                    if (!$val) return null;
                                    try {
                                        return \Carbon\Carbon::parse($val)->format('h:i A');
                                    } catch (\Throwable $e) {
                                        return substr($val, 0, 5);
                                    }
                                };
                            @endphp
                            <td class="text-center">
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    @if($mIn)
                                        @if($lateStatus['is_late'])
                                            @php
                                                $empMonthlyLates = $monthlyLateCounts[$a->employee_id] ?? 1;
                                                $empPenaltyAbs   = intdiv($empMonthlyLates, 3);
                                                $empLateRem      = $empMonthlyLates % 3;
                                            @endphp
                                            <span class="badge bg-warning-subtle text-dark border border-warning-subtle px-2 py-1 font-monospace" 
                                                  title="Morning Clock In: {{ $fmt12($mIn) }} ({{ $lateStatus['label'] }} • Cutoff 08:40 AM) • Rule: 3 Late = 1 Absent (Total {{ $empMonthlyLates }} late days this month) • {{ \App\Helpers\EthiopianCalendar::toEthiopianTime($mIn) }}">
                                                <i class="fas fa-arrow-right me-1 text-warning"></i>{{ $fmt12($mIn) }}
                                                <span class="badge bg-danger text-white ms-1 px-1 py-0" style="font-size: 0.65rem;">+{{ $lateStatus['late_minutes'] }}m</span>
                                                @if($empPenaltyAbs > 0)
                                                    <span class="badge bg-danger text-white ms-1 px-1 py-0" style="font-size: 0.62rem;" title="Rule: 3 Late = 1 Absent. Employee has {{ $empMonthlyLates }} late days this month = {{ $empPenaltyAbs }} day(s) absent penalty!">
                                                        <i class="fa-solid fa-scale-balanced me-0.5"></i>{{ $empPenaltyAbs }}d Pen
                                                    </span>
                                                @else
                                                    <span class="badge bg-warning text-dark border ms-1 px-1 py-0" style="font-size: 0.62rem;" title="Rule: 3 Late = 1 Absent. Late day {{ $empMonthlyLates }} of 3 ({{ 3 - $empMonthlyLates }} more = 1 absent penalty)">
                                                        {{ $empMonthlyLates }}/3
                                                    </span>
                                                @endif
                                            </span>
                                        @else
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 font-monospace" 
                                                  title="Morning Clock In: {{ $fmt12($mIn) }} (On time) • {{ \App\Helpers\EthiopianCalendar::toEthiopianTime($mIn) }}">
                                                <i class="fas fa-arrow-right me-1"></i>{{ $fmt12($mIn) }}
                                            </span>
                                        @endif
                                    @else
                                        <span class="badge bg-light text-muted border px-2 py-1" title="No Morning Clock In">—</span>
                                    @endif
                                    <span class="text-muted small">&bull;</span>
                                    @if($mOut)
                                        <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1 font-monospace" 
                                              title="Morning Clock Out: {{ $fmt12($mOut) }} (24h: {{ substr($mOut, 0, 5) }}) • {{ \App\Helpers\EthiopianCalendar::toEthiopianTime($mOut) }}">
                                            <i class="fas fa-arrow-left me-1"></i>{{ $fmt12($mOut) }}
                                        </span>
                                    @else
                                        <span class="badge bg-light text-muted border px-2 py-1" title="No Morning Clock Out">—</span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-center">
                                @if($isSat && empty($aIn) && empty($aOut))
                                    <span class="badge bg-light text-muted border px-2 py-1" style="font-size: 0.72rem;" title="Saturday Afternoon is non-working by official policy">
                                        <i class="fa-solid fa-mug-saucer me-1 text-warning"></i>Off (እረፍት)
                                    </span>
                                @else
                                    <div class="d-flex justify-content-center align-items-center gap-1">
                                        @if($aIn)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 font-monospace" 
                                                  title="Afternoon Clock In: {{ $fmt12($aIn) }} (24h: {{ substr($aIn, 0, 5) }}) • {{ \App\Helpers\EthiopianCalendar::toEthiopianTime($aIn) }}">
                                                <i class="fas fa-arrow-right me-1"></i>{{ $fmt12($aIn) }}
                                            </span>
                                        @else
                                            <span class="badge bg-light text-muted border px-2 py-1" title="No Afternoon Clock In">—</span>
                                        @endif
                                        <span class="text-muted small">&bull;</span>
                                        @if($aOut)
                                            <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1 font-monospace" 
                                                  title="Afternoon Clock Out: {{ $fmt12($aOut) }} (24h: {{ substr($aOut, 0, 5) }}) • {{ \App\Helpers\EthiopianCalendar::toEthiopianTime($aOut) }}">
                                                <i class="fas fa-arrow-left me-1"></i>{{ $fmt12($aOut) }}
                                            </span>
                                        @else
                                            <span class="badge bg-light text-muted border px-2 py-1" title="No Afternoon Clock Out">—</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($a->hours_worked > 0)
                                    <strong class="text-dark">{{ number_format($a->hours_worked, 1) }}h</strong>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @php 
                                    $statusColors = [
                                        'present' => 'success',
                                        'absent' => 'danger',
                                        'half_day' => 'warning',
                                        'leave' => 'info',
                                        'holiday' => 'secondary',
                                        'weekend' => 'light'
                                    ];
                                    $isSite = in_array(strtoupper((string)$a->status), ['S', 'SITE', 'ON_SITE']) 
                                        || str_contains((string)($a->notes ?? ''), 'On-Site')
                                        || $a->source === 'site_dispatch'
                                        || (method_exists($a, 'isOnSite') && $a->isOnSite());
                                    $rowStatus = $a->status;
                                    if (!$isSite) {
                                        // Under company policy, employees with punches or worked hours are Present (morning session is not required)
                                        if ($rowStatus === 'half_day' && ($a->hours_worked > 0 || $a->morning_in || $a->afternoon_in || $a->check_in)) {
                                            $rowStatus = 'present';
                                        } elseif ($a->attendance_date && $a->attendance_date->isSaturday() && $rowStatus === 'half_day' && ($a->morning_in || $a->hours_worked >= 2.0)) {
                                            $rowStatus = 'present';
                                        }
                                    }
                                @endphp
                                @if($isSite)
                                    <span class="badge text-white px-2 py-1 shadow-xs fw-bold" style="background-color: #6366f1;" title="{{ $a->notes }}">
                                        <span class="badge bg-white text-dark me-1" style="font-size:0.75rem;">S</span> On Site (ሳይት ላይ)
                                    </span>
                                @elseif($rowStatus === 'absent')
                                    <span class="badge bg-danger text-white px-2 py-1 shadow-xs fw-bold">
                                        <i class="fa-solid fa-user-xmark me-1"></i>Absent (ቀሪ)
                                    </span>
                                @elseif($rowStatus === 'leave')
                                    <span class="badge bg-info text-white px-2 py-1 shadow-xs fw-bold">
                                        <i class="fa-solid fa-plane-departure me-1"></i>Leave (ፈቃድ)
                                    </span>
                                @else
                                    <span class="badge bg-{{ $statusColors[$rowStatus] ?? 'secondary' }}">
                                        {{ ucfirst(str_replace('_', ' ', $rowStatus)) }}
                                    </span>
                                    @if($lateStatus['is_late'])
                                        <div class="mt-1">
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-1 py-0 font-monospace" style="font-size: 0.68rem;" title="Late arrival by {{ $lateStatus['late_minutes'] }} minutes (Cutoff: 08:40 AM)">
                                                <i class="fa-regular fa-clock me-1"></i>{{ $lateStatus['label'] }}
                                            </span>
                                        </div>
                                    @endif
                                @endif
                            </td>
                            <td class="text-center">
                                @if(($a->overtime_hours ?? 0) > 0 || ($a->overtime_pay ?? 0) > 0)
                                    <span class="badge bg-warning text-dark">{{ $a->overtime_hours ?? 0 }}h</span>
                                    @if(($a->overtime_pay ?? 0) > 0)
                                        <div class="small fw-bold text-success mt-1">{{ number_format($a->overtime_pay, 2) }} ETB</div>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if(str_contains($a->notes ?? '', 'On-Site') || in_array(strtoupper((string)$a->status), ['S', 'SITE', 'ON_SITE']) || $a->source === 'site_dispatch')
                                    <span class="badge bg-purple-subtle text-purple border" style="background: #f5f3ff; color: #6d28d9; border-color: #ddd6fe !important;">
                                        <i class="fa-solid fa-location-dot me-1"></i>Site Assigned
                                    </span>
                                @else
                                    <span class="badge bg-light text-secondary border">{{ ucfirst(str_replace('_', ' ', $a->source)) }}</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($a->is_approved)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="fas fa-check me-1"></i>Approved
                                    </span>
                                @else
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                        <i class="fas fa-hourglass-half me-1"></i>Pending
                                    </span>
                                @endif
                            </td>
                            <td class="text-center text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-primary px-2.5 py-1 fw-semibold shadow-xs" 
                                        data-bs-toggle="modal" data-bs-target="#editAttendanceModal{{ $a->id }}" title="Modify attendance status, times, and hours">
                                    <i class="fa-solid fa-pen-to-square me-1"></i>Modify
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="fas fa-inbox fa-3x mb-3 opacity-50"></i>
                                <p class="mb-0">No attendance records found.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($attendances->hasPages())
        <div class="card-footer bg-light">
            {{ $attendances->links() }}
        </div>
        @endif
    </div>
</div>

{{-- Modals for Modifying Attendance Records --}}
@foreach($attendances as $a)
@php
    $empDevId = trim((string)($a->employee?->device_user_id ?? ''));
    $bioDevId = trim((string)($a->biometric_device_id ?? ''));
    $empCode  = trim((string)($a->employee?->employee_code ?? ''));
    $modalDevId = $empDevId !== '' ? $empDevId : (($bioDevId !== '' && $bioDevId !== $empCode) ? $bioDevId : 'None');
    $isSiteModal = in_array(strtoupper((string)$a->status), ['S', 'SITE', 'ON_SITE']) 
        || str_contains((string)($a->notes ?? ''), 'On-Site') 
        || $a->source === 'site_dispatch';
    $currentStatus = $isSiteModal ? 'S' : $a->status;
@endphp
<div class="modal fade" id="editAttendanceModal{{ $a->id }}" tabindex="-1" aria-labelledby="editAttendanceModalLabel{{ $a->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary rounded-circle p-2"><i class="fa-solid fa-user-pen text-white"></i></span>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold" id="editAttendanceModalLabel{{ $a->id }}">
                            Modify Attendance (የአቴንዳንስ ማስተካከያ)
                        </h5>
                        <small class="text-white-50">{{ $a->employee?->full_name }} &bull; {{ $a->employee?->employee_code }}</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('attendance.update-record', $a->id) }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    {{-- Employee & Date Summary Banner --}}
                    <div class="bg-light p-3 rounded-3 mb-3 border">
                        <div class="row g-2 align-items-center">
                            <div class="col-sm-6">
                                <small class="text-muted d-block">Employee Name &amp; ID</small>
                                <strong class="text-dark fs-6">{{ $a->employee?->full_name }}</strong>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1 font-monospace">
                                    {{ $a->employee?->employee_code }}
                                </span>
                                @if(!empty($a->employee?->device_user_id))
                                    <span class="badge bg-info-subtle text-info border border-info-subtle ms-1 font-monospace">
                                        <i class="fa-solid fa-fingerprint me-0.5"></i>Device: {{ $a->employee->device_user_id }}
                                    </span>
                                @else
                                    <span class="badge bg-warning-subtle text-dark border border-warning-subtle ms-1 font-monospace">
                                        <i class="fa-solid fa-triangle-exclamation me-0.5"></i>No Device ID
                                    </span>
                                @endif
                            </div>
                            <div class="col-sm-6 text-sm-end">
                                <small class="text-muted d-block">Attendance Date</small>
                                <strong class="text-primary fs-6">{{ $a->attendance_date?->format('M d, Y (l)') }}</strong>
                                <div class="small text-muted font-monospace mt-0.5">
                                    🇪🇹 {{ \App\Helpers\EthiopianCalendar::format($a->attendance_date, 'am') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        {{-- Status Selection --}}
                        <div class="col-12 col-md-5">
                            <label class="form-label fw-bold text-dark mb-1">
                                <i class="fa-solid fa-tag me-1 text-primary"></i>Attendance Status <span class="text-danger">*</span>
                            </label>
                            <select name="status" id="statusSelect{{ $a->id }}" class="form-select form-select-lg fw-semibold" onchange="onStatusChange{{ $a->id }}(this.value)" required>
                                <option value="present" {{ $currentStatus === 'present' ? 'selected' : '' }}>✅ Present (ተገኝቷል - Office)</option>
                                <option value="S" {{ $currentStatus === 'S' ? 'selected' : '' }}>🏗️ S - On Site (ሳይት ላይ የወጣ - Site Duty)</option>
                                <option value="half_day" {{ $currentStatus === 'half_day' ? 'selected' : '' }}>⏳ Half Day (ግማሽ ቀን - 4.0 Hours)</option>
                                <option value="absent" {{ $currentStatus === 'absent' ? 'selected' : '' }}>❌ Absent (ቀሪ - 0.0 Hours)</option>
                                <option value="leave" {{ $currentStatus === 'leave' ? 'selected' : '' }}>🌴 Leave (ፈቃድ ላይ)</option>
                                <option value="holiday" {{ $currentStatus === 'holiday' ? 'selected' : '' }}>🎉 Holiday (የበዓል ቀን)</option>
                            </select>
                            <small class="text-muted">Selecting <strong>Absent</strong> sets worked hours to 0.</small>
                        </div>

                        {{-- Biometric Device ID (Editable) --}}
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-bold text-dark mb-1">
                                <i class="fa-solid fa-fingerprint me-1 text-primary"></i>Device ID (የመሳሪያ ID)
                            </label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-white text-primary">
                                    <i class="fa-solid fa-id-badge"></i>
                                </span>
                                <input type="text" name="device_user_id" id="devUserId{{ $a->id }}" 
                                       class="form-control form-control-lg font-monospace fw-bold" 
                                       value="{{ $a->employee?->device_user_id ?? ($a->biometric_device_id ?? '') }}" 
                                       placeholder="e.g. 16" title="Change or assign employee ZKTeco Device User ID">
                            </div>
                            <small class="text-muted" style="font-size: 0.72rem;">Update employee device ID</small>
                        </div>

                        {{-- Hours Worked --}}
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold text-dark mb-1">
                                <i class="fa-solid fa-business-time me-1 text-primary"></i>Total Hours (የተሠራ ሰዓት)
                            </label>
                            <input type="number" step="0.1" min="0" max="24" name="hours_worked" id="hoursWorked{{ $a->id }}" 
                                   class="form-control form-control-lg font-monospace fw-bold" 
                                   value="{{ $a->hours_worked ?? 0 }}" placeholder="e.g. 8.0">
                            <div class="d-flex gap-1 mt-1">
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size: 0.72rem;" onclick="setHours{{ $a->id }}(8.0)">8.0h</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size: 0.72rem;" onclick="setHours{{ $a->id }}(4.0)">4.0h</button>
                                <button type="button" class="btn btn-xs btn-outline-danger py-0 px-2" style="font-size: 0.72rem;" onclick="setHours{{ $a->id }}(0.0)">0.0h</button>
                            </div>
                        </div>

                        {{-- Clock Times Block (Morning & Afternoon Sessions) --}}
                        <div class="col-12" id="clockTimesSection{{ $a->id }}">
                            <div class="card border border-primary-subtle bg-light-subtle rounded-3">
                                <div class="card-body p-3">
                                    <div class="fw-bold small text-dark mb-2 d-flex align-items-center justify-content-between">
                                        <span><i class="fa-regular fa-clock me-1 text-primary"></i>Session Clock Times (መግቢያ እና መውጫ ሰዓቶች)</span>
                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" onclick="fillStandardTimes{{ $a->id }}()">
                                            <i class="fa-solid fa-wand-magic-sparkles me-1"></i>Fill Standard Schedule Times
                                        </button>
                                    </div>
                                    <div class="row g-2">
                                        {{-- Morning In --}}
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small mb-1 text-muted">☀️ Morning In</label>
                                            <input type="time" name="morning_in" id="morningIn{{ $a->id }}" class="form-control form-control-sm font-monospace" 
                                                   value="{{ $a->morning_in ? substr($a->morning_in, 0, 5) : '' }}">
                                        </div>
                                        {{-- Morning Out --}}
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small mb-1 text-muted">☀️ Morning Out</label>
                                            <input type="time" name="morning_out" id="morningOut{{ $a->id }}" class="form-control form-control-sm font-monospace" 
                                                   value="{{ $a->morning_out ? substr($a->morning_out, 0, 5) : '' }}">
                                        </div>
                                        {{-- Afternoon In --}}
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small mb-1 text-muted">🌤️ Afternoon In</label>
                                            <input type="time" name="afternoon_in" id="afternoonIn{{ $a->id }}" class="form-control form-control-sm font-monospace" 
                                                   value="{{ $a->afternoon_in ? substr($a->afternoon_in, 0, 5) : '' }}">
                                        </div>
                                        {{-- Afternoon Out --}}
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small mb-1 text-muted">🌤️ Afternoon Out</label>
                                            <input type="time" name="afternoon_out" id="afternoonOut{{ $a->id }}" class="form-control form-control-sm font-monospace" 
                                                   value="{{ $a->afternoon_out ? substr($a->afternoon_out, 0, 5) : '' }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Overtime Details --}}
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-dark mb-1">
                                <i class="fa-solid fa-clock-rotate-left text-warning me-1"></i>Overtime Hours
                            </label>
                            <input type="number" step="0.5" min="0" max="24" name="overtime_hours" class="form-control" 
                                   value="{{ $a->overtime_hours ?? 0 }}" placeholder="0.0">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-dark mb-1">
                                <i class="fa-solid fa-percent text-warning me-1"></i>Overtime Type
                            </label>
                            <select name="overtime_type" class="form-select">
                                <option value="none" {{ ($a->overtime_type ?? 'none') === 'none' ? 'selected' : '' }}>No Overtime</option>
                                <option value="holiday" {{ ($a->overtime_type ?? '') === 'holiday' ? 'selected' : '' }}>Holiday (×2.5)</option>
                                <option value="rest_day" {{ ($a->overtime_type ?? '') === 'rest_day' ? 'selected' : '' }}>Rest Day / Sunday (×2.0)</option>
                                <option value="night_12_4" {{ ($a->overtime_type ?? '') === 'night_12_4' ? 'selected' : '' }}>Night 12AM–4AM (×1.5)</option>
                                <option value="night_4_12" {{ ($a->overtime_type ?? '') === 'night_4_12' ? 'selected' : '' }}>Night 4PM–12AM (×1.75)</option>
                            </select>
                        </div>

                        {{-- Remarks / Notes --}}
                        <div class="col-12">
                            <label class="form-label small fw-bold text-dark mb-1">
                                <i class="fa-regular fa-comment-dots text-secondary me-1"></i>Reason / Notes (ምክንያት ወይም ማስታወሻ)
                            </label>
                            <input type="text" name="notes" class="form-control" value="{{ $a->notes }}" 
                                   placeholder="e.g. Authorized by Manager, Sick leave, Client meeting, Biometric missed punch...">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-3">
                        <i class="fa-solid fa-floppy-disk me-1"></i>Save Changes (አዘምን)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function onStatusChange{{ $a->id }}(status) {
    const hoursEl = document.getElementById('hoursWorked{{ $a->id }}');
    if (status === 'absent') {
        if (hoursEl) hoursEl.value = '0.0';
        document.getElementById('morningIn{{ $a->id }}').value = '';
        document.getElementById('morningOut{{ $a->id }}').value = '';
        document.getElementById('afternoonIn{{ $a->id }}').value = '';
        document.getElementById('afternoonOut{{ $a->id }}').value = '';
    } else if (status === 'half_day') {
        if (hoursEl && (!hoursEl.value || hoursEl.value === '0.0' || hoursEl.value === '0')) hoursEl.value = '4.0';
    } else if (status === 'present' || status === 'S') {
        if (hoursEl && (!hoursEl.value || hoursEl.value === '0.0' || hoursEl.value === '0')) hoursEl.value = '8.0';
    }
}
function setHours{{ $a->id }}(val) {
    const hoursEl = document.getElementById('hoursWorked{{ $a->id }}');
    if (hoursEl) hoursEl.value = val.toFixed(1);
    if (val === 0.0) {
        const sel = document.getElementById('statusSelect{{ $a->id }}');
        if (sel) sel.value = 'absent';
    }
}
function fillStandardTimes{{ $a->id }}() {
    document.getElementById('morningIn{{ $a->id }}').value = '08:30';
    document.getElementById('morningOut{{ $a->id }}').value = '12:30';
    document.getElementById('afternoonIn{{ $a->id }}').value = '13:30';
    document.getElementById('afternoonOut{{ $a->id }}').value = '17:30';
    const hoursEl = document.getElementById('hoursWorked{{ $a->id }}');
    if (hoursEl) hoursEl.value = '8.0';
    const sel = document.getElementById('statusSelect{{ $a->id }}');
    if (sel && sel.value === 'absent') sel.value = 'present';
}
</script>
@endforeach

{{-- Quick Create / Mark Attendance Modal --}}
<div class="modal fade" id="quickAttendanceModal" tabindex="-1" aria-labelledby="quickAttendanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-white text-primary rounded-circle p-2"><i class="fa-solid fa-user-plus"></i></span>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold" id="quickAttendanceModalLabel">
                            Mark / Add Attendance Record (ፈጣን ምዝገባ)
                        </h5>
                        <small class="text-white-50">Create or modify attendance for any employee</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('attendance.quick-create-or-update') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold text-dark mb-1">
                                <i class="fa-solid fa-user me-1 text-primary"></i>Employee (ሠራተኛ) <span class="text-danger">*</span>
                            </label>
                            <select name="employee_id" class="form-select" required>
                                <option value="">-- Select Employee --</option>
                                @foreach($allEmployees ?? [] as $emp)
                                    <option value="{{ $emp->id }}">
                                        {{ $emp->full_name }} ({{ $emp->employee_code }})
                                        @if($emp->device_user_id) [Device: {{ $emp->device_user_id }}] @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold text-dark mb-1">
                                <i class="fa-regular fa-calendar-check me-1 text-primary"></i>Attendance Date (ቀን) <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="attendance_date" class="form-control" value="{{ request('date', $statsDate ?? today()->toDateString()) }}" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold text-dark mb-1">
                                <i class="fa-solid fa-tag me-1 text-primary"></i>Status (ሁኔታ) <span class="text-danger">*</span>
                            </label>
                            <select name="status" class="form-select fw-semibold" required>
                                <option value="present">✅ Present (ተገኝቷል - Office)</option>
                                <option value="S">🏗️ S - On Site (ሳይት ላይ የወጣ - Site Duty)</option>
                                <option value="half_day">⏳ Half Day (ግማሽ ቀን - 4.0h)</option>
                                <option value="absent">❌ Absent (ቀሪ - 0.0h)</option>
                                <option value="leave">🌴 Leave (ፈቃድ ላይ)</option>
                                <option value="holiday">🎉 Holiday (የበዓል ቀን)</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold text-dark mb-1">
                                <i class="fa-solid fa-fingerprint me-1 text-primary"></i>Device ID (የመሳሪያ ID)
                            </label>
                            <input type="text" name="device_user_id" class="form-control font-monospace" placeholder="e.g. 16">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold text-dark mb-1">
                                <i class="fa-solid fa-business-time me-1 text-primary"></i>Hours Worked (ሰዓት)
                            </label>
                            <input type="number" step="0.1" min="0" max="24" name="hours_worked" class="form-control font-monospace" value="8.0">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small text-muted mb-1">☀️ Morning In</label>
                            <input type="time" name="morning_in" class="form-control form-control-sm font-monospace" value="08:30">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small text-muted mb-1">☀️ Morning Out</label>
                            <input type="time" name="morning_out" class="form-control form-control-sm font-monospace" value="12:30">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small text-muted mb-1">🌤️ Afternoon In</label>
                            <input type="time" name="afternoon_in" class="form-control form-control-sm font-monospace" value="13:30">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small text-muted mb-1">🌤️ Afternoon Out</label>
                            <input type="time" name="afternoon_out" class="form-control form-control-sm font-monospace" value="17:30">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-dark mb-1">
                                <i class="fa-regular fa-comment-dots text-secondary me-1"></i>Notes / Reason
                            </label>
                            <input type="text" name="notes" class="form-control" placeholder="e.g. Dispatched to Bole site, Sick leave, Manual entry...">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-3">
                        <i class="fa-solid fa-check me-1"></i>Save Record (መዝግብ)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Device Import Modal -->
<div class="modal fade" id="importDeviceModal" tabindex="-1" aria-labelledby="importDeviceModalLabel">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-gradient text-white" style="background: linear-gradient(135deg, #1a73e8, #0f4fa8);">
                <h5 class="modal-title" id="importDeviceModalLabel">
                    <i class="fas fa-file-import me-2"></i>Import Attendance from Biometric Device
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('attendance.importXls') }}" method="POST" enctype="multipart/form-data" id="importDeviceForm">
                @csrf
                <div class="modal-body p-4">

                    @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-times-circle me-2"></i>{{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    @endif
                    @if(session('warning'))
                    <div class="alert alert-warning alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i>{{ session('warning') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    @endif

                    {{-- File Upload Area --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-upload text-primary me-1"></i>
                            Select Attendance Export File <span class="text-danger">*</span>
                        </label>
                        <div class="upload-area border-2 border-dashed rounded-3 p-4 text-center position-relative"
                             id="uploadDropZone"
                             style="border-color: #1a73e8; background: #f0f6ff; cursor: pointer; transition: all 0.3s;">
                            <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3 d-block"></i>
                            <p class="mb-1 fw-semibold text-dark">Drag & drop your file here, or <span class="text-primary">click to browse</span></p>
                            <p class="text-muted small mb-0">Supports: <strong>.xls</strong> (biometric export), <strong>.xlsx</strong>, <strong>.csv</strong> — Max 10MB</p>
                            <input type="file" name="file" id="attendanceFile" accept=".xls,.xlsx,.csv"
                                   class="position-absolute top-0 start-0 w-100 h-100 opacity-0" style="cursor: pointer;" required>
                        </div>
                        <div id="fileNameDisplay" class="mt-2 d-none align-items-center justify-content-between p-2 rounded bg-light border border-success-subtle shadow-xs">
                            <div class="text-success small fw-semibold text-truncate me-2">
                                <i class="fas fa-check-circle me-1"></i><span id="fileNameText"></span>
                            </div>
                            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 flex-shrink-0" id="clearFileBtn" style="font-size: 0.75rem;" title="Clear selected file">
                                <i class="fas fa-times me-1"></i>Clear File
                            </button>
                        </div>
                    </div>

                    {{-- Format Info --}}
                    <div class="accordion" id="formatAccordion">
                        <div class="accordion-item border-0 shadow-sm rounded-3 mb-2">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed rounded-3 fw-semibold" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#collapseFormat">
                                    <i class="fas fa-table text-info me-2"></i>Expected File Format & Column Mapping
                                </button>
                            </h2>
                            <div id="collapseFormat" class="accordion-collapse collapse" data-bs-parent="#formatAccordion">
                                <div class="accordion-body pt-0">
                                    <p class="text-muted small">The system accepts the standard export from biometric attendance machines (Dahua, Hikvision, ZKTeco, etc.):</p>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered small mb-0 align-middle">
                                            <thead class="table-primary">
                                                <tr>
                                                    <th style="width: 25%;">Excel Column</th>
                                                    <th style="width: 35%;">System Field & Link Mode</th>
                                                    <th style="width: 40%;">Description / Example</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                 <tr class="table-light">
                                                     <td><strong class="text-primary"><i class="fa-solid fa-fingerprint me-1"></i>AC-No.</strong></td>
                                                     <td><strong class="text-dark">ZKTeco Device User ID</strong> <span class="badge bg-danger ms-1">Strict Match</span></td>
                                                     <td>Strict rule: must match <strong>ZKTeco Device User ID</strong> on the Employee Profile (e.g. <code>1</code>, <code>2</code>, <code>17</code>, <code>21</code>). If not assigned on the employee, records are strictly skipped.</td>
                                                 </tr>
                                                 <tr class="table-light">
                                                     <td><strong class="text-primary"><i class="far fa-calendar-alt me-1"></i>Date</strong></td>
                                                     <td><strong class="text-dark">Attendance Date</strong> <span class="badge bg-primary ms-1">Required</span></td>
                                                     <td>Punches are recorded for each specific day from this column (e.g. <code>9/2/2026</code>, <code>2026-09-02</code>).</td>
                                                 </tr>
                                                 <tr>
                                                     <td><code>Emp No.</code> / <code>Name</code></td>
                                                     <td>Device Reference / Name</td>
                                                     <td>Device log metadata. Strictly linked via the ZKTeco Device User ID.</td>
                                                 </tr>
                                                 <tr>
                                                     <td><code>Timetable</code></td>
                                                     <td>Session Type</td>
                                                     <td><code>Morning</code> / <code>Afternoon</code> (automatically combined into one daily record).</td>
                                                 </tr>
                                                 <tr>
                                                     <td><code>Clock In</code> / <code>Clock Out</code></td>
                                                     <td>Check-In / Check-Out Times</td>
                                                     <td>Actual punch times recorded by biometric scanner (e.g. <code>08:25</code>, <code>17:35</code>).</td>
                                                 </tr>
                                                 <tr>
                                                     <td><code>Late</code> / <code>OT Time</code></td>
                                                     <td>Late Minutes & Overtime</td>
                                                     <td>Calculates late arrival penalties and overtime pay based on basic salary.</td>
                                                 </tr>
                                             </tbody>
                                        </table>
                                    </div>
                                    <div class="alert alert-warning mt-3 mb-0 py-2 small">
                                         <i class="fas fa-shield-halved me-1"></i>
                                         <strong>Strict Device Matching:</strong> An employee <strong>must</strong> have their <strong>ZKTeco Device User ID</strong> set in their profile (e.g. 3, 21). If an employee does not have a Device User ID assigned, the system will <strong>not guess</strong> and will <strong>not show/import</strong> them in attendance!
                                     </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center mt-3 gap-2">
                        <a href="{{ route('attendance.downloadTemplate') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-download me-1"></i>Download CSV Template
                        </a>
                        <span class="text-muted small">Don't have a file? Download a sample template to see the expected format.</span>
                    </div>

                    @php
                        $latestAttDate = $lastAttendanceDate ?? ($availableDates->first() ?? null);
                        $formattedLastDate = $latestAttDate ? \Carbon\Carbon::parse($latestAttDate)->format('M d, Y (l)') : null;
                    @endphp

                    {{-- Incremental Import & History Protection Option --}}
                    <div class="mt-3 p-3 bg-light rounded-3 border border-primary-subtle shadow-xs">
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary rounded-circle p-1.5"><i class="fas fa-calendar-check text-white"></i></span>
                                <span class="fw-bold small text-dark">Incremental Attendance Sync</span>
                            </div>
                            @if($formattedLastDate)
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 small">
                                    <i class="fas fa-clock-rotate-left me-1"></i>Last Recorded Day: <strong>{{ $formattedLastDate }}</strong>
                                </span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary px-2 py-1 small">
                                    No prior records recorded yet
                                </span>
                            @endif
                        </div>

                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="after_last_day" value="1" id="afterLastDayToggle">
                            <label class="form-check-label fw-semibold text-dark small" for="afterLastDayToggle">
                                <i class="fas fa-forward-step text-primary me-1"></i>Only import records after last recorded day @if($formattedLastDate) ({{ \Carbon\Carbon::parse($latestAttDate)->format('M d, Y') }} onwards) @endif
                            </label>
                        </div>
                        <div class="text-muted small ps-4" style="font-size: 0.8rem;">
                            <i class="fas fa-shield-halved text-success me-1"></i>
                            <strong>Non-Destructive &amp; Smart Merging:</strong> All records in the file are safely imported and merged without wiping history. Toggle on only if you specifically want to skip older completed dates.
                        </div>
                    </div>

                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-success px-4" id="importSubmitBtn">
                        <i class="fas fa-file-import me-1"></i>Import Attendance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal: Clear Attendance & Resync Fresh from Biometrics --}}
<div class="modal fade" id="clearResyncModal" tabindex="-1" aria-labelledby="clearResyncModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger-subtle text-danger border-bottom">
                <h5 class="modal-title fw-bold" id="clearResyncModalLabel">
                    <i class="fa-solid fa-broom text-danger me-2"></i>Clear &amp; Resync Fresh from Biometrics
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('attendance.reset-and-resync') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="alert alert-warning mb-3 small">
                        <i class="fa-solid fa-triangle-exclamation me-1 fs-6"></i>
                        <strong>Clean Slate Sync:</strong> This will clear processed attendance records and immediately re-calculate everyone's attendance directly from the <strong>real raw biometric device punch logs</strong>.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark">Scope of Reset:</label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="scope" id="scopeAll" value="all" checked>
                            <label class="form-check-label fw-bold text-dark small" for="scopeAll">
                                <i class="fa-solid fa-layer-group text-primary me-1"></i>Clear ALL Dates &amp; Resync Everything Fresh
                            </label>
                            <small class="d-block text-muted ps-4" style="font-size:0.78rem;">Clears processed attendance and rebuilds every day cleanly from all raw biometric punch logs.</small>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="scope" id="scopeDate" value="date">
                            <label class="form-check-label fw-bold text-dark small" for="scopeDate">
                                <i class="fa-solid fa-calendar-day text-info me-1"></i>Clear &amp; Resync Specific Day Only
                            </label>
                            <div class="mt-2 ps-4">
                                <input type="date" name="date" class="form-control form-control-sm" value="{{ request('date', today()->toDateString()) }}">
                            </div>
                        </div>
                    </div>

                    {{-- Machine Timezone Conversion --}}
                    <div class="card border border-warning-subtle bg-warning bg-opacity-10 mb-3 rounded-3">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="form-label fw-bold small text-dark mb-0">
                                    <i class="fa-solid fa-clock-rotate-left text-warning-emphasis me-1"></i>Biometric Machine Timezone Conversion
                                </label>
                                <span class="badge bg-warning text-dark font-monospace fw-bold px-2 py-1">-5 Hours Offset</span>
                            </div>
                            <p class="text-muted mb-2" style="font-size:0.78rem;">
                                Machine clock is running 5 hours ahead:
                                <strong class="text-dark">Machine shows 05:07 PM &rarr; Local Ethiopia Time is 12:07 PM</strong>.
                                (e.g. Machine 01:40 PM &rarr; Morning Check-in 08:40 AM).
                            </p>
                            <div class="row g-2 align-items-center mb-2">
                                <div class="col-8">
                                    <select name="timezone_offset" id="timezoneOffsetSelect" class="form-select form-select-sm fw-semibold">
                                        <option value="-5" selected>-5 Hours (Convert 05:07 PM &rarr; 12:07 PM) [Recommended]</option>
                                        <option value="0">0 Hours (No conversion / Machine is on local time)</option>
                                        <option value="-4">-4 Hours</option>
                                        <option value="-3">-3 Hours</option>
                                        <option value="-6">-6 Hours</option>
                                    </select>
                                </div>
                                <div class="col-4 text-end">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2 small">
                                        <i class="fa-solid fa-check-double me-1"></i>Active Auto-Fix
                                    </span>
                                </div>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="shift_existing_logs" id="shiftExistingLogs" value="1" checked>
                                <label class="form-check-label text-dark small" for="shiftExistingLogs" style="font-size:0.78rem;">
                                    <strong>Convert &amp; shift existing punch logs:</strong> Safely preserve original machine timestamps in <code>raw_punch_time</code> and shift stored punches by -5h so Morning &amp; Afternoon sessions display real times.
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-success-subtle text-success-emphasis rounded-3 border border-success-subtle small">
                        <i class="fa-solid fa-shield-check text-success me-1"></i>
                        <strong>Safe:</strong> Raw biometric punch logs are permanently preserved on the machine &amp; database. Only the calculated daily attendance records are refreshed with 100% real punches.
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold shadow-xs">
                        <i class="fa-solid fa-arrows-rotate me-1"></i>Yes, Clear &amp; Resync Fresh Now
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Employee On-Site Attendance Modal (ወደ ሳይት የወጣ ሠራተኛ ምዝገባ) -->
<div class="modal fade" id="siteAttendanceModal" tabindex="-1" aria-labelledby="siteAttendanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-gradient text-white" style="background: linear-gradient(135deg, #4f46e5, #0d6efd);">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-person-digging fa-lg"></i>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="siteAttendanceModalLabel">
                            Send Employee to Site (ወደ ሳይት የወጣ ሠራተኛ ምዝገባ)
                        </h5>
                        <small class="text-white text-opacity-75">Status marked S &bull; Full pay credited &bull; Never deducted from payroll</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('attendance.record-site') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    {{-- Decision Maker Banner --}}
                    <div class="alert alert-light border border-primary-subtle d-flex align-items-center justify-content-between mb-3 py-2 px-3 rounded-3 shadow-xs">
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

                    <div class="alert alert-primary border border-primary-subtle d-flex align-items-center mb-4 py-2 px-3 rounded-3">
                        <i class="fa-solid fa-circle-info fa-lg me-3 text-primary"></i>
                        <div class="small">
                            <strong>Biometric-Free On-Site Policy:</strong> Field staff working at construction sites are not captured by the office fingerprint device. This record credits full working hours (<strong>{{ $workSchedule['total_hours'] ?? '8.0' }} hrs</strong>) with morning &amp; afternoon shifts assigned and sets status to <strong>S (On Site)</strong>. <strong>No payroll deductions will occur.</strong>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-muted text-uppercase">Employee(s) to Send (ወደ ሳይት የሚላኩ ሠራተኞች) <span class="text-danger">*</span></label>
                            <select name="employee_ids[]" class="form-select" multiple required style="min-height: 90px;">
                                @foreach($allEmployees ?? [] as $emp)
                                    <option value="{{ $emp->id }}">
                                        {{ $emp->full_name }} ({{ $emp->employee_code ?? 'EMP-'.$emp->id }}) - {{ $emp->department ?? 'Site Staff' }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Hold Ctrl/Cmd to select multiple employees.</small>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Start Date (የመነሻ ቀን) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="date" name="start_date" id="site_attendance_date" class="form-control" value="{{ request('date', today()->toDateString()) }}" required onchange="updateSiteEthiopianDate(this.value)">
                                <span class="input-group-text bg-white small font-monospace" id="site_et_date_preview">
                                    🇪🇹 {{ \App\Helpers\EthiopianCalendar::format(request('date', today()->toDateString()), 'am') }}
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">End Date (የመጨረሻ ቀን) <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" id="site_attendance_end_date" class="form-control" value="{{ request('date', today()->toDateString()) }}" required>
                            <small class="text-muted">Keep same as start date for a single day.</small>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label class="form-label fw-bold small text-muted text-uppercase">Construction Project / Job Site (የግንባታ ፕሮጀክት / ሳይት) <span class="text-danger">*</span></label>
                            <select name="project_id" class="form-select" required>
                                <option value="">-- Select Project --</option>
                                @foreach($projects ?? [] as $proj)
                                    <option value="{{ $proj->id }}">
                                        🏗️ {{ $proj->name }} @if(!empty($proj->code))({{ $proj->code }})@endif
                                    </option>
                                @endforeach
                            </select>
                            <input type="text" name="site_name" class="form-control form-control-sm mt-2" placeholder="Or enter specific site / location if not in list...">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold small text-muted text-uppercase">Credited Hours / Day</label>
                            <div class="input-group">
                                <input type="number" step="0.5" min="1" max="24" name="hours_worked" class="form-control" value="{{ $workSchedule['total_hours'] ?? '8.0' }}" required>
                                <span class="input-group-text">Hours</span>
                            </div>
                            <small class="text-muted">Standard daily policy: {{ $workSchedule['total_hours'] ?? '8.0' }} hours</small>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small text-muted text-uppercase">Assignment / Task Details (የሥራ ዝርዝር ማስታወሻ)</label>
                        <input type="text" name="task_notes" class="form-control" placeholder="e.g. Concrete casting supervision, Site inspection, Excavation survey...">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        <i class="fa-solid fa-check me-1"></i>Dispatch to Site (ይመዝገቡ)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Work Schedule & Shifts Modal -->
<!-- Work Schedule Modal -->
<div class="modal fade" id="workScheduleModal" tabindex="-1" aria-labelledby="workScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-gradient text-white py-3 px-4" style="background: linear-gradient(135deg, #1e293b, #0f172a);">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-white bg-opacity-10 p-2 text-warning">
                        <i class="fas fa-business-time fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="workScheduleModalLabel">
                            Configure Work Schedule &amp; Working Hours Policy
                        </h5>
                        <small class="text-white-50">Separate rules for Monday – Friday and Saturday Morning Session</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ url('/attendance') }}" method="POST">
                @csrf
                <input type="hidden" name="action" value="update_schedule">
                <input type="hidden" name="sat_work_mode" value="morning_only">
                <div class="modal-body p-4" style="max-height: calc(85vh - 130px); overflow-y: auto;">
                    <p class="text-muted small mb-3">
                        Define which times employees are actively in work and which times are non-working lunch/rest breaks. Fully synchronized with Ethiopian Time &amp; Calendar.
                    </p>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Shift Name / Title</label>
                            <input type="text" name="title" class="form-control form-control-sm" value="{{ $workSchedule['title'] ?? 'Standard Construction & Office Shift' }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Work Days Description</label>
                            <input type="text" name="work_days" class="form-control form-control-sm" value="{{ $workSchedule['work_days'] ?? 'Monday – Friday (Full Day) & Saturday (Morning Only)' }}" required>
                        </div>
                    </div>

                    <!-- ───────────────────────────────────────────────────────────── -->
                    <!-- 1. MONDAY – FRIDAY WORKING HOURS                              -->
                    <!-- ───────────────────────────────────────────────────────────── -->
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                            <span class="badge bg-dark text-white px-2 py-1"><i class="fa-solid fa-calendar-days text-info me-1"></i> Monday – Friday (ሰኞ – ዓርብ)</span>
                            <span class="text-muted small fw-normal">Full Working Day (8.0 Hours)</span>
                        </h6>
                    </div>

                    <!-- Monday – Friday: Morning Session -->
                    <div class="card border-success-subtle bg-success-subtle bg-opacity-10 mb-3 rounded-3 shadow-xs">
                        <div class="card-header bg-success text-white py-2 px-3 d-flex align-items-center justify-content-between">
                            <span><i class="fas fa-sun me-1 text-warning"></i><strong>Morning Session (ጠዋት የሥራ ሰዓት - In Work)</strong></span>
                            <span class="badge bg-white text-success px-2 py-0.5 small fw-bold">Active Work</span>
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Morning Clock-In Time (ጠዋት መግቢያ)</label>
                                    <input type="time" name="morning_in" class="form-control form-control-sm font-monospace" value="{{ $workSchedule['morning_in'] ?? '08:30' }}" required>
                                    <div class="form-text small text-muted">Standard entry time for employees.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Morning Clock-Out Time (ጠዋት መውጫ)</label>
                                    <input type="time" name="morning_out" class="form-control form-control-sm font-monospace" value="{{ $workSchedule['morning_out'] ?? '12:30' }}" required>
                                    <div class="form-text small text-muted">End of morning work period.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Monday – Friday: Lunch & Rest Break -->
                    <div class="card border-warning-subtle bg-warning-subtle bg-opacity-10 mb-3 rounded-3 shadow-xs">
                        <div class="card-header bg-warning text-dark py-2 px-3 d-flex align-items-center justify-content-between">
                            <span><i class="fas fa-utensils me-1"></i><strong>Lunch &amp; Rest Break (የምሳ እረፍት ሰዓት - Non-Working)</strong></span>
                            <span class="badge bg-dark text-warning px-2 py-0.5 small fw-bold">Don't Work</span>
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Break Starts (የምሳ እረፍት መጀመሪያ)</label>
                                    <input type="time" name="break_start" class="form-control form-control-sm font-monospace" value="{{ $workSchedule['break_start'] ?? '12:30' }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Break Ends (የምሳ እረፍት መጨረሻ)</label>
                                    <input type="time" name="break_end" class="form-control form-control-sm font-monospace" value="{{ $workSchedule['break_end'] ?? '13:30' }}" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Monday – Friday: Afternoon Session -->
                    <div class="card border-primary-subtle bg-primary-subtle bg-opacity-10 mb-4 rounded-3 shadow-xs">
                        <div class="card-header bg-primary text-white py-2 px-3 d-flex align-items-center justify-content-between">
                            <span><i class="fas fa-cloud-sun me-1 text-warning"></i><strong>Afternoon Session (ከሰዓት የሥራ ሰዓት - In Work)</strong></span>
                            <span class="badge bg-white text-primary px-2 py-0.5 small fw-bold">Active Work</span>
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Afternoon Clock-In Time (ከሰዓት መግቢያ)</label>
                                    <input type="time" name="afternoon_in" class="form-control form-control-sm font-monospace" value="{{ $workSchedule['afternoon_in'] ?? '13:30' }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Afternoon Clock-Out Time (ከሰዓት መውጫ)</label>
                                    <input type="time" name="afternoon_out" class="form-control form-control-sm font-monospace" value="{{ $workSchedule['afternoon_out'] ?? '17:30' }}" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ───────────────────────────────────────────────────────────── -->
                    <!-- 2. SATURDAY WORKING HOURS (MORNING ONLY)                      -->
                    <!-- ───────────────────────────────────────────────────────────── -->
                    <div class="card border-warning rounded-3 shadow-sm mb-2 overflow-hidden">
                        <div class="card-header bg-warning bg-opacity-25 py-2.5 px-3 border-bottom border-warning d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-warning text-dark border border-warning px-2.5 py-1 fw-bold">
                                    <i class="fa-solid fa-mug-saucer me-1"></i> Saturday Schedule (ቅዳሜ የሥራ ሰዓት)
                                </span>
                                <span class="fw-bold text-dark small">Morning Session Only (ጠዋት ብቻ)</span>
                            </div>
                            <span class="badge bg-dark text-white px-2 py-1 small">4.0 hrs Expected</span>
                        </div>
                        <div class="card-body p-3 bg-white">
                            <div class="alert alert-light border border-warning-subtle shadow-xs rounded-3 p-2 mb-3 d-flex align-items-center gap-2">
                                <i class="fa-solid fa-circle-info text-warning fs-5"></i>
                                <div class="small text-dark">
                                    <strong>Saturday Working Policy:</strong> Officially, work on Saturday is limited strictly to the <strong>morning session</strong>. There is no afternoon shift or lunch break requirement on Saturdays.
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-success text-uppercase">
                                        <i class="fas fa-sun text-warning me-1"></i>Saturday Clock-In Time (የቅዳሜ ጠዋት መግቢያ) <span class="text-danger">*</span>
                                    </label>
                                    <input type="time" name="sat_morning_in" class="form-control form-control-sm font-monospace" value="{{ $workSchedule['sat_morning_in'] ?? '08:30' }}" required>
                                    <div class="form-text small text-muted">Time employees clock in on Saturday morning.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-success text-uppercase">
                                        <i class="fas fa-door-open text-primary me-1"></i>Saturday Clock-Out Time (የቅዳሜ ጠዋት መውጫ) <span class="text-danger">*</span>
                                    </label>
                                    <input type="time" name="sat_morning_out" class="form-control form-control-sm font-monospace" value="{{ $workSchedule['sat_morning_out'] ?? '12:30' }}" required>
                                    <div class="form-text small text-muted">Time employees finish work for the weekend.</div>
                                </div>
                            </div>

                            <div class="mt-3 p-2 rounded-2 bg-light border d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-secondary text-white px-2 py-0.5" style="font-size: 0.7rem;">AFTERNOON</span>
                                    <span class="small fw-semibold text-muted">Saturday Afternoon (ከሰዓት):</span>
                                    <span class="small text-dark fw-bold">Off / Non-Working (ከሰዓት እረፍት)</span>
                                </div>
                                <span class="badge bg-success-subtle text-success border border-success px-2 py-0.5 small">
                                    <i class="fa-solid fa-check me-1"></i>Active Policy
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3 px-4 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold shadow-sm">
                        <i class="fas fa-save me-1"></i>Save Work Schedule Policy
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Ethiopian Date calculation helper in client JS
function gregorianToEthiopianDate(dateStr) {
    if (!dateStr) return '';
    const parts = dateStr.split('-');
    if (parts.length !== 3) return '';
    const year = parseInt(parts[0], 10);
    const month = parseInt(parts[1], 10);
    const day = parseInt(parts[2], 10);

    const a = Math.floor((14 - month) / 12);
    const y = year + 4800 - a;
    const m = month + 12 * a - 3;
    const jdn = day + Math.floor((153 * m + 2) / 5) + 365 * y + Math.floor(y / 4) - Math.floor(y / 100) + Math.floor(y / 400) - 32045;

    const ethiopianEpoch = 1723856;
    const r = (jdn - ethiopianEpoch) % 1461;
    const n = (r % 365) + 365 * Math.floor(r / 1460);

    const ethYear = 4 * Math.floor((jdn - ethiopianEpoch) / 1461) + Math.floor(r / 365) - Math.floor(r / 1460);
    const ethMonth = Math.floor(n / 30) + 1;
    const ethDay = (n % 30) + 1;

    const monthsAm = ['', 'መስከረም', 'ጥቅምት', 'ኅዳር', 'ታኅሣሥ', 'ጥር', 'የካቲት', 'መጋቢት', 'ሚያዝያ', 'ግንቦት', 'ሰኔ', 'ሐምሌ', 'ነሐሴ', 'ጳጉሜ'];
    return (monthsAm[ethMonth] || '') + ' ' + ethDay + ', ' + ethYear + ' ዓ.ም.';
}

function updateSiteEthiopianDate(dateStr) {
    const el = document.getElementById('site_et_date_preview');
    if (el) {
        el.textContent = '🇪🇹 ' + gregorianToEthiopianDate(dateStr);
    }
}

// File drop zone interaction
const dropZone = document.getElementById('uploadDropZone');
const fileInput = document.getElementById('attendanceFile');
const fileDisplay = document.getElementById('fileNameDisplay');
const fileNameText = document.getElementById('fileNameText');
const importForm = document.getElementById('importDeviceForm');
const submitBtn = document.getElementById('importSubmitBtn');

if (fileInput && dropZone) {
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            const file = this.files[0];
            fileNameText.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
            fileDisplay.classList.remove('d-none');
            fileDisplay.classList.add('d-flex');
            dropZone.style.borderColor = '#28a745';
            dropZone.style.background = '#f0fff4';
        }
    });

    const clearFileBtn = document.getElementById('clearFileBtn');
    if (clearFileBtn) {
        clearFileBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            fileInput.value = '';
            fileDisplay.classList.add('d-none');
            fileDisplay.classList.remove('d-flex');
            fileNameText.textContent = '';
            dropZone.style.borderColor = '#1a73e8';
            dropZone.style.background = '#f0f6ff';
        });
    }

    ['dragover', 'dragenter'].forEach(e => {
        dropZone.addEventListener(e, function(ev) {
            ev.preventDefault();
            dropZone.style.borderColor = '#0f4fa8';
            dropZone.style.background = '#e8f0fe';
        });
    });

    ['dragleave', 'drop'].forEach(e => {
        dropZone.addEventListener(e, function(ev) {
            ev.preventDefault();
            if (e === 'drop' && ev.dataTransfer.files.length > 0) {
                fileInput.files = ev.dataTransfer.files;
                fileInput.dispatchEvent(new Event('change'));
            } else {
                dropZone.style.borderColor = '#1a73e8';
                dropZone.style.background = '#f0f6ff';
            }
        });
    });
}

if (importForm && submitBtn) {
    importForm.addEventListener('submit', function() {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Importing...';
    });
}

// Synchronize Month and Date dropdowns
function onMonthFilterChange(selectedMonth) {
    const dateSelect = document.getElementById('filterDate');
    if (!dateSelect) return;
    const options = dateSelect.querySelectorAll('option');
    let currentMatchFound = false;

    options.forEach(opt => {
        if (!opt.value) {
            opt.hidden = false;
            return;
        }
        const optMonth = opt.getAttribute('data-month');
        if (!selectedMonth || optMonth === selectedMonth) {
            opt.hidden = false;
            if (opt.selected) currentMatchFound = true;
        } else {
            opt.hidden = true;
            if (opt.selected) opt.selected = false;
        }
    });

    if (selectedMonth && !currentMatchFound) {
        dateSelect.value = '';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const monthSelect = document.getElementById('filterMonth');
    const dateSelect = document.getElementById('filterDate');

    if (monthSelect && monthSelect.value) {
        onMonthFilterChange(monthSelect.value);
    }

    if (dateSelect) {
        dateSelect.addEventListener('change', function() {
            const selectedOpt = this.options[this.selectedIndex];
            if (selectedOpt && selectedOpt.getAttribute('data-month') && monthSelect) {
                const optMonth = selectedOpt.getAttribute('data-month');
                if (monthSelect.value !== optMonth) {
                    monthSelect.value = optMonth;
                }
            }
        });
    }

    // Auto-open modal if there was a warning/error from import
    @if(session('warning') || session('error'))
        var importModal = new bootstrap.Modal(document.getElementById('importDeviceModal'));
        if (importModal) importModal.show();
    @endif
});
</script>
@endpush

@endsection
