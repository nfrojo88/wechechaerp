@extends('layouts.app')

@section('title', 'Ask Money - Employee Expense Requests')

@section('content')
@php
    $user = auth()->user();
    $userRoleStr = $user ? strtolower(implode(' ', $user->getRoleNames()->toArray())) : '';
    $isHrUser = $user && ($user->can('hr.view') || str_contains($userRoleStr, 'hr') || str_contains($userRoleStr, 'coordinator') || $user->hasAnyRole(['admin', 'global_admin', 'coordinator', 'Coordinator']));
    $isGmUser = $user && (str_contains($userRoleStr, 'gm') || $user->hasAnyRole(['admin', 'global_admin']));
    $isFinanceHeadUser = $user && (str_contains($userRoleStr, 'finance_head') || str_contains($userRoleStr, 'finance_manager') || $user->hasAnyRole(['finance_head', 'admin', 'global_admin']));
    $isFinanceStaffUser = $user && (str_contains($userRoleStr, 'finance') || str_contains($userRoleStr, 'cashier') || str_contains($userRoleStr, 'accountant') || $user->hasAnyRole(['admin', 'global_admin']));
@endphp

<div class="container-fluid py-3">

    {{-- Page Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-0">
                <i class="fa-solid fa-hand-holding-dollar text-success me-2"></i>Ask Money
                <span class="fs-6 text-muted ms-2">(Employee Expense Request Module)</span>
            </h3>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-success shadow-sm px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#createRequestModal">
                <i class="fa-solid fa-plus me-1"></i> Request Money
            </button>
            <a href="{{ route('expense-requests.history') }}" class="btn btn-outline-secondary shadow-sm px-3">
                <i class="fa-solid fa-history me-1"></i> Paid History
            </a>
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-start border-4 border-success" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-start border-4 border-danger" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(isset($errors) && $errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong><i class="fa-solid fa-triangle-exclamation me-2"></i>Please check the errors below:</strong>
            <ul class="mb-0 mt-1 ps-3">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Queue Tabs Navigation --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom-0 pt-3 px-3">
            <ul class="nav nav-tabs card-header-tabs gap-1">
                <li class="nav-item">
                    <a class="nav-link fw-semibold {{ $tab === 'my_requests' ? 'active text-primary fw-bold' : 'text-secondary' }}" 
                       href="{{ route('expense-requests.index', ['tab' => 'my_requests']) }}">
                        <i class="fa-solid fa-user me-1"></i> My Requests 
                        <span class="badge bg-secondary ms-1">{{ $counters['my_requests'] }}</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link fw-semibold {{ $tab === 'paid_history' ? 'active text-success fw-bold' : 'text-secondary' }}" 
                       href="{{ route('expense-requests.index', ['tab' => 'paid_history']) }}">
                        <i class="fa-solid fa-circle-check me-1"></i> Paid History
                        <span class="badge bg-success ms-1">{{ $counters['paid_history'] }}</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link fw-semibold {{ $tab === 'rejected_history' ? 'active text-danger fw-bold' : 'text-secondary' }}" 
                       href="{{ route('expense-requests.index', ['tab' => 'rejected_history']) }}">
                        <i class="fa-solid fa-ban me-1"></i> Rejected History
                        @if(($counters['rejected_history'] ?? 0) > 0)
                            <span class="badge bg-danger ms-1">{{ $counters['rejected_history'] }}</span>
                        @endif
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body p-0">
            {{-- Filter & Search Bar --}}
            <div class="p-3 bg-light border-bottom d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <form method="GET" action="{{ route('expense-requests.index') }}" class="d-flex flex-wrap gap-2 align-items-center mb-0">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div class="input-group input-group-sm" style="max-width: 250px;">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search request #, user..." value="{{ request('search') }}">
                    </div>

                    <select name="category" class="form-select form-select-sm" style="max-width: 200px;" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <option value="Transport" {{ request('category') === 'Transport' ? 'selected' : '' }}>Transport</option>
                        <option value="Office Material" {{ request('category') === 'Office Material' ? 'selected' : '' }}>Office Material</option>
                        <option value="Loading & Unloading" {{ (request('category') === 'Loading & Unloading' || request('category') === 'Loading / Unloading' || request('category') === 'Loading Unloading') ? 'selected' : '' }}>Loading & Unloading</option>
                        <option value="Contract Work" {{ request('category') === 'Contract Work' ? 'selected' : '' }}>Contract Work</option>
                        <option value="Other" {{ request('category') === 'Other' ? 'selected' : '' }}>Other</option>
                    </select>

                    <button type="submit" class="btn btn-sm btn-secondary">Filter</button>
                    @if(request('search') || request('category'))
                        <a href="{{ route('expense-requests.index', ['tab' => $tab]) }}" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-xmark"></i> Clear</a>
                    @endif
                </form>
                <div class="small text-muted">Showing {{ $requests->firstItem() ?? 0 }} - {{ $requests->lastItem() ?? 0 }} of {{ $requests->total() }} requests</div>
            </div>

            {{-- Table --}}
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">REQ #</th>
                            <th>Employee</th>
                            <th>Category</th>
                            <th>Amount (ETB)</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Attachment</th>
                            <th>Date</th>
                            <th class="pe-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                        <tr>
                            <td class="ps-3 fw-bold text-dark">
                                {{ $req->request_number }}
                                @if($req->amount > 5000)
                                    <span class="badge bg-warning text-dark ms-1 small" title="Requires GM Approval (>5,000 ETB)">> 5k</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm bg-light rounded-circle text-primary fw-bold me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                        {{ strtoupper(substr($req->user->name ?? 'E', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-dark">{{ $req->user->name ?? 'Employee' }}</div>
                                        <small class="text-muted">{{ $req->employee->role_title ?? $req->employee->department ?? 'Staff' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @php
                                    $catIcon = match($req->category) {
                                        'Transport' => 'fa-car',
                                        'Office Material' => 'fa-boxes-packing',
                                        'Loading & Unloading', 'Loading / Unloading', 'Loading Unloading' => 'fa-truck-ramp-box',
                                        'Contract Work' => 'fa-file-signature',
                                        'Maintenance' => 'fa-screwdriver-wrench',
                                        default => 'fa-list',
                                    };
                                @endphp
                                <span class="badge bg-light text-dark border">
                                    <i class="fa-solid {{ $catIcon }} me-1 text-primary"></i>
                                    {{ $req->category }}
                                </span>
                                @if($req->category === 'Other' && $req->other_reason)
                                    <div class="small text-muted text-truncate" style="max-width: 150px;" title="{{ $req->other_reason }}">
                                        Reason: {{ $req->other_reason }}
                                    </div>
                                @endif
                                @if($req->maintenance_request_id && $req->maintenanceRequest)
                                    <div class="mt-1">
                                        <a href="{{ route('general-service.maintenance.show', $req->maintenanceRequest) }}" class="badge bg-warning text-dark text-decoration-none border shadow-xs" title="View linked maintenance ticket">
                                            <i class="fa-solid fa-screwdriver-wrench me-1"></i>{{ $req->maintenanceRequest->request_no }}
                                        </a>
                                    </div>
                                @endif
                            </td>
                            <td class="fw-bold fs-6 text-success">
                                {{ number_format($req->amount, 2) }} <small class="text-muted fw-normal">ETB</small>
                            </td>
                            <td>
                                <span class="text-truncate d-inline-block" style="max-width: 220px;" title="{{ $req->description }}">
                                    {{ $req->description }}
                                </span>
                            </td>
                            <td>
                                {!! $req->status_badge !!}
                                @if($req->status === 'Rejected')
                                    <div class="mt-1 p-1 bg-danger bg-opacity-10 border border-danger border-opacity-25 rounded small text-danger" style="max-width: 220px; font-size: 0.76rem;">
                                        <div class="fw-bold">
                                            <i class="fa-solid fa-circle-xmark me-1"></i>Rejected by: 
                                            <span class="text-dark">{{ $req->rejected_by_user->name ?? $req->rejected_by_role }}</span>
                                        </div>
                                        <div class="text-muted text-truncate" title="{{ $req->rejection_reason ?? 'No reason stated.' }}">
                                            <strong>Reason:</strong> {{ $req->rejection_reason ?? 'Rejected upon review' }}
                                        </div>
                                        @if($req->rejected_at)
                                            <div class="text-muted" style="font-size: 0.70rem;">
                                                <i class="fa-regular fa-clock me-1"></i>{{ $req->rejected_at->format('d M Y, h:i A') }}
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($req->attachment)
                                    <a href="{{ route('expense-requests.attachment', $req->id) }}" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2 shadow-sm" title="View attached receipt / document">
                                        <i class="fa-solid fa-paperclip me-1"></i>View
                                    </a>
                                @else
                                    <span class="text-muted small">None</span>
                                @endif
                            </td>
                            <td class="small text-muted">
                                {{ $req->created_at->format('M d, Y H:i') }}
                            </td>
                            <td class="pe-3 text-end">
                                {{-- HR Action Button --}}
                                @if($req->status === 'Pending (HR Review)' && $isHrUser)
                                    <button type="button" class="btn btn-sm btn-warning fw-bold py-1 px-2" data-bs-toggle="modal" data-bs-target="#hrReviewModal{{ $req->id }}">
                                        <i class="fa-solid fa-user-check me-1"></i>HR Review
                                    </button>
                                @endif

                                {{-- GM Action Button --}}
                                @if($req->status === 'Pending (GM Review)' && $isGmUser)
                                    <button type="button" class="btn btn-sm btn-info text-white fw-bold py-1 px-2" data-bs-toggle="modal" data-bs-target="#gmReviewModal{{ $req->id }}">
                                        <i class="fa-solid fa-user-shield me-1"></i>GM Review
                                    </button>
                                @endif

                                {{-- Finance Head Assign Button --}}
                                @if(in_array($req->status, ['Approved - Assigned to Finance', 'Assigned to Finance']) && $isFinanceHeadUser)
                                    <button type="button" class="btn btn-sm btn-primary fw-bold py-1 px-2" data-bs-toggle="modal" data-bs-target="#financeAssignModal{{ $req->id }}">
                                        <i class="fa-solid fa-user-gear me-1"></i>Assign Finance
                                    </button>
                                @endif

                                {{-- Finance Staff Payment Button --}}
                                @if($req->status === 'Assigned to Finance' && (auth()->user()->id == ($req->assigned_finance_staff_id ?? $req->finance_staff_id) || $isFinanceHeadUser))
                                    <button type="button" class="btn btn-sm btn-success fw-bold py-1 px-2" data-bs-toggle="modal" data-bs-target="#markPaidModal{{ $req->id }}">
                                        <i class="fa-solid fa-money-bill-wave me-1"></i>Process Payment
                                    </button>
                                @endif

                                {{-- Details Button --}}
                                <button type="button" class="btn btn-sm btn-light border py-1 px-2 ms-1" data-bs-toggle="modal" data-bs-target="#detailsModal{{ $req->id }}">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-inbox fa-3x mb-3 text-secondary opacity-50"></i>
                                <h6>No expense requests found.</h6>
                                <p class="small mb-0">Click <strong>"Request Money"</strong> to submit a new request.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($requests->hasPages())
            <div class="p-3 border-top">
                {{ $requests->links() }}
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Render ALL Modals OUTSIDE Table to ensure Bootstrap 5 Z-Index & Clickability --}}
@foreach($requests as $req)

    {{-- HR Review Modal --}}
    @if($req->status === 'Pending (HR Review)' && $isHrUser)
    <div class="modal fade" id="hrReviewModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow border-0">
                <form method="POST" action="{{ route('expense-requests.hr-review', $req->id) }}">
                    @csrf
                    <div class="modal-header bg-warning bg-gradient text-dark">
                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-tie me-2"></i>HR Review — Request #{{ $req->request_number }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="bg-light p-3 rounded-3 mb-3 border">
                            <div class="row g-2">
                                <div class="col-6">
                                    <span class="text-muted small">Employee:</span>
                                    <div class="fw-bold text-dark">{{ $req->user->name ?? 'N/A' }}</div>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted small">Category:</span>
                                    <div class="fw-semibold text-dark"><span class="badge bg-secondary me-1">{{ $req->category }}</span> {{ $req->other_reason }}</div>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted small">Requested Amount:</span>
                                    <div class="fs-5 fw-bold text-success">ETB {{ number_format($req->amount, 2) }}</div>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted small">Reason / Purpose:</span>
                                    <div class="fw-normal text-dark text-break">{{ $req->description }}</div>
                                </div>
                            </div>
                            @if($req->attachment)
                                <div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center">
                                    <span class="text-muted small"><i class="fa-solid fa-paperclip text-primary me-1"></i> Attached Receipt / Proof:</span>
                                    <a href="{{ route('expense-requests.attachment', $req->id) }}" target="_blank" class="btn btn-sm btn-primary py-1 px-2">
                                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Open Attachment
                                    </a>
                                </div>
                            @endif
                        </div>

                        @if($req->amount <= 5000)
                            <div class="alert alert-info py-2 px-3 small border-0 bg-info bg-opacity-10 text-dark rounded-3 d-flex align-items-center">
                                <i class="fa-solid fa-circle-info fa-lg me-2 text-info"></i>
                                <div><strong>Amount is 5,000 ETB or less.</strong> Approving will automatically skip GM and send directly to Finance Head.</div>
                            </div>
                        @else
                            <div class="alert alert-warning py-2 px-3 small border-0 bg-warning bg-opacity-10 text-dark rounded-3 d-flex align-items-center">
                                <i class="fa-solid fa-triangle-exclamation fa-lg me-2 text-warning"></i>
                                <div><strong>Amount exceeds 5,000 ETB.</strong> Approving will forward this request to General Manager (GM) for sign-off.</div>
                            </div>
                        @endif

                        <div class="mt-3">
                            <label class="form-label fw-semibold text-uppercase small text-muted">Rejection Reason (Optional)</label>
                            <textarea name="rejection_reason" class="form-control" rows="3" placeholder="State reason if rejecting (optional)..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-top-0 d-flex justify-content-between">
                        <button type="submit" name="action" value="reject" class="btn btn-outline-danger">
                            <i class="fa-solid fa-times me-1"></i> Reject Request
                        </button>
                        <button type="submit" name="action" value="approve" class="btn btn-success fw-bold px-3">
                            <i class="fa-solid fa-check me-1"></i> {{ $req->amount <= 5000 ? 'Approve & Send to Finance' : 'Approve & Forward to GM' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- GM Review Modal --}}
    @if($req->status === 'Pending (GM Review)' && $isGmUser)
    <div class="modal fade" id="gmReviewModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow border-0">
                <form method="POST" action="{{ route('expense-requests.gm-review', $req->id) }}">
                    @csrf
                    <div class="modal-header bg-info bg-gradient text-white">
                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-shield me-2"></i>GM Final Approval — Request #{{ $req->request_number }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="bg-light p-3 rounded-3 mb-3 border">
                            <div class="row g-2">
                                <div class="col-6">
                                    <span class="text-muted small">Employee:</span>
                                    <div class="fw-bold text-dark">{{ $req->user->name ?? 'N/A' }}</div>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted small">Category:</span>
                                    <div class="fw-semibold text-dark">{{ $req->category }}</div>
                                </div>
                                <div class="col-12">
                                    <span class="text-muted small">Amount:</span>
                                    <div class="fs-4 fw-bold text-success">ETB {{ number_format($req->amount, 2) }}</div>
                                </div>
                                <div class="col-12">
                                    <span class="text-muted small">Description:</span>
                                    <div class="fw-normal text-dark">{{ $req->description }}</div>
                                </div>
                            </div>
                            @if($req->hrReviewer)
                                <div class="mt-2 pt-2 border-top text-muted small"><i class="fa-solid fa-user-check text-success me-1"></i>Reviewed by HR: {{ $req->hrReviewer->name }} on {{ $req->hr_reviewed_at ? $req->hr_reviewed_at->format('M d, H:i') : 'N/A' }}</div>
                            @endif
                            @if($req->attachment)
                                <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                                    <span class="text-muted small"><i class="fa-solid fa-paperclip text-primary me-1"></i> Attached Receipt / Proof:</span>
                                    <a href="{{ route('expense-requests.attachment', $req->id) }}" target="_blank" class="btn btn-sm btn-primary py-1 px-2">
                                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Open Attachment
                                    </a>
                                </div>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold text-uppercase small text-muted">Rejection Reason (Optional)</label>
                            <textarea name="rejection_reason" class="form-control" rows="3" placeholder="State reason if rejecting (optional)..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-top-0 d-flex justify-content-between">
                        <button type="submit" name="action" value="reject" class="btn btn-outline-danger">
                            <i class="fa-solid fa-times me-1"></i> Reject Request
                        </button>
                        <button type="submit" name="action" value="approve" class="btn btn-success fw-bold px-3">
                            <i class="fa-solid fa-check-double me-1"></i> Approve & Send to Finance Head
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- Finance Assign Modal --}}
    @if(in_array($req->status, ['Approved - Assigned to Finance', 'Assigned to Finance']) && $isFinanceHeadUser)
    <div class="modal fade" id="financeAssignModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow border-0">
                <form method="POST" action="{{ route('expense-requests.finance-assign', $req->id) }}">
                    @csrf
                    <div class="modal-header bg-primary bg-gradient text-white">
                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-landmark me-2"></i>Finance Assignment — Request #{{ $req->request_number }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="bg-light p-3 rounded-3 mb-3 border">
                            <div class="row g-2">
                                <div class="col-6">
                                    <span class="text-muted small">Payee Employee:</span>
                                    <div class="fw-bold text-dark">{{ $req->user->name ?? 'N/A' }}</div>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted small">Category:</span>
                                    <div class="fw-semibold text-dark">{{ $req->category }}</div>
                                </div>
                                <div class="col-12">
                                    <span class="text-muted small">Amount to Disburse:</span>
                                    <div class="fs-4 fw-bold text-success">ETB {{ number_format($req->amount, 2) }}</div>
                                </div>
                            </div>
                            @if($req->attachment)
                                <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                                    <span class="text-muted small"><i class="fa-solid fa-paperclip text-primary me-1"></i> Attached Receipt / Proof:</span>
                                    <a href="{{ route('expense-requests.attachment', $req->id) }}" target="_blank" class="btn btn-sm btn-primary py-1 px-2">
                                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Open Attachment
                                    </a>
                                </div>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Source Bank / Cash Account (COA) <span class="text-danger">*</span></label>
                            <select name="coa_id" id="coaSelect{{ $req->id }}" class="form-select" data-req-id="{{ $req->id }}" onchange="autoDetectFinanceStaff(this)" required>
                                <option value="">-- Choose Bank / Cash Account from COA --</option>
                                @foreach($coaBankAccounts as $coaAcc)
                                    @php
                                        $mgr = $coaAcc->manager;
                                        $mgrId = $mgr ? $mgr->id : '';
                                        $mgrName = $mgr ? $mgr->name : 'Unassigned';
                                    @endphp
                                    <option value="{{ $coaAcc->id }}"
                                            data-assigned-staff-id="{{ $mgrId }}"
                                            data-assigned-staff-name="{{ $mgrName }}"
                                            {{ ($req->chart_of_account_id ?? $req->coa_id) == $coaAcc->id ? 'selected' : '' }}>
                                        [{{ $coaAcc->code }}] {{ $coaAcc->name }} — Balance: ETB {{ number_format($coaAcc->current_balance ?? 0, 2) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Auto-detected Finance Staff (read-only, from COA manager) --}}
                        <div id="autoAssignBox{{ $req->id }}" class="mb-3 {{ ($req->chart_of_account_id ?? $req->coa_id) ? '' : 'd-none' }}">
                            <label class="form-label fw-bold text-muted small text-uppercase">Auto-Assigned Finance Staff (from COA)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fa-solid fa-user-gear text-primary"></i></span>
                                <input type="text" id="assignedStaffDisplay{{ $req->id }}" class="form-control bg-light fw-semibold" readonly
                                    value="{{ ($req->chart_of_account_id ?? $req->coa_id) && $req->coa && $req->coa->manager ? $req->coa->manager->name : '' }}">
                            </div>
                            <input type="hidden" name="assigned_finance_staff_id" id="assignedStaffId{{ $req->id }}"
                                value="{{ ($req->chart_of_account_id ?? $req->coa_id) && $req->coa && $req->coa->manager ? $req->coa->manager->id : '' }}">
                            <div class="small text-muted mt-1" id="autoDetectHelp{{ $req->id }}">
                                <i class="fa-solid fa-wand-magic-sparkles text-primary me-1"></i>Finance staff is automatically assigned from the selected account's COA manager.
                            </div>
                        </div>
                        <div id="noManagerWarning{{ $req->id }}" class="mb-3" style="display:none;">
                            <div class="alert alert-warning py-2 small mb-0">
                                <i class="fa-solid fa-triangle-exclamation me-1"></i>This account has no COA manager assigned. Please assign a manager in Chart of Accounts first.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-top-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold px-3">
                            <i class="fa-solid fa-paper-plane me-1"></i> Assign to Finance Staff
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- Process Payment Modal --}}
    @if($req->status === 'Assigned to Finance' && (auth()->user()->id == ($req->assigned_finance_staff_id ?? $req->finance_staff_id) || $isFinanceHeadUser))
    <div class="modal fade" id="markPaidModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow border-0">
                <form method="POST" action="{{ route('expense-requests.mark-paid', $req->id) }}">
                    @csrf
                    <div class="modal-header bg-success bg-gradient text-white">
                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-money-bill-wave me-2"></i>Process Payment — Request #{{ $req->request_number }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="bg-light p-3 rounded-3 mb-3 border">
                            <div><strong>Payee Employee:</strong> {{ $req->user->name ?? 'N/A' }}</div>
                            <div><strong>Amount:</strong> <span class="fs-4 fw-bold text-success">ETB {{ number_format($req->amount, 2) }}</span></div>
                            <div><strong>Source Account (COA):</strong> {{ $req->chartOfAccount->name ?? 'N/A' }} ([{{ $req->chartOfAccount->code ?? 'N/A' }}])</div>
                            <div><strong>Available Balance:</strong> ETB {{ number_format($req->chartOfAccount->current_balance ?? 0, 2) }}</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Payment Reference / Transaction ID</label>
                            <input type="text" name="payment_reference" class="form-control" placeholder="e.g., CBE-TR-9823410" value="{{ 'PAY-' . strtoupper(Illuminate\Support\Str::random(6)) }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Payment Notes (Optional)</label>
                            <textarea name="payment_notes" class="form-control" rows="2" placeholder="Check #, transfer details, etc."></textarea>
                        </div>

                        <div class="alert alert-warning py-2 small mb-0">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i>Confirming will deduct <strong>ETB {{ number_format($req->amount, 2) }}</strong> from the selected account balance and create a ledger transaction atomically.
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-top-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success fw-bold">
                            <i class="fa-solid fa-check-circle me-1"></i> Confirm & Mark as Paid
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- Details Modal --}}
    <div class="modal fade" id="detailsModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content shadow border-0">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-invoice me-2"></i>Request Details — #{{ $req->request_number }}</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    @if($req->status === 'Rejected')
                    <div class="alert alert-danger border-start border-4 border-danger shadow-sm mb-3">
                        <div class="d-flex align-items-start gap-3">
                            <div class="rounded-circle bg-danger bg-opacity-10 p-2 text-danger flex-shrink-0">
                                <i class="fa-solid fa-ban fa-xl"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="fw-bold text-danger mb-1"><i class="fa-solid fa-circle-xmark me-1"></i>Expense Request Rejected</h6>
                                <div class="p-2 bg-white rounded border mb-2">
                                    <strong class="text-dark small d-block mb-1"><i class="fa-solid fa-comment-dots text-danger me-1"></i>Rejection Reason:</strong>
                                    <span class="text-danger fw-semibold">{{ $req->rejection_reason ?? 'Rejected upon review (No custom reason provided)' }}</span>
                                </div>
                                <div class="small text-muted d-flex flex-wrap gap-3">
                                    <div>
                                        <i class="fa-solid fa-user-xmark text-danger me-1"></i><strong>Rejected By:</strong> 
                                        <span class="text-dark fw-semibold">{{ $req->rejected_by_user->name ?? 'Reviewer' }}</span>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 ms-1">{{ $req->rejected_by_role }}</span>
                                    </div>
                                    @if($req->rejected_at)
                                    <div>
                                        <i class="fa-solid fa-calendar-xmark text-danger me-1"></i><strong>Date & Time:</strong>
                                        <span class="text-dark">{{ $req->rejected_at->format('d M Y \a\t h:i A') }}</span>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="text-muted small">Employee Info</div>
                                <div class="fw-bold fs-6">{{ $req->user->name ?? 'N/A' }}</div>
                                <div class="small text-muted">{{ $req->user->email ?? '' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="text-muted small">Request Amount</div>
                                <div class="fw-bold fs-4 text-success">ETB {{ number_format($req->amount, 2) }}</div>
                                <div class="small">{!! $req->status_badge !!}</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold">Category & Reason:</label>
                        <div><span class="badge bg-secondary">{{ $req->category }}</span> {{ $req->other_reason }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold">Description:</label>
                        <div class="p-3 bg-light rounded border">{{ $req->description }}</div>
                    </div>

                    {{-- Workflow Timeline --}}
                    <div class="mb-3">
                        <label class="fw-bold mb-2"><i class="fa-solid fa-timeline me-1"></i>Approval & Payment Timeline:</label>
                        <ul class="list-group list-group-flush border rounded">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fa-solid fa-paper-plane text-primary me-2"></i>Submitted by Employee</span>
                                <small class="text-muted">{{ $req->created_at->format('M d, Y H:i') }}</small>
                            </li>

                            @if($req->hr_reviewed_at)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fa-solid fa-user-check text-warning me-2"></i>HR Reviewed by {{ $req->hrReviewer->name ?? 'HR' }}</span>
                                <small class="text-muted">{{ $req->hr_reviewed_at->format('M d, Y H:i') }}</small>
                            </li>
                            @endif

                            @if($req->gm_approved_at || $req->gm_reviewed_at)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fa-solid fa-user-shield text-info me-2"></i>GM Approved by {{ $req->gmApprover->name ?? $req->gmReviewer->name ?? 'GM' }}</span>
                                <small class="text-muted">{{ ($req->gm_approved_at ?? $req->gm_reviewed_at)->format('M d, Y H:i') }}</small>
                            </li>
                            @endif

                            @if($req->finance_assigned_at)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fa-solid fa-user-gear text-primary me-2"></i>Assigned to {{ $req->financeStaff->name ?? $req->assignedFinanceStaff->name ?? 'Finance' }}</span>
                                <small class="text-muted">{{ $req->finance_assigned_at->format('M d, Y H:i') }}</small>
                            </li>
                            @endif

                            @if($req->paid_at)
                            <li class="list-group-item d-flex justify-content-between align-items-center bg-light-success">
                                <span><i class="fa-solid fa-check-circle text-success me-2"></i>Paid by {{ $req->paidBy->name ?? 'Finance' }} (Ref: {{ $req->payment_reference }})</span>
                                <small class="text-muted">{{ $req->paid_at->format('M d, Y H:i') }}</small>
                            </li>
                            @endif

                            @if($req->status === 'Rejected')
                            <li class="list-group-item bg-danger bg-opacity-10 text-danger border-start border-4 border-danger">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <i class="fa-solid fa-times-circle me-1 text-danger"></i>
                                        <strong>Rejected by {{ $req->rejected_by_user->name ?? 'Reviewer' }} ({{ $req->rejected_by_role }})</strong>
                                        <div class="small text-dark mt-1"><strong>Reason:</strong> {{ $req->rejection_reason ?? 'No reason stated.' }}</div>
                                    </div>
                                    @if($req->rejected_at)
                                        <small class="text-muted text-nowrap ms-2">{{ $req->rejected_at->format('M d, Y H:i') }}</small>
                                    @endif
                                </div>
                            </li>
                            @endif
                        </ul>
                    </div>

                    @if($req->attachment)
                    <div class="mb-3">
                        <label class="fw-bold mb-1">Receipt Attachment:</label>
                        <div>
                            <a href="{{ route('expense-requests.attachment', $req->id) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="fa-solid fa-paperclip me-1"></i>View Receipt / Document
                            </a>
                        </div>
                    </div>
                    @endif
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endforeach

{{-- Create Request Modal --}}
<div class="modal fade" id="createRequestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <form method="POST" action="{{ route('expense-requests.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header bg-success bg-gradient text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-hand-holding-dollar me-2"></i>New Expense Request ("Ask Money")</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Category <span class="text-danger">*</span></label>
                            <select name="category" id="categorySelect" class="form-select" onchange="toggleOtherReason()" required>
                                <option value="Transport">Transport</option>
                                <option value="Office Material">Office Material</option>
                                <option value="Loading & Unloading">Loading & Unloading</option>
                                <option value="Contract Work">Contract Work</option>
                                <option value="Maintenance">Maintenance &amp; Repairs</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Requested Amount (ETB) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text fw-bold bg-light">ETB</span>
                                <input type="number" step="0.01" min="1" name="amount" class="form-control fw-bold fs-5 text-success" placeholder="0.00" required>
                            </div>
                        </div>

                        <div class="col-12" id="otherReasonGroup" style="display: none;">
                            <label class="form-label fw-bold">Specify Reason for "Other" Category <span class="text-danger">*</span></label>
                            <input type="text" name="other_reason" id="otherReasonInput" class="form-control" placeholder="e.g., Client meeting lunch, Site emergency repair...">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">Description / Details <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Provide full details of why money is required..." required></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">Attachment (Receipt / Photo / Quote) <small class="text-muted">(Optional — Max 10MB)</small></label>
                            <input type="file" name="attachment" class="form-control" accept="image/jpeg,image/png,image/jpg,application/pdf,image/webp">
                            <small class="text-muted">Upload receipt image or PDF if available.</small>
                        </div>
                    </div>

                    <div class="alert alert-light border border-start border-4 border-success mt-3 mb-0 small">
                        <i class="fa-solid fa-circle-info text-success me-1"></i>
                        <strong>Workflow Notice:</strong> Requests 5,000 ETB or less will be directly reviewed by HR. Requests over 5,000 ETB will automatically require General Manager (GM) sign-off.
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold px-4">
                        <i class="fa-solid fa-paper-plane me-1"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleOtherReason() {
    const category = document.getElementById('categorySelect').value;
    const group = document.getElementById('otherReasonGroup');
    const input = document.getElementById('otherReasonInput');
    if (category === 'Other') {
        group.style.display = 'block';
        input.required = true;
    } else {
        group.style.display = 'none';
        input.required = false;
    }
}

function autoDetectFinanceStaff(selectEl) {
    const reqId = selectEl.dataset.reqId;
    const coaSelect = selectEl;
    const autoBox = document.getElementById('autoAssignBox' + reqId);
    const noMgrWarning = document.getElementById('noManagerWarning' + reqId);
    const displayInput = document.getElementById('assignedStaffDisplay' + reqId);
    const hiddenInput = document.getElementById('assignedStaffId' + reqId);
    const helpText = document.getElementById('autoDetectHelp' + reqId);

    if (!coaSelect) return;

    const opt = coaSelect.options[coaSelect.selectedIndex];
    if (!opt || !opt.value) {
        if (autoBox) autoBox.classList.add('d-none');
        if (noMgrWarning) noMgrWarning.style.display = 'none';
        return;
    }

    const staffId = opt.getAttribute('data-assigned-staff-id');
    const staffName = opt.getAttribute('data-assigned-staff-name');

    if (staffId && staffId !== '') {
        if (autoBox) autoBox.classList.remove('d-none');
        if (noMgrWarning) noMgrWarning.style.display = 'none';
        if (displayInput) displayInput.value = staffName;
        if (hiddenInput) hiddenInput.value = staffId;
        if (helpText) {
            helpText.innerHTML = '<i class="fa-solid fa-circle-check text-success me-1"></i><strong>Auto-assigned:</strong> ' + staffName + ' (COA Manager)';
        }
    } else {
        if (autoBox) autoBox.classList.add('d-none');
        if (noMgrWarning) noMgrWarning.style.display = '';
        if (hiddenInput) hiddenInput.value = '';
    }
}
</script>
@endsection
