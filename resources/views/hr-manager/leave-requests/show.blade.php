@extends('layouts.app')
@section('title', 'Leave Request Review - ' . ($leaveRequest->employee->full_name ?? 'Employee'))

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('leave-requests.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <h1 class="h3 mb-0 fw-bold" style="color:var(--brand-800)">
                    <i class="fa-solid fa-calendar-check text-primary me-2"></i>Leave Request Review
                </h1>
            </div>
            <p class="text-muted small mb-0">General Manager &amp; HR Leave Approval &amp; Balance Verification Portal</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            @if ($leaveRequest->isPending() && (Auth::user()->hasAnyRole(['gm', 'general_manager', 'hr_manager', 'hr_officer', 'hr', 'admin', 'global_admin']) || str_contains(strtolower(implode(' ', Auth::user()->getRoleNames()->toArray())), 'gm')))
                <button type="button" class="btn btn-success btn-sm px-3 fw-bold shadow-sm" onclick="approveRequest()">
                    <i class="fa-solid fa-circle-check me-1"></i> Accept / Approve Leave
                </button>
                <button type="button" class="btn btn-danger btn-sm px-3 fw-bold shadow-sm" onclick="rejectRequest()">
                    <i class="fa-solid fa-circle-xmark me-1"></i> Reject with Reason
                </button>
            @endif
            <a href="{{ route('leave-requests.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-list me-1"></i> All Requests
            </a>
        </div>
    </div>

    <div class="row g-4">
        {{-- Left Column: Request Details & Decision --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3 mb-4 overflow-hidden">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-file-lines text-primary me-2"></i>Requisition Information</h6>
                    <div>
                        @if ($leaveRequest->status === 'pending')
                            <span class="badge bg-warning text-dark px-3 py-1.5 rounded-pill fs-6">
                                <i class="fa-solid fa-clock me-1"></i>Pending GM / HR Decision
                            </span>
                        @elseif ($leaveRequest->status === 'approved')
                            <span class="badge bg-success text-white px-3 py-1.5 rounded-pill fs-6">
                                <i class="fa-solid fa-circle-check me-1"></i>Approved &amp; Granted
                            </span>
                        @elseif ($leaveRequest->status === 'rejected')
                            <span class="badge bg-danger text-white px-3 py-1.5 rounded-pill fs-6">
                                <i class="fa-solid fa-circle-xmark me-1"></i>Rejected
                            </span>
                        @else
                            <span class="badge bg-secondary text-white px-3 py-1.5 rounded-pill fs-6">
                                {{ ucfirst($leaveRequest->status) }}
                            </span>
                        @endif
                    </div>
                </div>
                <div class="card-body p-4">

                    {{-- Employee Bar --}}
                    <div class="p-3 bg-light rounded-3 border mb-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <span class="text-muted small d-block">Employee Name</span>
                                <strong class="text-dark fs-6">{{ $leaveRequest->employee->full_name ?? $leaveRequest->employee->name }}</strong>
                                <span class="badge bg-secondary font-monospace d-block mt-1" style="width: fit-content;">{{ $leaveRequest->employee->employee_code }}</span>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted small d-block">Department &amp; Role</span>
                                <strong class="text-dark">{{ $leaveRequest->employee->department ?? 'General' }}</strong>
                                <div class="text-muted small">{{ $leaveRequest->employee->role_title ?? 'Staff' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted small d-block">Assigned Project / Site</span>
                                <strong class="text-dark">{{ $leaveRequest->employee->project->name ?? 'Head Office' }}</strong>
                                <div class="text-muted small">Joined: {{ optional($leaveRequest->employee->date_of_joining)->format('d M Y') ?? 'N/A' }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- Leave Request Schedule & Period --}}
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="p-3 rounded-3 border bg-white h-100">
                                <span class="text-muted small d-block text-uppercase fw-bold">Leave Type</span>
                                <strong class="fs-6 text-primary">{{ $leaveRequest->leaveType->name }}</strong>
                                <span class="badge {{ $leaveRequest->leaveType->is_paid ? 'bg-success' : 'bg-warning text-dark' }} mt-1 d-inline-block">
                                    {{ $leaveRequest->leaveType->is_paid ? 'Paid Leave' : 'Unpaid Leave' }}
                                </span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded-3 border bg-white h-100">
                                <span class="text-muted small d-block text-uppercase fw-bold">Period Requested</span>
                                <div class="fw-bold text-dark mt-1">
                                    {{ optional($leaveRequest->start_date)->format('d M Y') }} &rarr; {{ optional($leaveRequest->end_date)->format('d M Y') }}
                                </div>
                                <small class="text-muted">{{ optional($leaveRequest->start_date)->format('l') }} to {{ optional($leaveRequest->end_date)->format('l') }}</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded-3 border bg-white h-100 text-center" style="background: #f8fafc;">
                                <span class="text-muted small d-block text-uppercase fw-bold">Total Days Requested</span>
                                <div class="fs-3 fw-bold text-primary">{{ $leaveRequest->days_requested }} <span class="fs-6 text-muted">day(s)</span></div>
                            </div>
                        </div>
                    </div>

                    {{-- Reason for Leave --}}
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark"><i class="fa-solid fa-comment-dots text-primary me-1"></i>Employee Reason / Justification</label>
                        <div class="p-3 bg-light rounded-3 border text-dark" style="white-space: pre-wrap; font-size: 0.95rem; line-height: 1.6;">{{ $leaveRequest->reason }}</div>
                    </div>

                    {{-- Attachment if any --}}
                    @if ($leaveRequest->attachment)
                    <div class="mb-4 p-3 bg-info bg-opacity-10 border border-info-subtle rounded-3 d-flex align-items-center justify-content-between">
                        <div class="small">
                            <i class="fa-solid fa-paperclip text-info me-1"></i> <strong>Medical / Supporting Attachment:</strong> {{ basename($leaveRequest->attachment) }}
                        </div>
                        <a href="{{ asset('storage/' . $leaveRequest->attachment) }}" target="_blank" class="btn btn-sm btn-info text-white px-3 shadow-xs">
                            <i class="fa-solid fa-download me-1"></i> Download / View
                        </a>
                    </div>
                    @endif

                    {{-- Approval / Rejection Result Card --}}
                    @if ($leaveRequest->status !== 'pending')
                    <div class="p-3 rounded-3 border mb-3 {{ $leaveRequest->status === 'approved' ? 'bg-success bg-opacity-10 border-success' : 'bg-danger bg-opacity-10 border-danger' }}">
                        <h6 class="fw-bold mb-2 {{ $leaveRequest->status === 'approved' ? 'text-success' : 'text-danger' }}">
                            <i class="fa-solid {{ $leaveRequest->status === 'approved' ? 'fa-circle-check' : 'fa-circle-xmark' }} me-1"></i>
                            Decision Details ({{ ucfirst($leaveRequest->status) }})
                        </h6>
                        <div class="row g-2 small">
                            <div class="col-md-6">
                                <span class="text-muted">Reviewed By:</span>
                                <strong>{{ $leaveRequest->approvedByUser?->name ?? 'Management' }}</strong>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted">Decision Date:</span>
                                <strong>{{ optional($leaveRequest->approved_at)->format('d M Y, h:i A') }}</strong>
                            </div>
                            @if ($leaveRequest->status === 'rejected' && $leaveRequest->rejection_reason)
                            <div class="col-12 mt-2 pt-2 border-top">
                                <span class="text-danger fw-bold d-block mb-1">Rejection Reason:</span>
                                <div class="text-dark bg-white p-2 rounded border">{{ $leaveRequest->rejection_reason }}</div>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif

                </div>

                {{-- Action Bar at bottom --}}
                @if ($leaveRequest->isPending() && (Auth::user()->hasAnyRole(['gm', 'general_manager', 'hr_manager', 'hr_officer', 'hr', 'admin', 'global_admin']) || str_contains(strtolower(implode(' ', Auth::user()->getRoleNames()->toArray())), 'gm')))
                <div class="card-footer bg-light border-top p-3 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-danger px-4 fw-semibold" onclick="rejectRequest()">
                        <i class="fa-solid fa-times me-1"></i> Reject with Reason
                    </button>
                    <button type="button" class="btn btn-success px-4 fw-bold shadow-sm" onclick="approveRequest()">
                        <i class="fa-solid fa-check me-1"></i> Accept &amp; Grant Leave ({{ $leaveRequest->days_requested }} Days)
                    </button>
                </div>
                @endif
            </div>
        </div>

        {{-- Right Column: Official Leave Balance Card (Matching Employee Profile) --}}
        <div class="col-lg-4">
            @php
                $employee = $leaveRequest->employee;
                $currentYear = $leaveRequest->start_date ? $leaveRequest->start_date->year : \Carbon\Carbon::now()->year;
                $annualBal = $currentBalance;
                
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

            <div class="card border-0 shadow-sm rounded-3 mb-4 overflow-hidden border">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
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

                    {{-- Impact Projection --}}
                    <div class="p-2.5 bg-white rounded-2 border mt-3 small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Days to Deduct:</span>
                            <strong class="text-danger">-{{ $leaveRequest->days_requested }} day(s)</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Balance After Leave:</span>
                            <strong class="{{ ($remainingDays - $leaveRequest->days_requested) >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($remainingDays - $leaveRequest->days_requested, 1) }} day(s)
                            </strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-success text-white py-3 px-4">
                <h5 class="modal-title fw-bold mb-0"><i class="fa-solid fa-circle-check me-2"></i>Approve Leave Request</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('leave-requests.approve', $leaveRequest->id) }}">
                @csrf
                <div class="modal-body p-4 bg-white">
                    <p class="text-dark mb-3">Are you sure you want to approve this leave request for <strong>{{ $leaveRequest->employee->full_name ?? $leaveRequest->employee->name }}</strong>?</p>
                    <div class="p-3 bg-light rounded-3 border small">
                        <div><strong>Leave Type:</strong> {{ $leaveRequest->leaveType->name }}</div>
                        <div><strong>Duration:</strong> {{ $leaveRequest->days_requested }} day(s) ({{ optional($leaveRequest->start_date)->format('d M Y') }} &rarr; {{ optional($leaveRequest->end_date)->format('d M Y') }})</div>
                        @if($currentBalance)
                        <div class="mt-1 text-success"><strong>Remaining Balance After:</strong> {{ number_format($currentBalance->remaining_days - $leaveRequest->days_requested, 1) }} day(s)</div>
                        @endif
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3 px-4">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                        <i class="fa-solid fa-check me-1"></i> Confirm &amp; Grant Leave
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white py-3 px-4">
                <h5 class="modal-title fw-bold mb-0"><i class="fa-solid fa-circle-xmark me-2"></i>Reject Leave Request</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('leave-requests.reject', $leaveRequest->id) }}">
                @csrf
                <div class="modal-body p-4 bg-white">
                    <p class="text-dark mb-2">Please specify the reason for rejecting <strong>{{ $leaveRequest->employee->full_name ?? $leaveRequest->employee->name }}</strong>'s leave request:</p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark text-uppercase">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="4" placeholder="e.g. Critical project deadline on site during this week, insufficient manpower coverage..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3 px-4">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm">
                        <i class="fa-solid fa-xmark me-1"></i> Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function approveRequest() {
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}
function rejectRequest() {
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}
</script>
@endsection


