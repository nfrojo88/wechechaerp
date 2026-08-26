@extends('layouts.app')

@section('title', 'Procurement My Queue')

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-tasks text-primary me-2"></i>Procurement — My Queue</h1>
            <p class="text-muted small mb-0">Items awaiting action by your role in the 14-stage procurement lifecycle.</p>
        </div>
        <a href="{{ route('purchase-requests.create') }}" class="btn btn-primary shadow-sm">
            <i class="fas fa-plus me-1"></i> New Purchase Request
        </a>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl">
            <div class="card border-0 shadow-sm bg-primary text-white h-100">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-white-50 small font-weight-bold">Awaiting Your Action</div>
                        <div class="h2 mb-0 font-weight-bold">{{ $kpi['my_pending'] }}</div>
                    </div>
                    <i class="fas fa-hourglass-half fa-2x text-white-50"></i>
                </div>
            </div>
        </div>

        @if(($kpi['pending_office_requests'] ?? 0) > 0 || !empty($isHr) || !empty($isCoordinator))
        <div class="col-12 col-sm-6 col-xl">
            <a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.index') ? route('office-requests.index', ['status' => 'pending_hr_approval']) : url('/office-requests?status=pending_hr_approval') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm bg-warning text-dark h-100">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-dark small font-weight-bold">Office Requisitions (HR Review)</div>
                            <div class="h2 mb-0 font-weight-bold text-dark">{{ $kpi['pending_office_requests'] ?? 0 }}</div>
                        </div>
                        <i class="fas fa-user-shield fa-2x text-dark opacity-50"></i>
                    </div>
                </div>
            </a>
        </div>
        @endif

        @if(($kpi['pending_store_office_requests'] ?? 0) > 0 || !empty($isStoreManager))
        <div class="col-12 col-sm-6 col-xl">
            <a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.index') ? route('office-requests.index', ['status' => 'pending_store_review']) : url('/office-requests?status=pending_store_review') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm bg-info text-dark h-100">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-dark small font-weight-bold">Office Supplies (Store Action)</div>
                            <div class="h2 mb-0 font-weight-bold text-dark">{{ $kpi['pending_store_office_requests'] ?? 0 }}</div>
                        </div>
                        <i class="fas fa-boxes-stacked fa-2x text-dark opacity-50"></i>
                    </div>
                </div>
            </a>
        </div>
        @endif

        @if(($kpi['pending_finance_office_requests'] ?? 0) > 0 || !empty($isFinanceHead))
        <div class="col-12 col-sm-6 col-xl">
            <a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.index') ? route('office-requests.index', ['status' => 'pending_finance']) : url('/office-requests?status=pending_finance') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm text-white h-100" style="background: linear-gradient(135deg,#7c3aed,#5b21b6);">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-white-50 small font-weight-bold">Office Supplies (Finance Action)</div>
                            <div class="h2 mb-0 font-weight-bold">{{ $kpi['pending_finance_office_requests'] ?? 0 }}</div>
                        </div>
                        <i class="fas fa-file-invoice-dollar fa-2x text-white-50"></i>
                    </div>
                </div>
            </a>
        </div>
        @endif

        <div class="col-12 col-sm-6 col-xl">
            <div class="card border-0 shadow-sm bg-danger text-white h-100">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-white-50 small font-weight-bold">Emergency MRs (Planning)</div>
                        <div class="h2 mb-0 font-weight-bold">{{ $kpi['emergency_mrs'] }}</div>
                    </div>
                    <i class="fas fa-bolt fa-2x text-white-50"></i>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl">
            <div class="card border-0 shadow-sm bg-success text-white h-100">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-white-50 small font-weight-bold">Intake Completed</div>
                        <div class="h2 mb-0 font-weight-bold">{{ $kpi['completed'] }}</div>
                    </div>
                    <i class="fas fa-check-circle fa-2x text-white-50"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('procurement.my-queue') }}" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">-- All Projects --</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" {{ request('project_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- All Statuses --</option>
                        @foreach(\App\Models\PurchaseRequest::statusLabels() as $key => $lbl)
                            <option value="{{ $key }}" {{ request('status') == $key ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-secondary flex-grow-1"><i class="fas fa-filter me-1"></i> Filter</button>
                    <a href="{{ route('procurement.my-queue') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-undo me-1"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Emergency MRs Section (Visible to Planning Team) -->
    @if($emergencyMrs->count() > 0)
    <div class="card border-warning shadow-sm mb-4">
        <div class="card-header bg-warning bg-opacity-25 border-warning d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-dark font-weight-bold"><i class="fas fa-bolt text-danger me-2"></i>Emergency Material Requests (Awaiting Planning Approval)</h5>
            <span class="badge bg-danger">{{ $emergencyMrs->count() }} Pending</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Ref No</th>
                            <th>Project</th>
                            <th>Requested By</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($emergencyMrs as $mr)
                        <tr>
                            <td><strong>{{ $mr->reference_number }}</strong></td>
                            <td>{{ $mr->project?->name }}</td>
                            <td>{{ $mr->requestedBy?->name ?? $mr->creator?->name ?? 'N/A' }}</td>
                            <td>{{ $mr->created_at->format('M d, Y H:i') }}</td>
                            <td>
                                <div class="d-flex gap-1">
                                    <form method="POST" action="{{ route('material-requests.planning-approve', $mr->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check me-1"></i> Approve</button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectMrModal{{ $mr->id }}">
                                        <i class="fas fa-times me-1"></i> Reject
                                    </button>
                                </div>

                                <!-- Reject Modal -->
                                <div class="modal fade" id="rejectMrModal{{ $mr->id }}" tabindex="-1">
                                    <div class="modal-dialog">
                                        <form method="POST" action="{{ route('material-requests.planning-reject', $mr->id) }}" class="modal-content">
                                            @csrf
                                             <div class="modal-header">
                                                <h5 class="modal-title">Reject Emergency Request</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <label class="form-label">Rejection Reason</label>
                                                <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- Purchase Requests Queue Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 font-weight-bold"><i class="fas fa-list me-2 text-primary"></i>Purchase Requests Pending Role Action</h5>
            @if(($kpi['pending_office_requests'] ?? 0) > 0)
                <span class="badge bg-warning text-dark px-3 py-2">
                    <i class="fa-solid fa-boxes-stacked me-1"></i> {{ $kpi['pending_office_requests'] }} Office Supply {{ \Illuminate\Support\Str::plural('Request', $kpi['pending_office_requests']) }} Pending Decision
                </span>
            @endif
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>PR Number</th>
                            <th>Project / Purpose</th>
                            <th>Channel Source</th>
                            <th>Priority</th>
                            <th>Stage / Status</th>
                            <th>Current Owner</th>
                            <th>Created Date</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($myPrs as $pr)
                        <tr class="{{ $pr->is_office_request && $pr->status === 'pending_hr_approval' ? 'table-warning bg-opacity-25' : '' }}">
                            <td>
                                @if($pr->is_office_request)
                                    <a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.show') ? route('office-requests.show', $pr) : url('/office-requests/' . $pr->id) }}" class="fw-bold text-decoration-none text-primary">
                                        {{ $pr->pr_no }}
                                    </a>
                                @else
                                    <a href="{{ route('purchase-requests.show', $pr->id) }}" class="fw-bold text-decoration-none">
                                        {{ $pr->pr_no }}
                                    </a>
                                @endif
                            </td>
                            <td>
                                @if($pr->is_office_request)
                                    <div>
                                        <span class="fw-bold text-dark"><i class="fa-solid fa-building me-1 text-secondary"></i> Head Office</span>
                                        <small class="text-muted d-block">{{ $pr->office_purpose ?: 'Office Supplies' }} ({{ $pr->items->count() }} items)</small>
                                    </div>
                                @else
                                    <span class="fw-medium text-dark">{{ $pr->project?->name ?? 'N/A' }}</span>
                                @endif
                            </td>
                            <td>
                                @if($pr->is_office_request)
                                    <span class="badge bg-warning text-dark border border-warning">
                                        <i class="fa-solid fa-boxes-stacked me-1"></i> Office Supply
                                    </span>
                                @elseif($pr->materialRequest)
                                    <span class="badge bg-light text-dark border">{{ $pr->materialRequest->source ?? 'Manual' }}</span>
                                @else
                                    <span class="badge bg-light text-dark border">Direct PR</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $pr->priority === 'urgent' ? 'danger' : ($pr->priority === 'high' ? 'warning' : 'secondary') }}">
                                    {{ ucfirst($pr->priority) }}
                                </span>
                            </td>
                            <td>
                                @if($pr->is_office_request && $pr->status === 'pending_hr_approval')
                                    <span class="badge bg-warning text-dark border border-warning">
                                        <i class="fa-solid fa-hourglass-half me-1"></i> Pending HR / Coordinator
                                    </span>
                                @elseif($pr->is_office_request && in_array($pr->status, ['approved', 'pending_store_review']))
                                    <span class="badge bg-info text-dark border border-info">
                                        <i class="fa-solid fa-boxes-stacked me-1"></i> Store Review &amp; Dispatch
                                    </span>
                                @elseif($pr->is_office_request && in_array($pr->status, ['pending_pm_review', 'pending_proc_team']))
                                    <span class="badge bg-primary text-white">
                                        <i class="fa-solid fa-cart-shopping me-1"></i> Pending PM / Buying
                                    </span>
                                @else
                                    <span class="badge bg-{{ \App\Models\PurchaseRequest::statusBadgeClass($pr->status) }}">
                                        {{ $pr->status_label }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($pr->is_office_request && $pr->status === 'pending_hr_approval')
                                    <span class="badge bg-warning text-dark">
                                        <i class="fas fa-user-shield me-1"></i> HR / Coordinator
                                    </span>
                                @elseif($pr->is_office_request && in_array($pr->status, ['approved', 'pending_store_review']))
                                    <span class="badge bg-info text-dark">
                                        <i class="fas fa-warehouse me-1"></i> Store Manager
                                    </span>
                                @elseif($pr->is_office_request && in_array($pr->status, ['pending_pm_review', 'pending_proc_team']))
                                    <span class="badge bg-primary text-white">
                                        <i class="fas fa-user-tie me-1"></i> Purchase Manager
                                    </span>
                                @elseif($pr->is_office_request && $pr->status === 'pending_finance')
                                    <span class="badge text-white" style="background:#7c3aed;">
                                        <i class="fas fa-coins me-1"></i> Finance Head
                                    </span>
                                @else
                                    <span class="badge bg-secondary bg-opacity-10 text-dark">
                                        <i class="fas fa-user-tag me-1"></i> {{ ucfirst(str_replace('_', ' ', $pr->current_owner_role ?? 'None')) }}
                                    </span>
                                @endif
                            </td>
                            <td>{{ $pr->created_at->format('M d, Y') }}</td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end align-items-center gap-1 flex-wrap">
                                    @if($pr->is_office_request)
                                        <a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.show') ? route('office-requests.show', $pr) : url('/office-requests/' . $pr->id) }}" class="btn btn-sm btn-outline-primary" title="View Request Details">
                                            <i class="fas fa-eye me-1"></i> View
                                        </a>

                                        @if($pr->status === 'pending_hr_approval')
                                            <a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.show') ? route('office-requests.show', $pr) : url('/office-requests/' . $pr->id) }}" class="btn btn-sm btn-warning text-dark fw-bold shadow-sm" title="Approve or Reject">
                                                <i class="fas fa-gavel me-1"></i> Decide
                                            </a>
                                        @elseif(in_array($pr->status, ['approved', 'pending_store_review']))
                                            <button type="button" class="btn btn-sm btn-success fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#dispatchModal{{ $pr->id }}" title="Issue from Store & Send to Finance Head">
                                                <i class="fas fa-boxes-packing me-1"></i> Dispatch → Finance
                                            </button>
                                            <button type="button" class="btn btn-sm btn-info text-dark fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#sendToPmModal{{ $pr->id }}" title="Send to Purchase Manager (PM)">
                                                <i class="fas fa-paper-plane me-1"></i> Send to PM
                                            </button>
                                        @elseif($pr->status === 'pending_finance')
                                            <button type="button" class="btn btn-sm fw-bold shadow-sm text-white" style="background:#7c3aed;" data-bs-toggle="modal" data-bs-target="#financeConfirmModal{{ $pr->id }}" title="Confirm Expense & Mark as Paid">
                                                <i class="fas fa-file-invoice-dollar me-1"></i> Confirm Expense
                                            </button>
                                        @endif
                                    @else
                                        <a href="{{ route('purchase-requests.show', $pr->id) }}" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye me-1"></i> View & Review
                                        </a>
                                    @endif
                                </div>

                                @if($pr->is_office_request && in_array($pr->status, ['approved', 'pending_store_review', 'pending_finance']))
                                <!-- Send to PM Modal -->
                                <div class="modal fade" id="sendToPmModal{{ $pr->id }}" tabindex="-1" aria-hidden="true" style="text-align: left;">
                                    <div class="modal-dialog">
                                        <form method="POST" action="{{ \Illuminate\Support\Facades\Route::has('office-requests.send-to-pm') ? route('office-requests.send-to-pm', $pr) : url('/office-requests/' . $pr->id . '/send-to-pm') }}" class="modal-content border-0 shadow">
                                            @csrf
                                            <div class="modal-header bg-primary text-white">
                                                <h5 class="modal-title fw-bold">
                                                    <i class="fas fa-paper-plane me-2"></i>Send to Purchase Manager (PM)
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <p class="text-muted small mb-3">
                                                    Forward <strong>{{ $pr->pr_no }}</strong> ({{ $pr->office_purpose ?: 'Office Supplies' }}) to Purchase Manager (PM) for procurement sourcing & buying.
                                                </p>
                                                <div class="mb-0">
                                                    <label class="form-label fw-semibold text-dark">Store Manager Note / Remarks (አስተያየት)</label>
                                                    <textarea name="notes" class="form-control" rows="3" placeholder="e.g. Items out of stock in central store. Please source from suppliers..."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary px-4">
                                                    <i class="fas fa-paper-plane me-1"></i> Confirm & Send to PM
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Store Dispatch Modal (routes to Finance Head) -->
                                <div class="modal fade" id="dispatchModal{{ $pr->id }}" tabindex="-1" aria-hidden="true" style="text-align: left;">
                                    <div class="modal-dialog">
                                        <form method="POST" action="{{ \Illuminate\Support\Facades\Route::has('office-requests.store-dispatch') ? route('office-requests.store-dispatch', $pr) : url('/office-requests/' . $pr->id . '/store-dispatch') }}" class="modal-content border-0 shadow">
                                            @csrf
                                            <div class="modal-header bg-success text-white">
                                                <h5 class="modal-title fw-bold">
                                                    <i class="fas fa-boxes-packing me-2"></i>Dispatch from Store &amp; Send to Finance Head
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div style="background:#d1fae5; border:1px solid #34d399; border-radius:8px; padding:10px 14px; margin-bottom:14px; font-size:0.88rem; color:#065f46; line-height:1.5;">
                                                    <i class="fas fa-arrow-right" style="margin-right:6px;"></i>
                                                    After confirming, <strong>{{ $pr->pr_no }}</strong> will be marked as <strong>issued from store</strong> and automatically forwarded to <strong>Finance Head</strong> for expense recording &amp; payment tracking. A Finance expense entry will be created automatically.
                                                </div>
                                                <div class="mb-0">
                                                    <label class="form-label fw-semibold text-dark">Store Manager Dispatch Note (የመዝገብ ማስታወሻ)</label>
                                                    <textarea name="notes" class="form-control" rows="3" placeholder="e.g. All 3 items issued from Main Store to Office Secretary on Aug 26..."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-success px-4 fw-bold">
                                                    <i class="fas fa-check me-1"></i> Confirm Dispatch &amp; Send to Finance
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                <!-- Finance Head: Confirm Expense Modal -->
                                @if($pr->is_office_request && $pr->status === 'pending_finance')
                                <div class="modal fade" id="financeConfirmModal{{ $pr->id }}" tabindex="-1" aria-hidden="true" style="text-align: left;">
                                    <div class="modal-dialog">
                                        <form method="POST" action="{{ \Illuminate\Support\Facades\Route::has('office-requests.finance-confirm') ? route('office-requests.finance-confirm', $pr) : url('/office-requests/' . $pr->id . '/finance-confirm') }}" class="modal-content border-0 shadow">
                                            @csrf
                                            <div class="modal-header text-white" style="background:#7c3aed;">
                                                <h5 class="modal-title fw-bold">
                                                    <i class="fas fa-file-invoice-dollar me-2"></i>Confirm Office Expense & Mark Paid
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="alert" style="background:#ede9fe; border:1px solid #7c3aed; color:#5b21b6;">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    <strong>{{ $pr->pr_no }}</strong> — Office supplies have been issued from Store. As Finance Head, record the expense and confirm payment.
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold text-dark">Amount Paid / Assigned (ETB) <span class="text-muted fw-normal small">(optional)</span></label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">ETB</span>
                                                        <input type="number" name="payment_amount" class="form-control" min="0" step="0.01" placeholder="e.g. 4500.00">
                                                    </div>
                                                </div>
                                                <div class="mb-0">
                                                    <label class="form-label fw-semibold text-dark">Finance Notes / Remarks (ማስታወሻ)</label>
                                                    <textarea name="payment_notes" class="form-control" rows="3" placeholder="e.g. Charged to office petty cash. Voucher #VC-2026-08-001 issued..."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn text-white px-4 fw-bold" style="background:#7c3aed;">
                                                    <i class="fas fa-check-double me-1"></i> Confirm & Close Request
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                @endif

                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fas fa-check-circle fa-3x mb-3 text-success d-block"></i>
                                No pending procurement items awaiting your action right now!
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($myPrs->hasPages())
        <div class="card-footer bg-white border-0 py-3">
            {{ $myPrs->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
