@extends('layouts.app')
@section('title', 'ZKTeco Device Status')

@section('content')
<div class="container-fluid">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="fa-solid fa-satellite-dish text-info me-2"></i>ZKTeco Device Status</h1>
            <p class="text-muted mt-1 mb-0">Live connection status and heartbeat monitoring</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('attendance.machine-test') }}" class="btn btn-primary">
                <i class="fa-solid fa-satellite-dish me-1"></i>Machine Test Console
            </a>
            <a href="/iclock/debug.php" target="_blank" class="btn btn-warning">
                <i class="fa-solid fa-bug me-1"></i>Live Debugger
            </a>
            <a href="{{ route('attendance.deviceLogs') }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-list me-1"></i>Device Logs
            </a>
            <a href="{{ route('attendance.index') }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i>Attendance
            </a>
        </div>
    </div>

    {{-- Stats Row --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="display-6 fw-bold text-info">{{ $devices->count() }}</div>
                <div class="text-muted small mt-1">Registered Devices</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                @php
                    $onlineCount = $devices->filter(fn($d) => $d->last_seen_at && \Carbon\Carbon::parse($d->last_seen_at)->diffInMinutes(now()) < 2)->count();
                @endphp
                <div class="display-6 fw-bold {{ $onlineCount > 0 ? 'text-success' : 'text-danger' }}">{{ $onlineCount }}</div>
                <div class="text-muted small mt-1">Online Now</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="display-6 fw-bold text-primary">{{ $todayPunches }}</div>
                <div class="text-muted small mt-1">Today's Punches</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="display-6 fw-bold {{ $unmatchedIds->count() > 0 ? 'text-warning' : 'text-success' }}">
                    {{ $unmatchedIds->count() }}
                </div>
                <div class="text-muted small mt-1">Unlinked Device Users</div>
            </div>
        </div>
    </div>

    {{-- Devices Card --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-light d-flex align-items-center justify-content-between py-3">
            <h6 class="mb-0 fw-semibold"><i class="fa-solid fa-server me-2"></i>Connected Devices</h6>
        </div>
        <div class="card-body p-0">
            @if($devices->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-satellite-dish fa-3x mb-3 d-block opacity-25"></i>
                <strong>No devices seen yet.</strong>
                <br>
                <small>Once you configure your ZKTeco device to point to this server, it will appear here after the first heartbeat.</small>
                <br><br>
                <div class="bg-dark rounded p-3 d-inline-block text-start">
                    <code class="text-success">Server: wechechaconstruction.com</code><br>
                    <code class="text-success">Port: 80</code><br>
                    <code class="text-success">Path: /iclock/cdata.php</code>
                </div>
            </div>
            @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Status</th>
                            <th>Serial Number</th>
                            <th>Name / Location</th>
                            <th>Last Heartbeat</th>
                            <th>Unsynced Punches</th>
                            <th>First Seen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($devices as $device)
                        @php
                            $lastSeen   = $device->last_seen_at ? \Carbon\Carbon::parse($device->last_seen_at) : null;
                            $minsAgo    = $lastSeen ? $lastSeen->diffInMinutes(now()) : null;
                            $isOnline   = $lastSeen && $minsAgo < 2;   // < 2 min = online
                            $isRecent   = $lastSeen && $minsAgo < 10;  // < 10 min = recently seen
                            $unsynced   = $unsyncedCounts[$device->serial_number] ?? 0;
                        @endphp
                        <tr>
                            <td class="ps-3">
                                @if($isOnline)
                                    <span class="badge bg-success"><i class="fa-solid fa-circle-dot me-1"></i>Online</span>
                                @elseif($isRecent)
                                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Recent</span>
                                @elseif($lastSeen)
                                    <span class="badge bg-danger"><i class="fa-solid fa-circle-xmark me-1"></i>Offline</span>
                                @else
                                    <span class="badge bg-secondary">Unknown</span>
                                @endif
                            </td>
                            <td><code class="fw-bold">{{ $device->serial_number }}</code></td>
                            <td>
                                {{ $device->name ?? '—' }}
                                @if($device->location)
                                    <br><small class="text-muted"><i class="fa-solid fa-location-dot me-1"></i>{{ $device->location }}</small>
                                @endif
                            </td>
                            <td>
                                @if($lastSeen)
                                    <span class="fw-semibold">{{ $lastSeen->format('d M Y H:i:s') }}</span>
                                    <br><small class="text-muted">{{ $lastSeen->diffForHumans() }}</small>
                                @else
                                    <span class="text-muted">Never</span>
                                @endif
                            </td>
                            <td>
                                @if($unsynced > 0)
                                    <span class="badge bg-warning text-dark">{{ $unsynced }} pending</span>
                                @else
                                    <span class="badge bg-success-subtle text-success border border-success">All synced</span>
                                @endif
                            </td>
                            <td>
                                <small class="text-muted">
                                    {{ $device->created_at ? \Carbon\Carbon::parse($device->created_at)->format('d M Y') : '—' }}
                                </small>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    {{-- Unlinked Users Warning --}}
    @if($unmatchedIds->isNotEmpty())
    <div class="card border-0 shadow-sm border-start border-4 border-warning">
        <div class="card-body">
            <h6 class="fw-bold mb-3 text-warning">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>Unlinked ZKTeco User IDs
            </h6>
            <p class="small text-muted mb-3">
                These device user IDs have punch records but are <strong>not linked to any employee</strong>.
                Go to the employee's edit page and set their <strong>Device User ID</strong> to match.
            </p>
            <div class="d-flex flex-wrap gap-2">
                @foreach($unmatchedIds as $uid)
                <div class="d-flex align-items-center gap-2 border rounded px-3 py-2 bg-light">
                    <i class="fa-solid fa-fingerprint text-warning"></i>
                    <code class="fw-bold">{{ $uid }}</code>
                    <a href="{{ route('employees.index') }}" class="btn btn-sm btn-outline-primary ms-2">
                        <i class="fa-solid fa-user-gear me-1"></i>Map Employee
                    </a>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Setup Instructions --}}
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-light py-3">
            <h6 class="mb-0 fw-semibold"><i class="fa-solid fa-circle-info me-2 text-info"></i>Device Configuration Guide</h6>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-6">
                    <h6 class="fw-semibold mb-2"><i class="fa-solid fa-cog me-2 text-primary"></i>Step 1: Configure Device</h6>
                    <ol class="small text-muted">
                        <li>Press <kbd>Menu</kbd> on the ZKTeco device</li>
                        <li>Go to <strong>Comm → Cloud Server</strong> or <strong>ADMS Settings</strong></li>
                        <li>Set <strong>Server Address</strong> to: <code>wechechaconstruction.com</code></li>
                        <li>Set <strong>Port</strong> to: <code>80</code></li>
                        <li>Disable <strong>HTTPS</strong> (use standard HTTP on Port 80)</li>
                        <li>Save and <strong>restart</strong> the device</li>
                    </ol>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-semibold mb-2"><i class="fa-solid fa-link me-2 text-success"></i>Step 2: Link Employees</h6>
                    <ol class="small text-muted">
                        <li>Check the ZKTeco device's <strong>User List</strong> to see assigned user IDs</li>
                        <li>Go to <a href="{{ route('employees.index') }}">Employees list</a></li>
                        <li>Edit each employee and set their <strong>ZKTeco Device User ID</strong> to match</li>
                        <li>Once linked, punches will automatically sync to Attendance</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- Auto-refresh every 30 seconds --}}
<script>
    setTimeout(() => window.location.reload(), 30000);
</script>
@endsection
