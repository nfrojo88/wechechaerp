@extends('layouts.app')
@section('title', 'Device Logs & Attendance Reset - Admin')

@section('content')
<div class="container-fluid py-3">

    {{-- Page Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h3 mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-fingerprint text-primary me-2"></i>Biometric Devices &amp; Punch Logs
                </h1>
                <span class="badge bg-primary-subtle text-primary border border-primary fw-semibold px-2 py-1">
                    <i class="fa-solid fa-shield-halved me-1"></i>Admin &amp; Global Admin
                </span>
            </div>
            <p class="text-muted mb-0 small">
                Classify physical ZKTeco devices (Head Office vs Construction Sites), monitor real-time punches, and sync employee attendance.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('attendance.index') }}" class="btn btn-outline-secondary btn-sm shadow-xs">
                <i class="fa-solid fa-arrow-left me-1"></i>Attendance (HR View)
            </a>
            <a href="{{ route('attendance.zkteco-status') }}" class="btn btn-outline-info btn-sm shadow-xs">
                <i class="fa-solid fa-satellite-dish me-1"></i>Device Status
            </a>
            <a href="{{ route('admin.activity-logs') }}" class="btn btn-outline-dark btn-sm shadow-xs">
                <i class="fa-solid fa-clock-rotate-left me-1"></i>Audit Trail
            </a>
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




    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- BIOMETRIC DEVICES BY LOCATION (HEAD OFFICE VS SITES)        --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div class="card border-0 shadow-sm mb-4 border-top border-4 border-primary">
        <div class="card-header bg-white py-3 border-bottom">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="mb-1 text-dark fw-bold">
                        <i class="fa-solid fa-network-wired text-primary me-2"></i>Biometric Devices &amp; Location Assignment
                    </h5>
                    <p class="text-muted small mb-0">
                        Separate physical ZKTeco devices into <strong>Head Office</strong> vs <strong>Construction Project Sites</strong>. Punches from site devices automatically link employee attendance to project sites.
                    </p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-primary btn-sm shadow-xs fw-semibold" onclick="openCreateDeviceModal()">
                        <i class="fa-solid fa-plus me-1"></i>+ Link / Register Device
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            {{-- Quick Summary Stats --}}
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="p-3 bg-light rounded-3 border">
                        <div class="small text-muted fw-semibold mb-1">Total Devices</div>
                        <div class="h4 mb-0 fw-bold text-dark">{{ $allDevices->count() }}</div>
                        <div class="small text-muted mt-1">{{ number_format($totalLogsCount ?? 0) }} total punches</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 bg-primary-subtle bg-opacity-25 rounded-3 border border-primary-subtle">
                        <div class="small text-primary fw-semibold mb-1">
                            <i class="fa-solid fa-building me-1"></i>Head Office Devices
                        </div>
                        <div class="h4 mb-0 fw-bold text-primary">{{ $headOfficeDevices->count() }}</div>
                        <div class="small text-muted mt-1">{{ number_format($hoPunchesCount ?? 0) }} punches</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 bg-success-subtle bg-opacity-25 rounded-3 border border-success-subtle">
                        <div class="small text-success fw-semibold mb-1">
                            <i class="fa-solid fa-helmet-safety me-1"></i>Construction Sites
                        </div>
                        <div class="h4 mb-0 fw-bold text-success">{{ $siteDevices->count() }}</div>
                        <div class="small text-muted mt-1">{{ number_format($sitePunchesCount ?? 0) }} punches</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 bg-info-subtle bg-opacity-25 rounded-3 border border-info-subtle">
                        <div class="small text-info fw-semibold mb-1">
                            <i class="fa-solid fa-signal me-1"></i>Online Status
                        </div>
                        <div class="h4 mb-0 fw-bold text-info">
                            {{ $allDevices->filter(fn($d) => $d->isOnline())->count() }} / {{ $allDevices->count() }}
                        </div>
                        <div class="small text-muted mt-1">Active in last 10m</div>
                    </div>
                </div>
            </div>

            {{-- Device List Tabs --}}
            <ul class="nav nav-pills mb-3 gap-2" id="deviceTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active btn-sm" id="all-devices-tab" data-bs-toggle="pill" data-bs-target="#tab-all-devices" type="button" role="tab">
                        All Devices <span class="badge bg-secondary ms-1">{{ $allDevices->count() }}</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link btn-sm" id="ho-devices-tab" data-bs-toggle="pill" data-bs-target="#tab-ho-devices" type="button" role="tab">
                        <i class="fa-solid fa-building me-1 text-primary"></i>Head Office Devices <span class="badge bg-primary ms-1">{{ $headOfficeDevices->count() }}</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link btn-sm" id="site-devices-tab" data-bs-toggle="pill" data-bs-target="#tab-site-devices" type="button" role="tab">
                        <i class="fa-solid fa-helmet-safety me-1 text-success"></i>Construction Site Devices <span class="badge bg-success ms-1">{{ $siteDevices->count() }}</span>
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="deviceTabsContent">
                {{-- Tab 1: All Devices --}}
                <div class="tab-pane fade show active" id="tab-all-devices" role="tabpanel">
                    @include('admin.attendance.partials.devices_table', ['deviceList' => $allDevices])
                </div>

                {{-- Tab 2: Head Office Devices --}}
                <div class="tab-pane fade" id="tab-ho-devices" role="tabpanel">
                    @include('admin.attendance.partials.devices_table', ['deviceList' => $headOfficeDevices])
                </div>

                {{-- Tab 3: Construction Site Devices --}}
                <div class="tab-pane fade" id="tab-site-devices" role="tabpanel">
                    @include('admin.attendance.partials.devices_table', ['deviceList' => $siteDevices])
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ Two-column info row (Endpoints & Sync) ═══ --}}
    <div class="row g-3 mb-4">

        {{-- ADMS Endpoint Card --}}
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-plug-circle-bolt text-primary me-2"></i>ZKTeco Device Server Endpoints
                    </h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2">Configure your physical ZKTeco device <strong>ADMS &rarr; Cloud Server</strong> settings to point to:</p>
                    <div class="bg-dark rounded p-3 mb-3 font-monospace small">
                        <div class="text-success mb-1">
                            <span class="text-warning">Server Address:</span> wechechaconstruction.com
                        </div>
                        <div class="text-success mb-1">
                            <span class="text-warning">Port:</span> 80 (HTTP)
                        </div>
                        <div class="text-success">
                            <span class="text-warning">Full URL:</span> http://wechechaconstruction.com/iclock/cdata.php
                        </div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="badge bg-light text-dark border px-2 py-1">
                            <i class="fa-solid fa-circle text-success me-1" style="font-size:8px"></i>
                            Handshake: <code>GET /iclock/cdata.php?SN=XXXX&amp;options=all</code>
                        </span>
                        <span class="badge bg-light text-dark border px-2 py-1">
                            <i class="fa-solid fa-circle text-warning me-1" style="font-size:8px"></i>
                            Heartbeat: <code>GET /iclock/getrequest.php?SN=XXXX</code>
                        </span>
                        <span class="badge bg-light text-dark border px-2 py-1">
                            <i class="fa-solid fa-circle text-danger me-1" style="font-size:8px"></i>
                            Punch Push: <code>POST /iclock/cdata.php?SN=XXXX&amp;table=ATTLOG</code>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sync Card --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-rotate text-primary me-2"></i>Sync Punches &rarr; Attendance
                    </h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2">
                        Execute automatic calculation of employee work hours and shift attendance from raw biometric punches.
                    </p>

                    @if(isset($totalLogsCount) && $totalLogsCount > 0)
                        <div class="alert alert-info py-2 px-3 mb-3 small rounded-2">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-1">
                                <div>
                                    <i class="fa-solid fa-database text-primary me-1"></i>
                                    <strong>{{ number_format($totalLogsCount) }}</strong> biometric punches stored
                                    @if($earliestPunch && $latestPunch)
                                        <br><span class="text-muted">Dates:</span> <strong>{{ \Carbon\Carbon::parse($earliestPunch)->format('M d, Y') }}</strong> to <strong>{{ \Carbon\Carbon::parse($latestPunch)->format('M d, Y') }}</strong>
                                    @endif
                                </div>
                                @if($earliestPunch && $latestPunch)
                                    <button type="button" class="btn btn-sm btn-primary py-0 px-2 shadow-xs" style="font-size: 0.75rem;"
                                            onclick="setLogDates('{{ \Carbon\Carbon::parse($earliestPunch)->format('Y-m-d') }}', '{{ \Carbon\Carbon::parse($latestPunch)->format('Y-m-d') }}')">
                                        Use Stored Dates
                                    </button>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="alert alert-warning py-2 px-3 mb-3 small rounded-2">
                            <i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>
                            <strong>No biometric punches found in database.</strong> Connect a device or upload punches.
                        </div>
                    @endif

                    @if(isset($unlinkedCount) && $unlinkedCount > 0)
                        <div class="alert alert-warning py-1 px-2 mb-3 small rounded-2" style="font-size: 0.78rem;">
                            <i class="fa-solid fa-unlink text-danger me-1"></i>
                            <strong>{{ $unlinkedCount }}</strong> punch logs have Device IDs not linked to an employee.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('attendance.zkteco-sync') }}" id="zktecoSyncForm">
                        @csrf
                        <div class="row g-2 align-items-end mb-2" id="dateInputsRow">
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1 text-dark">
                                    <i class="far fa-calendar-alt text-primary me-1"></i>Start Date
                                </label>
                                <input type="date" name="start_date" id="syncStartDate" class="form-control form-control-sm"
                                       value="{{ request('start_date', (isset($earliestPunch) && $earliestPunch ? \Carbon\Carbon::parse($earliestPunch)->format('Y-m-d') : now()->format('Y-m-d'))) }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1 text-dark">
                                    <i class="far fa-calendar-check text-success me-1"></i>End Date
                                </label>
                                <input type="date" name="end_date" id="syncEndDate" class="form-control form-control-sm"
                                       value="{{ request('end_date', (isset($latestPunch) && $latestPunch ? \Carbon\Carbon::parse($latestPunch)->format('Y-m-d') : now()->format('Y-m-d'))) }}" required>
                            </div>
                        </div>

                        {{-- Location & Project Sync Filters --}}
                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1 text-dark">
                                <i class="fa-solid fa-location-dot text-primary me-1"></i>Sync Location Scope
                            </label>
                            <select name="location_type" id="syncLocationType" class="form-select form-select-sm" onchange="toggleSyncLocation(this.value)">
                                <option value="all">🌐 All Locations (Head Office &amp; All Sites)</option>
                                <option value="head_office">🏢 Head Office Devices Only</option>
                                <option value="site">🏗️ Construction Site Devices Only</option>
                            </select>
                        </div>
                        <div class="mb-2" id="syncProjectWrapper" style="display: none;">
                            <label class="form-label small fw-semibold mb-1 text-dark">
                                <i class="fa-solid fa-helmet-safety text-success me-1"></i>Select Construction Project / Site
                            </label>
                            <select name="project_id" id="syncProjectId" class="form-select form-select-sm">
                                <option value="">-- All Construction Sites --</option>
                                @foreach($projects as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-1">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="sync_all" value="1" id="syncAllCheck" onchange="toggleSyncAll(this.checked)">
                                <label class="form-check-label small fw-semibold text-primary" for="syncAllCheck">
                                    Sync ALL Available Dates
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="force" value="1" id="forceSync" checked>
                                <label class="form-check-label small fw-semibold text-dark" for="forceSync">Always update existing records</label>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-fill shadow-xs" id="syncNowBtn">
                                <i class="fa-solid fa-rotate me-1"></i>Sync Now
                            </button>
                            <a href="{{ route('attendance.index') }}" class="btn btn-outline-success btn-sm">
                                <i class="fa-solid fa-table me-1"></i>View Attendance
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Form --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('admin.attendance.device-logs') }}" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label fw-semibold small text-muted mb-1">Date From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm"
                           value="{{ request('date_from', '') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small text-muted mb-1">Date To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm"
                           value="{{ request('date_to', '') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small text-muted mb-1">Location Scope</label>
                    <select name="location_type" id="filterLocationType" class="form-select form-select-sm" onchange="toggleFilterProject(this.value)">
                        <option value="">All Locations</option>
                        <option value="head_office" {{ request('location_type') === 'head_office' ? 'selected' : '' }}>🏢 Head Office</option>
                        <option value="site" {{ request('location_type') === 'site' ? 'selected' : '' }}>🏗️ Construction Site</option>
                    </select>
                </div>
                <div class="col-md-2" id="filterProjectCol" style="{{ request('location_type') === 'site' ? '' : 'display:none;' }}">
                    <label class="form-label fw-semibold small text-muted mb-1">Site / Project</label>
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">All Sites</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" {{ request('project_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small text-muted mb-1">Link Status</label>
                    <select name="linked" class="form-select form-select-sm">
                        <option value="">All Records</option>
                        <option value="linked"   {{ request('linked') === 'linked'   ? 'selected' : '' }}>Linked to Employee</option>
                        <option value="unlinked" {{ request('linked') === 'unlinked' ? 'selected' : '' }}>Not Linked</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill shadow-xs">
                        <i class="fa-solid fa-filter me-1"></i>Filter
                    </button>
                    <a href="{{ route('admin.attendance.device-logs') }}" class="btn btn-outline-secondary btn-sm shadow-xs" title="Reset Filters">
                        <i class="fa-solid fa-rotate-right"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Logs Table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between py-3 border-bottom">
            <h6 class="mb-0 fw-bold text-dark">
                <i class="fa-solid fa-list me-2 text-primary"></i>Raw Punch Records (ZKTeco Biometrics)
            </h6>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary-subtle text-primary border border-primary px-2 py-1">
                    {{ $logs->total() }} records found
                </span>
                @if(isset($totalLogsCount) && $totalLogsCount > 0)
                <button type="button" class="btn btn-outline-danger btn-sm shadow-xs py-1"
                        data-bs-toggle="modal" data-bs-target="#clearDeviceLogsModal"
                        title="Purge raw biometric device punch logs only (employee attendance records remain safe)">
                    <i class="fa-solid fa-eraser me-1"></i>Clear Device Punches
                </button>
                @endif
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width:60px;">#</th>
                            <th>Device &amp; Location</th>
                            <th>Device User ID</th>
                            <th>Punch Time</th>
                            <th>Type</th>
                            <th>Verify Mode</th>
                            <th>Sync Status</th>
                            <th>Linked Employee</th>
                            <th class="pe-3 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                        <tr>
                            <td class="ps-3 text-muted small">{{ $log->id }}</td>
                            <td>
                                @if($log->device_sn)
                                    <div><code class="fw-bold text-dark bg-light px-2 py-1 rounded border">{{ $log->device_sn }}</code></div>
                                    @if($log->zkDevice)
                                        @if($log->zkDevice->device_type === 'site')
                                            <span class="badge bg-success-subtle text-success border border-success d-inline-flex align-items-center gap-1 mt-1">
                                                <i class="fa-solid fa-helmet-safety"></i>Site: {{ $log->zkDevice->project->name ?? $log->zkDevice->location_name ?? 'Construction Site' }}
                                            </span>
                                        @else
                                            <span class="badge bg-primary-subtle text-primary border border-primary d-inline-flex align-items-center gap-1 mt-1">
                                                <i class="fa-solid fa-building"></i>Head Office
                                            </span>
                                        @endif
                                    @else
                                        <a href="javascript:void(0)" onclick="openLinkModalWithSn('{{ $log->device_sn }}')" class="badge bg-warning-subtle text-warning-emphasis border border-warning text-decoration-none d-inline-flex align-items-center gap-1 mt-1" title="Click to classify device location">
                                            <i class="fa-solid fa-link-slash"></i>Unlinked (Assign)
                                        </a>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td><code class="fw-bold fs-6 text-primary">{{ $log->device_user_id }}</code></td>
                            <td>
                                @if($log->punch_time)
                                    <span class="fw-semibold text-dark">{{ $log->punch_time->format('d M Y') }}</span>
                                    <br><small class="text-muted">{{ $log->punch_time->format('h:i:s A') }}</small>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                            <td>
                                @php $statusMap = ['0'=>'Check-In','1'=>'Check-Out','2'=>'Break-Out','3'=>'Break-In']; @endphp
                                @if($log->status !== null)
                                    <span class="badge {{ $log->status == '0' ? 'bg-success' : ($log->status == '1' ? 'bg-danger' : 'bg-secondary') }}">
                                        {{ $statusMap[$log->status] ?? 'Punch '.$log->status }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @php $verifyMap = ['1'=>'Fingerprint','4'=>'ID Card','15'=>'Face','16'=>'Palm']; @endphp
                                <span class="badge bg-light text-dark border">
                                    {{ $verifyMap[$log->verify_mode] ?? ($log->verify_mode ?? '—') }}
                                </span>
                            </td>
                            <td>
                                @if(isset($log->synced_at) && $log->synced_at)
                                    <span class="badge bg-success-subtle text-success border border-success">
                                        <i class="fa-solid fa-check me-1"></i>Synced
                                    </span>
                                @else
                                    <span class="badge bg-warning-subtle text-warning border border-warning">
                                        <i class="fa-solid fa-clock me-1"></i>Pending
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($log->employee)
                                    <a href="{{ route('employees.show', $log->employee) }}" class="badge bg-success text-decoration-none">
                                        <i class="fa-solid fa-link me-1"></i>{{ $log->employee->full_name }}
                                    </a>
                                @else
                                    <span class="badge bg-warning text-dark">
                                        <i class="fa-solid fa-unlink me-1"></i>Not Linked
                                    </span>
                                    <br><small class="text-muted">Match Device ID <code>{{ $log->device_user_id }}</code> on employee</small>
                                @endif
                            </td>
                            <td class="pe-3 text-end">
                                @if(!$log->employee)
                                    <a href="{{ route('employees.index') }}?search={{ $log->full_name }}" class="btn btn-outline-primary btn-sm" title="Find Employee">
                                        <i class="fa-solid fa-magnifying-glass"></i>
                                    </a>
                                @else
                                    <a href="{{ route('employees.show', $log->employee) }}" class="btn btn-sm btn-outline-secondary" title="View Employee">
                                        <i class="fa-solid fa-user"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-fingerprint fa-3x mb-3 d-block opacity-25"></i>
                                <strong class="fs-6">No device logs found.</strong>
                                <br><small class="text-muted">Logs appear automatically once your physical ZKTeco device pushes punches to the server.</small>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($logs->hasPages())
            <div class="p-3 border-top">
                {{ $logs->appends(request()->all())->links() }}
            </div>
            @endif
        </div>
    </div>

</div>

{{-- Modal: Clear Device Punch Logs Only (Raw punches, attendance records are untouched) --}}
<div class="modal fade" id="clearDeviceLogsModal" tabindex="-1" aria-labelledby="clearDeviceLogsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger-subtle text-danger border-bottom">
                <h5 class="modal-title fw-bold" id="clearDeviceLogsModalLabel">
                    <i class="fa-solid fa-eraser text-danger me-2"></i>Clear Raw Device Punch Logs
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.attendance.clear-history') }}" method="POST">
                @csrf
                <input type="hidden" name="clear_type" value="device_logs">
                <div class="modal-body p-4">
                    <div class="alert alert-warning d-flex align-items-center mb-3">
                        <i class="fa-solid fa-triangle-exclamation fs-4 me-3"></i>
                        <div class="small">
                            This will clear all <strong>{{ number_format($totalLogsCount ?? 0) }}</strong> raw biometric punch logs in <code>device_attendance_logs</code>.
                        </div>
                    </div>
                    <div class="p-3 bg-success-subtle text-success-emphasis rounded-3 border border-success-subtle small mb-3">
                        <i class="fa-solid fa-shield-check me-1 text-success"></i>
                        <strong>Protected:</strong> Processed employee attendance records (<code>attendances</code> table) will <strong>NOT</strong> be deleted.
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-semibold">
                        <i class="fa-solid fa-eraser me-1"></i>Yes, Clear Device Punches
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- MODALS FOR BIOMETRIC DEVICE REGISTRATION & LOCATION LINKING --}}
{{-- ══════════════════════════════════════════════════════════════ --}}

{{-- Modal: Register / Edit Biometric Device --}}
<div class="modal fade" id="deviceModal" tabindex="-1" aria-labelledby="deviceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="deviceModalLabel">
                    <i class="fa-solid fa-fingerprint me-2"></i><span id="deviceModalTitle">Link / Register Biometric Device</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.attendance.devices.save') }}" method="POST" id="deviceForm">
                @csrf
                <input type="hidden" name="id" id="dev_id" value="">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        {{-- Device SN --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">
                                Device Serial Number (SN) <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="device_sn" id="dev_device_sn" class="form-control font-monospace" placeholder="e.g. BKT82309101" required>
                            <div class="form-text small text-muted">Found on hardware label or device Cloud Server (ADMS) menu.</div>
                        </div>

                        {{-- Device Name --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">Device Friendly Name</label>
                            <input type="text" name="device_name" id="dev_device_name" class="form-control" placeholder="e.g. HQ Reception Biometric or Site Main Gate">
                        </div>

                        {{-- Location Classification --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold small text-dark d-block">
                                Location Classification <span class="text-danger">*</span>
                            </label>
                            <div class="d-flex gap-3 p-3 bg-light rounded-3 border">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="device_type" id="type_head_office" value="head_office" checked onchange="handleModalTypeChange('head_office')">
                                    <label class="form-check-label fw-semibold text-primary" for="type_head_office">
                                        <i class="fa-solid fa-building me-1"></i>Head Office
                                    </label>
                                    <div class="small text-muted">Attendance synced as HQ office hours without project tagging.</div>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="device_type" id="type_site" value="site" onchange="handleModalTypeChange('site')">
                                    <label class="form-check-label fw-semibold text-success" for="type_site">
                                        <i class="fa-solid fa-helmet-safety me-1"></i>Construction Site
                                    </label>
                                    <div class="small text-muted">Attendance synced directly into project site records.</div>
                                </div>
                            </div>
                        </div>

                        {{-- Project Site Selection (Visible when site is chosen) --}}
                        <div class="col-md-12" id="modalProjectSection" style="display: none;">
                            <label class="form-label fw-semibold small text-dark">
                                Linked Construction Project / Site <span class="text-danger">*</span>
                            </label>
                            <select name="project_id" id="dev_project_id" class="form-select">
                                <option value="">-- Select Project / Site --</option>
                                @foreach($projects as $proj)
                                    <option value="{{ $proj->id }}">{{ $proj->name }} @if($proj->location)({{ $proj->location }})@endif</option>
                                @endforeach
                            </select>
                            <div class="form-text small text-muted">All punches from this device will tag employee attendance with this construction site.</div>
                        </div>

                        {{-- Specific Placement Location --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">Physical Placement / Location</label>
                            <input type="text" name="location_name" id="dev_location_name" class="form-control" placeholder="e.g. Ground Floor Entrance, Gate 2, Site Office">
                        </div>

                        {{-- Device Model --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">Hardware Model Name</label>
                            <input type="text" name="model_name" id="dev_model_name" class="form-control" placeholder="e.g. ZKTeco K40, MB2000, SilkBio-101TC">
                        </div>

                        {{-- IP Address & Port (Optional) --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">Local IP Address (Optional)</label>
                            <input type="text" name="ip_address" id="dev_ip_address" class="form-control font-monospace" placeholder="192.168.1.201">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">Port (Optional)</label>
                            <input type="number" name="port" id="dev_port" class="form-control" placeholder="4370">
                        </div>

                        {{-- Active Status & Notes --}}
                        <div class="col-12">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="dev_is_active" value="1" checked>
                                <label class="form-check-label fw-semibold text-dark small" for="dev_is_active">Device is Active &amp; Receiving Logs</label>
                            </div>
                            <label class="form-label fw-semibold small text-dark">Notes / Description</label>
                            <textarea name="notes" id="dev_notes" class="form-control form-control-sm" rows="2" placeholder="Optional notes, installation date, operator contacts..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold">
                        <i class="fa-solid fa-save me-1"></i>Save Device
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal: Delete Device Confirmation --}}
<div class="modal fade" id="deleteDeviceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-trash-can me-2"></i>Delete Device Record
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="deleteDeviceForm" method="POST" action="{{ route('admin.attendance.device-logs') }}">
                @csrf
                <input type="hidden" name="action" value="delete_device">
                <input type="hidden" name="delete_device_id" id="deleteDeviceIdInput" value="">
                <div class="modal-body p-4">
                    <p class="mb-2">Are you sure you want to remove device registration for <strong id="deleteDeviceName"></strong> (<code id="deleteDeviceSn"></code>)?</p>
                    <p class="small text-muted mb-0">Existing biometric punch records received from this device will remain safely stored.</p>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold">
                        <i class="fa-solid fa-trash-can me-1"></i>Yes, Delete Device
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function setLogDates(start, end) {
    const s = document.getElementById('syncStartDate');
    const e = document.getElementById('syncEndDate');
    if (s) s.value = start;
    if (e) e.value = end;
    const chk = document.getElementById('syncAllCheck');
    if (chk) { chk.checked = false; toggleSyncAll(false); }
}

function toggleSyncAll(isAll) {
    const s = document.getElementById('syncStartDate');
    const e = document.getElementById('syncEndDate');
    if (s) {
        s.disabled = isAll;
        s.required = !isAll;
    }
    if (e) {
        e.disabled = isAll;
        e.required = !isAll;
    }
}

function toggleSyncLocation(val) {
    const wrapper = document.getElementById('syncProjectWrapper');
    if (wrapper) {
        wrapper.style.display = (val === 'site') ? 'block' : 'none';
    }
}

function toggleFilterProject(val) {
    const col = document.getElementById('filterProjectCol');
    if (col) {
        col.style.display = (val === 'site') ? 'block' : 'none';
    }
}

function handleModalTypeChange(type) {
    const section = document.getElementById('modalProjectSection');
    const projectSelect = document.getElementById('dev_project_id');
    if (type === 'site') {
        if (section) section.style.display = 'block';
        if (projectSelect) projectSelect.required = true;
    } else {
        if (section) section.style.display = 'none';
        if (projectSelect) {
            projectSelect.required = false;
            projectSelect.value = '';
        }
    }
}

function openCreateDeviceModal() {
    const form = document.getElementById('deviceForm');
    if (form) form.reset();
    document.getElementById('dev_id').value = '';
    document.getElementById('deviceModalTitle').textContent = 'Link / Register Biometric Device';
    document.getElementById('type_head_office').checked = true;
    document.getElementById('dev_is_active').checked = true;
    handleModalTypeChange('head_office');
    const modalEl = document.getElementById('deviceModal');
    if (modalEl) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }
}

function openEditDeviceModal(dev) {
    const sn = dev.serial_number || dev.device_sn || '';
    document.getElementById('deviceModalTitle').textContent = 'Edit Biometric Device (' + sn + ')';
    document.getElementById('dev_id').value = dev.id || '';
    document.getElementById('dev_device_sn').value = sn;
    document.getElementById('dev_device_name').value = dev.name || dev.device_name || '';
    document.getElementById('dev_model_name').value = dev.model_name || '';
    document.getElementById('dev_location_name').value = dev.location || dev.location_name || '';
    document.getElementById('dev_ip_address').value = dev.ip_address || '';
    document.getElementById('dev_port').value = dev.port || '';
    document.getElementById('dev_notes').value = dev.notes || '';
    document.getElementById('dev_is_active').checked = (dev.is_active == 1);

    if (dev.device_type === 'site') {
        document.getElementById('type_site').checked = true;
        handleModalTypeChange('site');
        document.getElementById('dev_project_id').value = dev.project_id || '';
    } else {
        document.getElementById('type_head_office').checked = true;
        handleModalTypeChange('head_office');
    }

    const modalEl = document.getElementById('deviceModal');
    if (modalEl) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }
}

function openLinkModalWithSn(sn) {
    openCreateDeviceModal();
    const snInput = document.getElementById('dev_device_sn');
    if (snInput) {
        snInput.value = sn;
    }
}

function openDeleteDeviceModal(id, name, sn) {
    document.getElementById('deleteDeviceName').textContent = name;
    document.getElementById('deleteDeviceSn').textContent = sn;
    const idInput = document.getElementById('deleteDeviceIdInput');
    if (idInput) {
        idInput.value = id;
    }
    const form = document.getElementById('deleteDeviceForm');
    if (form) {
        form.action = '{{ url("/admin/attendance/devices") }}/' + id + '/delete';
    }
    const modalEl = document.getElementById('deleteDeviceModal');
    if (modalEl) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }
}

document.getElementById('zktecoSyncForm')?.addEventListener('submit', function() {
    const btn = document.getElementById('syncNowBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Syncing...';
    }
});
</script>
@endpush
