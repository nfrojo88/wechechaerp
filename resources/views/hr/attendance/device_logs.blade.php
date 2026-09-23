@extends('layouts.app')
@section('title', 'Device Attendance Logs')

@section('content')
<div class="container-fluid">

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="fa-solid fa-fingerprint text-primary me-2"></i>Device Attendance Logs</h1>
            <p class="text-muted mt-1 mb-0">Raw punch records received from ZKTeco biometric attendance device</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('attendance.zkteco-status') }}" class="btn btn-outline-info">
                <i class="fa-solid fa-satellite-dish me-1"></i>Device Status
            </a>
            <a href="{{ route('attendance.index') }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i>Back to Attendance
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show shadow-sm">
        <i class="fa-solid fa-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show shadow-sm">
        <i class="fa-solid fa-triangle-exclamation me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- ═══ Two-column info row ═══ --}}
    <div class="row g-3 mb-4">

        {{-- ADMS Endpoint Card --}}
        <div class="col-md-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">
                        <i class="fa-solid fa-plug-circle-bolt text-primary me-2"></i>ZKTeco Device Endpoints
                    </h6>
                    <p class="small text-muted mb-2">Configure your ZKTeco device <strong>ADMS → Cloud Server</strong> settings to point to:</p>
                    <div class="bg-dark rounded p-3 mb-3">
                        <code class="text-success d-block mb-1">
                            <span class="text-warning">Server Address:</span>
                            wechechaconstruction.com
                        </code>
                        <code class="text-success d-block mb-1">
                            <span class="text-warning">Port:</span> 80 (HTTP)
                        </code>
                        <code class="text-success d-block">
                            <span class="text-warning">Full URL:</span>
                            http://wechechaconstruction.com/iclock/cdata.php
                        </code>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="badge bg-light text-dark border px-2 py-1">
                            <i class="fa-solid fa-circle text-success me-1" style="font-size:8px"></i>
                            Handshake: <code>GET /iclock/cdata.php?SN=XXXX&options=all</code>
                        </span>
                        <span class="badge bg-light text-dark border px-2 py-1">
                            <i class="fa-solid fa-circle text-warning me-1" style="font-size:8px"></i>
                            Heartbeat: <code>GET /iclock/getrequest.php?SN=XXXX</code>
                        </span>
                        <span class="badge bg-light text-dark border px-2 py-1">
                            <i class="fa-solid fa-circle text-danger me-1" style="font-size:8px"></i>
                            Punch Push: <code>POST /iclock/cdata.php?SN=XXXX&table=ATTLOG</code>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sync Card --}}
        <div class="col-md-5">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary">
                <div class="card-body">
                    <h6 class="fw-bold mb-2">
                        <i class="fa-solid fa-rotate text-primary me-2"></i>Sync Punches → Attendance
                    </h6>
                    <p class="small text-muted mb-3">
                        Sync automatically runs every 5 minutes. Use this to trigger an immediate sync for a single date or date range.
                    </p>
                    <form method="POST" action="{{ route('attendance.zkteco-sync') }}" id="zktecoSyncForm">
                        @csrf
                        <div class="row g-2 align-items-end mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1 text-dark">
                                    <i class="far fa-calendar-alt text-primary me-1"></i>Start Date
                                </label>
                                <input type="date" name="start_date" id="syncStartDate" class="form-control form-control-sm"
                                       value="{{ request('start_date', request('date_from', now()->format('Y-m-d'))) }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1 text-dark">
                                    <i class="far fa-calendar-check text-success me-1"></i>End Date
                                </label>
                                <input type="date" name="end_date" id="syncEndDate" class="form-control form-control-sm"
                                       value="{{ request('end_date', request('date_to', now()->format('Y-m-d'))) }}" required>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="force" value="1" id="forceSync">
                                <label class="form-check-label small text-muted" for="forceSync">Re-sync existing</label>
                            </div>
                            <button type="button" class="btn btn-link btn-sm text-decoration-none p-0 text-primary small" onclick="setSyncToToday()">
                                <i class="fas fa-calendar-day me-1"></i>Today Only
                            </button>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-fill" id="syncNowBtn">
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
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Date From</label>
                    <input type="date" name="date_from" class="form-control"
                           value="{{ request('date_from', now()->startOfDay()->format('Y-m-d')) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Date To</label>
                    <input type="date" name="date_to" class="form-control"
                           value="{{ request('date_to', now()->format('Y-m-d')) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Link Status</label>
                    <select name="linked" class="form-select">
                        <option value="">All Records</option>
                        <option value="linked"   {{ request('linked') === 'linked'   ? 'selected' : '' }}>Linked to Employee</option>
                        <option value="unlinked" {{ request('linked') === 'unlinked' ? 'selected' : '' }}>Not Linked</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill"><i class="fa-solid fa-filter me-1"></i>Filter</button>
                    <a href="{{ route('attendance.deviceLogs') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-right"></i></a>
                </div>
            </form>
        </div>
    </div>

    {{-- Logs Table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light d-flex align-items-center justify-content-between py-3">
            <h6 class="mb-0 fw-semibold"><i class="fa-solid fa-list me-2"></i>Raw Punch Records</h6>
            <span class="badge bg-primary">{{ $logs->total() }} total records</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">#</th>
                            <th>Device SN</th>
                            <th>ZKTeco User ID</th>
                            <th>Punch Time</th>
                            <th>Type</th>
                            <th>Verify Mode</th>
                            <th>Synced</th>
                            <th>Linked Employee</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                        <tr>
                            <td class="ps-3 text-muted small">{{ $log->id }}</td>
                            <td>
                                @if($log->device_sn)
                                    <span class="badge bg-secondary">{{ $log->device_sn }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td><code class="fw-bold">{{ $log->device_user_id }}</code></td>
                            <td>
                                @if($log->punch_time)
                                    <span class="fw-semibold">{{ $log->punch_time->format('d M Y') }}</span>
                                    <br><small class="text-muted">{{ $log->punch_time->format('H:i:s') }}</small>
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
                                    <br><small class="text-muted">Set Device ID = <code>{{ $log->device_user_id }}</code> on employee</small>
                                @endif
                            </td>
                            <td>
                                @if(!$log->employee)
                                    <a href="{{ route('employees.index') }}?search={{ $log->full_name }}" class="btn btn-xs btn-outline-primary btn-sm" title="Find Employee">
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
                                <strong>No device logs found.</strong>
                                <br><small>Logs appear once your ZKTeco device starts sending data to the ADMS endpoint above.</small>
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
@endsection

@push('scripts')
<script>
function setSyncToToday() {
    const today = '{{ now()->format("Y-m-d") }}';
    const s = document.getElementById('syncStartDate');
    const e = document.getElementById('syncEndDate');
    if (s) s.value = today;
    if (e) e.value = today;
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
