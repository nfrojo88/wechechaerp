@extends('layouts.app')
@section('title', 'Attendance Management')
@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="fas fa-calendar-check me-2 text-primary"></i>Attendance Management</h1>
            <p class="text-muted mt-1">Track and manage employee attendance records</p>
        </div>
        <div class="btn-group" role="group">
            <a href="{{ route('attendance.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>Record Attendance
            </a>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importDeviceModal">
                <i class="fas fa-file-excel me-1"></i>Bulk Upload (XLS / Biometric)
            </button>
            <a href="{{ route('attendance.deviceLogs') }}" class="btn btn-outline-info">
                <i class="fa-solid fa-fingerprint me-1"></i>Device Logs
            </a>
            <form action="{{ route('attendance.clearHistory') }}" method="POST" class="d-inline"
                  onsubmit="return confirm('⚠️ Are you sure you want to completely clear and wipe all previous attendance history? This will remove all records so you can start from scratch.');">
                @csrf
                <button type="submit" class="btn btn-outline-danger">
                    <i class="fas fa-trash-alt me-1"></i>Clear History
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <!-- Filter Section -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('attendance.index') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Specific Date</label>
                    <input type="date" name="date" class="form-control" value="{{ request('date', $selectedDate ?? '') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Employee / Device ID</label>
                    <input type="text" name="employee" class="form-control" placeholder="Name, code, or device ID..." value="{{ request('employee') }}">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                    <a href="{{ route('attendance.index') }}" class="btn btn-outline-secondary" title="Reset Filters">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Quick Date Navigator (Shows all uploaded dates) --}}
    @if(isset($availableDates) && $availableDates->count() > 0)
    <div class="card border-0 shadow-sm mb-4 bg-white">
        <div class="card-body py-2 px-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary rounded-circle p-2"><i class="far fa-calendar-alt text-white"></i></span>
                    <div>
                        <span class="fw-bold small text-dark d-block">Jump to Uploaded Date:</span>
                        <small class="text-muted">Click any date to see that day's attendance</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-1 flex-wrap">
                    <a href="{{ route('attendance.index') }}" 
                       class="btn btn-sm {{ !request('date') && !request('date_from') ? 'btn-dark' : 'btn-outline-secondary' }} rounded-pill px-3">
                        <i class="fas fa-list me-1"></i>All Dates
                    </a>
                    @foreach($availableDates as $dt)
                        @php
                            $isActive = request('date') === $dt || (request('date_from') === $dt && request('date_to') === $dt);
                            $cDate = \Carbon\Carbon::parse($dt);
                        @endphp
                        <a href="{{ route('attendance.index', ['date' => $dt]) }}" 
                           class="btn btn-sm {{ $isActive ? 'btn-primary shadow fw-bold' : 'btn-outline-primary' }} rounded-pill px-3">
                            <i class="far fa-calendar-check me-1"></i>{{ $cDate->format('M d, Y') }} ({{ $cDate->format('D') }})
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Statistics Cards -->
    <div class="row mb-4">
        @php
            $targetDateFormatted = isset($stats['date']) ? \Carbon\Carbon::parse($stats['date'])->format('M d, Y') : 'Today';
        @endphp
        <div class="col-md-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body py-2">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                        Present on {{ $targetDateFormatted }}
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        {{ $stats['present'] ?? 0 }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body py-2">
                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                        Absent on {{ $targetDateFormatted }}
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        {{ $stats['absent'] ?? 0 }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body py-2">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                        Half Day on {{ $targetDateFormatted }}
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        {{ $stats['half_day'] ?? 0 }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body py-2">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                        Leave on {{ $targetDateFormatted }}
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        {{ $stats['leave'] ?? 0 }}
                    </div>
                </div>
            </div>
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
                                        $devId = $a->employee->device_user_id ?: $a->biometric_device_id;
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
                            </td>
                            <td class="text-nowrap">
                                <a href="{{ route('attendance.index', ['date' => $a->attendance_date->format('Y-m-d')]) }}" 
                                   class="fw-bold text-primary text-decoration-none" title="Click to view all records for {{ $a->attendance_date->format('M d, Y') }}">
                                    <i class="far fa-calendar-alt me-1"></i>{{ $a->attendance_date->format('M d, Y') }}
                                </a>
                                <br><small class="text-muted">{{ $a->attendance_date->format('l') }}</small>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    @if($a->morning_in)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" title="Morning Clock In">
                                            <i class="fas fa-arrow-right me-1"></i>{{ substr($a->morning_in, 0, 5) }}
                                        </span>
                                    @else
                                        <span class="badge bg-light text-muted border px-2 py-1" title="No Morning Clock In">—</span>
                                    @endif
                                    <span class="text-muted small">&bull;</span>
                                    @if($a->morning_out)
                                        <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1" title="Morning Clock Out">
                                            <i class="fas fa-arrow-left me-1"></i>{{ substr($a->morning_out, 0, 5) }}
                                        </span>
                                    @else
                                        <span class="badge bg-light text-muted border px-2 py-1" title="No Morning Clock Out">—</span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    @if($a->afternoon_in)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" title="Afternoon Clock In">
                                            <i class="fas fa-arrow-right me-1"></i>{{ substr($a->afternoon_in, 0, 5) }}
                                        </span>
                                    @else
                                        <span class="badge bg-light text-muted border px-2 py-1" title="No Afternoon Clock In">—</span>
                                    @endif
                                    <span class="text-muted small">&bull;</span>
                                    @if($a->afternoon_out)
                                        <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1" title="Afternoon Clock Out">
                                            <i class="fas fa-arrow-left me-1"></i>{{ substr($a->afternoon_out, 0, 5) }}
                                        </span>
                                    @else
                                        <span class="badge bg-light text-muted border px-2 py-1" title="No Afternoon Clock Out">—</span>
                                    @endif
                                </div>
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
                                @endphp
                                <span class="badge bg-{{ $statusColors[$a->status] ?? 'secondary' }}">
                                    {{ ucfirst(str_replace('_', ' ', $a->status)) }}
                                </span>
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
                                <span class="badge bg-light text-secondary border">{{ ucfirst(str_replace('_', ' ', $a->source)) }}</span>
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
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
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
                        <div id="fileNameDisplay" class="mt-2 text-success d-none">
                            <i class="fas fa-check-circle me-1"></i><span id="fileNameText"></span>
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
                                                    <td><strong class="text-dark">ZKTeco Device User ID</strong> <span class="badge bg-success ms-1">Primary Link</span></td>
                                                    <td>Matches numeric ID assigned in biometric device (e.g. <code>1</code>, <code>2</code>, <code>17</code>, <code>21</code>, <code>50</code>) set on the Employee Profile.</td>
                                                </tr>
                                                <tr class="table-light">
                                                    <td><strong class="text-primary"><i class="far fa-calendar-alt me-1"></i>Date</strong></td>
                                                    <td><strong class="text-dark">Attendance Date</strong> <span class="badge bg-primary ms-1">Required</span></td>
                                                    <td>Punches are recorded for each specific day from this column (e.g. <code>9/2/2026</code>, <code>2026-09-02</code>).</td>
                                                </tr>
                                                <tr>
                                                    <td><code>Emp No.</code></td>
                                                    <td>Employee Code (Fallback)</td>
                                                    <td>Alternative match against Employee Code (e.g. <code>EMP-20</code>, <code>78</code>).</td>
                                                </tr>
                                                <tr>
                                                    <td><code>Name</code></td>
                                                    <td>Employee Name (Fallback)</td>
                                                    <td>Fallback match against Employee Full Name (e.g. <code>Mekdes...Alemu</code>).</td>
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
                                    <div class="alert alert-info mt-3 mb-0 py-2 small">
                                        <i class="fas fa-lightbulb me-1"></i>
                                        <strong>How linking works:</strong> Set each employee's <strong>ZKTeco Device User ID</strong> on their profile to match their <strong>AC-No.</strong> in the machine (e.g. 1, 2, 21). When you upload the file, all punches are automatically matched and saved under each row's <strong>Date</strong>!
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

                    {{-- Start from Scratch Option --}}
                    <div class="form-check form-switch mt-3 p-3 bg-light rounded-3 border border-warning">
                        <input class="form-check-input ms-0 me-2" type="checkbox" name="clear_before_import" value="1" id="clearBeforeImport">
                        <label class="form-check-label fw-semibold text-danger" for="clearBeforeImport">
                            <i class="fas fa-trash-alt me-1"></i>Wipe / Clear previous attendance history before uploading
                        </label>
                        <div class="text-muted small ps-4">Enable this to delete all prior attendance records and start completely fresh from scratch with this file.</div>
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

@push('scripts')
<script>
// File drop zone interaction
const dropZone = document.getElementById('uploadDropZone');
const fileInput = document.getElementById('attendanceFile');
const fileDisplay = document.getElementById('fileNameDisplay');
const fileNameText = document.getElementById('fileNameText');
const importForm = document.getElementById('importDeviceForm');
const submitBtn = document.getElementById('importSubmitBtn');

fileInput.addEventListener('change', function() {
    if (this.files.length > 0) {
        const file = this.files[0];
        fileNameText.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
        fileDisplay.classList.remove('d-none');
        dropZone.style.borderColor = '#28a745';
        dropZone.style.background = '#f0fff4';
    }
});

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

importForm.addEventListener('submit', function() {
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Importing...';
});

// Auto-open modal if there was a warning/error from import
@if(session('warning') || session('error'))
    var importModal = new bootstrap.Modal(document.getElementById('importDeviceModal'));
    importModal.show();
@endif
</script>
@endpush

@endsection
