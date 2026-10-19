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
                        <option value="Service" {{ request('category') === 'Service' ? 'selected' : '' }}>Service (አገልግሎት)</option>
                        <option value="Transport" {{ request('category') === 'Transport' ? 'selected' : '' }}>Transport (ትራንስፖርት)</option>
                        <option value="Loading & Unloading" {{ (request('category') === 'Loading & Unloading' || request('category') === 'Loading / Unloading' || request('category') === 'Loading Unloading') ? 'selected' : '' }}>Loading &amp; Unloading (መጫን/ማውረድ)</option>
                        <option value="Contract Work" {{ request('category') === 'Contract Work' ? 'selected' : '' }}>Contract Work (የኮንትራት ስራ)</option>
                        <option value="Office Material" {{ request('category') === 'Office Material' ? 'selected' : '' }}>Office Material (የቢሮ እቃ)</option>
                        <option value="Maintenance" {{ request('category') === 'Maintenance' ? 'selected' : '' }}>Maintenance (ጥገና)</option>
                        <option value="Other" {{ request('category') === 'Other' ? 'selected' : '' }}>Other (ሌሎች)</option>
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
                                        {{ strtoupper(substr($req->employee->full_name ?? $req->user->name ?? 'E', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-dark">{{ $req->employee->full_name ?? $req->user->name ?? 'Employee' }}</div>
                                        <small class="text-muted">{{ $req->employee->role_title ?? $req->employee->department ?? 'Staff' }}</small>
                                        @if($req->employee && $req->user && $req->user->name !== $req->employee->full_name)
                                            <div class="text-muted" style="font-size: 0.72rem;"><i class="fa-solid fa-user-pen me-1"></i>By: {{ $req->user->name }}</div>
                                        @endif
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
                                @if(in_array($req->category, ['Transport', 'Loading & Unloading', 'Loading / Unloading', 'Loading Unloading']))
                                    <div class="mt-1 p-2 rounded-2 bg-light border border-info-subtle small shadow-xs" style="font-size: 0.74rem; line-height: 1.45; min-width: 200px;">
                                        @if($req->employee)
                                            <div class="text-truncate mb-1" title="Beneficiary / Driver / Handler: {{ $req->employee->full_name }}">
                                                <i class="fa-solid fa-id-badge text-info me-1"></i>
                                                <span class="text-muted">For:</span>
                                                <strong class="text-dark">{{ $req->employee->full_name }}</strong>
                                            </div>
                                        @endif
                                        @if($req->project)
                                            <div class="text-truncate mb-1" title="Project / Site: {{ $req->project->name }}">
                                                <i class="fa-solid fa-helmet-safety text-warning me-1"></i>
                                                <span class="text-muted">Project:</span>
                                                <strong class="text-dark">{{ $req->project->name }}</strong>
                                            </div>
                                        @endif
                                        <div class="text-truncate mb-1" title="Asked By: {{ $req->user->name ?? 'Requester' }}">
                                            <i class="fa-solid fa-user-pen text-primary me-1"></i>
                                            <span class="text-muted">Asked by:</span>
                                            <strong class="text-dark">{{ $req->user->name ?? 'Requester' }}</strong>
                                        </div>
                                        @php
                                            $appr = (is_array($req->approver_info ?? null) ? $req->approver_info : (method_exists($req, 'getApproverInfoAttribute') ? $req->getApproverInfoAttribute() : []));
                                            $isApproved = !empty($appr['is_approved']);
                                            $isRejected = !empty($appr['is_rejected']) || ($req->status === 'Rejected');
                                            $apprName = $appr['name'] ?? ($req->gmApprover->name ?? $req->gmReviewer->name ?? $req->hrReviewer->name ?? null);
                                            $apprRole = $appr['role'] ?? ($req->gm_approved_at ? 'GM' : ($req->hr_reviewed_at ? 'HR' : null));
                                            $apprText = $appr['text'] ?? ($apprName ? ($apprName . ($apprRole ? " ({$apprRole})" : '')) : ($isRejected ? 'Rejected' : ($req->status ?? 'Pending Review')));
                                        @endphp
                                        <div class="text-truncate" title="Approved by: {{ $apprText }}">
                                            <i class="fa-solid {{ $isApproved ? 'fa-circle-check text-success' : ($isRejected ? 'fa-circle-xmark text-danger' : 'fa-hourglass-half text-warning') }} me-1"></i>
                                            <span class="text-muted">Approved by:</span>
                                            @if(!empty($apprName))
                                                <strong class="text-success">{{ $apprName }}</strong>
                                                @if(!empty($apprRole))
                                                    <span class="text-muted" style="font-size: 0.68rem;">({{ $apprRole }})</span>
                                                @endif
                                            @elseif($isRejected)
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle py-0 px-1" style="font-size: 0.68rem;">
                                                    {{ $apprText }}
                                                </span>
                                            @else
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle py-0 px-1" style="font-size: 0.68rem;">
                                                    {{ $apprText }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @elseif($req->project)
                                    <div class="mt-1 small text-muted text-truncate" style="max-width: 180px;" title="Project / Site: {{ $req->project->name }}">
                                        <i class="fa-solid fa-helmet-safety text-warning me-1"></i>{{ $req->project->name }}
                                    </div>
                                @endif
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
                                @if($req->account_number)
                                    <div class="mt-1">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2 text-start text-wrap shadow-xs" style="font-size: 0.72rem; line-height: 1.35;" title="Bank Transfer: {{ $req->account_name }} ({{ $req->bank_name }}: {{ $req->account_number }})">
                                            <i class="fa-solid fa-building-columns me-1"></i>Pay to: <span class="font-monospace fw-bold">{{ $req->account_number }}</span>
                                            @if($req->bank_name) <span class="text-muted">({{ $req->bank_name }})</span> @endif
                                            @if($req->account_name) <div class="text-dark fw-semibold" style="font-size: 0.70rem;"><i class="fa-solid fa-user me-1 text-secondary"></i>{{ $req->account_name }}</div> @endif
                                        </span>
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
                                {{-- Employee Confirmation Action Button --}}
                                @php
                                    $canEmployeeApprove = auth()->check() && (
                                        (auth()->user()->employee && auth()->user()->employee->id == $req->employee_id) ||
                                        ($req->employee && $req->employee->user_id == auth()->id()) ||
                                        auth()->user()->hasAnyRole(['admin', 'global_admin'])
                                    );
                                @endphp
                                @if($req->status === 'Pending (Employee Approval)' && $canEmployeeApprove)
                                    <button type="button" class="btn btn-sm btn-success fw-bold py-1 px-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#employeeApproveModal{{ $req->id }}" title="Employee Approval / Rejection">
                                        <i class="fa-solid fa-check-double me-1"></i>Approve / Reject
                                    </button>
                                @endif

                                {{-- HR / Coordinator Action Button --}}
                                @if($req->status === 'Pending (HR Review)' && $isHrUser)
                                    <button type="button" class="btn btn-sm btn-warning fw-bold py-1 px-2" data-bs-toggle="modal" data-bs-target="#hrReviewModal{{ $req->id }}" title="HR or Coordinator Review">
                                        <i class="fa-solid fa-user-check me-1"></i>HR/Coord Review
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

    {{-- Employee Confirmation Modal --}}
    @if($req->status === 'Pending (Employee Approval)')
    <div class="modal fade" id="employeeApproveModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow border-0 rounded-4 overflow-hidden">
                <form method="POST" action="{{ route('expense-requests.employee-approve', $req->id) }}">
                    @csrf
                    <div class="modal-header bg-primary bg-gradient text-white py-3 px-4">
                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-check me-2"></i>Employee Confirmation — Request #{{ $req->request_number }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-info border-0 rounded-3 mb-3 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-circle-info fs-4"></i>
                            <div class="small">
                                <strong>Employee Confirmation Step:</strong> A money request has been submitted for you by <strong>{{ $req->user->name ?? 'Requester' }}</strong>. Please review and confirm. Once approved, it will automatically be sent to <strong>HR or Coordinator</strong> for approval.
                            </div>
                        </div>

                        <div class="bg-light p-3 rounded-3 mb-3 border">
                            <div class="row g-2">
                                <div class="col-6">
                                    <span class="text-muted small">Assigned Employee:</span>
                                    <div class="fw-bold text-dark">{{ $req->employee->full_name ?? 'N/A' }}</div>
                                    @if($req->employee)
                                        <small class="text-muted">{{ $req->employee->department ?? '' }} ({{ $req->employee->role_title ?? 'Staff' }})</small>
                                    @endif
                                </div>
                                <div class="col-6">
                                    <span class="text-muted small">Requested Amount:</span>
                                    <div class="fw-bold text-success fs-5">ETB {{ number_format($req->amount, 2) }}</div>
                                </div>
                                <div class="col-12 mt-2">
                                    <span class="text-muted small">Category &amp; Reason:</span>
                                    <div class="fw-semibold text-dark"><span class="badge bg-secondary me-1">{{ $req->category }}</span> {{ $req->other_reason }}</div>
                                </div>
                                <div class="col-12 mt-2">
                                    <span class="text-muted small">Description:</span>
                                    <div class="text-dark small bg-white p-2 rounded border">{{ $req->description }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold text-secondary small">Optional Notes / Reason (if declining)</label>
                            <textarea name="rejection_reason" class="form-control" rows="2" placeholder="Provide note or reason if declining..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 px-4 d-flex justify-content-between">
                        <button type="submit" name="action" value="reject" class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="return confirm('Are you sure you want to decline this request?')">
                            <i class="fa-solid fa-times me-1"></i>Decline Request
                        </button>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="action" value="approve" class="btn btn-primary fw-bold btn-sm rounded-pill px-4 shadow-sm">
                                <i class="fa-solid fa-check-double me-1"></i>Confirm &amp; Send to HR / Coordinator
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- HR / Coordinator Review Modal --}}
    @if($req->status === 'Pending (HR Review)' && $isHrUser)
    <div class="modal fade" id="hrReviewModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow border-0">
                <form method="POST" action="{{ route('expense-requests.hr-review', $req->id) }}">
                    @csrf
                    <div class="modal-header bg-warning bg-gradient text-dark">
                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-check me-2"></i>HR / Coordinator Review — Request #{{ $req->request_number }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="bg-light p-3 rounded-3 mb-3 border">
                            <div class="row g-2">
                                <div class="col-6">
                                    <span class="text-muted small">Employee / Beneficiary:</span>
                                    <div class="fw-bold text-dark">{{ $req->employee->full_name ?? $req->user->name ?? 'N/A' }}</div>
                                    @if($req->employee)
                                        <small class="text-muted">{{ $req->employee->department ?? '' }} ({{ $req->employee->role_title ?? 'Staff' }})</small>
                                    @endif
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
                                    <span class="text-muted small">Requester Account:</span>
                                    <div class="fw-semibold text-dark">{{ $req->user->name ?? 'N/A' }}</div>
                                </div>
                                <div class="col-12">
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
                                <div><strong>Amount is 5,000 ETB or less.</strong> HR / Coordinator approval will automatically route directly to Finance Head.</div>
                            </div>
                        @else
                            <div class="alert alert-warning py-2 px-3 small border-0 bg-warning bg-opacity-10 text-dark rounded-3 d-flex align-items-center">
                                <i class="fa-solid fa-triangle-exclamation fa-lg me-2 text-warning"></i>
                                <div><strong>Amount exceeds 5,000 ETB.</strong> Approval by HR / Coordinator will forward this request to General Manager (GM) for sign-off.</div>
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
    @php $pcr = $req->linked_replenishment; @endphp
    <div class="modal fade" id="gmReviewModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered {{ $pcr ? 'modal-xl' : 'modal-lg' }}">
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
                                    <div class="fw-semibold text-dark">{{ $req->category }} @if($req->other_reason) ({{ $req->other_reason }}) @endif</div>
                                </div>
                                <div class="col-12 mt-2">
                                    <span class="text-muted small">Amount to Authorize:</span>
                                    <div class="fs-4 fw-bold text-success font-monospace">ETB {{ number_format($req->amount, 2) }}</div>
                                </div>
                                <div class="col-12">
                                    <span class="text-muted small">Description:</span>
                                    <div class="fw-normal text-dark">{{ $req->description }}</div>
                                </div>
                            </div>
                            @if($req->hrReviewer)
                                <div class="mt-2 pt-2 border-top text-muted small"><i class="fa-solid fa-user-check text-success me-1"></i>Reviewed by HR: {{ $req->hrReviewer->name }} on {{ $req->hr_reviewed_at ? $req->hr_reviewed_at->format('M d, H:i') : 'N/A' }}</div>
                            @endif
                        </div>

                        @if($pcr)
                            {{-- Full Itemized Petty Cash Replenishment Statement & All Receipts for GM Review --}}
                            <div class="card border border-primary border-opacity-25 rounded-3 mb-3 overflow-hidden shadow-xs">
                                <div class="card-header bg-primary bg-opacity-10 py-2 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-receipt text-primary fs-5"></i>
                                        <div>
                                            <strong class="text-dark">Petty Cash Statement &amp; Receipts (#{{ $pcr->request_no }})</strong>
                                            <div class="text-muted small">Account: [{{ $pcr->chartOfAccount->code ?? '1010' }}] {{ $pcr->chartOfAccount->name ?? 'Petty Cash' }} &bull; Custodian: {{ $pcr->requester->name ?? 'Staff' }}</div>
                                        </div>
                                    </div>
                                    @if($pcr->attachment_url)
                                        <a href="{{ $pcr->attachment_url }}" target="_blank" class="btn btn-sm btn-primary rounded-pill px-3 shadow-xs">
                                            <i class="fa-solid fa-file-invoice me-1"></i> View Scanned Receipts Booklet
                                        </a>
                                    @endif
                                </div>
                                <div class="card-body p-0">
                                    @if($pcr->audited_by)
                                        <div class="p-2 px-3 bg-success bg-opacity-10 border-bottom border-success border-opacity-25 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                            <div>
                                                <i class="fa-solid fa-circle-check text-success me-1"></i>
                                                <strong class="text-dark small">Audit Cleared by:</strong>
                                                <span class="text-muted small">{{ $pcr->auditor->name ?? 'Auditor' }} ({{ optional($pcr->audited_at)->format('d M Y, H:i') }})</span>
                                                @if($pcr->audit_notes)
                                                    <span class="text-dark small ms-2"><em>"{{ $pcr->audit_notes }}"</em></span>
                                                @endif
                                            </div>
                                            <span class="badge bg-success font-monospace">Audited</span>
                                        </div>
                                    @endif

                                    <div class="row g-0 text-center border-bottom bg-light">
                                        <div class="col-3 p-2 border-end">
                                            <div class="text-muted small fw-bold">Start Balance</div>
                                            <div class="fw-bold text-dark font-monospace">ETB {{ number_format($pcr->current_balance_at_request, 2) }}</div>
                                        </div>
                                        <div class="col-3 p-2 border-end">
                                            <div class="text-muted small fw-bold">Total Spent</div>
                                            <div class="fw-bold text-danger font-monospace">ETB {{ number_format($pcr->total_expenses_amount, 2) }}</div>
                                        </div>
                                        <div class="col-3 p-2 border-end">
                                            <div class="text-muted small fw-bold">Replenishment Due</div>
                                            <div class="fw-bold text-success font-monospace">ETB {{ number_format($pcr->requested_amount, 2) }}</div>
                                        </div>
                                        <div class="col-3 p-2">
                                            <div class="text-muted small fw-bold">Vouchers</div>
                                            <div class="fw-bold text-primary">{{ $pcr->items->count() }} Attached</div>
                                        </div>
                                    </div>

                                    <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                                        <table class="table table-hover table-sm align-middle mb-0" style="font-size: 0.85rem;">
                                            <thead class="table-light text-muted small text-uppercase sticky-top">
                                                <tr>
                                                    <th class="ps-3 py-2" style="width: 40px;">#</th>
                                                    <th class="py-2" style="width: 100px;">Date</th>
                                                    <th class="py-2" style="width: 130px;">Voucher / Ref #</th>
                                                    <th class="py-2">Description / Payee</th>
                                                    <th class="py-2">Target Account</th>
                                                    <th class="py-2 pe-3 text-end" style="width: 120px;">Amount (ETB)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($pcr->items as $idx => $receiptItem)
                                                    <tr>
                                                        <td class="ps-3 text-muted">{{ $idx + 1 }}</td>
                                                        <td>{{ optional($receiptItem->entry_date)->format('M d, Y') ?? '—' }}</td>
                                                        <td><span class="badge bg-light text-dark border font-monospace">{{ $receiptItem->reference ?: 'Voucher' }}</span></td>
                                                        <td><div class="fw-semibold text-dark">{{ $receiptItem->description }}</div></td>
                                                        <td><span class="badge bg-secondary bg-opacity-10 text-dark">{{ $receiptItem->target_account_name ?: 'General' }}</span></td>
                                                        <td class="pe-3 text-end fw-bold font-monospace text-dark">ETB {{ number_format($receiptItem->amount, 2) }}</td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="6" class="text-center py-3 text-muted">No individual line vouchers recorded.</td></tr>
                                                @endforelse
                                            </tbody>
                                            <tfoot class="table-light">
                                                <tr class="fw-bold">
                                                    <td colspan="5" class="text-end pe-2">Total Verified Receipts:</td>
                                                    <td class="pe-3 text-end font-monospace text-success">ETB {{ number_format($pcr->items->sum('amount') ?: $pcr->requested_amount, 2) }}</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($req->attachment)
                            <div class="mb-3 p-3 bg-light rounded-3 border d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-dark d-block"><i class="fa-solid fa-paperclip text-primary me-1"></i> Attached Supporting Invoice / Receipt Document</strong>
                                    <small class="text-muted">Direct proof / voucher uploaded by applicant</small>
                                </div>
                                <a href="{{ route('expense-requests.attachment', $req->id) }}" target="_blank" class="btn btn-sm btn-primary rounded-pill px-3 shadow-xs">
                                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Open Attachment
                                </a>
                            </div>
                        @endif

                        @if($req->maintenanceRequest)
                            <div class="alert alert-warning border border-warning d-flex align-items-center justify-content-between p-3 rounded-3 mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-wrench text-warning fs-5"></i>
                                    <div>
                                        <strong class="text-dark">Maintenance Request Ticket: {{ $req->maintenanceRequest->request_no }}</strong>
                                        <span class="text-muted small d-block">Asset: <strong>{{ $req->maintenanceRequest->asset_name }}</strong> ({{ $req->maintenanceRequest->asset_code ?? 'Asset' }}) &bull; Issue: {{ $req->maintenanceRequest->issue_category ?? 'Maintenance' }}</span>
                                    </div>
                                </div>
                                <a href="{{ route('general-service.maintenance.show', $req->maintenanceRequest) }}" target="_blank" class="btn btn-sm btn-outline-dark rounded-pill px-3">
                                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>View Ticket
                                </a>
                            </div>
                        @endif

                        {{-- Store Routing Directive Card --}}
                        <div class="card border border-primary border-opacity-25 rounded-3 mb-3 bg-light" id="gmStoreCard{{ $req->id }}">
                            <div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center">
                                <strong class="text-dark small"><i class="fa-solid fa-warehouse text-primary me-1"></i>Store Manager Routing Directive (If sending to Store)</strong>
                                <span class="badge bg-primary-subtle text-primary small">Store Fulfillment</span>
                            </div>
                            <div class="card-body p-3">
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1">Target Warehouse / Store</label>
                                        <select name="destination_store_id" class="form-select form-select-sm">
                                            @foreach($stores ?? \App\Models\Store::where('is_active', true)->get() as $storeOption)
                                                <option value="{{ $storeOption->id }}">
                                                    {{ $storeOption->name }} ({{ $storeOption->code ?? 'Store' }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1">Instructions for Store Manager</label>
                                        <input type="text" name="gm_notes" class="form-control form-control-sm" placeholder="e.g. Issue replacement spare parts from warehouse stock...">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold text-uppercase small text-muted">Rejection Reason (Optional)</label>
                            <textarea name="rejection_reason" class="form-control" rows="3" placeholder="State reason if rejecting (optional)..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-top-0 d-flex justify-content-between flex-wrap gap-2">
                        <button type="submit" name="action" value="reject" class="btn btn-outline-danger">
                            <i class="fa-solid fa-times me-1"></i> Reject Request
                        </button>
                        <div class="d-flex gap-2">
                            <button type="submit" name="action" value="send_to_store" class="btn btn-primary fw-bold px-3 shadow-xs">
                                <i class="fa-solid fa-warehouse me-1"></i> Send to Store Manager
                            </button>
                            <button type="submit" name="action" value="approve" class="btn btn-success fw-bold px-3 shadow-xs">
                                <i class="fa-solid fa-money-bill-wave me-1"></i> Approve &amp; Send to Finance Head
                            </button>
                        </div>
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
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content shadow border-0">
                <form method="POST" action="{{ route('expense-requests.mark-paid', $req->id) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header bg-success bg-gradient text-white">
                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-money-bill-wave me-2"></i>Process Payment — Request #{{ $req->request_number }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="bg-light p-3 rounded-3 mb-3 border">
                            <div class="row g-2 small">
                                <div class="col-md-6">
                                    <span class="text-muted">Payee Employee:</span>
                                    <strong class="d-block text-dark">{{ $req->user->name ?? 'N/A' }}</strong>
                                </div>
                                <div class="col-md-6">
                                    <span class="text-muted">Category / Purpose:</span>
                                    <strong class="d-block text-dark">{{ $req->category }} @if($req->other_reason)({{ $req->other_reason }})@endif</strong>
                                </div>
                                <div class="col-md-6">
                                    <span class="text-muted">Funding Account (COA):</span>
                                    <strong class="d-block text-dark">{{ $req->chartOfAccount->name ?? 'N/A' }} ([{{ $req->chartOfAccount->code ?? 'N/A' }}])</strong>
                                </div>
                                <div class="col-md-6">
                                    <span class="text-muted">Available Balance:</span>
                                    <strong class="d-block text-success font-monospace">ETB {{ number_format($req->chartOfAccount->current_balance ?? 0, 2) }}</strong>
                                </div>
                            </div>
                        </div>

                        {{-- Designated Payee Bank Account Card (If Specified) --}}
                        @if($req->account_number || $req->is_bank_payment)
                            <div class="card border border-success border-opacity-50 rounded-3 mb-3 bg-success bg-opacity-10 overflow-hidden shadow-xs">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small class="text-success text-uppercase fw-bold" style="font-size:0.75rem;">
                                            <i class="fa-solid fa-building-columns me-1"></i>Payee Bank Account Details (ክፍያው የሚገባበት የባንክ ሂሳብ)
                                        </small>
                                        @if($req->account_number)
                                            <button type="button" class="btn btn-xs btn-outline-success py-0 px-2 fw-semibold" style="font-size: 11px;" onclick="navigator.clipboard.writeText('{{ $req->account_number }}'); this.innerText='Copied!'; setTimeout(()=>this.innerText='Copy', 1500);">
                                                <i class="fa-regular fa-copy me-1"></i>Copy Account #
                                            </button>
                                        @endif
                                    </div>
                                    <div class="row g-2 align-items-center">
                                        <div class="col-md-6 border-end pe-3">
                                            <span class="fs-5 fw-bold text-success font-monospace">{{ $req->account_number ?: 'Not specified' }}</span>
                                            <small class="text-muted d-block" style="font-size:0.75rem;">Bank: <strong>{{ $req->bank_name ?: 'Bank Transfer' }}</strong></small>
                                        </div>
                                        <div class="col-md-6 ps-3">
                                            <small class="text-muted text-uppercase fw-semibold d-block" style="font-size:0.72rem;">Account Holder Name (የባለቤቱ ስም):</small>
                                            <strong class="text-dark fs-6">{{ $req->account_name ?: ($req->user->name ?? 'N/A') }}</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Invoice / Base Amount --}}
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-dark">Invoice / Base Amount (ETB) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text fw-bold bg-light">ETB</span>
                                <input type="number" step="0.01" min="0.01" name="gross_amount" id="modalGrossAmount{{ $req->id }}" 
                                       class="form-control fw-bold fs-5 text-dark" 
                                       value="{{ $req->gross_amount ?? $req->amount }}" 
                                       oninput="recalculatePaymentTax({{ $req->id }})" required>
                            </div>
                        </div>

                        {{-- Service Tax & Deduction Config (VAT & Withholding) --}}
                        <div class="card border border-primary-subtle bg-light rounded-3 p-3 mb-3" id="paymentTaxPanel{{ $req->id }}">
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                <strong class="text-primary small text-uppercase">
                                    <i class="fa-solid fa-receipt me-1"></i>Service Tax &amp; Deduction Config (VAT &amp; Withholding)
                                </strong>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-0">Tax Calculator</span>
                            </div>

                            <div class="row g-3">
                                {{-- VAT Option --}}
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold mb-1">VAT Option (ቫት)</label>
                                    <select name="vat_type" id="modalVatType{{ $req->id }}" class="form-select form-select-sm" onchange="recalculatePaymentTax({{ $req->id }})">
                                        <option value="none" {{ ($req->vat_type ?? 'none') === 'none' ? 'selected' : '' }}>No VAT (0% / ያለ ቫት)</option>
                                        <option value="exclusive" {{ ($req->vat_type ?? '') === 'exclusive' ? 'selected' : '' }}>15% VAT Added (+15% ተጨማሪ ቫት)</option>
                                        <option value="vat_b" {{ in_array(($req->vat_type ?? ''), ['vat_b', 'inclusive']) ? 'selected' : '' }}>15% VAT Included / VAT B (ከቫት 15% ጋር የተካተተ - ቫት ቢ)</option>
                                    </select>
                                    <input type="hidden" name="vat_rate" id="modalVatRate{{ $req->id }}" value="{{ $req->vat_rate ?? 15.00 }}">
                                    <input type="hidden" name="vat_amount" id="modalVatAmount{{ $req->id }}" value="{{ $req->vat_amount ?? 0.00 }}">
                                </div>

                                {{-- Withholding Tax --}}
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold mb-1">Withholding Tax (የቅድመ ግብር 3%)</label>
                                    <div class="form-check form-switch mt-1">
                                        <input class="form-check-input" type="checkbox" role="switch" name="has_withholding" value="1" 
                                               id="modalWithholdingToggle{{ $req->id }}" 
                                               {{ ($req->has_withholding ?? false) ? 'checked' : '' }}
                                               onchange="recalculatePaymentTax({{ $req->id }})">
                                        <label class="form-check-label small" for="modalWithholdingToggle{{ $req->id }}">
                                            Apply 3% Service Withholding Deduction
                                        </label>
                                    </div>
                                    <input type="hidden" name="withholding_rate" id="modalWithholdingRate{{ $req->id }}" value="{{ $req->withholding_rate ?? 3.00 }}">
                                    <input type="hidden" name="withholding_amount" id="modalWithholdingAmount{{ $req->id }}" value="{{ $req->withholding_amount ?? 0.00 }}">
                                </div>
                            </div>

                            {{-- Real-time Tax Breakdown Card --}}
                            <div class="mt-3 p-2 bg-white rounded border shadow-sm">
                                <div class="row text-center g-2 small">
                                    <div class="col-3 border-end">
                                        <span class="text-muted d-block" style="font-size:0.75rem;">Base Amount</span>
                                        <strong class="text-dark" id="displayBaseAmount{{ $req->id }}">ETB {{ number_format($req->gross_amount ?? $req->amount, 2) }}</strong>
                                    </div>
                                    <div class="col-3 border-end">
                                        <span class="text-muted d-block" style="font-size:0.75rem;">VAT (15%)</span>
                                        <strong class="text-info" id="displayVatAmount{{ $req->id }}">+ ETB {{ number_format($req->vat_amount ?? 0, 2) }}</strong>
                                    </div>
                                    <div class="col-3 border-end">
                                        <span class="text-muted d-block" style="font-size:0.75rem;">Withholding (3%)</span>
                                        <strong class="text-danger" id="displayWhtAmount{{ $req->id }}">- ETB {{ number_format($req->withholding_amount ?? 0, 2) }}</strong>
                                    </div>
                                    <div class="col-3">
                                        <span class="text-muted d-block" style="font-size:0.75rem;">Net Payable</span>
                                        <strong class="text-success" id="displayNetAmount{{ $req->id }}">ETB {{ number_format($req->net_amount ?? $req->amount, 2) }}</strong>
                                    </div>
                                </div>
                            </div>

                            {{-- Withholding Tax Receipt & Voucher Upload Section --}}
                            <div id="withholdingReceiptSection{{ $req->id }}" class="mt-3 p-3 bg-white rounded-3 border border-danger-subtle shadow-sm" style="{{ ($req->has_withholding ?? false) ? '' : 'display:none;' }}">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <label class="form-label small fw-bold text-danger text-uppercase mb-0">
                                        <i class="fa-solid fa-file-invoice-dollar me-1"></i>Withholding Tax Receipt / Slip Upload (የቅድመ ግብር ደረሰኝ) <span class="text-danger">*</span>
                                    </label>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0">Required for 3% WHT</span>
                                </div>
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-7">
                                        <input type="file" name="withholding_receipt" id="modalWithholdingReceipt{{ $req->id }}" 
                                               class="form-control form-control-sm" 
                                               accept="image/jpeg,image/png,image/jpg,application/pdf,image/webp"
                                               data-has-existing="{{ !empty($req->withholding_receipt) ? '1' : '0' }}"
                                               {{ ($req->has_withholding ?? false) && empty($req->withholding_receipt) ? 'required' : '' }}>
                                        <small class="text-muted" style="font-size:0.75rem;">Upload official Withholding receipt image or PDF.</small>
                                    </div>
                                    <div class="col-md-5">
                                        <input type="text" name="withholding_receipt_number" id="modalWithholdingReceiptNo{{ $req->id }}" 
                                               class="form-control form-control-sm" 
                                               placeholder="WHT Receipt / Voucher #" 
                                               value="{{ $req->withholding_receipt_number ?? '' }}">
                                        <small class="text-muted" style="font-size:0.75rem;">Voucher / Receipt Serial # (Optional)</small>
                                    </div>
                                </div>
                                @if(!empty($req->withholding_receipt))
                                    <div class="mt-2 text-success small">
                                        <i class="fa-solid fa-circle-check me-1"></i> Existing slip uploaded: 
                                        <a href="{{ $req->withholding_receipt_url }}" target="_blank" class="fw-bold text-decoration-underline text-success">View Current Withholding Slip</a>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Main Expense Receipt / Payment Voucher Upload Section --}}
                        <div class="p-3 bg-white rounded-3 border border-success-subtle shadow-sm mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="form-label small fw-bold text-success text-uppercase mb-0">
                                    <i class="fa-solid fa-receipt me-1"></i>Expense Receipt / Payment Voucher Upload (የወጪ / የክፍያ ደረሰኝ)
                                </label>
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0">Payment Proof</span>
                            </div>
                            <div class="row g-2 align-items-center">
                                <div class="col-12">
                                    <input type="file" name="attachment" id="modalExpenseReceiptIdx{{ $req->id }}" 
                                           class="form-control form-control-sm" 
                                           accept="image/jpeg,image/png,image/jpg,application/pdf,image/webp">
                                    <small class="text-muted" style="font-size:0.75rem;">Upload paid receipt, cash voucher, scanned invoice, or disbursement document (PDF, JPG, PNG - max 10MB).</small>
                                </div>
                            </div>
                            @if(!empty($req->attachment))
                                <div class="mt-2 p-2 bg-light rounded border d-flex justify-content-between align-items-center small">
                                    <div>
                                        <i class="fa-solid fa-circle-check text-success me-1"></i>
                                        <strong class="text-dark">Attached Receipt:</strong>
                                        <span class="text-muted ms-1">Existing receipt on file</span>
                                    </div>
                                    <a href="{{ route('expense-requests.attachment', $req->id) }}" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2 shadow-xs">
                                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> View Receipt
                                    </a>
                                </div>
                            @endif
                        </div>

                        <input type="hidden" name="net_amount" id="modalNetAmount{{ $req->id }}" value="{{ $req->net_amount ?? $req->amount }}">
                        <input type="hidden" name="paid_amount" id="modalPaidAmount{{ $req->id }}" value="{{ $req->net_amount ?? $req->amount }}">

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Payment Reference / Transaction ID <span class="text-danger">*</span></label>
                            <input type="text" name="payment_reference" class="form-control" placeholder="e.g., CBE-TR-9823410" value="{{ 'PAY-' . strtoupper(Illuminate\Support\Str::random(6)) }}" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small">Payment Notes (Optional)</label>
                            <textarea name="payment_notes" class="form-control" rows="2" placeholder="Check #, transfer details, memo..."></textarea>
                        </div>

                        <div class="alert alert-warning py-2 small mb-0">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i>Confirming will deduct the net amount (<strong id="alertDeductAmount{{ $req->id }}">ETB {{ number_format($req->net_amount ?? $req->amount, 2) }}</strong>) from the selected account balance and record journal entries atomically.
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-top-0 d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success fw-bold px-4" id="btnConfirmPaid{{ $req->id }}">
                            <i class="fa-solid fa-check-circle me-1"></i> Confirm &amp; Mark as Paid (<span id="btnPayAmount{{ $req->id }}">ETB {{ number_format($req->net_amount ?? $req->amount, 2) }}</span>)
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- Details Modal --}}
    @php $pcr = $req->linked_replenishment; @endphp
    <div class="modal fade" id="detailsModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog {{ $pcr ? 'modal-xl' : 'modal-lg' }} modal-dialog-centered">
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
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <div class="text-muted small mb-1"><i class="fa-solid fa-user-pen text-primary me-1"></i>Asker (Requested By):</div>
                                <div class="fw-bold fs-6 text-dark">{{ $req->user->name ?? 'N/A' }}</div>
                                <div class="small text-muted">{{ $req->user->email ?? '' }}</div>
                                @if($req->employee)
                                    <div class="mt-2 pt-2 border-top">
                                        <div class="text-muted small"><i class="fa-solid fa-id-badge text-info me-1"></i>Assigned Beneficiary / Driver:</div>
                                        <div class="fw-semibold text-dark">{{ $req->employee->full_name }}</div>
                                        <small class="text-muted">{{ $req->employee->department ?? '' }} ({{ $req->employee->role_title ?? 'Staff' }})</small>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <div class="text-muted small mb-1">Request Amount:</div>
                                <div class="fw-bold fs-4 text-success mb-1">ETB {{ number_format($req->amount, 2) }}</div>
                                <div class="small mb-2">{!! $req->status_badge !!}</div>
                                @php
                                    $detailAppr = (is_array($req->approver_info ?? null) ? $req->approver_info : (method_exists($req, 'getApproverInfoAttribute') ? $req->getApproverInfoAttribute() : []));
                                    $dApproved = !empty($detailAppr['is_approved']);
                                    $dRejected = !empty($detailAppr['is_rejected']) || ($req->status === 'Rejected');
                                    $dName = $detailAppr['name'] ?? ($req->gmApprover->name ?? $req->gmReviewer->name ?? $req->hrReviewer->name ?? null);
                                    $dRole = $detailAppr['role'] ?? ($req->gm_approved_at ? 'General Manager (GM)' : ($req->hr_reviewed_at ? 'HR / Coordinator' : null));
                                    $dDate = $detailAppr['date'] ?? ($req->gm_approved_at ?? $req->hr_reviewed_at ?? null);
                                    $dText = $detailAppr['text'] ?? ($dName ? ($dName . ($dRole ? " ({$dRole})" : '')) : ($dRejected ? 'Rejected' : ($req->status ?? 'Pending Review')));
                                @endphp
                                <div class="mt-2 pt-2 border-top">
                                    <div class="text-muted small"><i class="fa-solid fa-user-check text-success me-1"></i>Approval Information:</div>
                                    <div class="small mt-1">
                                        @if(!empty($dName))
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                <i class="fa-solid fa-check me-1"></i>Approved by: <strong>{{ $dName }}</strong> @if($dRole) ({{ $dRole }}) @endif
                                            </span>
                                            @if(!empty($dDate))
                                                <div class="text-muted mt-1" style="font-size:0.72rem;">
                                                    <i class="fa-regular fa-clock me-1"></i>{{ \Carbon\Carbon::parse($dDate)->format('d M Y, h:i A') }}
                                                </div>
                                            @endif
                                        @elseif($dRejected)
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                                <i class="fa-solid fa-xmark me-1"></i>{{ $dText }}
                                            </span>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">
                                                <i class="fa-solid fa-hourglass-half me-1"></i>{{ $dText }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Payee Bank Account Details (If Specified) --}}
                    @if($req->account_number || $req->is_bank_payment)
                        <div class="card border border-success border-opacity-50 rounded-3 mb-3 bg-success bg-opacity-10 overflow-hidden shadow-xs">
                            <div class="card-header bg-success bg-opacity-10 py-2 px-3 d-flex justify-content-between align-items-center">
                                <span class="small fw-bold text-success text-uppercase">
                                    <i class="fa-solid fa-building-columns me-1"></i>Payee Bank Account (ክፍያ የሚፈጸምበት የባንክ ሂሳብ)
                                </span>
                                @if($req->account_number)
                                    <button type="button" class="btn btn-xs btn-outline-success py-0 px-2 fw-semibold" style="font-size: 11px;" onclick="navigator.clipboard.writeText('{{ $req->account_number }}'); this.innerText='Copied!'; setTimeout(()=>this.innerText='Copy', 1500);">
                                        <i class="fa-regular fa-copy me-1"></i>Copy Account #
                                    </button>
                                @endif
                            </div>
                            <div class="card-body p-3 bg-white">
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-4 border-end pe-2">
                                        <small class="text-muted text-uppercase fw-semibold d-block" style="font-size:0.72rem;">Account Number (የሂሳብ ቁጥር):</small>
                                        <span class="fs-5 fw-bold text-success font-monospace">{{ $req->account_number ?: 'Not specified' }}</span>
                                    </div>
                                    <div class="col-md-4 border-end px-2">
                                        <small class="text-muted text-uppercase fw-semibold d-block" style="font-size:0.72rem;">Bank Name (የባንክ ስም):</small>
                                        <strong class="text-dark fs-6">{{ $req->bank_name ?: 'Bank Transfer' }}</strong>
                                    </div>
                                    <div class="col-md-4 ps-2">
                                        <small class="text-muted text-uppercase fw-semibold d-block" style="font-size:0.72rem;">Account Holder Name (የባለቤቱ ስም):</small>
                                        <strong class="text-dark fs-6">{{ $req->account_name ?: ($req->user->name ?? 'N/A') }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="fw-bold">Category & Reason:</label>
                        <div><span class="badge bg-secondary">{{ $req->category }}</span> {{ $req->other_reason }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold">Description:</label>
                        <div class="p-3 bg-light rounded border">{{ $req->description }}</div>
                    </div>

                    @if($pcr)
                        {{-- Detailed Itemized Statement & Receipts Breakdown --}}
                        <div class="card border border-primary border-opacity-25 rounded-3 mb-3 overflow-hidden shadow-xs">
                            <div class="card-header bg-primary bg-opacity-10 py-2 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-receipt text-primary fs-5"></i>
                                    <div>
                                        <strong class="text-dark">Petty Cash Replenishment Statement &amp; All Receipts (#{{ $pcr->request_no }})</strong>
                                        <div class="text-muted small">Account: [{{ $pcr->chartOfAccount->code ?? '1010' }}] {{ $pcr->chartOfAccount->name ?? 'Petty Cash' }} &bull; Custodian: {{ $pcr->requester->name ?? 'Staff' }}</div>
                                    </div>
                                </div>
                                @if($pcr->attachment_url)
                                    <a href="{{ $pcr->attachment_url }}" target="_blank" class="btn btn-sm btn-primary rounded-pill px-3 shadow-xs">
                                        <i class="fa-solid fa-file-invoice me-1"></i> View Scanned Receipts Document
                                    </a>
                                @endif
                            </div>
                            <div class="card-body p-0">
                                @if($pcr->audited_by)
                                    <div class="p-2 px-3 bg-success bg-opacity-10 border-bottom border-success border-opacity-25 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                        <div>
                                            <i class="fa-solid fa-circle-check text-success me-1"></i>
                                            <strong class="text-dark small">Audit Cleared by:</strong>
                                            <span class="text-muted small">{{ $pcr->auditor->name ?? 'Auditor' }} ({{ optional($pcr->audited_at)->format('d M Y, H:i') }})</span>
                                            @if($pcr->audit_notes)
                                                <span class="text-dark small ms-2"><em>"{{ $pcr->audit_notes }}"</em></span>
                                            @endif
                                        </div>
                                        <span class="badge bg-success font-monospace">Audit Cleared</span>
                                    </div>
                                @endif

                                <div class="row g-0 text-center border-bottom bg-light">
                                    <div class="col-3 p-2 border-end">
                                        <div class="text-muted small fw-bold">Start Balance</div>
                                        <div class="fw-bold text-dark font-monospace">ETB {{ number_format($pcr->current_balance_at_request, 2) }}</div>
                                    </div>
                                    <div class="col-3 p-2 border-end">
                                        <div class="text-muted small fw-bold">Total Reconciled Spent</div>
                                        <div class="fw-bold text-danger font-monospace">ETB {{ number_format($pcr->total_expenses_amount, 2) }}</div>
                                    </div>
                                    <div class="col-3 p-2 border-end">
                                        <div class="text-muted small fw-bold">Replenishment Due</div>
                                        <div class="fw-bold text-success font-monospace">ETB {{ number_format($pcr->requested_amount, 2) }}</div>
                                    </div>
                                    <div class="col-3 p-2">
                                        <div class="text-muted small fw-bold">Vouchers</div>
                                        <div class="fw-bold text-primary">{{ $pcr->items->count() }} Attached</div>
                                    </div>
                                </div>

                                <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                                    <table class="table table-hover table-sm align-middle mb-0" style="font-size: 0.85rem;">
                                        <thead class="table-light text-muted small text-uppercase sticky-top">
                                            <tr>
                                                <th class="ps-3 py-2" style="width: 40px;">#</th>
                                                <th class="py-2" style="width: 100px;">Date</th>
                                                <th class="py-2" style="width: 130px;">Voucher / Ref #</th>
                                                <th class="py-2">Description / Payee</th>
                                                <th class="py-2">Target Account</th>
                                                <th class="py-2 pe-3 text-end" style="width: 120px;">Amount (ETB)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($pcr->items as $idx => $receiptItem)
                                                <tr>
                                                    <td class="ps-3 text-muted">{{ $idx + 1 }}</td>
                                                    <td>{{ optional($receiptItem->entry_date)->format('M d, Y') ?? '—' }}</td>
                                                    <td><span class="badge bg-light text-dark border font-monospace">{{ $receiptItem->reference ?: 'Voucher' }}</span></td>
                                                    <td><div class="fw-semibold text-dark">{{ $receiptItem->description }}</div></td>
                                                    <td><span class="badge bg-secondary bg-opacity-10 text-dark">{{ $receiptItem->target_account_name ?: 'General' }}</span></td>
                                                    <td class="pe-3 text-end fw-bold font-monospace text-dark">ETB {{ number_format($receiptItem->amount, 2) }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="6" class="text-center py-3 text-muted">No individual line vouchers recorded.</td></tr>
                                            @endforelse
                                        </tbody>
                                        <tfoot class="table-light">
                                            <tr class="fw-bold">
                                                <td colspan="5" class="text-end pe-2">Total Verified Receipts:</td>
                                                <td class="pe-3 text-end font-monospace text-success">ETB {{ number_format($pcr->items->sum('amount') ?: $pcr->requested_amount, 2) }}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($req->maintenanceRequest)
                        <div class="card border border-warning border-opacity-25 rounded-3 mb-3 overflow-hidden shadow-xs">
                            <div class="card-header bg-warning bg-opacity-10 py-2 px-3">
                                <strong class="text-dark"><i class="fa-solid fa-screwdriver-wrench text-warning me-2"></i>Linked Maintenance Request: {{ $req->maintenanceRequest->request_no }}</strong>
                            </div>
                            <div class="card-body p-3">
                                <div class="row g-2 small">
                                    <div class="col-md-6"><strong>Asset:</strong> {{ $req->maintenanceRequest->asset_name }} ({{ $req->maintenanceRequest->asset_code ?? 'Tag' }})</div>
                                    <div class="col-md-6"><strong>Issue:</strong> {{ $req->maintenanceRequest->issue_type_label }}</div>
                                    <div class="col-12 mt-2"><strong>Fault Description:</strong> {{ $req->maintenanceRequest->description }}</div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @php $linkedPr = $req->purchaseRequest; @endphp
                    @if($linkedPr)
                        <div class="card border border-success border-opacity-25 rounded-3 mb-3 overflow-hidden shadow-sm">
                            <div class="card-header bg-success bg-opacity-10 py-2 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-cart-shopping text-success fs-5"></i>
                                    <div>
                                        <strong class="text-dark">Procurement &amp; Purchase Request Statement</strong>
                                        <div class="text-muted small">PR #{{ $linkedPr->pr_no }} &bull; Originated from GM-Approved Purchase Request</div>
                                    </div>
                                </div>
                                <a href="{{ url('/purchase-requests/' . $linkedPr->id) }}" target="_blank" class="btn btn-sm btn-success rounded-pill px-3">
                                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> View Full PR
                                </a>
                            </div>
                            <div class="card-body p-3">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <div class="p-2 bg-light rounded border">
                                            <div class="text-muted small fw-bold">PR Number</div>
                                            <div class="fw-bold text-dark font-monospace">{{ $linkedPr->pr_no }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-2 bg-light rounded border">
                                            <div class="text-muted small fw-bold">Project</div>
                                            <div class="fw-bold text-dark">{{ $linkedPr->project->name ?? '—' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-2 bg-light rounded border">
                                            <div class="text-muted small fw-bold">Supplier</div>
                                            @php
                                                $prSupplier = $linkedPr->proformaInvoices()->where('gm_selected', true)->first();
                                                $supplierLabel = $prSupplier ? ($prSupplier->supplier->name ?? $prSupplier->supplier_name ?? '—') : ($linkedPr->supplier->name ?? '—');
                                            @endphp
                                            <div class="fw-bold text-dark">{{ $supplierLabel }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-2 bg-light rounded border">
                                            <div class="text-muted small fw-bold">Total Amount</div>
                                            <div class="fw-bold text-success fs-6 font-monospace">ETB {{ number_format($linkedPr->direct_buy_amount ?? $req->amount, 2) }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-2 bg-light rounded border">
                                            <div class="text-muted small fw-bold">Sourcing Method</div>
                                            <div class="fw-bold text-dark">{{ ucwords(str_replace('_', ' ', $linkedPr->sourcing_method ?? 'proforma')) }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-2 bg-light rounded border">
                                            <div class="text-muted small fw-bold">PR Status</div>
                                            <div><span class="badge bg-warning text-dark">{{ ucwords(str_replace('_', ' ', $linkedPr->status)) }}</span></div>
                                        </div>
                                    </div>
                                </div>

                                @php $gmDecision = $linkedPr->gmDecisions()->latest()->first(); @endphp
                                @if($gmDecision && $gmDecision->notes)
                                <div class="alert alert-info border-start border-4 border-info py-2 px-3 mb-3 small">
                                    <i class="fa-solid fa-user-shield text-info me-1"></i>
                                    <strong>GM Approval Notes:</strong> {{ $gmDecision->notes }}
                                </div>
                                @endif

                                @if($linkedPr->items && $linkedPr->items->count() > 0)
                                <div>
                                    <strong class="d-block mb-2 small text-muted text-uppercase"><i class="fa-solid fa-list-ul me-1"></i>Requested Items</strong>
                                    <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                                        <table class="table table-sm table-bordered align-middle mb-0" style="font-size: 0.82rem;">
                                            <thead class="table-dark small text-uppercase">
                                                <tr>
                                                    <th class="ps-2">#</th>
                                                    <th>Item / Material</th>
                                                    <th class="text-center">Qty</th>
                                                    <th class="text-center">Unit</th>
                                                    <th class="text-end pe-2">Unit Price</th>
                                                    <th class="text-end pe-2">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                 @php
                                                     $selectedProforma = $linkedPr->proformaInvoices ? $linkedPr->proformaInvoices->firstWhere('gm_selected', true) : null;
                                                 @endphp
                                                 @foreach($linkedPr->items as $idx => $prItem)
                                                 @php
                                                     $itemName = $prItem->product?->name ?? $prItem->item_name ?? $prItem->description ?? ('Item #' . ($prItem->product_id ?? ($idx + 1)));
                                                     $qty = (float)$prItem->quantity;
                                                     $unitPrice = (float)($prItem->estimated_unit_cost ?? $prItem->unit_price ?? $prItem->estimated_unit_price ?? 0);
                                                     if ($unitPrice <= 0 && $selectedProforma && $selectedProforma->items) {
                                                         $pItem = $selectedProforma->items->firstWhere('product_id', $prItem->product_id);
                                                         if ($pItem && (float)$pItem->unit_price > 0) {
                                                             $unitPrice = (float)$pItem->unit_price;
                                                         }
                                                     }
                                                     if ($unitPrice <= 0 && $linkedPr->items->count() === 1 && (float)($linkedPr->direct_buy_amount ?? $req->amount) > 0) {
                                                         $unitPrice = (float)($linkedPr->direct_buy_amount ?? $req->amount) / ($qty > 0 ? $qty : 1);
                                                     }
                                                     $lineTotal = $qty * $unitPrice;
                                                     if ($lineTotal <= 0 && $linkedPr->items->count() === 1 && (float)($linkedPr->direct_buy_amount ?? $req->amount) > 0) {
                                                         $lineTotal = (float)($linkedPr->direct_buy_amount ?? $req->amount);
                                                     }
                                                 @endphp
                                                 <tr>
                                                     <td class="ps-2 text-muted">{{ $idx + 1 }}</td>
                                                     <td>
                                                         <div class="fw-semibold text-dark">{{ $itemName }}</div>
                                                         @if(!empty($prItem->specifications) || !empty($prItem->specification))
                                                             <div class="text-muted small">{{ $prItem->specifications ?? $prItem->specification }}</div>
                                                         @endif
                                                     </td>
                                                     <td class="text-center fw-bold">{{ number_format($qty, 3) }}</td>
                                                     <td class="text-center text-muted">{{ $prItem->unit ?? 'pcs' }}</td>
                                                     <td class="text-end pe-2 font-monospace">{{ number_format($unitPrice, 2) }}</td>
                                                     <td class="text-end pe-2 fw-bold font-monospace text-success">
                                                         {{ number_format($lineTotal, 2) }}
                                                     </td>
                                                 </tr>
                                                 @endforeach
                                             </tbody>
                                             <tfoot class="table-light">
                                                 @php
                                                     $calcGrandTotal = (float)($linkedPr->direct_buy_amount ?? 0);
                                                     if ($calcGrandTotal <= 0 && $selectedProforma && (float)$selectedProforma->total_amount > 0) {
                                                         $calcGrandTotal = (float)$selectedProforma->total_amount;
                                                     }
                                                     if ($calcGrandTotal <= 0 && (float)$req->amount > 0) {
                                                         $calcGrandTotal = (float)$req->amount;
                                                     }
                                                 @endphp
                                                 <tr class="fw-bold">
                                                     <td colspan="5" class="text-end pe-2">Grand Total:</td>
                                                     <td class="text-end pe-2 font-monospace text-success">
                                                         ETB {{ number_format($calcGrandTotal, 2) }}
                                                     </td>
                                                 </tr>
                                             </tfoot>
                                        </table>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Workflow Timeline --}}
                    <div class="mb-3">
                        <label class="fw-bold mb-2"><i class="fa-solid fa-timeline me-1"></i>Approval & Payment Timeline:</label>
                        <ul class="list-group list-group-flush border rounded">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>
                                    <i class="fa-solid fa-paper-plane text-primary me-2"></i>
                                    Asked by <strong>{{ $req->user->name ?? 'Requester' }}</strong>
                                    @if($req->employee && $req->user && $req->employee->full_name !== $req->user->name)
                                        <span class="text-muted">(for {{ $req->employee->full_name }})</span>
                                    @endif
                                </span>
                                <small class="text-muted">{{ $req->created_at->format('M d, Y H:i') }}</small>
                            </li>

                            @if($req->employee_approved_at)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fa-solid fa-id-badge text-info me-2"></i>Confirmed by Beneficiary ({{ $req->employee->full_name ?? 'Employee' }})</span>
                                <small class="text-muted">{{ $req->employee_approved_at->format('M d, Y H:i') }}</small>
                            </li>
                            @endif

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

                    {{-- ── 4. Fiscal Receipt & OCR Studio with ON/OFF VAT Switch ── --}}
                    @php
                        $isVatActive = ($req->vat_amount > 0 && $req->vat_type !== 'none') || (!$req->attachment && ($req->vat_type ?? '') !== 'none');
                        $linkedRcp = $req->receipt;
                    @endphp
                    <div class="card border border-2 rounded-4 mb-3 overflow-hidden shadow-sm" style="border-color: #10b981 !important;">
                        <div class="card-header bg-dark text-white py-2.5 px-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-success text-white p-1.5 rounded-circle">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                                </span>
                                <div>
                                    <h6 class="fw-bold mb-0 text-white small text-uppercase">
                                        Receipt OCR Studio &amp; Fiscal Proof (ደረሰኝ እና የቫት ማረጋገጫ)
                                    </h6>
                                    <span class="text-white-50" style="font-size:0.72rem;">
                                        AI Vision Fiscal Receipt Scanner &bull; Multi-Engine Extraction &bull; ERCA Verification
                                    </span>
                                </div>
                            </div>
                            <div id="vatStatusBadgeContainer_{{ $req->id }}">
                                @if($req->attachment)
                                    @if($req->vat_amount > 0 && $req->vat_type !== 'none')
                                        <span class="badge bg-success-subtle text-success border border-success font-monospace" style="font-size:0.72rem;">
                                            <i class="fa-solid fa-circle-check me-1"></i>Saved in VAT (+ETB {{ number_format($req->vat_amount, 2) }})
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary font-monospace" style="font-size:0.72rem;">
                                            <i class="fa-solid fa-ban me-1"></i>Non-VAT Expense Proof
                                        </span>
                                    @endif
                                @else
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning font-monospace" style="font-size:0.72rem;">
                                        <i class="fa-solid fa-circle-exclamation me-1"></i>No Receipt Attached Yet
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="card-body p-3 bg-white">
                            {{-- Existing Receipt Status & View Link --}}
                            <div id="existingReceiptBox_{{ $req->id }}" class="{{ $req->attachment ? '' : 'd-none' }}">
                                <div class="alert alert-light border d-flex justify-content-between align-items-center py-2 px-3 mb-3 rounded-3 shadow-xs">
                                    <div class="d-flex align-items-center gap-2.5">
                                        <i class="fa-solid fa-file-invoice text-success fs-4"></i>
                                        <div>
                                            <strong class="text-dark d-block small">Existing Receipt Document on File</strong>
                                            <div class="text-muted" style="font-size:0.74rem;" id="existingReceiptInfo_{{ $req->id }}">
                                                @if($req->vat_amount > 0 && $req->vat_type !== 'none')
                                                    <span class="text-success fw-bold"><i class="fa-solid fa-check-double me-1"></i>ERCA VAT Line 100 Active (VAT: ETB {{ number_format($req->vat_amount, 2) }})</span>
                                                @else
                                                    <span class="text-secondary fw-semibold"><i class="fa-solid fa-circle-info me-1"></i>Non-VAT Proof (Excluded from ERCA VAT Reports)</span>
                                                @endif
                                                @if($linkedRcp && $linkedRcp->fs_no)
                                                    &bull; FS: <span class="font-monospace text-dark fw-bold">{{ $linkedRcp->fs_no }}</span>
                                                @endif
                                                @if($linkedRcp && $linkedRcp->vendor_tin)
                                                    &bull; TIN: <span class="font-monospace text-dark fw-bold">{{ $linkedRcp->vendor_tin }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <a href="{{ route('expense-requests.attachment', $req->id) }}" id="btnViewReceipt_{{ $req->id }}" target="_blank" class="btn btn-sm btn-outline-success px-3 shadow-xs fw-semibold">
                                            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> View Receipt
                                        </a>
                                    </div>
                                </div>
                            </div>

                            {{-- MASTER ON / OFF SWITCH: Save in VAT --}}
                            <div class="card p-3 mb-3 border shadow-xs transition-all {{ $isVatActive ? 'border-success bg-success bg-opacity-10' : 'border-secondary-subtle bg-light' }}" id="vatCard_{{ $req->id }}">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div class="me-3">
                                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                            <h6 class="mb-0 fw-bold text-dark">
                                                <i class="fa-solid fa-receipt text-primary me-1"></i> Save to VAT Report (በቫት መዝገብ ላይ ይግባ)?
                                            </h6>
                                            <span class="badge {{ $isVatActive ? 'bg-success' : 'bg-secondary' }}" id="vatStatusBadge_{{ $req->id }}">
                                                {{ $isVatActive ? '✅ Save to VAT (ERCA Line 100)' : '⚪ Do NOT Save to VAT (Non-VAT)' }}
                                            </span>
                                        </div>
                                        <small class="text-muted d-block" id="vatHelpText_{{ $req->id }}">
                                            {{ $isVatActive
                                                ? 'ON: Scanned receipt will be declared in ERCA VAT Line 100 & VAT Reports (+15% input tax claim).'
                                                : 'OFF: Non-VAT expense proof only. Receipt is saved for records but will NOT be saved or declared in VAT reports.' }}
                                        </small>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input ms-0 cursor-pointer" type="checkbox" role="switch" name="save_in_vat_toggle" id="vatSwitch_{{ $req->id }}"
                                               value="1" {{ $isVatActive ? 'checked' : '' }}
                                               onchange="onVatSwitchChanged({{ $req->id }}, this.checked)">
                                        <label class="form-check-label visually-hidden" for="vatSwitch_{{ $req->id }}">Save in VAT</label>
                                    </div>
                                </div>
                            </div>

                            {{-- Feedback Alert Box for AJAX --}}
                            <div id="ocrSaveAlert_{{ $req->id }}" style="display:none;" class="mb-3"></div>

                            {{-- Receipt Attachment Form & OCR Studio Container --}}
                            <form action="{{ route('expense-requests.attach-receipt', $req->id) }}" method="POST" enctype="multipart/form-data" id="attachReceiptForm_{{ $req->id }}" data-req-id="{{ $req->id }}" data-has-existing="{{ !empty($req->attachment) ? '1' : '0' }}">
                                @csrf
                                <input type="hidden" name="save_in_vat" id="hiddenSaveInVat_{{ $req->id }}" value="{{ $isVatActive ? '1' : '0' }}">
                                <input type="hidden" name="vendor_name" id="ocrVendor_{{ $req->id }}" value="{{ $linkedRcp->vendor_name ?? '' }}">
                                <input type="hidden" name="supplier_tin" id="ocrTin_{{ $req->id }}" value="{{ $linkedRcp->vendor_tin ?? '' }}">
                                <input type="hidden" name="fs_no" id="ocrFs_{{ $req->id }}" value="{{ $linkedRcp->fs_no ?? '' }}">
                                <input type="hidden" name="receipt_date" id="ocrDate_{{ $req->id }}" value="{{ optional($linkedRcp?->receipt_date)->format('Y-m-d') ?? '' }}">
                                <input type="hidden" name="mrc" id="ocrMrc_{{ $req->id }}" value="{{ $linkedRcp->mrc_no ?? '' }}">
                                <input type="hidden" name="subtotal" id="ocrSubtotal_{{ $req->id }}" value="{{ $req->gross_amount ?? $req->amount }}">
                                <input type="hidden" name="vat_amount" id="ocrVat_{{ $req->id }}" value="{{ $req->vat_amount ?? '0.00' }}">
                                <input type="hidden" name="total_amount" id="ocrTotal_{{ $req->id }}" value="{{ $req->gross_amount ?? $req->amount }}">
                                <input type="hidden" name="ocr_items_json" id="ocrItemsJson_{{ $req->id }}">

                                {{-- Drag & Drop Upload Trigger --}}
                                <div class="border border-2 border-dashed rounded-3 p-3 text-center bg-light cursor-pointer mb-2"
                                     style="border-color:#10b981 !important;"
                                     onclick="document.getElementById('receiptFileInput_{{ $req->id }}').click()">
                                    <input type="file" name="receipt_file" id="receiptFileInput_{{ $req->id }}" class="d-none" accept=".pdf,.jpg,.jpeg,.png,.webp">
                                    <i class="fa-solid fa-cloud-arrow-up fa-2x text-success mb-1.5"></i>
                                    <div class="fw-bold small text-dark">
                                        {{ $req->attachment ? 'Click or Drop File to Replace / Re-Scan Receipt' : 'Click to Browse or Drag & Drop Fiscal Receipt / Slip' }}
                                    </div>
                                    <div class="text-muted" style="font-size:0.72rem;">JPG, PNG, WEBP, PDF up to 10MB &bull; Automatic AI Vision Extraction</div>
                                </div>

                                {{-- Studio Feedback Container --}}
                                <div id="ocrFeedbackArea_{{ $req->id }}" class="mt-2"></div>

                                {{-- Submit Button --}}
                                <div class="mt-3">
                                    <button type="submit" id="btnSaveReceipt_{{ $req->id }}" class="btn btn-success fw-bold w-100 py-2.5 shadow-sm">
                                        <i class="fa-solid fa-cloud-arrow-up me-1"></i>
                                        <span id="btnSaveReceiptText_{{ $req->id }}">
                                            {{ $isVatActive ? 'Save Receipt & Save to VAT (+15% Tax Claim)' : 'Save Receipt Only (Do NOT Save in VAT)' }}
                                        </span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    @if($req->withholding_receipt)
                    <div class="mb-3">
                        <label class="fw-bold mb-1 text-danger"><i class="fa-solid fa-file-invoice-dollar me-1"></i>3% Withholding Tax Receipt / Slip:</label>
                        <div>
                            <a href="{{ $req->withholding_receipt_url }}" target="_blank" class="btn btn-outline-danger btn-sm shadow-sm">
                                <i class="fa-solid fa-file-pdf me-1"></i>View Withholding Slip @if($req->withholding_receipt_number) (Ref: {{ $req->withholding_receipt_number }}) @endif
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
        <div class="modal-content shadow border-0 rounded-4 overflow-hidden">
            <form method="POST" action="{{ route('expense-requests.store') }}" enctype="multipart/form-data" id="createExpenseRequestForm">
                @csrf
                <div class="modal-header bg-success bg-gradient text-white py-3 px-4">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-hand-holding-dollar me-2"></i>New Expense Request ("Ask Money")</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Category <span class="text-danger">*</span></label>
                            <select name="category" id="categorySelect" class="form-select fw-semibold" onchange="toggleCategoryOptions()" required>
                                <option value="Service">🤝 Service (አገልግሎት)</option>
                                <option value="Transport">🚚 Transport (ትራንስፖርት)</option>
                                <option value="Loading & Unloading">📦 Loading &amp; Unloading (መጫን እና ማውረድ)</option>
                                <option value="Contract Work">📝 Contract Work (የኮንትራት ስራ)</option>
                                <option value="Office Material">📁 Office Material (የቢሮ እቃ)</option>
                                <option value="Maintenance">🔧 Maintenance &amp; Repairs (ጥገና)</option>
                                <option value="Other">✨ Other (ሌሎች)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Invoice / Base Amount (ETB) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text fw-bold bg-light">ETB</span>
                                <input type="number" step="0.01" min="0.01" name="gross_amount" id="createGrossAmount" class="form-control fw-bold fs-5 text-dark" placeholder="0.00" oninput="recalculateCreateTaxes()" required>
                            </div>
                        </div>

                        {{-- Project Selection (Mandatory for Loading & Unloading, Optional for other expenses) --}}
                        <div class="col-12" id="projectSelectGroup">
                            <div class="p-3 bg-light rounded-3 border border-1 shadow-sm" id="projectContainer">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label fw-bold text-dark mb-0">
                                        <i class="fa-solid fa-helmet-safety text-warning me-1"></i>Project / Site (የስራ ፕሮጀክት / ሳይት ይምረጡ)
                                        <span class="text-danger" id="projectRequiredAsterisk" style="display: none;">*</span>
                                    </label>
                                    <span class="badge bg-secondary-subtle text-secondary px-2 py-1" id="projectCategoryBadge">
                                        Optional / እንደ አስፈላጊነቱ
                                    </span>
                                </div>
                                <select name="project_id" id="projectSelect" class="form-select fw-semibold mt-1">
                                    <option value="">-- Select Project / Site (ፕሮጀክት ይምረጡ) --</option>
                                    @foreach($projects ?? [] as $proj)
                                        <option value="{{ $proj->id }}">
                                            🏗️ {{ $proj->name }} @if(!empty($proj->code))({{ $proj->code }})@endif @if(!empty($proj->location)) — {{ $proj->location }}@endif
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted d-block mt-1" id="projectHelpText">
                                    <i class="fa-solid fa-circle-info me-1"></i>Select the destination project site for this expense request.
                                </small>
                            </div>
                        </div>

                        {{-- Employee Selection for Transport / Loading & Unloading Expense --}}
                        <div class="col-12" id="employeeSelectGroup" style="display: none;">
                            <div class="p-3 bg-light border border-info border-2 rounded-3 shadow-sm position-relative overflow-hidden">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label fw-bold text-dark mb-0 fs-6">
                                        <i class="fa-solid fa-user-tag text-info me-1"></i>Assign Beneficiary Employee / Driver / Handler (ተጠቃሚ ሰራተኛ / አሽከርካሪ ይምረጡ) <span class="text-danger" id="employeeRequiredAsterisk">*</span>
                                    </label>
                                    <span class="badge bg-info text-white px-2 py-1" id="employeeCategoryBadge"><i class="fa-solid fa-truck-ramp-box me-1"></i>Transport &amp; Loading Allocation</span>
                                </div>
                                @php
                                    $currentUserEmp = auth()->user()->employee;
                                    $currentEmpId = $currentUserEmp?->id;
                                    $currentUserId = auth()->id();
                                    $currentEmpBank = $currentUserEmp?->bank_name;
                                    $currentEmpAcc = $currentUserEmp?->account_number;
                                    $currentEmpName = $currentUserEmp?->full_name ?? auth()->user()->name;
                                @endphp
                                <select name="employee_id" id="employeeSelect" class="form-select form-select-lg fw-semibold mt-1 border-info" onchange="onAssignedEmployeeChanged(this)">
                                    <option value="" selected disabled>-- Select Another Employee / Driver / Handler (ሌላ ሰራተኛ/አሽከርካሪ ይምረጡ) --</option>
                                    @foreach($employees ?? [] as $emp)
                                        @if(($currentEmpId && $emp->id == $currentEmpId) || ($emp->user_id && $emp->user_id == $currentUserId))
                                            @continue
                                        @endif
                                        <option value="{{ $emp->id }}"
                                                data-bank="{{ $emp->bank_name ?? '' }}"
                                                data-account="{{ $emp->account_number ?? '' }}"
                                                data-name="{{ $emp->full_name ?? '' }}">
                                            👤 {{ $emp->full_name }} ({{ $emp->employee_code ?? 'EMP' }}) — {{ $emp->role_title ?? $emp->department ?? 'Staff' }}
                                        </option>
                                    @endforeach
                                </select>

                                {{-- Strict Warning Notice: Self-Assignment Prohibited --}}
                                <div class="mt-2 p-2 bg-danger-subtle text-danger rounded border border-danger-subtle small d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-ban text-danger fs-5"></i>
                                    <div>
                                        <strong class="text-danger d-block">Strict Policy: Self-Assignment Prohibited (ራስዎን መምረጥ በጥብቅ የተከለከለ ነው)</strong>
                                        <span class="text-dark" style="font-size: 0.8rem;">You cannot assign transport or loading/unloading money to yourself. You must select the actual beneficiary driver or employee who will receive the funds.</span>
                                    </div>
                                </div>

                                <div class="mt-2 p-2 bg-white rounded border border-info-subtle small d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-shield-halved text-success fs-5"></i>
                                    <div>
                                        <strong class="text-dark d-block">First Approval Step: Assigned Employee Confirmation</strong>
                                        <span class="text-muted" style="font-size: 0.8rem;">This request will first be sent to the assigned employee/driver for confirmation, then route to <strong>HR or Coordinator</strong> and GM/Finance.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Service Tax & Deduction Section (VAT & Withholding) --}}
                        <div class="col-12" id="createTaxSection">
                            <div class="card border border-primary-subtle bg-light rounded-3 p-3">
                                <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                    <strong class="text-primary small text-uppercase">
                                        <i class="fa-solid fa-receipt me-1"></i>Service Tax &amp; Deduction Config (VAT &amp; Withholding)
                                    </strong>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-0">Tax Calculator</span>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold mb-1">VAT Option (ቫት)</label>
                                        <select name="vat_type" id="createVatType" class="form-select form-select-sm" onchange="recalculateCreateTaxes()">
                                            <option value="none">No VAT (0% / ያለ ቫት)</option>
                                            <option value="exclusive">15% VAT Added (+15% ተጨማሪ ቫት)</option>
                                            <option value="vat_b">15% VAT Included / VAT B (ከቫት 15% ጋር የተካተተ - ቫት ቢ)</option>
                                        </select>
                                        <input type="hidden" name="vat_rate" id="createVatRate" value="15.00">
                                        <input type="hidden" name="vat_amount" id="createVatAmount" value="0.00">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold mb-1">Withholding Tax (የቅድመ ግብር 3%)</label>
                                        <div class="form-check form-switch mt-1">
                                            <input class="form-check-input" type="checkbox" role="switch" name="has_withholding" value="1" id="createWithholdingToggle" onchange="recalculateCreateTaxes()">
                                            <label class="form-check-label small" for="createWithholdingToggle">
                                                Apply 3% Service Withholding Deduction
                                            </label>
                                        </div>
                                        <input type="hidden" name="withholding_rate" id="createWithholdingRate" value="3.00">
                                        <input type="hidden" name="withholding_amount" id="createWithholdingAmount" value="0.00">
                                    </div>
                                </div>

                                {{-- Real-time Tax Breakdown Card --}}
                                <div class="mt-3 p-2 bg-white rounded border shadow-sm">
                                    <div class="row text-center g-2 small">
                                        <div class="col-3 border-end">
                                            <span class="text-muted d-block" style="font-size:0.75rem;">Base Amount</span>
                                            <strong class="text-dark" id="displayCreateBase">ETB 0.00</strong>
                                        </div>
                                        <div class="col-3 border-end">
                                            <span class="text-muted d-block" style="font-size:0.75rem;">VAT (15%)</span>
                                            <strong class="text-info" id="displayCreateVat">+ ETB 0.00</strong>
                                        </div>
                                        <div class="col-3 border-end">
                                            <span class="text-muted d-block" style="font-size:0.75rem;">Withholding (3%)</span>
                                            <strong class="text-danger" id="displayCreateWht">- ETB 0.00</strong>
                                        </div>
                                        <div class="col-3">
                                            <span class="text-muted d-block" style="font-size:0.75rem;">Net Requested</span>
                                            <strong class="text-success" id="displayCreateNet">ETB 0.00</strong>
                                        </div>
                                    </div>
                                </div>

                                {{-- Withholding Tax Receipt & Voucher Upload Section --}}
                                <div id="createWithholdingReceiptSection" class="mt-3 p-3 bg-white rounded-3 border border-danger-subtle shadow-sm" style="display:none;">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <label class="form-label small fw-bold text-danger text-uppercase mb-0">
                                            <i class="fa-solid fa-file-invoice-dollar me-1"></i>Withholding Tax Receipt / Slip Upload (የቅድመ ግብር ደረሰኝ) <span class="text-danger">*</span>
                                        </label>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0">Required for 3% WHT</span>
                                    </div>
                                    <div class="row g-2 align-items-center">
                                        <div class="col-md-7">
                                            <input type="file" name="withholding_receipt" id="createWithholdingReceipt" 
                                                   class="form-control form-control-sm" 
                                                   accept="image/jpeg,image/png,image/jpg,application/pdf,image/webp">
                                            <small class="text-muted" style="font-size:0.75rem;">Upload official Withholding slip (PDF, JPG, PNG).</small>
                                        </div>
                                        <div class="col-md-5">
                                            <input type="text" name="withholding_receipt_number" id="createWithholdingReceiptNo" 
                                                   class="form-control form-control-sm" 
                                                   placeholder="Receipt / Voucher #">
                                            <small class="text-muted" style="font-size:0.75rem;">WHT Receipt Serial # (Optional)</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <input type="hidden" name="amount" id="createFinalAmount" value="0.00">
                        <input type="hidden" name="net_amount" id="createNetAmount" value="0.00">

                        {{-- Bank Payment Destination Section (ክፍያ የሚፈጸምበት የባንክ ሂሳብ / ወደዚህ ሂሳብ ይተላለፍ) --}}
                        <div class="col-12" id="createBankPaymentSection">
                            <div class="card border border-primary-subtle bg-light rounded-3 p-3 shadow-xs">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" role="switch" name="is_bank_payment" id="createBankPaymentToggle" value="1" onchange="toggleBankPaymentOptions()">
                                        <label class="form-check-label fw-bold text-dark fs-6" for="createBankPaymentToggle">
                                            <i class="fa-solid fa-building-columns text-primary me-1"></i>Pay to Bank Account / Electronic Transfer (በባንክ ሂሳብ ይከፈል / ወደዚህ ሂሳብ ይተላለፍ)
                                        </label>
                                    </div>
                                    <span class="badge bg-secondary-subtle text-secondary border px-2 py-1 rounded-pill font-monospace" id="bankPaymentStatusBadge">
                                        <i class="fa-solid fa-lock me-1"></i>Locked / Optional
                                    </span>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    Unlock this option to specify the receiving bank account number, bank name, and payee name for electronic disbursement.
                                </small>

                                {{-- Unlocked Bank Payment Details Box --}}
                                <div id="bankPaymentFieldsContainer" class="mt-3 pt-3 border-top" style="display: none;">
                                    @if(!empty($currentEmpAcc) || !empty($currentEmpBank))
                                        <div class="mb-3 p-2 bg-white rounded border border-primary-subtle d-flex align-items-center justify-content-between flex-wrap gap-2 small">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="fa-solid fa-wand-magic-sparkles text-primary fs-6"></i>
                                                <div>
                                                    <strong class="text-dark">Your Registered Account:</strong>
                                                    <span class="text-muted ms-1">{{ $currentEmpBank ?: 'CBE' }} — <span class="font-monospace text-primary fw-bold">{{ $currentEmpAcc }}</span> ({{ $currentEmpName }})</span>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill py-0 px-3 fw-semibold shadow-xs" onclick="fillMyBankDetails('{{ addslashes($currentEmpName) }}', '{{ addslashes($currentEmpBank ?? 'Commercial Bank of Ethiopia (CBE)') }}', '{{ addslashes($currentEmpAcc ?? '') }}')">
                                                <i class="fa-solid fa-user-check me-1"></i>Auto-Fill My Account
                                            </button>
                                        </div>
                                    @endif

                                    <div class="row g-2">
                                        {{-- Bank Name --}}
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                <i class="fa-solid fa-building-columns text-primary me-1"></i>Bank Name (የባንክ ስም) <span class="text-danger" id="bankNameAsterisk" style="display:none;">*</span>
                                            </label>
                                            <input type="text" list="ethiopianBanksList" name="bank_name" id="createBankName" class="form-control form-control-sm fw-semibold" placeholder="Select or type bank...">
                                            <datalist id="ethiopianBanksList">
                                                <option value="Commercial Bank of Ethiopia (CBE)">
                                                <option value="Telebirr">
                                                <option value="Awash Bank">
                                                <option value="Bank of Abyssinia">
                                                <option value="Dashen Bank">
                                                <option value="Cooperative Bank of Oromia (Coop)">
                                                <option value="Hibret Bank (United)">
                                                <option value="Nib International Bank">
                                                <option value="Wegagen Bank">
                                                <option value="Zemen Bank">
                                                <option value="Oromia Bank">
                                                <option value="Sinqee Bank">
                                                <option value="Lion International Bank">
                                                <option value="Berhan Bank">
                                                <option value="Enat Bank">
                                                <option value="Global Bank Ethiopia">
                                                <option value="Tsedey Bank">
                                                <option value="Amhara Bank">
                                                <option value="Gadaa Bank">
                                                <option value="Hijra Bank">
                                                <option value="ZamZam Bank">
                                                <option value="CBE Birr">
                                            </datalist>
                                            <small class="text-muted" style="font-size:0.75rem;">e.g., CBE, Telebirr, Awash</small>
                                        </div>

                                        {{-- Account Number / To be paid to this account --}}
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                <i class="fa-solid fa-credit-card text-success me-1"></i>Account Number (የሂሳብ ቁጥር) <span class="text-danger" id="accountNoAsterisk" style="display:none;">*</span>
                                            </label>
                                            <input type="text" name="account_number" id="createAccountNumber" class="form-control form-control-sm font-monospace fw-bold" placeholder="e.g. 1000123456789 or 0911XXXXXX">
                                            <small class="text-muted" style="font-size:0.75rem;">To be paid to this account</small>
                                        </div>

                                        {{-- Account Holder Name / Beneficiary Name --}}
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                <i class="fa-solid fa-user-tag text-info me-1"></i>Account Holder Name (የባለቤቱ ስም) <span class="text-danger" id="accountNameAsterisk" style="display:none;">*</span>
                                            </label>
                                            <input type="text" name="account_name" id="createAccountName" class="form-control form-control-sm fw-semibold" placeholder="e.g. Abebe Balcha / Vendor Name">
                                            <small class="text-muted" style="font-size:0.75rem;">Payee / Beneficiary full name</small>
                                        </div>
                                    </div>
                                </div>
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
                            <label class="form-label fw-bold">Attachment (Receipt / Invoice / Proforma / Photo) <small class="text-muted">(Optional — Max 10MB)</small></label>
                            <input type="file" name="attachment" class="form-control" accept="image/jpeg,image/png,image/jpg,application/pdf,image/webp">
                            <small class="text-muted">Upload invoice receipt image or PDF if available.</small>
                        </div>
                    </div>

                    <div class="alert alert-light border border-start border-4 border-success mt-3 mb-0 small">
                        <i class="fa-solid fa-circle-info text-success me-1"></i>
                        <strong>Workflow Notice:</strong> Every Transport and Ask Money request is first reviewed &amp; approved by <strong>HR or Coordinator</strong>. Requests over 5,000 ETB will automatically require General Manager (GM) sign-off.
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0 py-3 px-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold rounded-pill px-4" id="btnSubmitCreate">
                        <i class="fa-solid fa-paper-plane me-1"></i> Submit Request (<span id="btnSubmitAmount">ETB 0.00</span>)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
function toggleCategoryOptions() {
    const category = document.getElementById('categorySelect').value;
    const group = document.getElementById('otherReasonGroup');
    const input = document.getElementById('otherReasonInput');
    const taxSection = document.getElementById('createTaxSection');
    const employeeGroup = document.getElementById('employeeSelectGroup');
    const employeeSelect = document.getElementById('employeeSelect');

    const projectContainer = document.getElementById('projectContainer');
    const projectSelect = document.getElementById('projectSelect');
    const projectAsterisk = document.getElementById('projectRequiredAsterisk');
    const projectBadge = document.getElementById('projectCategoryBadge');
    const projectHelpText = document.getElementById('projectHelpText');

    if (category === 'Other') {
        if (group) group.style.display = 'block';
        if (input) input.required = true;
    } else {
        if (group) group.style.display = 'none';
        if (input) input.required = false;
    }

    // Dynamic Project Selection Configuration
    if (category === 'Loading & Unloading') {
        if (projectContainer) {
            projectContainer.className = 'p-3 bg-warning-subtle rounded-3 border border-warning border-2 shadow-sm';
        }
        if (projectAsterisk) projectAsterisk.style.display = 'inline';
        if (projectSelect) projectSelect.required = true;
        if (projectBadge) {
            projectBadge.className = 'badge bg-warning text-dark px-2 py-1 fw-bold';
            projectBadge.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i>Required for Loading &amp; Unloading (ለመጫን/ማውረድ ግዴታ)';
        }
        if (projectHelpText) {
            projectHelpText.innerHTML = '<i class="fa-solid fa-check-circle text-warning me-1"></i><strong>Mandatory:</strong> Please select the construction project / site destination for this loading &amp; unloading expense.';
        }
    } else {
        if (projectContainer) {
            projectContainer.className = 'p-3 bg-light rounded-3 border border-1 shadow-sm';
        }
        if (projectAsterisk) projectAsterisk.style.display = 'none';
        if (projectSelect) projectSelect.required = false;
        if (projectBadge) {
            projectBadge.className = 'badge bg-secondary-subtle text-secondary px-2 py-1';
            projectBadge.innerHTML = 'Optional / እንደ አስፈላጊነቱ';
        }
        if (projectHelpText) {
            projectHelpText.innerHTML = '<i class="fa-solid fa-circle-info me-1"></i>Select the destination project site for this expense request.';
        }
    }

    if (category === 'Transport' || category === 'Loading & Unloading') {
        if (employeeGroup) employeeGroup.style.display = 'block';
        if (employeeSelect) {
            employeeSelect.required = true;
        }
        const badge = document.getElementById('employeeCategoryBadge');
        if (badge) {
            if (category === 'Loading & Unloading') {
                badge.className = 'badge bg-warning text-dark px-2 py-1';
                badge.innerHTML = '<i class="fa-solid fa-dolly me-1"></i>Loading &amp; Unloading Allocation';
            } else {
                badge.className = 'badge bg-info text-white px-2 py-1';
                badge.innerHTML = '<i class="fa-solid fa-truck-ramp-box me-1"></i>Transport Money Allocation';
            }
        }
    } else {
        if (employeeGroup) employeeGroup.style.display = 'none';
        if (employeeSelect) {
            employeeSelect.required = false;
            employeeSelect.value = '';
        }
    }

    if (taxSection) {
        if (category === 'Service' || category === 'Contract Work') {
            taxSection.style.display = 'block';
        } else {
            taxSection.style.display = 'none';
            const vatType = document.getElementById('createVatType');
            const whtToggle = document.getElementById('createWithholdingToggle');
            if (vatType) vatType.value = 'none';
            if (whtToggle) whtToggle.checked = false;
        }
    }
    recalculateCreateTaxes();
}

function recalculateCreateTaxes() {
    const grossInput = document.getElementById('createGrossAmount');
    const vatTypeSelect = document.getElementById('createVatType');
    const whtToggle = document.getElementById('createWithholdingToggle');

    if (!grossInput) return;
    const gross = parseFloat(grossInput.value) || 0;
    const vatType = vatTypeSelect ? vatTypeSelect.value : 'none';
    const vatRate = 15.00;
    const hasWht = whtToggle ? whtToggle.checked : false;
    const whtRate = 3.00;


    let vatAmount = 0.0;
    let baseAmount = gross;
    let whtAmount = 0.0;
    let netAmount = gross;

    if (vatType === 'exclusive') {
        vatAmount = Math.round(gross * (vatRate / 100) * 100) / 100;
        baseAmount = gross;
        const totalGrossWithVat = gross + vatAmount;
        if (hasWht) {
            whtAmount = Math.round(baseAmount * (whtRate / 100) * 100) / 100;
        }
        netAmount = Math.round((totalGrossWithVat - whtAmount) * 100) / 100;
    } else if (vatType === 'inclusive' || vatType === 'vat_b') {
        baseAmount = Math.round((gross / (1 + (vatRate / 100))) * 100) / 100;
        vatAmount = Math.round((gross - baseAmount) * 100) / 100;
        if (hasWht) {
            whtAmount = Math.round(baseAmount * (whtRate / 100) * 100) / 100;
        }
        netAmount = Math.round((gross - whtAmount) * 100) / 100;
    } else {
        baseAmount = gross;
        vatAmount = 0.0;
        if (hasWht) {
            whtAmount = Math.round(baseAmount * (whtRate / 100) * 100) / 100;
        }
        netAmount = Math.round((gross - whtAmount) * 100) / 100;
    }

    // Set hidden inputs
    const hiddenVat = document.getElementById('createVatAmount');
    const hiddenWht = document.getElementById('createWithholdingAmount');
    const hiddenNet = document.getElementById('createNetAmount');
    const hiddenAmount = document.getElementById('createFinalAmount');

    if (hiddenVat) hiddenVat.value = vatAmount.toFixed(2);
    if (hiddenWht) hiddenWht.value = whtAmount.toFixed(2);
    if (hiddenNet) hiddenNet.value = netAmount.toFixed(2);
    if (hiddenAmount) hiddenAmount.value = (netAmount > 0 ? netAmount : gross).toFixed(2);

    // Update display labels
    const dispBase = document.getElementById('displayCreateBase');
    const dispVat = document.getElementById('displayCreateVat');
    const dispWht = document.getElementById('displayCreateWht');
    const dispNet = document.getElementById('displayCreateNet');
    const btnSpan = document.getElementById('btnSubmitAmount');

    const fmt = num => 'ETB ' + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    if (dispBase) dispBase.innerText = fmt(baseAmount);
    if (dispVat) dispVat.innerText = (vatAmount > 0 ? '+ ' : '') + fmt(vatAmount);
    if (dispWht) dispWht.innerText = (whtAmount > 0 ? '- ' : '') + fmt(whtAmount);
    if (dispNet) dispNet.innerText = fmt(netAmount);
    if (btnSpan) btnSpan.innerText = fmt(netAmount);

    // Toggle Withholding Receipt Section requirement
    const createWhtSection = document.getElementById('createWithholdingReceiptSection');
    const createWhtInput = document.getElementById('createWithholdingReceipt');
    if (createWhtSection) {
        if (hasWht) {
            createWhtSection.style.display = 'block';
            if (createWhtInput) createWhtInput.required = true;
        } else {
            createWhtSection.style.display = 'none';
            if (createWhtInput) createWhtInput.required = false;
        }
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

/**
 * Real-time VAT and Withholding Tax calculation for Payment Modal
 */
function recalculatePaymentTax(reqId) {
    const grossInput = document.getElementById('modalGrossAmount' + reqId);
    const vatTypeSelect = document.getElementById('modalVatType' + reqId);
    const whtToggle = document.getElementById('modalWithholdingToggle' + reqId);

    if (!grossInput) return;
    const gross = parseFloat(grossInput.value) || 0;
    const vatType = vatTypeSelect ? vatTypeSelect.value : 'none';
    const vatRate = 15.00;
    const hasWht = whtToggle ? whtToggle.checked : false;
    const whtRate = 3.00;

    let vatAmount = 0.0;
    let baseAmount = gross;
    let whtAmount = 0.0;
    let netAmount = gross;

    if (vatType === 'exclusive') {
        vatAmount = Math.round(gross * (vatRate / 100) * 100) / 100;
        baseAmount = gross;
        const totalGrossWithVat = gross + vatAmount;
        if (hasWht) {
            whtAmount = Math.round(baseAmount * (whtRate / 100) * 100) / 100;
        }
        netAmount = Math.round((totalGrossWithVat - whtAmount) * 100) / 100;
    } else if (vatType === 'inclusive' || vatType === 'vat_b') {
        baseAmount = Math.round((gross / (1 + (vatRate / 100))) * 100) / 100;
        vatAmount = Math.round((gross - baseAmount) * 100) / 100;
        if (hasWht) {
            whtAmount = Math.round(baseAmount * (whtRate / 100) * 100) / 100;
        }
        netAmount = Math.round((gross - whtAmount) * 100) / 100;
    } else {
        baseAmount = gross;
        vatAmount = 0.0;
        if (hasWht) {
            whtAmount = Math.round(baseAmount * (whtRate / 100) * 100) / 100;
        }
        netAmount = Math.round((gross - whtAmount) * 100) / 100;
    }

    // Set hidden inputs
    const hiddenVat = document.getElementById('modalVatAmount' + reqId);
    const hiddenWht = document.getElementById('modalWithholdingAmount' + reqId);
    const hiddenNet = document.getElementById('modalNetAmount' + reqId);
    const hiddenPaid = document.getElementById('modalPaidAmount' + reqId);

    if (hiddenVat) hiddenVat.value = vatAmount.toFixed(2);
    if (hiddenWht) hiddenWht.value = whtAmount.toFixed(2);
    if (hiddenNet) hiddenNet.value = netAmount.toFixed(2);
    if (hiddenPaid) hiddenPaid.value = netAmount.toFixed(2);

    // Update display labels
    const dispBase = document.getElementById('displayBaseAmount' + reqId);
    const dispVat = document.getElementById('displayVatAmount' + reqId);
    const dispWht = document.getElementById('displayWhtAmount' + reqId);
    const dispNet = document.getElementById('displayNetAmount' + reqId);
    const btnPaySpan = document.getElementById('btnPayAmount' + reqId);
    const alertDeduct = document.getElementById('alertDeductAmount' + reqId);

    const fmt = num => 'ETB ' + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    if (dispBase) dispBase.innerText = fmt(baseAmount);
    if (dispVat) dispVat.innerText = (vatAmount > 0 ? '+ ' : '') + fmt(vatAmount);
    if (dispWht) dispWht.innerText = (whtAmount > 0 ? '- ' : '') + fmt(whtAmount);
    if (dispNet) dispNet.innerText = fmt(netAmount);
    if (btnPaySpan) btnPaySpan.innerText = fmt(netAmount);
    if (alertDeduct) alertDeduct.innerText = fmt(netAmount);

    // Toggle Withholding Receipt Upload Requirement and Visibility
    const whtReceiptGroup = document.getElementById('withholdingReceiptSection' + reqId);
    const whtReceiptInput = document.getElementById('modalWithholdingReceipt' + reqId);
    if (whtReceiptGroup) {
        if (hasWht) {
            whtReceiptGroup.style.display = 'block';
            if (whtReceiptInput && whtReceiptInput.dataset.hasExisting !== '1') {
                whtReceiptInput.required = true;
            }
        } else {
            whtReceiptGroup.style.display = 'none';
            if (whtReceiptInput) {
                whtReceiptInput.required = false;
            }
        }
    }
}

function toggleBankPaymentOptions() {
    const toggle = document.getElementById('createBankPaymentToggle');
    const container = document.getElementById('bankPaymentFieldsContainer');
    const badge = document.getElementById('bankPaymentStatusBadge');
    const isUnlocked = toggle && toggle.checked;

    if (container) {
        container.style.display = isUnlocked ? 'block' : 'none';
    }

    if (badge) {
        if (isUnlocked) {
            badge.className = 'badge bg-success text-white border border-success px-2 py-1 rounded-pill font-monospace';
            badge.innerHTML = '<i class="fa-solid fa-lock-open me-1"></i>Unlocked — Direct Bank Transfer';
        } else {
            badge.className = 'badge bg-secondary-subtle text-secondary border px-2 py-1 rounded-pill font-monospace';
            badge.innerHTML = '<i class="fa-solid fa-lock me-1"></i>Locked / Optional';
        }
    }

    const bankInput = document.getElementById('createBankName');
    const accInput = document.getElementById('createAccountNumber');
    const nameInput = document.getElementById('createAccountName');
    const bankAsterisk = document.getElementById('bankNameAsterisk');
    const accAsterisk = document.getElementById('accountNoAsterisk');
    const nameAsterisk = document.getElementById('accountNameAsterisk');

    if (bankAsterisk) bankAsterisk.style.display = isUnlocked ? 'inline' : 'none';
    if (accAsterisk) accAsterisk.style.display = isUnlocked ? 'inline' : 'none';
    if (nameAsterisk) nameAsterisk.style.display = isUnlocked ? 'inline' : 'none';

    if (isUnlocked) {
        if (accInput && !accInput.value) accInput.focus();
    }
}

function fillMyBankDetails(name, bank, acc) {
    const toggle = document.getElementById('createBankPaymentToggle');
    if (toggle && !toggle.checked) {
        toggle.checked = true;
        toggleBankPaymentOptions();
    }
    const bankInput = document.getElementById('createBankName');
    const accInput = document.getElementById('createAccountNumber');
    const nameInput = document.getElementById('createAccountName');

    if (bankInput && bank) bankInput.value = bank;
    if (accInput && acc) accInput.value = acc;
    if (nameInput && name) nameInput.value = name;
}

function onAssignedEmployeeChanged(select) {
    if (!select || !select.value) return;
    const opt = select.options[select.selectedIndex];
    if (!opt) return;
    const bank = opt.dataset.bank || '';
    const acc = opt.dataset.account || '';
    const name = opt.dataset.name || '';

    // If bank payment is currently toggled on and fields are empty, autofill with selected employee's bank
    const toggle = document.getElementById('createBankPaymentToggle');
    const accInput = document.getElementById('createAccountNumber');
    if (toggle && toggle.checked && accInput && !accInput.value) {
        fillMyBankDetails(name, bank, acc);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    toggleCategoryOptions();
    toggleBankPaymentOptions();

    // Strict form validation on request submission
    const createForm = document.getElementById('createExpenseRequestForm');
    if (createForm) {
        createForm.addEventListener('submit', function (e) {
            const category = document.getElementById('categorySelect').value;
            const employeeSelect = document.getElementById('employeeSelect');
            const projectSelect = document.getElementById('projectSelect');
            const bankToggle = document.getElementById('createBankPaymentToggle');

            if (category === 'Transport' || category === 'Loading & Unloading') {
                if (!employeeSelect || !employeeSelect.value) {
                    e.preventDefault();
                    const label = category === 'Transport' ? 'Transport (ለትራንስፖርት)' : 'Loading & Unloading (ለመጫን እና ማውረድ)';
                    alert('Strict Requirement: You must select and assign another employee or driver/handler for ' + label + ' requests. Self-assignment is prohibited. (ራስዎን መምረጥ አይፈቀድም፤ እባክዎ ሌላ ሰራተኛ ወይም አሽከርካሪ ይምረጡ)።');
                    employeeSelect?.focus();
                    return false;
                }
            }

            if (category === 'Loading & Unloading') {
                if (!projectSelect || !projectSelect.value) {
                    e.preventDefault();
                    alert('Strict Requirement: You must select a Project / Site for Loading & Unloading requests. (እባክዎ ለመጫን እና ማውረድ ወጪ የስራ ፕሮጀክት / ሳይት ይምረጡ)።');
                    projectSelect?.focus();
                    return false;
                }
            }

            // Validate Bank Payment details if unlocked / enabled
            if (bankToggle && bankToggle.checked) {
                const accInput = document.getElementById('createAccountNumber');
                const nameInput = document.getElementById('createAccountName');

                if (!accInput || !accInput.value.trim()) {
                    e.preventDefault();
                    alert('Bank Option Requirement: Please enter the Account Number to be paid to. (እባክዎ ክፍያው የሚገባበትን የባንክ ሂሳብ ቁጥር ያስገቡ)።');
                    accInput?.focus();
                    return false;
                }

                if (!nameInput || !nameInput.value.trim()) {
                    e.preventDefault();
                    alert('Bank Option Requirement: Please enter the Account Holder Name in the bank payment section. (እባክዎ በባንክ ክፍያ ክፍል ውስጥ የባለቤቱን ስም ያስገቡ)።');
                    nameInput?.focus();
                    return false;
                }
            }
        });
    }

    // Auto-calculate payment modals when opened
    document.querySelectorAll('[id^="markPaidModal"]').forEach(function (modal) {
        modal.addEventListener('shown.bs.modal', function () {
            const reqId = modal.id.replace('markPaidModal', '');
            if (reqId) {
                recalculatePaymentTax(reqId);
            }
        });
    });
});
</script>

<script src="{{ asset('js/receipt-ocr-scanner.js') }}"></script>
<script>
    // ─── Master ON/OFF Switch Handler for Expense Request Details VAT ───
    window.onVatSwitchChanged = function(reqId, isChecked) {
        const card = document.getElementById('vatCard_' + reqId);
        const badge = document.getElementById('vatStatusBadge_' + reqId);
        const help = document.getElementById('vatHelpText_' + reqId);
        const hidden = document.getElementById('hiddenSaveInVat_' + reqId);
        const btnText = document.getElementById('btnSaveReceiptText_' + reqId);

        if (hidden) hidden.value = isChecked ? '1' : '0';

        if (isChecked) {
            if (card) {
                card.classList.remove('border-secondary-subtle', 'bg-light');
                card.classList.add('border-success', 'bg-success', 'bg-opacity-10');
            }
            if (badge) {
                badge.className = 'badge bg-success';
                badge.innerText = '✅ Save to VAT (ERCA Line 100)';
            }
            if (help) {
                help.innerText = 'ON: Scanned receipt will be declared in ERCA VAT Line 100 & VAT Reports (+15% input tax claim).';
            }
            if (btnText) {
                btnText.innerText = 'Save Receipt & Save to VAT (+15% Tax Claim)';
            }
        } else {
            if (card) {
                card.classList.remove('border-success', 'bg-success', 'bg-opacity-10');
                card.classList.add('border-secondary-subtle', 'bg-light');
            }
            if (badge) {
                badge.className = 'badge bg-secondary';
                badge.innerText = '⚪ Do NOT Save to VAT (Non-VAT)';
            }
            if (help) {
                help.innerText = 'OFF: Non-VAT expense proof only. Receipt is saved for records but will NOT be saved or declared in VAT reports.';
            }
            if (btnText) {
                btnText.innerText = 'Save Receipt Only (Do NOT Save in VAT)';
            }
        }

        // Auto-synchronize with rendered OCR Studio VAT selector if active
        const form = document.getElementById('attachReceiptForm_' + reqId);
        if (form) {
            const optIncluded = form.querySelector('.ocr-vat-opt-included');
            const optNoVat = form.querySelector('.ocr-vat-opt-none');
            if (isChecked && optIncluded) {
                optIncluded.checked = true;
                optIncluded.dispatchEvent(new Event('change', { bubbles: true }));
            } else if (!isChecked && optNoVat) {
                optNoVat.checked = true;
                optNoVat.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    };

    // Initialize OCR Studio on details modals
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[id^="detailsModal"]').forEach(function (modal) {
            modal.addEventListener('shown.bs.modal', function () {
                const reqId = modal.id.replace('detailsModal', '');
                if (!reqId) return;

                const fileInput = document.getElementById('receiptFileInput_' + reqId);
                if (fileInput && !fileInput.dataset.ocrInitialized && window.ReceiptOcrScanner) {
                    fileInput.dataset.ocrInitialized = 'true';
                    ReceiptOcrScanner.init({
                        fileInput: fileInput,
                        form: '#attachReceiptForm_' + reqId,
                        submitBtn: '#btnSaveReceipt_' + reqId,
                        statusContainer: '#ocrFeedbackArea_' + reqId,
                        studioMode: true,
                        purchaserTin: '0038480010',
                        encodedBy: '{{ strtolower(preg_replace("/[^a-zA-Z0-9_]/", "", Auth::user()->name ?? "wechacha")) }}',
                        fields: {
                            vendor_name: '#ocrVendor_' + reqId,
                            supplier_tin: '#ocrTin_' + reqId,
                            fs_no: '#ocrFs_' + reqId,
                            receipt_date: '#ocrDate_' + reqId,
                            subtotal: '#ocrSubtotal_' + reqId,
                            vat_amount: '#ocrVat_' + reqId,
                            total_amount: '#ocrTotal_' + reqId,
                            mrc: '#ocrMrc_' + reqId
                        },
                        onScanComplete: function(scanData) {
                            const isVatOn = document.getElementById('vatSwitch_' + reqId)?.checked;
                            const hiddenSave = document.getElementById('hiddenSaveInVat_' + reqId);
                            if (hiddenSave) hiddenSave.value = isVatOn ? '1' : '0';
                            if (!isVatOn) {
                                const hiddenVat = document.getElementById('ocrVat_' + reqId);
                                if (hiddenVat) hiddenVat.value = '0.00';
                            }
                        }
                    });
                }
            });
        });

        // AJAX submit with live visual updates for attachReceiptForm
        document.querySelectorAll('[id^="attachReceiptForm_"]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const reqId = form.dataset.reqId;
                const fileInput = document.getElementById('receiptFileInput_' + reqId);
                const hasExisting = form.dataset.hasExisting === '1';

                if (!hasExisting && (!fileInput || !fileInput.files || fileInput.files.length === 0)) {
                    alert('Please select or upload a receipt file (Image or PDF).');
                    return;
                }

                const submitBtn = document.getElementById('btnSaveReceipt_' + reqId);
                const origHtml = submitBtn ? submitBtn.innerHTML : '';
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Saving Receipt & Updating VAT...';
                }

                const alertBox = document.getElementById('ocrSaveAlert_' + reqId);
                if (alertBox) alertBox.style.display = 'none';

                const formData = new FormData(form);

                fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(res => res.json().then(data => ({ ok: res.ok, status: res.status, data })))
                .then(res => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = origHtml;
                    }

                    if (res.ok && res.data && res.data.success) {
                        if (alertBox) {
                            alertBox.className = 'alert alert-success py-2 px-3 small border-0 mb-3 shadow-xs';
                            alertBox.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i> ' + res.data.message;
                            alertBox.style.display = 'block';
                        }

                        // Update existing receipt box
                        const existingBox = document.getElementById('existingReceiptBox_' + reqId);
                        const existingInfo = document.getElementById('existingReceiptInfo_' + reqId);
                        const btnView = document.getElementById('btnViewReceipt_' + reqId);
                        const headerBadge = document.getElementById('vatStatusBadgeContainer_' + reqId);

                        if (existingBox) existingBox.classList.remove('d-none');
                        form.dataset.hasExisting = '1';

                        if (res.data.save_in_vat) {
                            const vatFmt = Number(res.data.vat_amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 });
                            if (existingInfo) {
                                existingInfo.innerHTML = `<span class="text-success fw-bold"><i class="fa-solid fa-check-double me-1"></i>ERCA VAT Line 100 Active (VAT: ETB ${vatFmt})</span>`;
                            }
                            if (headerBadge) {
                                headerBadge.innerHTML = `<span class="badge bg-success-subtle text-success border border-success font-monospace" style="font-size:0.72rem;"><i class="fa-solid fa-circle-check me-1"></i>Saved in VAT (+ETB ${vatFmt})</span>`;
                            }
                        } else {
                            if (existingInfo) {
                                existingInfo.innerHTML = `<span class="text-secondary fw-semibold"><i class="fa-solid fa-circle-info me-1"></i>Non-VAT Proof (Excluded from ERCA VAT Reports)</span>`;
                            }
                            if (headerBadge) {
                                headerBadge.innerHTML = `<span class="badge bg-secondary-subtle text-secondary border border-secondary font-monospace" style="font-size:0.72rem;"><i class="fa-solid fa-ban me-1"></i>Non-VAT Expense Proof</span>`;
                            }
                        }

                        if (res.data.attachment && btnView) {
                            btnView.href = res.data.attachment.startsWith('http') ? res.data.attachment : ('/uploads/' + res.data.attachment.replace(/^uploads\//, ''));
                        }
                    } else {
                        const errMsg = (res.data && res.data.message) || 'Error saving receipt.';
                        if (alertBox) {
                            alertBox.className = 'alert alert-danger py-2 px-3 small border-0 mb-3 shadow-xs';
                            alertBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i> ' + errMsg;
                            alertBox.style.display = 'block';
                        } else {
                            alert(errMsg);
                        }
                    }
                })
                .catch(err => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = origHtml;
                    }
                    if (alertBox) {
                        alertBox.className = 'alert alert-danger py-2 px-3 small border-0 mb-3 shadow-xs';
                        alertBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i> Network error: ' + err.message;
                        alertBox.style.display = 'block';
                    } else {
                        alert('Network error: ' + err.message);
                    }
                });
            });
        });
    });
</script>

@endsection
