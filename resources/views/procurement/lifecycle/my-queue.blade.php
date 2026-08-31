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
        @endif        <div class="col-12 col-sm-6 col-xl">
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

    <!-- Merged Unified Purchase & Material Requests Queue Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 font-weight-bold"><i class="fas fa-list me-2 text-primary"></i>Purchase &amp; Material Requests Pending Role Action</h5>
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
                            <th>PR / Ref Number</th>
                            <th>Project / Purpose / Store</th>
                            <th>Channel Source</th>
                            <th>Priority</th>
                            <th>Stage / Status</th>
                            <th>Current Owner</th>
                            <th>Created Date</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- 1. Merged Material & Maintenance Requisitions --}}
                        @foreach($materialRequestsQueue ?? [] as $mr)
                        @php
                            $linkedPr = $mr->purchaseRequests?->first() ?? \App\Models\PurchaseRequest::where('material_request_id', $mr->id)->first();
                        @endphp
                        <tr class="table-light bg-opacity-25">
                            <td>
                                @if($linkedPr)
                                    <a href="{{ route('purchase-requests.show', $linkedPr->id) }}" class="fw-bold text-decoration-none text-primary font-monospace">
                                        {{ $linkedPr->pr_no }}
                                    </a>
                                    <small class="text-muted d-block" style="font-size: 0.75rem;">MR: {{ $mr->reference_number }}</small>
                                @else
                                    <a href="{{ route('material-requests.show', $mr) }}" class="fw-bold text-decoration-none text-primary font-monospace">
                                        {{ $mr->reference_number }}
                                    </a>
                                @endif
                                @if($mr->maintenance_request_id && $mr->maintenanceRequest)
                                    <div class="mt-1">
                                        <a href="{{ route('general-service.maintenance.show', $mr->maintenanceRequest) }}" class="badge bg-warning text-dark text-decoration-none border shadow-xs" title="View linked maintenance ticket">
                                            <i class="fa-solid fa-screwdriver-wrench me-1"></i>{{ $mr->maintenanceRequest->request_no }}
                                        </a>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $mr->project?->name ?? 'General Store' }}</div>
                                <small class="text-muted d-block"><i class="fa-solid fa-warehouse me-1"></i>{{ $mr->store?->name ?? 'Default Store' }}</small>
                                <div class="small mt-1">
                                    @foreach($mr->items as $item)
                                        <span class="text-secondary">{{ $item->product->name ?? 'Material' }}: <strong>{{ (float)$item->quantity_requested }} {{ $item->product->unit ?? '' }}</strong></span>@if(!$loop->last), @endif
                                    @endforeach
                                </div>
                            </td>
                            <td>
                                @if($mr->maintenance_request_id)
                                    <span class="badge bg-warning text-dark border border-warning">
                                        <i class="fa-solid fa-screwdriver-wrench me-1"></i> Maintenance MR
                                    </span>
                                @else
                                    <span class="badge bg-info text-dark border border-info">
                                        <i class="fa-solid fa-hard-hat me-1"></i> Site Requisition
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ optional($mr->required_date)->isPast() ? 'danger' : 'warning' }}">
                                    {{ optional($mr->required_date)->isPast() ? 'Urgent' : 'High' }}
                                </span>
                            </td>
                            <td>
                                @if($linkedPr)
                                    <span class="badge bg-{{ \App\Models\PurchaseRequest::statusBadgeClass($linkedPr->status) }}">
                                        {{ $linkedPr->status_label }}
                                    </span>
                                @else
                                    @php
                                        $mrBadge = match($mr->status) {
                                            'sent_to_store_manager' => ['class' => 'bg-warning text-dark', 'label' => 'Sent to Store Manager', 'icon' => 'fa-warehouse'],
                                            'pending', 'pending_planning' => ['class' => 'bg-warning text-dark', 'label' => 'Pending Planning', 'icon' => 'fa-hourglass-half'],
                                            'planning_approved'     => ['class' => 'bg-info text-dark',    'label' => 'Planning Approved',     'icon' => 'fa-check'],
                                            'issued'                => ['class' => 'bg-success',           'label' => 'Issued from Store',     'icon' => 'fa-check-double'],
                                            'processed'             => ['class' => 'bg-info text-dark',    'label' => 'Processed',             'icon' => 'fa-boxes-packing'],
                                            'needs_purchase', 'sent_to_pr' => ['class' => 'bg-primary',   'label' => 'In Procurement (PR)',   'icon' => 'fa-cart-shopping'],
                                            default                 => ['class' => 'bg-secondary',         'label' => ucfirst(str_replace('_', ' ', $mr->status)), 'icon' => 'fa-circle-dot'],
                                        };
                                    @endphp
                                    <span class="badge {{ $mrBadge['class'] }}">
                                        <i class="fa-solid {{ $mrBadge['icon'] }} me-1"></i>{{ $mrBadge['label'] }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($linkedPr)
                                    <span class="badge bg-secondary bg-opacity-10 text-dark">
                                        <i class="fas fa-user-tag me-1"></i> {{ ucfirst(str_replace('_', ' ', $linkedPr->current_owner_role ?? 'Purchase')) }}
                                    </span>
                                @elseif(in_array($mr->status, ['needs_purchase', 'sent_to_pr']))
                                    <span class="badge bg-secondary bg-opacity-10 text-dark">
                                        <i class="fas fa-user-tag me-1"></i> Purchase
                                    </span>
                                @else
                                    <span class="badge bg-secondary bg-opacity-10 text-dark">
                                        <i class="fas fa-warehouse me-1"></i> Store Manager
                                    </span>
                                @endif
                            </td>
                            <td>{{ $mr->created_at->format('M d, Y') }}</td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1 flex-wrap">
                                    @if($linkedPr)
                                        <a href="{{ route('purchase-requests.show', $linkedPr->id) }}" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye me-1"></i> View &amp; Review
                                        </a>
                                    @elseif(in_array($mr->status, ['needs_purchase', 'sent_to_pr']))
                                        <a href="{{ route('purchase-requests.create', ['material_request_id' => $mr->id, 'project_id' => $mr->project_id, 'store_id' => $mr->destination_store_id]) }}" class="btn btn-sm btn-primary shadow-sm" title="Create and review PR">
                                            <i class="fas fa-eye me-1"></i> View &amp; Review
                                        </a>
                                    @else
                                        @if(in_array($mr->status, ['sent_to_store_manager', 'planning_approved', 'pending']))
                                            <form method="POST" action="{{ route('material-requests.send-to-pr', $mr) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-primary shadow-sm" title="Route to Purchase Request (Buy)">
                                                    <i class="fas fa-cart-plus me-1"></i> Route to PR
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('material-requests.create-transfer', $mr) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Create Transfer from other store">
                                                    <i class="fas fa-exchange-alt me-1"></i> Transfer
                                                </button>
                                            </form>
                                        @endif
                                        <a href="{{ route('material-requests.show', $mr) }}" class="btn btn-sm btn-primary" title="View Full Details">
                                            <i class="fas fa-eye me-1"></i> View &amp; Review
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach

                        @foreach($myPrs as $pr)
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
                                    <div>
                                        <span class="badge bg-light text-dark border">{{ $pr->materialRequest->source ?? 'Material Request' }}</span>
                                        @if($pr->materialRequest->maintenance_request_id && $pr->materialRequest->maintenanceRequest)
                                            <div class="mt-1">
                                                <a href="{{ route('general-service.maintenance.show', $pr->materialRequest->maintenanceRequest) }}" class="badge bg-warning text-dark text-decoration-none border shadow-xs" title="Linked Maintenance Ticket">
                                                    <i class="fa-solid fa-screwdriver-wrench me-1"></i>{{ $pr->materialRequest->maintenanceRequest->request_no }}
                                                </a>
                                            </div>
                                        @endif
                                    </div>
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
                                            <i class="fas fa-eye me-1"></i> View &amp; Review
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach

                        @if(($materialRequestsQueue->isEmpty() ?? true) && $myPrs->isEmpty())
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fas fa-check-circle fa-3x mb-3 text-success d-block"></i>
                                No pending procurement or requisition items awaiting your action right now!
                            </td>
                        </tr>
                        @endif
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
