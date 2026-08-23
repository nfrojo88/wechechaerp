@extends('layouts.app')

@section('title', 'Expense Track & Approve')

@section('content')
<div class="container-fluid px-2 px-md-3">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h3 mb-1 text-dark fw-bold">
                <i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i>Expense Track & Approve
            </h1>
            <p class="text-muted small mb-0">Track all employee expense requests, operational expenses, GM/HR approvals, and paid history in one unified location.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ url('/expense-requests') }}" class="btn btn-outline-primary btn-sm rounded-3 px-3">
                <i class="fa-solid fa-hand-holding-dollar me-1"></i> Ask Money Portal
            </a>
            <a href="{{ route('expenses.create') }}" class="btn btn-primary btn-sm rounded-3 px-3 shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> New Direct Expense
            </a>
        </div>
    </div>

    <!-- Quick Filter Navigation Tabs -->
    <div class="card border-0 shadow-sm mb-4 rounded-4 overflow-hidden">
        <div class="card-body p-2 bg-light">
            <ul class="nav nav-pills nav-fill gap-1 flex-nowrap overflow-auto" id="expenseTabs" style="white-space: nowrap;">
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold py-2 {{ $activeTab === 'all' ? 'active shadow-sm bg-dark text-white' : 'text-secondary bg-white' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'all', 'page' => 1]) }}">
                        <i class="fa-solid fa-list me-1"></i> All Expenses
                        <span class="badge {{ $activeTab === 'all' ? 'bg-primary text-white' : 'bg-secondary' }} ms-1">{{ $tabCounts['all'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold py-2 {{ $activeTab === 'pending_hr' ? 'active shadow-sm bg-warning text-dark' : 'text-secondary bg-white' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'pending_hr', 'page' => 1]) }}">
                        <i class="fa-solid fa-user-check me-1"></i> HR Review
                        <span class="badge {{ $activeTab === 'pending_hr' ? 'bg-dark text-white' : 'bg-warning text-dark' }} ms-1">{{ $tabCounts['pending_hr'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold py-2 {{ $activeTab === 'pending_gm' ? 'active shadow-sm bg-info text-white' : 'text-secondary bg-white' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'pending_gm', 'page' => 1]) }}">
                        <i class="fa-solid fa-user-shield me-1"></i> GM Review
                        <span class="badge {{ $activeTab === 'pending_gm' ? 'bg-white text-info' : 'bg-info text-white' }} ms-1">{{ $tabCounts['pending_gm'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold py-2 {{ $activeTab === 'finance_queue' ? 'active shadow-sm bg-primary text-white' : 'text-secondary bg-white' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'finance_queue', 'page' => 1]) }}">
                        <i class="fa-solid fa-building-columns me-1"></i> Finance Queue
                        <span class="badge {{ $activeTab === 'finance_queue' ? 'bg-white text-primary' : 'bg-primary text-white' }} ms-1">{{ $tabCounts['finance_queue'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold py-2 {{ $activeTab === 'paid' ? 'active shadow-sm bg-success text-white' : 'text-secondary bg-white' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'paid', 'page' => 1]) }}">
                        <i class="fa-solid fa-circle-check me-1"></i> Paid History
                        <span class="badge {{ $activeTab === 'paid' ? 'bg-white text-success' : 'bg-success text-white' }} ms-1">{{ $tabCounts['paid'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold py-2 {{ $activeTab === 'rejected' ? 'active shadow-sm bg-danger text-white' : 'text-secondary bg-white' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'rejected', 'page' => 1]) }}">
                        <i class="fa-solid fa-circle-xmark me-1"></i> Rejected
                        <span class="badge {{ $activeTab === 'rejected' ? 'bg-white text-danger' : 'bg-danger text-white' }} ms-1">{{ $tabCounts['rejected'] }}</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Filter Bar Card -->
    <div class="card border-0 shadow-sm mb-4 rounded-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ url('/expenses') }}" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="{{ $activeTab }}">
                
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1 fw-bold">Search</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="REQ #, employee, keyword..." value="{{ request('search') }}">
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-bold">Project / Dept</label>
                    <select name="project" class="form-select form-select-sm">
                        <option value="all">All Projects & Depts</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->name }}" {{ request('project') == $p->name ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-bold">Category</label>
                    <select name="category" class="form-select form-select-sm">
                        <option value="all">All Categories</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" {{ request('category') == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1 fw-bold">Date Range</label>
                    <div class="d-flex gap-1">
                        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                    </div>
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-dark w-100 rounded-3">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    @if(request()->hasAny(['search', 'project', 'category', 'date_from', 'date_to']))
                        <a href="{{ url('/expenses?tab=' . $activeTab) }}" class="btn btn-sm btn-outline-danger rounded-3" title="Clear Filters">
                            <i class="fa-solid fa-rotate-left"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Main Expenses Table Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="min-width: 1000px;">
                    <thead class="table-light text-uppercase small text-muted fw-bold">
                        <tr>
                            <th class="ps-4 py-3" style="width: 13%;">ID / REQ #</th>
                            <th class="py-3" style="width: 10%;">Date</th>
                            <th class="py-3" style="width: 16%;">Requester / Dept</th>
                            <th class="py-3" style="width: 13%;">Category</th>
                            <th class="py-3" style="width: 20%;">Description</th>
                            <th class="text-end py-3" style="width: 10%;">Amount</th>
                            <th class="text-center py-3" style="width: 10%;">Status</th>
                            <th class="text-end pe-4 py-3" style="width: 8%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($paginatedItems as $item)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark font-monospace" style="font-size: .85rem;">{{ $item->id_formatted }}</div>
                                    <span class="badge bg-light text-muted border px-2 py-0" style="font-size: .65rem; text-transform: uppercase;">
                                        {{ str_replace('_', ' ', $item->type) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-dark fw-semibold" style="font-size: .85rem;">{{ \Carbon\Carbon::parse($item->date)->format('M d, Y') }}</span>
                                    <div class="small text-muted" style="font-size: .7rem;">{{ \Carbon\Carbon::parse($item->date)->format('H:i') }}</div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark" style="font-size: .85rem;">{{ $item->applicant_name }}</div>
                                    <span class="text-muted" style="font-size: .75rem;">
                                        <i class="fa-solid fa-building me-1 opacity-50"></i>{{ $item->project }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill" style="font-size: .75rem;">
                                        {{ $item->category }}
                                    </span>
                                </td>
                                <td>
                                    <div class="text-dark" style="font-size: .85rem; max-width: 260px; word-break: break-word;" title="{{ $item->description }}">
                                        {{ Str::limit($item->description, 60) }}
                                    </div>
                                    @if(!empty($item->rejection_reason))
                                        <div class="small text-danger fw-semibold" style="font-size: .72rem;">
                                            <i class="fa-solid fa-triangle-exclamation me-1"></i>{{ Str::limit($item->rejection_reason, 45) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="fw-bold text-success" style="font-size: .95rem;">ETB {{ number_format($item->net_amount, 2) }}</div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $item->color }} rounded-pill px-2 py-1" style="font-size: .75rem;">
                                        {{ $item->status }}
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Detail Button -->
                                        <button type="button" class="btn btn-light border btn-sm rounded-start-3" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#detailModal{{ $item->id_raw }}_{{ $item->type }}" 
                                                title="View Details">
                                            <i class="fa-solid fa-eye text-primary"></i>
                                        </button>

                                        @if($item->type === 'expense_request')
                                            @php $req = $item->raw_model; @endphp
                                            
                                            <!-- HR Review Action Button -->
                                            @if($req->status === \App\Models\ExpenseRequest::STATUS_PENDING_HR && (auth()->user()->hasAnyRole(['HR Manager', 'hr_manager', 'HR Officer', 'hr_officer', 'admin', 'global_admin']) || auth()->user()->can('hr.view')))
                                                <button type="button" class="btn btn-warning btn-sm text-dark fw-semibold" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#hrReviewModal{{ $req->id }}"
                                                        title="HR Review">
                                                    <i class="fa-solid fa-user-check me-1"></i> HR Review
                                                </button>
                                            @endif

                                            <!-- GM Review Action Button -->
                                            @if($req->status === \App\Models\ExpenseRequest::STATUS_PENDING_GM && (auth()->user()->hasAnyRole(['General Manager', 'gm', 'admin', 'global_admin'])))
                                                <button type="button" class="btn btn-info btn-sm text-white fw-semibold" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#gmReviewModal{{ $req->id }}"
                                                        title="GM Review">
                                                    <i class="fa-solid fa-user-shield me-1"></i> GM Review
                                                </button>
                                            @endif

                                            <!-- Finance Assign / Pay Action Button -->
                                            @if(in_array($req->status, [\App\Models\ExpenseRequest::STATUS_APPROVED_ASSIGNED, \App\Models\ExpenseRequest::STATUS_ASSIGNED]) && (auth()->user()->hasAnyRole(['Finance head', 'finance_head', 'Finance staff', 'finance_staff', 'admin', 'global_admin'])))
                                                <button type="button" class="btn btn-primary btn-sm text-white fw-semibold" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#financeAssignModal{{ $req->id }}"
                                                        title="Assign Account or Disburse Payment">
                                                    <i class="fa-solid fa-money-bill-wave me-1"></i> Assign / Pay
                                                </button>
                                            @endif

                                            <!-- Paid Indicator Button -->
                                            @if($req->status === \App\Models\ExpenseRequest::STATUS_PAID)
                                                <button type="button" class="btn btn-success-subtle text-success border border-success-subtle btn-sm fw-semibold"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#detailModal{{ $item->id_raw }}_{{ $item->type }}">
                                                    <i class="fa-solid fa-check-circle me-1"></i> Paid
                                                </button>
                                            @endif

                                        @elseif($item->type === 'expense')
                                            @if(strtolower($item->status_raw) === 'pending')
                                                <form method="POST" action="{{ $item->route_approve }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-success btn-sm"><i class="fa-solid fa-check"></i> Approve</button>
                                                </form>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-inbox fa-3x mb-3 opacity-25"></i>
                                    <p class="mb-0 fw-semibold">No expenses found matching the selected tab or criteria.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($paginatedItems->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $paginatedItems->links() }}
            </div>
        @endif
    </div>
</div>

<!-- ========================================== -->
<!-- MODALS RENDERED OUTSIDE TABLE (CLEAN DOM)  -->
<!-- ========================================== -->
@foreach($paginatedItems as $item)
    <!-- 1. Detail Modal -->
    <div class="modal fade" id="detailModal{{ $item->id_raw }}_{{ $item->type }}" tabindex="-1" aria-labelledby="detailModalLabel{{ $item->id_raw }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-white border-bottom py-3 px-4">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary-subtle text-primary p-2 rounded-3 fs-6">
                            <i class="fa-solid fa-file-invoice"></i>
                        </span>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0" id="detailModalLabel{{ $item->id_raw }}">
                                {{ $item->id_formatted }}
                            </h5>
                            <span class="text-muted small text-uppercase">{{ str_replace('_', ' ', $item->type) }} Details</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="small text-muted text-uppercase fw-bold mb-1">Requester & Department</div>
                                <div class="fw-bold text-dark fs-6">{{ $item->applicant_name }}</div>
                                <div class="small text-muted"><i class="fa-solid fa-building me-1"></i>{{ $item->project }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="small text-muted text-uppercase fw-bold mb-1">Amount & Category</div>
                                <div class="fw-bold text-success fs-5">ETB {{ number_format($item->net_amount, 2) }}</div>
                                <div class="small text-muted"><i class="fa-solid fa-tag me-1"></i>Category: {{ $item->category }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small text-muted text-uppercase fw-bold">Description & Purpose</label>
                        <div class="p-3 border rounded-3 bg-light text-dark">
                            {{ $item->description }}
                        </div>
                    </div>

                    @if($item->attachment_url)
                        <div class="mb-4">
                            <label class="form-label small text-muted text-uppercase fw-bold">Attachment / Supporting Receipt</label>
                            <div>
                                <a href="{{ $item->attachment_url }}" target="_blank" class="btn btn-outline-primary btn-sm rounded-3 px-3">
                                    <i class="fa-solid fa-paperclip me-1"></i> Open / Download Supporting Document
                                </a>
                            </div>
                        </div>
                    @endif

                    @if($item->type === 'expense_request')
                        @php $req = $item->raw_model; @endphp
                        <div class="p-3 border rounded-3 bg-light">
                            <div class="fw-bold text-dark mb-2"><i class="fa-solid fa-timeline me-1 text-primary"></i> Review & Payment Timeline</div>
                            <ul class="list-unstyled mb-0 small text-muted">
                                <li class="mb-1"><i class="fa-solid fa-circle-dot me-2 text-secondary"></i> Submitted on: <strong class="text-dark">{{ $req->created_at->format('M d, Y H:i') }}</strong></li>
                                @if($req->hr_reviewed_at)
                                    <li class="mb-1"><i class="fa-solid fa-circle-check me-2 text-warning"></i> HR Review by <strong class="text-dark">{{ $req->hrReviewer->name ?? 'HR' }}</strong> on {{ $req->hr_reviewed_at->format('M d, Y H:i') }}</li>
                                @endif
                                @if($req->gm_approved_at)
                                    <li class="mb-1"><i class="fa-solid fa-circle-check me-2 text-info"></i> GM Approval by <strong class="text-dark">{{ $req->gmApprover->name ?? 'GM' }}</strong> on {{ $req->gm_approved_at->format('M d, Y H:i') }}</li>
                                @endif
                                @if($req->paid_at)
                                    <li class="mb-1 text-success"><i class="fa-solid fa-circle-check me-2"></i> <strong>Paid on {{ $req->paid_at->format('M d, Y H:i') }}</strong> by {{ $req->paidBy->name ?? 'Finance' }} | Ref: {{ $req->payment_reference ?? 'N/A' }}</li>
                                @endif
                                @if($req->rejection_reason)
                                    <li class="mt-2 text-danger fw-semibold"><i class="fa-solid fa-triangle-exclamation me-2"></i> Rejection Reason: {{ $req->rejection_reason }}</li>
                                @endif
                            </ul>
                        </div>
                    @endif
                </div>
                <div class="modal-footer bg-light border-0 py-3 px-4">
                    <button type="button" class="btn btn-secondary rounded-3 px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    @if($item->type === 'expense_request')
        @php $req = $item->raw_model; @endphp

        <!-- 2. HR Review Modal -->
        <div class="modal fade" id="hrReviewModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <form method="POST" action="{{ route('expense-requests.hr-review', $req->id) }}">
                        @csrf
                        <div class="modal-header bg-white border-bottom py-3 px-4">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-warning-subtle text-warning p-2 rounded-3 fs-6">
                                    <i class="fa-solid fa-user-check"></i>
                                </span>
                                <div>
                                    <h5 class="modal-title fw-bold text-dark mb-0">HR Review: {{ $req->request_number }}</h5>
                                    <span class="text-muted small">Employee Expense Verification</span>
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4 bg-white">
                            <div class="p-3 bg-light rounded-3 mb-3 border">
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted small">Requester:</span>
                                    <span class="fw-bold text-dark">{{ $item->applicant_name }}</span>
                                </div>
                                <div class="d-flex justify-content-between mt-1">
                                    <span class="text-muted small">Amount:</span>
                                    <span class="fw-bold text-success">ETB {{ number_format($req->amount, 2) }}</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Decision Action</label>
                                <select name="action" class="form-select" id="hrAction{{ $req->id }}" onchange="document.getElementById('hrReason{{ $req->id }}').style.display = this.value === 'reject' ? 'block' : 'none';">
                                    <option value="approve">Approve {{ $req->amount > 5000 ? '(Forward to GM > 5k ETB)' : '(Forward to Finance Head)' }}</option>
                                    <option value="reject">Reject Request</option>
                                </select>
                            </div>
                            <div class="mb-3" id="hrReason{{ $req->id }}" style="display: none;">
                                <label class="form-label small fw-bold text-danger">Rejection Reason *</label>
                                <textarea name="rejection_reason" class="form-control" rows="3" placeholder="Provide clear reason for rejection..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer bg-light border-0 py-3 px-4">
                            <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary rounded-3 px-4">Submit Decision</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- 3. GM Review Modal -->
        <div class="modal fade" id="gmReviewModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <form method="POST" action="{{ route('expense-requests.gm-review', $req->id) }}">
                        @csrf
                        <div class="modal-header bg-white border-bottom py-3 px-4">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-info-subtle text-info p-2 rounded-3 fs-6">
                                    <i class="fa-solid fa-user-shield"></i>
                                </span>
                                <div>
                                    <h5 class="modal-title fw-bold text-dark mb-0">GM Approval: {{ $req->request_number }}</h5>
                                    <span class="text-muted small">High-Value Expense Authorization (> 5,000 ETB)</span>
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4 bg-white">
                            <div class="p-3 bg-light rounded-3 mb-3 border">
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted small">Requester:</span>
                                    <span class="fw-bold text-dark">{{ $item->applicant_name }}</span>
                                </div>
                                <div class="d-flex justify-content-between mt-1">
                                    <span class="text-muted small">Total Requested:</span>
                                    <span class="fw-bold text-success fs-5">ETB {{ number_format($req->amount, 2) }}</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">GM Action</label>
                                <select name="action" class="form-select" id="gmAction{{ $req->id }}" onchange="document.getElementById('gmReason{{ $req->id }}').style.display = this.value === 'reject' ? 'block' : 'none';">
                                    <option value="approve">Approve (Send to Finance Head for Disbursement)</option>
                                    <option value="reject">Reject Request</option>
                                </select>
                            </div>
                            <div class="mb-3" id="gmReason{{ $req->id }}" style="display: none;">
                                <label class="form-label small fw-bold text-danger">Rejection Reason *</label>
                                <textarea name="rejection_reason" class="form-control" rows="3" placeholder="State reason for GM rejection..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer bg-light border-0 py-3 px-4">
                            <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary rounded-3 px-4">Submit GM Decision</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- 4. Finance Assign / Pay Modal -->
        <div class="modal fade" id="financeAssignModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="modal-header bg-white border-bottom py-3 px-4">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary-subtle text-primary p-2 rounded-3 fs-6">
                                <i class="fa-solid fa-money-bill-wave"></i>
                            </span>
                            <div>
                                <h5 class="modal-title fw-bold text-dark mb-0">Finance Processing: {{ $req->request_number }}</h5>
                                <span class="text-muted small">Total: <strong>ETB {{ number_format($req->amount, 2) }}</strong> &bull; Requester: <strong>{{ $item->applicant_name }}</strong></span>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4 bg-white">
                        <div class="row g-4">
                            <!-- Option 1: Assign COA & Staff -->
                            <div class="col-md-6 border-end pe-md-4">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <span class="badge bg-primary text-white rounded-circle">1</span>
                                    <h6 class="fw-bold text-dark mb-0">Assign Account & Staff</h6>
                                </div>
                                <form method="POST" action="{{ route('expense-requests.finance-assign', $req->id) }}">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-dark">Chart of Account (COA) <span class="text-danger">*</span></label>
                                        <select name="coa_id" 
                                                id="coaSelect{{ $req->id }}"
                                                class="form-select form-select-sm bg-white" 
                                                onchange="autoSelectFinanceStaff(this, {{ $req->id }})"
                                                required>
                                            <option value="" data-staff-id="" data-staff-name="">-- Select COA Account --</option>
                                            @foreach($chartOfAccounts as $coa)
                                                @php
                                                    $isSelected = ($req->coa_id == $coa->id || $req->chart_of_account_id == $coa->id);
                                                    $assignedStaffId = $coa->assigned_to ?? '';
                                                    $assignedStaffName = $coa->manager->name ?? '';
                                                @endphp
                                                <option value="{{ $coa->id }}" 
                                                        data-staff-id="{{ $assignedStaffId }}" 
                                                        data-staff-name="{{ $assignedStaffName }}"
                                                        {{ $isSelected ? 'selected' : '' }}>
                                                    {{ $coa->code }} - {{ $coa->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-dark">Assigned Finance Staff</label>
                                        <select name="assigned_finance_staff_id" 
                                                id="financeStaffSelect{{ $req->id }}" 
                                                class="form-select form-select-sm bg-white">
                                            <option value="">Auto-assigned from COA or Select Staff...</option>
                                            @foreach($financeStaff as $st)
                                                <option value="{{ $st->id }}" {{ ($req->assigned_finance_staff_id == $st->id || $req->finance_staff_id == $st->id) ? 'selected' : '' }}>
                                                    {{ $st->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div id="autoStaffBadge{{ $req->id }}" class="mt-2" style="display: none;"></div>
                                    </div>
                                    <button type="submit" class="btn btn-outline-primary btn-sm w-100 rounded-3 py-2 fw-semibold">
                                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Assignment
                                    </button>
                                </form>
                            </div>

                            <!-- Option 2: Direct Mark as Paid -->
                            <div class="col-md-6 ps-md-4">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <span class="badge bg-success text-white rounded-circle">2</span>
                                    <h6 class="fw-bold text-dark mb-0">Disburse & Mark Paid</h6>
                                </div>
                                <form method="POST" action="{{ route('expense-requests.mark-paid', $req->id) }}">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-dark">Payment Reference / Txn #</label>
                                        <input type="text" name="payment_reference" class="form-control form-control-sm bg-white" placeholder="e.g. FT260823-001, Cash Voucher #">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-dark">Payment Notes</label>
                                        <textarea name="payment_notes" class="form-control form-control-sm bg-white" rows="2" placeholder="Payment remarks..."></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-success btn-sm w-100 rounded-3 py-2 fw-semibold">
                                        <i class="fa-solid fa-circle-check me-1"></i> Confirm Paid (ETB {{ number_format($req->amount, 2) }})
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endforeach

<script>
/**
 * Automatically select the assigned staff member custodian linked to the selected COA account
 */
function autoSelectFinanceStaff(selectEl, requestId) {
    const selectedOption = selectEl.options[selectEl.selectedIndex];
    if (!selectedOption) return;

    const staffId = selectedOption.getAttribute('data-staff-id');
    const staffName = selectedOption.getAttribute('data-staff-name');
    const staffSelect = document.getElementById('financeStaffSelect' + requestId);
    const badgeEl = document.getElementById('autoStaffBadge' + requestId);

    if (staffSelect) {
        if (staffId && staffId !== '') {
            staffSelect.value = staffId;
            if (badgeEl) {
                badgeEl.innerHTML = '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size: .75rem;"><i class="fa-solid fa-link me-1"></i>Auto-assigned from COA: <strong>' + (staffName || 'Staff #' + staffId) + '</strong></span>';
                badgeEl.style.display = 'block';
            }
        } else {
            if (badgeEl) {
                badgeEl.style.display = 'none';
            }
        }
    }
}

// Auto-trigger on modal open to reflect current COA custodian
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[id^="financeAssignModal"]').forEach(function (modal) {
        modal.addEventListener('shown.bs.modal', function () {
            const coaSelect = modal.querySelector('select[name="coa_id"]');
            if (coaSelect && coaSelect.selectedIndex > 0) {
                const reqId = modal.id.replace('financeAssignModal', '');
                autoSelectFinanceStaff(coaSelect, reqId);
            }
        });
    });
});
</script>

<style>
/* Clean custom styles for Expense Track table */
.table thead th {
    font-weight: 700;
    letter-spacing: 0.5px;
    background-color: #f8fafc !important;
    border-bottom: 2px solid #e2e8f0;
}
.table tbody tr:hover {
    background-color: #f8faff;
}
.modal-content {
    background-color: #ffffff !important;
}
.form-control, .form-select {
    border-color: #cbd5e1;
}
.form-control:focus, .form-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.15);
}
</style>
@endsection
