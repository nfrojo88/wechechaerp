@extends('layouts.app')
@section('title', 'Device Logs & Attendance Reset - Admin')

@section('content')
<div class="container-fluid py-3">

    {{-- Page Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h3 mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-fingerprint text-primary me-2"></i>Device Logs &amp; Attendance Reset
                </h1>
                <span class="badge bg-danger-subtle text-danger border border-danger fw-semibold px-2 py-1">
                    <i class="fa-solid fa-shield-halved me-1"></i>Admin &amp; Global Admin
                </span>
            </div>
            <p class="text-muted mb-0 small">
                Raw biometric punch records, ZKTeco ADMS integration, attendance sync, and administrative database wipe tools.
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
    {{-- ADMINISTRATIVE CLEAR & RESET SECTION (DANGER ZONE)         --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div class="card border-0 shadow-sm mb-4 border-top border-4 border-danger">
        <div class="card-header bg-danger-subtle bg-opacity-10 py-3 border-bottom border-danger-subtle">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="mb-1 text-danger fw-bold">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>Attendance &amp; Biometric Records Reset (Administrative Wipe)
                    </h5>
                    <p class="text-muted small mb-0">
                        Purge attendance records and raw biometric logs. This operation was relocated from the HR module to prevent unauthorized resets. All actions are logged.
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-white text-dark border shadow-xs px-2 py-1">
                        <i class="fa-solid fa-table me-1 text-primary"></i>
                        Attendance: <strong>{{ number_format($totalAttendanceCount ?? 0) }}</strong>
                    </span>
                    <span class="badge bg-white text-dark border shadow-xs px-2 py-1">
                        <i class="fa-solid fa-fingerprint me-1 text-info"></i>
                        Device Punches: <strong>{{ number_format($totalLogsCount ?? 0) }}</strong>
                    </span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">

                {{-- Option 1: Clear Attendance Table Only --}}
                <div class="col-md-4">
                    <div class="card h-100 border border-light-subtle shadow-xs bg-light bg-opacity-50">
                        <div class="card-body d-flex flex-column p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-warning-subtle text-dark border border-warning">
                                    <i class="fa-solid fa-table me-1 text-warning"></i>Attendance Table
                                </span>
                                <span class="badge bg-secondary-subtle text-secondary small">
                                    {{ number_format($totalAttendanceCount ?? 0) }} records
                                </span>
                            </div>
                            <h6 class="fw-bold text-dark mb-2">Clear Attendance Records Only</h6>
                            <p class="small text-muted mb-3 flex-grow-1">
                                Wipes processed employee attendance records (<code>attendances</code>). Keeps raw biometric device punches intact so you can re-sync anytime.
                            </p>
                            <button type="button" class="btn btn-outline-warning btn-sm w-100 fw-semibold"
                                    data-bs-toggle="modal" data-bs-target="#clearAttendanceModal">
                                <i class="fa-solid fa-eraser me-1"></i>Clear Attendance Table
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Option 2: Clear Device Punches Only --}}
                <div class="col-md-4">
                    <div class="card h-100 border border-light-subtle shadow-xs bg-light bg-opacity-50">
                        <div class="card-body d-flex flex-column p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-info-subtle text-dark border border-info">
                                    <i class="fa-solid fa-fingerprint me-1 text-info"></i>Device Punches
                                </span>
                                <span class="badge bg-secondary-subtle text-secondary small">
                                    {{ number_format($totalLogsCount ?? 0) }} punches
                                </span>
                            </div>
                            <h6 class="fw-bold text-dark mb-2">Clear Device Punch Logs Only</h6>
                            <p class="small text-muted mb-3 flex-grow-1">
                                Wipes raw biometric punch logs (<code>device_attendance_logs</code>) received from ZKTeco. Keeps existing employee attendance sheets untouched.
                            </p>
                            <button type="button" class="btn btn-outline-info btn-sm w-100 fw-semibold"
                                    data-bs-toggle="modal" data-bs-target="#clearDeviceLogsModal">
                                <i class="fa-solid fa-fingerprint me-1"></i>Clear Device Logs
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Option 3: Complete Master Wipe (Both) --}}
                <div class="col-md-4">
                    <div class="card h-100 border border-danger-subtle shadow-xs bg-danger-subtle bg-opacity-25">
                        <div class="card-body d-flex flex-column p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-danger text-white">
                                    <i class="fa-solid fa-radiation me-1"></i>Master Wipe
                                </span>
                                <span class="badge bg-danger-subtle text-danger border border-danger small">
                                    {{ number_format(($totalAttendanceCount ?? 0) + ($totalLogsCount ?? 0)) }} total
                                </span>
                            </div>
                            <h6 class="fw-bold text-danger mb-2">Complete Master Wipe (All Data)</h6>
                            <p class="small text-muted mb-3 flex-grow-1">
                                Completely wipes <strong>BOTH</strong> processed attendance and raw biometric device punch logs. Clean slate to start uploads and sync from scratch.
                            </p>
                            <button type="button" class="btn btn-danger btn-sm w-100 fw-bold shadow-xs"
                                    data-bs-toggle="modal" data-bs-target="#clearMasterModal">
                                <i class="fa-solid fa-trash-can me-1"></i>Master Wipe (Start Fresh)
                            </button>
                        </div>
                    </div>
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
                    <p class="small text-muted mb-2">Configure your physical ZKTeco device <strong>ADMS → Cloud Server</strong> settings to point to:</p>
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

                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-1">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="sync_all" value="1" id="syncAllCheck" onchange="toggleSyncAll(this.checked)">
                                <label class="form-check-label small fw-semibold text-primary" for="syncAllCheck">
                                    Sync ALL Available Dates
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="force" value="1" id="forceSync">
                                <label class="form-check-label small text-muted" for="forceSync">Re-sync existing</label>
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
                <div class="col-md-3">
                    <label class="form-label fw-semibold small text-muted mb-1">Date From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm"
                           value="{{ request('date_from', '') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small text-muted mb-1">Date To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm"
                           value="{{ request('date_to', '') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small text-muted mb-1">Link Status</label>
                    <select name="linked" class="form-select form-select-sm">
                        <option value="">All Records</option>
                        <option value="linked"   {{ request('linked') === 'linked'   ? 'selected' : '' }}>Linked to Employee</option>
                        <option value="unlinked" {{ request('linked') === 'unlinked' ? 'selected' : '' }}>Not Linked</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
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
            <span class="badge bg-primary-subtle text-primary border border-primary px-2 py-1">
                {{ $logs->total() }} records found
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width:60px;">#</th>
                            <th>Device SN</th>
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
                                    <span class="badge bg-secondary-subtle text-dark border">{{ $log->device_sn }}</span>
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

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- MODALS FOR ATTENDANCE & BIOMETRIC DATA RESET               --}}
{{-- ══════════════════════════════════════════════════════════════ --}}

