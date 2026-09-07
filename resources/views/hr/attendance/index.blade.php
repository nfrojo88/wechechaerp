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
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <!-- Filter Section -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('attendance.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Employee</label>
                    <input type="text" name="employee" class="form-control" placeholder="Search employee..." value="{{ request('employee') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        <option value="present" @selected(request('status')=='present')>Present</option>
                        <option value="absent" @selected(request('status')=='absent')>Absent</option>
                        <option value="half_day" @selected(request('status')=='half_day')>Half Day</option>
                        <option value="leave" @selected(request('status')=='leave')>Leave</option>
                        <option value="holiday" @selected(request('status')=='holiday')>Holiday</option>
                    </select>
                </div>
                <div class="col-md-1 d-flex gap-2 align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Present Today</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        {{ \App\Models\Attendance::whereDate('attendance_date', now())->where('status', 'present')->count() }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Absent Today</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        {{ \App\Models\Attendance::whereDate('attendance_date', now())->where('status', 'absent')->count() }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">On Leave Today</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        {{ \App\Models\Attendance::whereDate('attendance_date', now())->where('status', 'leave')->count() }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Half Day Today</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        {{ \App\Models\Attendance::whereDate('attendance_date', now())->where('status', 'half_day')->count() }}
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
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th class="text-center">Hours</th>
                            <th>Status</th>
                            <th class="text-center">OT Hrs</th>
                            <th class="text-end">OT Pay</th>
                            <th>Source</th>
                            <th class="text-center">Approved</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($attendances as $a)
                        <tr>
                            <td>
                                <strong>{{ $a->employee->full_name ?? $a->employee->first_name . ' ' . $a->employee->last_name }}</strong>
                                <br><small class="text-muted">{{ $a->employee->employee_code ?? 'N/A' }}</small>
                            </td>
                            <td>{{ $a->attendance_date->format('M d, Y') }}</td>
                            <td>
                                @if($a->check_in)
                                    <span class="badge bg-info">{{ $a->check_in }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($a->check_out)
                                    <span class="badge bg-info">{{ $a->check_out }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($a->hours_worked)
                                    <strong>{{ $a->hours_worked }}h</strong>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
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
                                @if(($a->overtime_hours ?? 0) > 0)
                                    <span class="badge bg-warning text-dark">{{ $a->overtime_hours }}h</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if(($a->overtime_pay ?? 0) > 0)
                                    @php
                                        $otLabels = ['holiday'=>'Holiday×2.5','rest_day'=>'Rest×2.0','night_12_4'=>'Night×1.5','night_4_12'=>'Night×1.75'];
                                    @endphp
                                    <span class="fw-bold text-warning" title="{{ $otLabels[$a->overtime_type] ?? '' }}">
                                        {{ number_format($a->overtime_pay, 2) }}
                                    </span>
                                    <br><small class="text-muted">{{ $otLabels[$a->overtime_type] ?? '' }}</small>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <small class="text-muted">{{ ucfirst($a->source) }}</small>
                            </td>
                            <td class="text-center">
                                @if($a->is_approved)
                                    <span class="badge bg-success">
                                        <i class="fas fa-check me-1"></i>Approved
                                    </span>
                                @else
                                    <span class="badge bg-warning">
                                        <i class="fas fa-hourglass-half me-1"></i>Pending
                                    </span>
                                @endif
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
                                        <table class="table table-sm table-bordered small mb-0">
                                            <thead class="table-primary">
                                                <tr>
                                                    <th>Column (XLS)</th>
                                                    <th>System Field</th>
                                                    <th>Example</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr><td><code>Emp No.</code></td><td>Employee Code</td><td>EMP001, 78</td></tr>
                                                <tr><td><code>Name</code></td><td>Employee Name (fallback)</td><td>John Doe</td></tr>
                                                <tr><td><code>Date</code></td><td>Attendance Date</td><td>9/2/2026</td></tr>
                                                <tr><td><code>Timetable</code></td><td>Session</td><td>Morning / Afternoon</td></tr>
                                                <tr><td><code>Clock In</code></td><td>Check-in time</td><td>08:25</td></tr>
                                                <tr><td><code>Clock Out</code></td><td>Check-out time</td><td>17:35</td></tr>
                                                <tr><td><code>Late</code></td><td>Late minutes (note)</td><td>15</td></tr>
                                                <tr><td><code>OT Time</code></td><td>Overtime hours</td><td>1.5</td></tr>
                                                <tr><td><code>Absent</code></td><td>Absent flag</td><td>True / False</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="alert alert-info mt-3 mb-0 py-2 small">
                                        <i class="fas fa-lightbulb me-1"></i>
                                        <strong>Note:</strong> Morning + Afternoon sessions for the same employee and date are automatically merged into a single attendance record.
                                        Employee matching works automatically via <strong>Emp No.</strong>, <strong>Device User ID</strong>, or <strong>Employee Full Name</strong>.
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
