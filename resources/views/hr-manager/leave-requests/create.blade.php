@extends('layouts.app')
@section('title', 'Ask / Request Leave - ' . config('app.name'))

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="h3 mb-0 fw-bold" style="color:var(--brand-800)">
                        <i class="fa-solid fa-calendar-plus text-primary me-2"></i>Ask / Request Leave
                    </h1>
                    <p class="text-muted small mb-0">Submit a leave requisition for General Manager &amp; HR approval</p>
                </div>
                <a href="{{ route('leave-requests.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Requests
                </a>
            </div>

            <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                <div class="card-header bg-primary text-white py-3 px-4">
                    <h5 class="mb-0 fw-bold">
                        <i class="fa-solid fa-pen-to-square me-2"></i>Leave Application Form
                    </h5>
                </div>
                <div class="card-body p-4 bg-white">
                    <form method="POST" action="{{ route('leave-requests.store') }}" enctype="multipart/form-data">
                        @csrf

                        <!-- Employee Info / Selection -->
                        @if($isHrOrGm && $allEmployees->count() > 1)
                            <div class="mb-4 p-3 bg-light rounded-3 border">
                                <label class="form-label fw-bold text-dark mb-1">
                                    <i class="fa-solid fa-user-gear text-primary me-1"></i>Applying For Employee (Management Selection)
                                </label>
                                <select name="employee_id" id="employee_select" class="form-select select2" onchange="window.location.href='{{ route('leave-requests.create') }}?employee_id=' + this.value">
                                    @foreach($allEmployees as $emp)
                                        <option value="{{ $emp->id }}" {{ $employee->id == $emp->id ? 'selected' : '' }}>
                                            {{ $emp->full_name ?? $emp->name }} — {{ $emp->employee_code }} ({{ $emp->department ?? 'General' }} - {{ $emp->role_title ?? 'Staff' }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                            <div class="mb-4 p-3 bg-light rounded-3 border">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <span class="text-muted small d-block">Employee Name &amp; Code</span>
                                        <strong class="text-dark fs-6">{{ $employee->full_name ?? $employee->name }}</strong>
                                        <span class="badge bg-secondary ms-2 font-monospace">{{ $employee->employee_code }}</span>
                                    </div>
                                    <div class="col-md-6">
                                        <span class="text-muted small d-block">Department &amp; Role</span>
                                        <strong class="text-dark">{{ $employee->department ?? 'General' }}</strong> — <span class="text-muted">{{ $employee->role_title ?? 'Staff' }}</span>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Live Available Leave Balances Box -->
                        @if ($balances->count() > 0)
                            <div class="mb-4 p-3 rounded-3 border" style="background: #f8fafc;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="fw-bold mb-0 text-dark">
                                        <i class="fa-solid fa-chart-pie text-primary me-1"></i>Available Leave Balance for Current Year ({{ date('Y') }})
                                    </h6>
                                    <span class="badge bg-info bg-opacity-10 text-info border border-info-subtle">Real-Time Quota</span>
                                </div>
                                <div class="row g-2">
                                    @foreach ($balances as $balance)
                                        <div class="col-md-6">
                                            <div class="p-2.5 bg-white rounded-2 border h-100 d-flex justify-content-between align-items-center">
                                                <div>
                                                    <span class="small fw-semibold text-dark d-block">{{ $balance->leaveType->name }}</span>
                                                    <small class="text-muted" style="font-size: 0.72rem;">Used: {{ number_format($balance->used_days, 0) }} / Total: {{ number_format($balance->total_days, 0) }}</small>
                                                </div>
                                                <span class="badge {{ $balance->remaining_days > 0 ? 'bg-success' : 'bg-danger' }} rounded-pill font-monospace fs-6 px-2.5">
                                                    {{ number_format($balance->remaining_days, 1) }} Left
                                                </span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Leave Type -->
                        <div class="mb-3">
                            <label for="leave_type_id" class="form-label fw-bold text-dark">Leave Type <span class="text-danger">*</span></label>
                            <select name="leave_type_id" id="leave_type_id" class="form-select @error('leave_type_id') is-invalid @enderror" required>
                                <option value="">-- Choose Leave Type --</option>
                                @foreach ($leaveTypes as $type)
                                    @php
                                        $b = $balances->firstWhere('leave_type_id', $type->id);
                                        $rem = $b ? $b->remaining_days : $type->days_allowed;
                                    @endphp
                                    <option value="{{ $type->id }}" data-remaining="{{ $rem }}" {{ old('leave_type_id') == $type->id ? 'selected' : '' }}>
                                        {{ $type->name }} ({{ number_format($rem, 1) }} days available | {{ $type->is_paid ? 'Paid' : 'Unpaid' }})
                                    </option>
                                @endforeach
                            </select>
                            @error('leave_type_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Date Range -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="start_date" class="form-label fw-bold text-dark">Start Date (From) <span class="text-danger">*</span></label>
                                <input type="date" name="start_date" id="start_date" 
                                       class="form-control @error('start_date') is-invalid @enderror" 
                                       value="{{ old('start_date') }}" required>
                                @error('start_date')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="end_date" class="form-label fw-bold text-dark">End Date (To) <span class="text-danger">*</span></label>
                                <input type="date" name="end_date" id="end_date" 
                                       class="form-control @error('end_date') is-invalid @enderror" 
                                       value="{{ old('end_date') }}" required>
                                @error('end_date')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Total Days Requested Preview -->
                        <div class="mb-4 p-3 bg-light rounded-3 border d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small d-block">Calculated Duration:</span>
                                <strong class="fs-5 text-primary" id="daysText">0 day(s)</strong>
                            </div>
                            <div id="balanceAlert" class="small text-muted"></div>
                        </div>

                        <!-- Reason -->
                        <div class="mb-3">
                            <label for="reason" class="form-label fw-bold text-dark">Reason / Justification for Leave <span class="text-danger">*</span></label>
                            <textarea name="reason" id="reason" 
                                      class="form-control @error('reason') is-invalid @enderror" 
                                      rows="4" placeholder="Describe the reason for your leave request in detail..." required>{{ old('reason') }}</textarea>
                            @error('reason')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Attachment -->
                        <div class="mb-4">
                            <label for="attachment" class="form-label fw-bold text-dark">Supporting Document / Medical Note (Optional)</label>
                            <input type="file" name="attachment" id="attachment" 
                                   class="form-control @error('attachment') is-invalid @enderror"
                                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            <small class="text-muted">Accepted formats: PDF, DOC, DOCX, JPG, PNG (Max 5MB)</small>
                            @error('attachment')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Submit Buttons -->
                        <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                            <a href="{{ route('leave-requests.index') }}" class="btn btn-outline-secondary px-4 rounded-pill">
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-primary px-4 fw-bold rounded-pill shadow-sm">
                                <i class="fa-solid fa-paper-plane me-1"></i> Submit Leave Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const leaveTypeSelect = document.getElementById('leave_type_id');
    const daysText = document.getElementById('daysText');
    const balanceAlert = document.getElementById('balanceAlert');

    function calculate() {
        const start = startDateInput.value ? new Date(startDateInput.value) : null;
        const end = endDateInput.value ? new Date(endDateInput.value) : null;

        if (start && end && end >= start) {
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            daysText.innerText = diffDays + ' day(s)';

            const selectedOption = leaveTypeSelect.options[leaveTypeSelect.selectedIndex];
            const remaining = selectedOption ? parseFloat(selectedOption.getAttribute('data-remaining') || '999') : 999;

            if (diffDays > remaining) {
                balanceAlert.innerHTML = `<span class="text-danger fw-bold"><i class="fa-solid fa-triangle-exclamation me-1"></i>Warning: Request exceeds available balance (${remaining} days)!</span>`;
            } else {
                balanceAlert.innerHTML = `<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i>Within available balance (${remaining - diffDays} remaining after leave)</span>`;
            }
        } else {
            daysText.innerText = '0 day(s)';
            balanceAlert.innerHTML = '';
        }
    }

    startDateInput.addEventListener('change', function() {
        endDateInput.min = this.value;
        if (endDateInput.value && endDateInput.value < this.value) {
            endDateInput.value = this.value;
        }
        calculate();
    });

    endDateInput.addEventListener('change', calculate);
    leaveTypeSelect.addEventListener('change', calculate);
});
</script>
@endsection