{{-- Modal 1: Clear Attendance Records Only --}}
<div class="modal fade" id="clearAttendanceModal" tabindex="-1" aria-labelledby="clearAttendanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning-subtle text-dark border-bottom">
                <h5 class="modal-title fw-bold" id="clearAttendanceModalLabel">
                    <i class="fa-solid fa-eraser text-warning me-2"></i>Clear Attendance Records Table
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.attendance.clear-history') }}" method="POST">
                @csrf
                <input type="hidden" name="clear_type" value="attendance">
                <div class="modal-body p-4">
                    <div class="alert alert-warning d-flex align-items-center mb-3">
                        <i class="fa-solid fa-triangle-exclamation fs-4 me-3"></i>
                        <div class="small">
                            This will delete all <strong>{{ number_format($totalAttendanceCount ?? 0) }}</strong> employee attendance records currently stored in the <code>attendances</code> table.
                        </div>
                    </div>
                    <p class="text-muted small mb-3">
                        <strong>What happens:</strong>
                        <ul class="small text-muted mb-0">
                            <li>All daily attendance cards, hours, late arrivals, and statuses will be cleared.</li>
                            <li><strong>Raw biometric punch logs are preserved</strong>, allowing you to re-sync attendance later if needed.</li>
                            <li>This action is logged in the system audit trail.</li>
                        </ul>
                    </p>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm fw-semibold">
                        <i class="fa-solid fa-eraser me-1"></i>Yes, Clear Attendance Records
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal 2: Clear Device Punch Logs Only --}}
<div class="modal fade" id="clearDeviceLogsModal" tabindex="-1" aria-labelledby="clearDeviceLogsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info-subtle text-dark border-bottom">
                <h5 class="modal-title fw-bold" id="clearDeviceLogsModalLabel">
                    <i class="fa-solid fa-fingerprint text-info me-2"></i>Clear Device Punch Logs
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.attendance.clear-history') }}" method="POST">
                @csrf
                <input type="hidden" name="clear_type" value="device_logs">
                <div class="modal-body p-4">
                    <div class="alert alert-info d-flex align-items-center mb-3">
                        <i class="fa-solid fa-circle-info fs-4 me-3"></i>
                        <div class="small">
                            This will delete all <strong>{{ number_format($totalLogsCount ?? 0) }}</strong> raw biometric punch records in <code>device_attendance_logs</code>.
                        </div>
                    </div>
                    <p class="text-muted small mb-3">
                        <strong>What happens:</strong>
                        <ul class="small text-muted mb-0">
                            <li>Raw punch records pushed by ZKTeco hardware devices will be removed.</li>
                            <li><strong>Existing employee attendance sheets remain untouched.</strong></li>
                            <li>This action is logged in the system audit trail.</li>
                        </ul>
                    </p>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info btn-sm fw-semibold text-white">
                        <i class="fa-solid fa-fingerprint me-1"></i>Yes, Clear Device Punches
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal 3: Complete Master Wipe (Both) --}}
<div class="modal fade" id="clearMasterModal" tabindex="-1" aria-labelledby="clearMasterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold" id="clearMasterModalLabel">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>Master Wipe: Clear All Attendance &amp; Biometrics
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.attendance.clear-history') }}" method="POST">
                @csrf
                <input type="hidden" name="clear_type" value="all">
                <div class="modal-body p-4">
                    <div class="alert alert-danger d-flex align-items-center mb-3">
                        <i class="fa-solid fa-radiation fs-3 me-3"></i>
                        <div>
                            <strong>DANGER: IRREVERSIBLE OPERATION</strong>
                            <div class="small">
                                This will permanently purge both <strong>{{ number_format($totalAttendanceCount ?? 0) }}</strong> attendance records and <strong>{{ number_format($totalLogsCount ?? 0) }}</strong> raw device punches.
                            </div>
                        </div>
                    </div>
                    <p class="text-dark small mb-3">
                        Use this option only when resetting the entire attendance module to start completely from scratch.
                    </p>
                    <div class="form-check p-2 bg-light rounded border mb-2">
                        <input class="form-check-input ms-0 me-2" type="checkbox" id="confirmWipeCheck" required>
                        <label class="form-check-label small fw-semibold text-danger" for="confirmWipeCheck">
                            I understand this will permanently delete all attendance data.
                        </label>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold">
                        <i class="fa-solid fa-trash-can me-1"></i>Yes, Perform Master Wipe
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

document.getElementById('zktecoSyncForm')?.addEventListener('submit', function() {
    const btn = document.getElementById('syncNowBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Syncing...';
    }
});
</script>
@endpush
