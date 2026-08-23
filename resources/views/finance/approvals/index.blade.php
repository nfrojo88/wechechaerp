@extends('layouts.app')

@section('title', 'Expense Track & Approve')

@section('content')
<div class="container-fluid px-0">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h3 mb-1 text-dark fw-bold">
                <i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i>Expense Track & Approve
            </h1>
            <p class="text-muted small mb-0">Track all employee expense requests, direct expenses, GM/HR approvals, and paid history in one central location.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ url('/expense-requests') }}" class="btn btn-outline-primary btn-sm rounded-3">
                <i class="fa-solid fa-hand-holding-dollar me-1"></i> Ask Money Portal
            </a>
            <a href="{{ route('expenses.create') }}" class="btn btn-primary btn-sm rounded-3">
                <i class="fa-solid fa-plus me-1"></i> New Direct Expense
            </a>
        </div>
    </div>

    <!-- Quick Filter Navigation Tabs -->
    <div class="card border-0 shadow-sm mb-4 rounded-4 overflow-hidden">
        <div class="card-body p-2 bg-light">
            <ul class="nav nav-pills nav-fill gap-1" id="expenseTabs">
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold {{ $activeTab === 'all' ? 'active shadow-sm' : 'text-secondary' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'all', 'page' => 1]) }}">
                        <i class="fa-solid fa-list me-1"></i> All Expenses
                        <span class="badge {{ $activeTab === 'all' ? 'bg-white text-primary' : 'bg-secondary' }} ms-1">{{ $tabCounts['all'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold {{ $activeTab === 'pending_hr' ? 'active shadow-sm bg-warning text-dark' : 'text-secondary' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'pending_hr', 'page' => 1]) }}">
                        <i class="fa-solid fa-user-check me-1"></i> HR Review
                        <span class="badge {{ $activeTab === 'pending_hr' ? 'bg-dark text-white' : 'bg-warning text-dark' }} ms-1">{{ $tabCounts['pending_hr'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold {{ $activeTab === 'pending_gm' ? 'active shadow-sm bg-info text-white' : 'text-secondary' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'pending_gm', 'page' => 1]) }}">
                        <i class="fa-solid fa-user-shield me-1"></i> GM Review
                        <span class="badge {{ $activeTab === 'pending_gm' ? 'bg-white text-info' : 'bg-info text-white' }} ms-1">{{ $tabCounts['pending_gm'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold {{ $activeTab === 'finance_queue' ? 'active shadow-sm bg-primary text-white' : 'text-secondary' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'finance_queue', 'page' => 1]) }}">
                        <i class="fa-solid fa-building-columns me-1"></i> Finance Queue
                        <span class="badge {{ $activeTab === 'finance_queue' ? 'bg-white text-primary' : 'bg-primary text-white' }} ms-1">{{ $tabCounts['finance_queue'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold {{ $activeTab === 'paid' ? 'active shadow-sm bg-success text-white' : 'text-secondary' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'paid', 'page' => 1]) }}">
                        <i class="fa-solid fa-circle-check me-1"></i> Paid History
                        <span class="badge {{ $activeTab === 'paid' ? 'bg-white text-success' : 'bg-success text-white' }} ms-1">{{ $tabCounts['paid'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold {{ $activeTab === 'rejected' ? 'active shadow-sm bg-danger text-white' : 'text-secondary' }}" 
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
                        <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="REQ #, employee, keyword..." value="{{ request('search') }}">
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-bold">Project / Dept</label>
                    <select name="project" class="form-select form-select-sm">
                        <option value="all">All Projects & Depts</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->name }}" @selected(request('project') == $p->name)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-bold">Category</label>
                    <select name="category" class="form-select form-select-sm">
                        <option value="all">All Categories</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" @selected(request('category') == $cat)>{{ $cat }}</option>
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

    <!-- Main Expenses & Approvals Table Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead class="table-light text-uppercase small text-muted fw-bold">
                        <tr>
                            <th class="ps-4">ID / REQ #</th>
                            <th>Date</th>
                            <th>Requester / Dept</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th class="text-end">Amount (ETB)</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Attachment</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($paginatedItems as $item)
                            <tr>
                                <td class="ps-4">
                                    <span class="fw-bold text-dark font-monospace">{{ $item->id_formatted }}</span>
                                    <div class="small text-muted text-uppercase" style="font-size: .68rem;">{{ str_replace('_', ' ', $item->type) }}</div>
                                </td>
                                <td>
                                    <span class="text-dark">{{ \Carbon\Carbon::parse($item->date)->format('M d, Y') }}</span>
                                    <div class="small text-muted" style="font-size: .7rem;">{{ \Carbon\Carbon::parse($item->date)->format('H:i') }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $item->applicant_name }}</div>
                                    <span class="badge bg-light text-muted border rounded-pill" style="font-size: .7rem;">
                                        <i class="fa-solid fa-building me-1"></i>{{ $item->project }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill">
                                        {{ $item->category }}
                                    </span>
                                </td>
                                <td style="max-width: 280px;" class="text-truncate" title="{{ $item->description }}">
                                    <span class="text-dark">{{ $item->description }}</span>
                                    @if(!empty($item->rejection_reason))
                                        <div class="small text-danger fw-semibold"><i class="fa-solid fa-triangle-exclamation me-1"></i>Reason: {{ $item->rejection_reason }}</div>
                                    @endif
                                </td>
                                <td class="text-end fw-bold text-success fs-6">
                                    {{ number_format($item->net_amount, 2) }}
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $item->color }} rounded-pill px-3 py-1">
                                        {{ $item->status }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if($item->attachment_url)
                                        <a href="{{ $item->attachment_url }}" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-0" style="font-size: .75rem;">
                                            <i class="fa-solid fa-paperclip me-1"></i> View
                                        </a>
                                    @else
                                        <span class="text-muted small">&mdash;</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Detail Button (Opens Modal) -->
                                        <button type="button" class="btn btn-light border btn-sm rounded-start-3" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#detailModal{{ $item->id_raw }}_{{ $item->type }}" 
                                                title="View Full Details">
                                            <i class="fa-solid fa-eye text-primary"></i>
                                        </button>

                                        @if($item->type === 'expense_request')
                                            @php $req = $item->raw_model; @endphp
                                            
                                            <!-- HR Review Action -->
                                            @if($req->status === \App\Models\ExpenseRequest::STATUS_PENDING_HR && (auth()->user()->hasAnyRole(['HR Manager', 'hr_manager', 'HR Officer', 'hr_officer', 'admin', 'global_admin']) || auth()->user()->can('hr.view')))
                                                <button type="button" class="btn btn-warning btn-sm text-dark fw-semibold" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#hrReviewModal{{ $req->id }}">
                                                    <i class="fa-solid fa-user-check me-1"></i> HR Review
                                                </button>
                                            @endif

                                            <!-- GM Review Action -->
                                            @if($req->status === \App\Models\ExpenseRequest::STATUS_PENDING_GM && (auth()->user()->hasAnyRole(['General Manager', 'gm', 'admin', 'global_admin'])))
                                                <button type="button" class="btn btn-info btn-sm text-white fw-semibold" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#gmReviewModal{{ $req->id }}">
                                                    <i class="fa-solid fa-user-shield me-1"></i> GM Review
                                                </button>
                                            @endif

                                            <!-- Finance Assign / Pay Action -->
                                            @if(in_array($req->status, [\App\Models\ExpenseRequest::STATUS_APPROVED_ASSIGNED, \App\Models\ExpenseRequest::STATUS_ASSIGNED]) && (auth()->user()->hasAnyRole(['Finance head', 'finance_head', 'Finance staff', 'finance_staff', 'admin', 'global_admin'])))
                                                <button type="button" class="btn btn-primary btn-sm text-white fw-semibold" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#financeAssignModal{{ $req->id }}">
                                                    <i class="fa-solid fa-money-bill-wave me-1"></i> Assign / Pay
                                                </button>
                                            @endif

                                            <!-- Paid Receipt -->
                                            @if($req->status === \App\Models\ExpenseRequest::STATUS_PAID)
                                                <button type="button" class="btn btn-success-subtle text-success border border-success-subtle btn-sm fw-semibold disabled">
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

                            <!-- Detail Modal for Item -->
                            <div class="modal fade" id="detailModal{{ $item->id_raw }}_{{ $item->type }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content border-0 shadow rounded-4">
                                        <div class="modal-header bg-light border-0 py-3">
                                            <h5 class="modal-title fw-bold text-dark">
                                                <i class="fa-solid fa-file-invoice me-2 text-primary"></i>
                                                {{ $item->id_formatted }} &mdash; Expense Details
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <div class="row g-3 mb-3">
                                                <div class="col-md-6">
                                                    <div class="p-3 bg-light rounded-3">
                                                        <div class="small text-muted text-uppercase fw-bold mb-1">Requester</div>
                                                        <div class="fw-bold text-dark">{{ $item->applicant_name }}</div>
                                                        <div class="small text-muted">{{ $item->project }}</div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="p-3 bg-light rounded-3">
                                                        <div class="small text-muted text-uppercase fw-bold mb-1">Amount Requested</div>
                                                        <div class="fw-bold text-success fs-5">ETB {{ number_format($item->net_amount, 2) }}</div>
                                                        <div class="small text-muted">Category: {{ $item->category }}</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label small text-muted text-uppercase fw-bold">Description / Purpose</label>
                                                <div class="p-3 border rounded-3 bg-white text-dark">
                                                    {{ $item->description }}
                                                </div>
                                            </div>

                                            @if($item->attachment_url)
                                                <div class="mb-3">
                                                    <label class="form-label small text-muted text-uppercase fw-bold">Attachment / Receipt</label>
                                                    <div>
                                                        <a href="{{ $item->attachment_url }}" target="_blank" class="btn btn-outline-primary btn-sm rounded-3">
                                                            <i class="fa-solid fa-download me-1"></i> Open & Download Receipt
                                                        </a>
                                                    </div>
                                                </div>
                                            @endif

                                            @if($item->type === 'expense_request')
                                                @php $req = $item->raw_model; @endphp
                                                <div class="p-3 border rounded-3 bg-light mt-3">
                                                    <div class="fw-bold text-dark mb-2"><i class="fa-solid fa-timeline me-1 text-primary"></i> Approval & Payment Audit Trail</div>
                                                    <ul class="list-unstyled mb-0 small text-muted">
                                                        <li><i class="fa-solid fa-circle-dot me-1 text-secondary"></i> Submitted on: <strong>{{ $req->created_at->format('M d, Y H:i') }}</strong></li>
                                                        @if($req->hr_reviewed_at)
                                                            <li><i class="fa-solid fa-circle-check me-1 text-warning"></i> HR Reviewed by <strong>{{ $req->hrReviewer->name ?? 'HR' }}</strong> on {{ $req->hr_reviewed_at->format('M d, Y H:i') }}</li>
                                                        @endif
                                                        @if($req->gm_approved_at)
                                                            <li><i class="fa-solid fa-circle-check me-1 text-info"></i> GM Approved by <strong>{{ $req->gmApprover->name ?? 'GM' }}</strong> on {{ $req->gm_approved_at->format('M d, Y H:i') }}</li>
                                                        @endif
                                                        @if($req->paid_at)
                                                            <li><i class="fa-solid fa-circle-check me-1 text-success"></i> <strong>Paid</strong> on {{ $req->paid_at->format('M d, Y H:i') }} by <strong>{{ $req->paidBy->name ?? 'Finance' }}</strong> | Ref: {{ $req->payment_reference ?? 'N/A' }}</li>
                                                        @endif
                                                        @if($req->rejection_reason)
                                                            <li class="text-danger fw-semibold mt-1"><i class="fa-solid fa-circle-xmark me-1"></i> Rejection Reason: {{ $req->rejection_reason }}</li>
                                                        @endif
                                                    </ul>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="modal-footer bg-light border-0 py-3">
                                            <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Modals for HR Review, GM Review, Finance Assign (if expense_request) -->
                            @if($item->type === 'expense_request')
                                @php $req = $item->raw_model; @endphp

                                <!-- HR Review Modal -->
                                <div class="modal fade" id="hrReviewModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content border-0 shadow rounded-4">
                                            <form method="POST" action="{{ route('expense-requests.hr-review', $req->id) }}">
                                                @csrf
                                                <div class="modal-header bg-warning-subtle text-dark border-0">
                                                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-check me-2"></i>HR Review: {{ $req->request_number }}</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <p class="mb-3">Review expense request of <strong>ETB {{ number_format($req->amount, 2) }}</strong> for <strong>{{ $item->applicant_name }}</strong>.</p>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Action</label>
                                                        <select name="action" class="form-select" id="hrAction{{ $req->id }}" onchange="document.getElementById('hrReason{{ $req->id }}').style.display = this.value === 'reject' ? 'block' : 'none';">
                                                            <option value="approve">Approve {{ $req->amount > 5000 ? '(Forward to GM >5k)' : '(Forward to Finance Head)' }}</option>
                                                            <option value="reject">Reject Request</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3" id="hrReason{{ $req->id }}" style="display: none;">
                                                        <label class="form-label fw-semibold text-danger">Rejection Reason</label>
                                                        <textarea name="rejection_reason" class="form-control" rows="3" placeholder="Provide reason for rejection..."></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light border-0">
                                                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary rounded-3">Submit Decision</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- GM Review Modal -->
                                <div class="modal fade" id="gmReviewModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content border-0 shadow rounded-4">
                                            <form method="POST" action="{{ route('expense-requests.gm-review', $req->id) }}">
                                                @csrf
                                                <div class="modal-header bg-info-subtle text-dark border-0">
                                                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-shield me-2"></i>GM Approval: {{ $req->request_number }}</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <p class="mb-3">High-value request of <strong>ETB {{ number_format($req->amount, 2) }}</strong> by <strong>{{ $item->applicant_name }}</strong>.</p>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Action</label>
                                                        <select name="action" class="form-select" id="gmAction{{ $req->id }}" onchange="document.getElementById('gmReason{{ $req->id }}').style.display = this.value === 'reject' ? 'block' : 'none';">
                                                            <option value="approve">Approve (Send to Finance Head for Disbursement)</option>
                                                            <option value="reject">Reject Request</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3" id="gmReason{{ $req->id }}" style="display: none;">
                                                        <label class="form-label fw-semibold text-danger">Rejection Reason</label>
                                                        <textarea name="rejection_reason" class="form-control" rows="3" placeholder="Provide reason for rejection..."></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light border-0">
                                                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary rounded-3">Submit GM Approval</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Finance Assign / Pay Modal -->
                                <div class="modal fade" id="financeAssignModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content border-0 shadow rounded-4">
                                            <div class="modal-header bg-primary text-white border-0 py-3">
                                                <h5 class="modal-title fw-bold"><i class="fa-solid fa-money-bill-wave me-2"></i>Finance Processing: {{ $req->request_number }}</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="row g-4">
                                                    <!-- Option 1: Assign COA & Staff -->
                                                    <div class="col-md-6 border-end">
                                                        <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-user-tag me-1 text-primary"></i> 1. Assign Account & Staff</h6>
                                                        <form method="POST" action="{{ route('expense-requests.finance-assign', $req->id) }}">
                                                            @csrf
                                                            <div class="mb-3">
                                                                <label class="form-label small fw-bold">Chart of Account (COA) *</label>
                                                                <select name="coa_id" class="form-select form-select-sm" required>
                                                                    <option value="">Select COA Account...</option>
                                                                    @foreach($chartOfAccounts as $coa)
                                                                        <option value="{{ $coa->id }}" @selected($req->coa_id == $coa->id || $req->chart_of_account_id == $coa->id)>
                                                                            {{ $coa->code }} - {{ $coa->name }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label small fw-bold">Assign Finance Staff</label>
                                                                <select name="assigned_finance_staff_id" class="form-select form-select-sm">
                                                                    <option value="">Auto-assign or Select Staff...</option>
                                                                    @foreach($financeStaff as $st)
                                                                        <option value="{{ $st->id }}" @selected($req->assigned_finance_staff_id == $st->id || $req->finance_staff_id == $st->id)>
                                                                            {{ $st->name }} ({{ $st->department ?? 'Finance' }})
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <button type="submit" class="btn btn-outline-primary btn-sm w-100 rounded-3">
                                                                <i class="fa-solid fa-save me-1"></i> Save Assignment
                                                            </button>
                                                        </form>
                                                    </div>

                                                    <!-- Option 2: Direct Mark as Paid -->
                                                    <div class="col-md-6">
                                                        <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-check-double me-1 text-success"></i> 2. Disburse & Mark Paid</h6>
                                                        <form method="POST" action="{{ route('expense-requests.mark-paid', $req->id) }}">
                                                            @csrf
                                                            <div class="mb-2">
                                                                <label class="form-label small fw-bold">Payment Reference / Txn #</label>
                                                                <input type="text" name="payment_reference" class="form-control form-control-sm" placeholder="e.g. FT260823-001, Cash Voucher #">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label small fw-bold">Payment Notes</label>
                                                                <textarea name="payment_notes" class="form-control form-control-sm" rows="2" placeholder="Payment remarks..."></textarea>
                                                            </div>
                                                            <button type="submit" class="btn btn-success btn-sm w-100 rounded-3">
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

                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-inbox fa-3x mb-3 opacity-25"></i>
                                    <p class="mb-0 fw-semibold">No expenses found for this category or filter.</p>
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
@endsection
