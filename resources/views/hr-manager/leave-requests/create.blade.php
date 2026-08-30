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
                @if($isHrOrGm)
                    <a href="{{ route('leave-requests.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-arrow-left me-1"></i> All Leave Requests
                    </a>
                @else
                    <a href="{{ route('leave-requests.my-requests') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-clock-rotate-left me-1"></i> My Leave History
                    </a>
                @endif
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

                        @php
                            $currentYear = \Carbon\Carbon::now()->year;
                            $annualType = $leaveTypes->firstWhere('code', 'ANNUAL') ?? $leaveTypes->first();
                            $annualBal = $balances->firstWhere('leave_type_id', $annualType?->id) ?? $balances->first();
                            
                            $joinDate = $employee->date_of_joining ? \Carbon\Carbon::parse($employee->date_of_joining) : null;
                            $joinYear = $joinDate ? $joinDate->year : $currentYear;
                            $monthsActive = 12;
                            if ($joinDate && $joinYear === $currentYear) {
                                $monthsActive = max(1, min(12, 12 - $joinDate->month + 1));
                            } elseif ($joinDate && $joinYear < $currentYear) {
                                $monthsActive = 12;
                            }
                            $accruedDays = round(($monthsActive * (16.0 / 12)), 2);
                            $totalDays = $annualBal ? (float)$annualBal->total_days : 16.0;
                            $usedDays = $annualBal ? (float)$annualBal->used_days : 0.0;
                            $remainingDays = $annualBal ? (float)$annualBal->remaining_days : 16.0;
                            $usedPct = $totalDays > 0 ? min(100, round(($usedDays / $totalDays) * 100)) : 0;
                        @endphp

                        <!-- Official Leave Balance Card -->
                        <div class="card border-0 shadow-sm rounded-3 mb-4 border" style="background:#fff;">
                            <div class="card-header bg-white border-bottom py-2.5 px-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-umbrella-beach text-info me-2"></i>Leave Balance {{ $currentYear }}</h6>
                                    <small class="text-muted" style="font-size:0.75rem;">16 Days/Year (1.33 Days/Month)</small>
                                </div>
                            </div>
                            <div class="card-body p-3">
                                {{-- Active Balance Display --}}
                                <div class="bg-light rounded-3 p-3 text-center mb-3 border">
                                    <span class="text-muted small fw-semibold text-uppercase d-block" style="letter-spacing:0.05em; font-size:0.72rem;">Available Balance</span>
                                    <h2 class="fw-bold text-primary mb-0 font-monospace">{{ number_format($remainingDays, 2) }} <span class="fs-6 text-muted fw-normal">Days</span></h2>
                                    <div class="badge bg-primary bg-opacity-10 text-primary mt-1 font-monospace" style="font-size:0.75rem;">
                                        Accrued to Date: {{ number_format($accruedDays, 2) }} Days ({{ $monthsActive }} Mos)
                                    </div>
                                </div>

                                {{-- Progress Bar --}}
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1 small">
                                        <span class="text-muted fw-semibold"><i class="fa-solid fa-plane-departure text-info me-1"></i>Annual Leave</span>
                                        <span class="fw-bold text-dark">{{ number_format($usedDays, 1) }} used / {{ number_format($totalDays, 1) }} total</span>
                                    </div>
                                    <div class="progress rounded-pill" style="height:10px;">
                                        <div class="progress-bar {{ $usedPct > 80 ? 'bg-danger' : ($usedPct > 50 ? 'bg-warning' : 'bg-info') }}" style="width:{{ $usedPct }}%;"></div>
                                    </div>
                                    <div class="d-flex justify-content-between mt-1" style="font-size:0.75rem;">
                                        <span class="text-success fw-semibold">{{ number_format($remainingDays, 1) }} days remaining</span>
                                        <span class="text-muted">{{ $usedPct }}% taken</span>
                                    </div>
                                </div>

                                {{-- Calculation Metadata --}}
                                <div class="p-2.5 bg-light rounded-2 small text-muted" style="font-size:0.75rem;">
                                    <div class="d-flex justify-content-between py-1 border-bottom border-light">
                                        <span>Standard Quota:</span>
                                        <strong class="text-dark">16.0 Days / Year</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-1 border-bottom border-light">
                                        <span>Monthly Accrual:</span>
                                        <strong class="text-dark">1.33 Days / Month</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-1">
                                        <span>Service Since:</span>
                                        <strong class="text-dark">{{ optional($joinDate)->format('d M Y') ?? 'N/A' }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Leave Type -->
                        <div class="mb-3">
                            <label for="leave_type_id" class="form-label fw-bold text-dark">Leave Type <span class="text-danger">*</span></label>
                            <select name="leave_type_id" id="leave_type_id" class="form-select @error('leave_type_id') is-invalid @enderror" required>
                                <option value="">-- Choose Leave Type --</option>
                                @foreach ($leaveTypes as $type)
                                    <option value="{{ $type->id }}" {{ old('leave_type_id', $annualType?->id) == $type->id ? 'selected' : '' }}>
                                        {{ $type->name }}
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
    const daysText = document.getElementById('daysText');
    const balanceAlert = document.getElementById('balanceAlert');
    const annualRemaining = {{ (float) $remainingDays }};

    function calculate() {
        const start = startDateInput.value ? new Date(startDateInput.value) : null;
        const end = endDateInput.value ? new Date(endDateInput.value) : null;

        if (start && end && end >= start) {
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            daysText.innerText = diffDays + ' day(s)';

            if (diffDays > annualRemaining) {
                balanceAlert.innerHTML = `<span class="text-danger fw-bold"><i class="fa-solid fa-triangle-exclamation me-1"></i>Requested duration (${diffDays} days) exceeds available balance (${annualRemaining} days)!</span>`;
            } else {
                balanceAlert.innerHTML = `<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i>Within balance (${(annualRemaining - diffDays).toFixed(1)} remaining after leave)</span>`;
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
});
</script>
@endsection


