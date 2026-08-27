@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="h3 mb-0 fw-bold">
                <i class="fa-solid fa-calendar-check text-primary me-2"></i>Leave Request Management
            </h2>
            <small class="text-muted">General Manager &amp; HR Leave Approval Portal</small>
        </div>
        <div class="col-md-4 text-end d-flex justify-content-end gap-2">
            <a href="{{ route('leave-requests.create') }}" class="btn btn-primary btn-sm shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> Ask / Request Leave
            </a>
            <a href="{{ route('leave-requests.export') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-download me-1"></i> Export Report
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4 g-3">
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-3 bg-white" style="border-left: 4px solid #f59e0b !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold">Pending Review</span>
                            <h3 class="fw-bold text-warning mb-0">{{ $leaveRequests->where('status', 'pending')->count() }}</h3>
                        </div>
                        <div class="p-2 rounded-circle bg-warning bg-opacity-10 text-warning">
                            <i class="fa-solid fa-clock fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-3 bg-white" style="border-left: 4px solid #10b981 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold">Approved</span>
                            <h3 class="fw-bold text-success mb-0">{{ $leaveRequests->where('status', 'approved')->count() }}</h3>
                        </div>
                        <div class="p-2 rounded-circle bg-success bg-opacity-10 text-success">
                            <i class="fa-solid fa-circle-check fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-3 bg-white" style="border-left: 4px solid #ef4444 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold">Rejected</span>
                            <h3 class="fw-bold text-danger mb-0">{{ $leaveRequests->where('status', 'rejected')->count() }}</h3>
                        </div>
                        <div class="p-2 rounded-circle bg-danger bg-opacity-10 text-danger">
                            <i class="fa-solid fa-circle-xmark fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-3 bg-white" style="border-left: 4px solid #0ea5e9 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold">This Month</span>
                            <h3 class="fw-bold text-info mb-0">{{ $leaveRequests->filter(fn($r) => $r->created_at && $r->created_at->isCurrentMonth())->count() }}</h3>
                        </div>
                        <div class="p-2 rounded-circle bg-info bg-opacity-10 text-info">
                            <i class="fa-solid fa-calendar-days fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending Review</option>
                        <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Employee</label>
                    <select name="employee_id" class="form-select form-select-sm select2">
                        <option value="">All Employees</option>
                        @foreach ($employees as $emp)
                            <option value="{{ $emp->id }}" {{ request('employee_id') == $emp->id ? 'selected' : '' }}>
                                {{ $emp->full_name ?? $emp->name }} ({{ $emp->employee_code }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Leave Type</label>
                    <select name="leave_type_id" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        @foreach ($leaveTypes as $type)
                            <option value="{{ $type->id }}" {{ request('leave_type_id') == $type->id ? 'selected' : '' }}>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2 pt-3">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    <a href="{{ route('leave-requests.index') }}" class="btn btn-outline-secondary btn-sm">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Leave Requests Table -->
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th width="4%" class="ps-3">
                            <input type="checkbox" id="selectAll" class="form-check-input">
                        </th>
                        <th>Employee</th>
                        <th>Leave Type</th>
                        <th>Period</th>
                        <th>Days Req.</th>
                        <th>Available Days (Balance)</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($leaveRequests as $request)
                        @php
                            $bal = $request->balance ?? null;
                            $remDays = $bal ? $bal->remaining_days : null;
                        @endphp
                        <tr>
                            <td class="ps-3">
                                <input type="checkbox" class="form-check-input request-checkbox" value="{{ $request->id }}">
                            </td>
                            <td>
                                <strong class="text-dark">{{ $request->employee->full_name ?? $request->employee->name }}</strong>
                                <br>
                                <span class="badge bg-secondary font-monospace" style="font-size: 0.72rem;">{{ $request->employee->employee_code }}</span>
                                <small class="text-muted ms-1">{{ $request->employee->department ?? '' }}</small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $request->leaveType->name }}</span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark small">{{ optional($request->start_date)->format('d M Y') }} &rarr; {{ optional($request->end_date)->format('d M Y') }}</div>
                                <small class="text-muted">Requested: {{ optional($request->created_at)->format('d M Y') }}</small>
                            </td>
                            <td>
                                <span class="badge bg-primary fs-6 px-2.5 py-1">{{ $request->days_requested }} d</span>
                            </td>
                            <td>
                                @if($remDays !== null)
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge {{ $remDays >= $request->days_requested ? 'bg-success' : 'bg-danger' }} px-2 py-1 font-monospace">
                                            {{ number_format($remDays, 1) }} days left
                                        </span>
                                        @if($bal)
                                        <small class="text-muted" style="font-size:0.75rem;">(of {{ number_format($bal->total_days, 0) }})</small>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-muted small">Standard Quota</span>
                                @endif
                            </td>
                            <td>
                                @if ($request->status === 'pending')
                                    <span class="badge bg-warning text-dark px-2 py-1"><i class="fa-solid fa-clock me-1"></i>Pending Review</span>
                                @elseif ($request->status === 'approved')
                                    <span class="badge bg-success px-2 py-1"><i class="fa-solid fa-check me-1"></i>Approved</span>
                                    <small class="text-muted d-block" style="font-size:0.72rem;">by {{ $request->approvedByUser?->name ?? 'GM' }}</small>
                                @elseif ($request->status === 'rejected')
                                    <span class="badge bg-danger px-2 py-1"><i class="fa-solid fa-xmark me-1"></i>Rejected</span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst($request->status) }}</span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('leave-requests.show', $request->id) }}" class="btn btn-outline-primary" title="View Details & Decision">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    @if ($request->isPending())
                                        <button type="button" class="btn btn-success" onclick="approveRequestModal('{{ $request->id }}', '{{ addslashes($request->employee->full_name ?? $request->employee->name) }}', '{{ $request->days_requested }}')" title="Approve Leave">
                                            <i class="fa-solid fa-check"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger" onclick="rejectRequestModal('{{ $request->id }}', '{{ addslashes($request->employee->full_name ?? $request->employee->name) }}')" title="Reject Leave">
                                            <i class="fa-solid fa-times"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-calendar-xmark fa-2x mb-2 opacity-50"></i>
                                <p class="mb-0">No leave requests found matching the criteria.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>


    <!-- Pagination -->
    <div class="mt-4">
        {{ $leaveRequests->links() }}
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
            <form method="POST" id="approveForm">
                @csrf
                <div class="modal-body p-4 bg-white">
                    <p class="text-dark mb-3">Are you sure you want to approve the leave request for <strong id="approveEmployeeName"></strong>?</p>
                    <div class="alert alert-info py-2 px-3 small rounded-3 mb-0" id="approveDetails">
                        <i class="fa-solid fa-calendar me-1"></i> <span id="approveDaysCount"></span>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3 px-4">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                        <i class="fa-solid fa-check me-1"></i> Approve &amp; Grant Leave
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
            <form method="POST" id="rejectForm">
                @csrf
                <div class="modal-body p-4 bg-white">
                    <p class="text-dark mb-2">Please specify the reason for rejecting <strong id="rejectEmployeeName"></strong>'s leave request:</p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark text-uppercase">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="4" placeholder="e.g. Inadequate shift coverage, urgent project delivery requirement..." required></textarea>
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
function approveRequestModal(requestId, employeeName, days) {
    const form = document.getElementById('approveForm');
    form.action = `/leave-requests/${requestId}/approve`;
    document.getElementById('approveEmployeeName').innerText = employeeName;
    document.getElementById('approveDaysCount').innerText = `Approving ${days} day(s) leave`;
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

function rejectRequestModal(requestId, employeeName) {
    const form = document.getElementById('rejectForm');
    form.action = `/leave-requests/${requestId}/reject`;
    document.getElementById('rejectEmployeeName').innerText = employeeName;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

// Select all checkbox
const selectAll = document.getElementById('selectAll');
if (selectAll) {
    selectAll.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.request-checkbox');
        checkboxes.forEach(cb => cb.checked = this.checked);
    });
}
</script>
@endsection

