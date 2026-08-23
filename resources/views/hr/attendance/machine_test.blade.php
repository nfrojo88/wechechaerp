@extends('layouts.app')
@section('title', 'Biometric & Attendance Machine Connection Test - Construct-Pro ERP')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="h3 mb-1 fw-bold text-dark">
                <i class="fa-solid fa-satellite-dish text-primary me-2"></i>Attendance Machine Live Connection Test
            </h2>
            <p class="text-muted small mb-0">Diagnostic dashboard to verify live biometrics (ZKTeco MB460 / ADMS) machine communication, test punch simulation, and logs.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ url('/iclock/debug.php') }}" target="_blank" class="btn btn-outline-info btn-sm rounded-pill px-3">
                <i class="fa-solid fa-terminal me-1"></i>Live Raw ADMS Terminal
            </a>
            <a href="{{ route('attendance.zkteco-status') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="fa-solid fa-server me-1"></i>Device Status
            </a>
        </div>
    </div>

    <!-- Alert / Help Banner -->
    <div class="alert alert-primary border-0 rounded-4 shadow-sm p-4 mb-4">
        <div class="d-flex align-items-start gap-3">
            <div class="bg-primary text-white rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                <i class="fa-solid fa-network-wired fa-lg"></i>
            </div>
            <div class="flex-grow-1">
                <h5 class="fw-bold mb-1">How to Configure Your Physical ZKTeco Device:</h5>
                <ol class="small mb-0 ps-3 text-secondary">
                    <li>On your ZKTeco physical machine, press <kbd class="bg-dark text-white px-2 py-1 rounded">M/OK (Menu)</kbd> &rarr; Go to <strong>Comm. (Communication)</strong> &rarr; <strong>Cloud Server / ADMS / Web Server</strong>.</li>
                    <li>Set <strong>Server Address / Domain:</strong> <code class="text-primary fw-bold">wechechaconstruction.com</code> (or server IP).</li>
                    <li>Set <strong>Server Port:</strong> <code class="text-primary fw-bold">80</code> (or <code class="text-primary fw-bold">443</code> with HTTPS enabled).</li>
                    <li>Enable <strong>Domain Name Mode:</strong> <span class="badge bg-success">ON / Enabled</span> (if available).</li>
                    <li>Press <strong>ESC/Save</strong> & restart device network. Once connected, machine logs appear below automatically!</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left: Live Connection Status & Simulation Box -->
        <div class="col-lg-5">
            <!-- Simulated Test Punch Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-light py-3 border-0 rounded-top-4">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-fingerprint me-2 text-success"></i>Send Test Punch (Simulate Machine)
                    </h5>
                </div>
                <div class="card-body p-4">
                    <p class="small text-muted mb-3">Send a test biometric punch right from this page to verify that the ERP immediately saves the punch into the database and syncs the attendance record.</p>

                    <form action="{{ Route::has('attendance.simulate-punch') ? route('attendance.simulate-punch') : url('/attendance/simulate-punch') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Select Employee</label>
                            <select name="device_user_id" class="form-select rounded-3" required id="testEmployeeSelect">
                                <option value="">-- Choose Employee --</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->device_user_id ?: $emp->id }}" data-dept="{{ $emp->department }}">
                                        {{ $emp->full_name }} (Device PIN: {{ $emp->device_user_id ?: $emp->id }}) - {{ $emp->department ?: 'General' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Punch Type</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="punch_state" id="punchIn" value="0" checked>
                                    <label class="form-check-label fw-semibold text-success" for="punchIn">
                                        <i class="fa-solid fa-arrow-right-to-bracket me-1"></i>Check-In (Morning/Day In)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="punch_state" id="punchOut" value="1">
                                    <label class="form-check-label fw-semibold text-danger" for="punchOut">
                                        <i class="fa-solid fa-arrow-right-from-bracket me-1"></i>Check-Out (Day Out)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="device_sn" value="TEST-DEVICE-01">

                        <button type="submit" class="btn btn-success rounded-pill px-4 w-100 shadow-sm fw-bold">
                            <i class="fa-solid fa-play me-1"></i>Send Test Punch &amp; Sync Now
                        </button>
                    </form>
                </div>
            </div>

            <!-- Clear / Clean Test Logs Card -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-light py-3 border-0 rounded-top-4">
                    <h5 class="fw-bold mb-0 text-danger">
                        <i class="fa-solid fa-trash-can me-2"></i>Reset / Delete Test Data
                    </h5>
                </div>
                <div class="card-body p-4">
                    <p class="small text-muted mb-3">After verifying that your machine connection and database sync works correctly, you can delete all simulated test records and clear diagnostic logs with one click.</p>

                    <form action="{{ Route::has('attendance.clear-test-logs') ? route('attendance.clear-test-logs') : url('/attendance/clear-test-logs') }}" method="POST" onsubmit="return confirm('Are you sure you want to clear test logs?');">
                        @csrf
                        <input type="hidden" name="delete_all_logs" value="1">
                        <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                            <i class="fa-solid fa-broom me-1"></i>Clear Test Logs &amp; Reset Diagnostics
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right: Live Log Feed & Machine Records -->
        <div class="col-lg-7">
            <!-- Connected Devices Status -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-light py-3 border-0 rounded-top-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-hard-drive me-2 text-primary"></i>Detected Physical Attendance Machines
                    </h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Machine Serial (SN)</th>
                                <th>Device IP</th>
                                <th>Firmware</th>
                                <th>Last Ping / Heartbeat</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($devices as $dev)
                            <tr>
                                <td class="fw-bold text-dark"><code>{{ $dev->device_sn }}</code></td>
                                <td>{{ $dev->ip_address ?? '—' }}</td>
                                <td><small class="text-muted">{{ $dev->firmware_version ?? 'ADMS Standalone' }}</small></td>
                                <td>{{ $dev->last_seen_at ? \Carbon\Carbon::parse($dev->last_seen_at)->diffForHumans() : 'Never' }}</td>
                                <td>
                                    <span class="badge bg-success rounded-pill px-3"><i class="fa-solid fa-circle-check me-1"></i>Online</span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-satellite-dish fa-2x mb-2 opacity-50"></i>
                                    <p class="mb-0 small">No physical machine heartbeat recorded yet. Once configured in menu, it will appear here automatically.</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Device Attendance Logs -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-light py-3 border-0 rounded-top-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-list-check me-2 text-primary"></i>Live Received Punch Logs (Last 30)
                    </h5>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="location.reload();">
                        <i class="fa-solid fa-rotate me-1"></i>Refresh
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Employee / PIN</th>
                                <th>Punch Time</th>
                                <th>Type</th>
                                <th>Source Device</th>
                                <th>Sync Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentLogs as $log)
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark">{{ $log->employee_name ?? 'PIN #' . $log->device_user_id }}</div>
                                    <small class="text-muted">{{ $log->employee_department ?? 'User ID: ' . $log->device_user_id }}</small>
                                </td>
                                <td>
                                    <strong>{{ \Carbon\Carbon::parse($log->punch_time)->format('h:i:s A') }}</strong>
                                    <small class="d-block text-muted">{{ \Carbon\Carbon::parse($log->punch_time)->format('d M Y') }}</small>
                                </td>
                                <td>
                                    @if($log->punch_state == 0 || $log->punch_state == 4)
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success rounded-pill px-2">Check In</span>
                                    @else
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger rounded-pill px-2">Check Out</span>
                                    @endif
                                </td>
                                <td><code>{{ $log->device_sn }}</code></td>
                                <td>
                                    @if($log->synced_at)
                                        <span class="badge bg-success rounded-pill px-2"><i class="fa-solid fa-check me-1"></i>Synced to Attendance</span>
                                    @else
                                        <span class="badge bg-warning text-dark rounded-pill px-2"><i class="fa-solid fa-clock me-1"></i>Raw Log</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-clipboard-list fa-3x mb-2 opacity-50"></i>
                                    <p class="mb-0">No punches recorded yet. Use the simulation box on the left to send a test punch!</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
