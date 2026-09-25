@extends('layouts.app')
@section('title', 'GM Maintenance & Operations Approvals')

@section('content')
<style>
/* Fix Bootstrap modal stacking, backdrop, and unlock scroll function */
.modal {
    z-index: 1065 !important;
}
.modal-backdrop {
    z-index: 1055 !important;
}
.modal-backdrop.show {
    opacity: 0.45 !important;
}
.modal-content {
    border-radius: 1rem !important;
    overflow: hidden;
    box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.3) !important;
}
/* Unlock smooth scrolling on modal bodies */
.modal-dialog-scrollable .modal-body,
.modal-body[style*="overflow-y"] {
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch;
}
.modal-body::-webkit-scrollbar {
    width: 6px;
}
.modal-body::-webkit-scrollbar-track {
    background: #f1f5f9;
}
.modal-body::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.modal-body::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>

<div class="container-fluid py-3">

    {{-- Top Executive Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 pb-2 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill fw-semibold" style="font-size:0.8rem;">
                    <i class="fa-solid fa-user-shield me-1"></i>General Manager Executive Office
                </span>
                <span class="badge bg-light text-muted border px-2 py-1 rounded-pill" style="font-size:0.75rem;">
                    <i class="fa-regular fa-clock me-1"></i>{{ now()->format('l, F j, Y') }}
                </span>
            </div>
            <h1 class="h3 mb-0 text-dark fw-bold mt-1">
                <i class="fa-solid fa-list-check text-primary me-2"></i>Maintenance &amp; Operations Approvals
            </h1>
            <p class="text-muted small mb-0 mt-1">
                Executive sign-off for General Service repair tickets, maintenance budgets (Ask Money), and store spare parts (Ask Material). Approved requests are routed immediately to the appropriate section.
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <a href="{{ route('dashboard.gm') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-chart-line me-1"></i>GM Dashboard
            </a>
            <a href="{{ route('general-service.maintenance.index') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-wrench me-1"></i>Maintenance Hub
            </a>
        </div>
    </div>

    {{-- KPI Highlights --}}
    <div class="row g-3 mb-4">
        {{-- Maintenance Reports / Tickets --}}
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #f59e0b !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-uppercase text-secondary fw-bold small" style="font-size: 0.72rem; letter-spacing: 0.5px;">Maintenance Tickets</span>
                            <h3 class="fw-bold mb-0 text-dark mt-1">{{ $maintenanceTickets->count() }}</h3>
                            <small class="text-muted">{{ $pendingTicketsCount }} active / pending review</small>
                        </div>
                        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background: rgba(245, 158, 11, 0.12); width: 50px; height: 50px;">
                            <i class="fa-solid fa-wrench text-warning fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Pending Expenses Amount --}}
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #10b981 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-uppercase text-secondary fw-bold small" style="font-size: 0.72rem; letter-spacing: 0.5px;">Funding Requested (Ask Money)</span>
                            <h3 class="fw-bold mb-0 text-success mt-1">{{ number_format($totalPendingExpenseAmount, 2) }} <span class="fs-6 text-muted fw-normal">ETB</span></h3>
                            <small class="text-muted">{{ $pendingExpenses->count() }} tickets requesting budget</small>
                        </div>
                        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background: rgba(16, 185, 129, 0.12); width: 50px; height: 50px;">
                            <i class="fa-solid fa-hand-holding-dollar text-success fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Pending Material Items --}}
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #3b82f6 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-uppercase text-secondary fw-bold small" style="font-size: 0.72rem; letter-spacing: 0.5px;">Materials Requested (Store)</span>
                            <h3 class="fw-bold mb-0 text-primary mt-1">{{ $pendingMaterials->count() }} <span class="fs-6 text-muted fw-normal">requests</span></h3>
                            <small class="text-muted">{{ $totalPendingMaterialItems }} total item lines requested</small>
                        </div>
                        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background: rgba(59, 130, 246, 0.12); width: 50px; height: 50px;">
                            <i class="fa-solid fa-boxes-stacked text-primary fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Decided Log --}}
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #6366f1 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-uppercase text-secondary fw-bold small" style="font-size: 0.72rem; letter-spacing: 0.5px;">Decided / Processed</span>
                            <h3 class="fw-bold mb-0 text-dark mt-1">{{ $totalDecidedCount }}</h3>
                            <small class="text-muted">Passed to Finance, Coordinator or Store</small>
                        </div>
                        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background: rgba(99, 102, 241, 0.12); width: 50px; height: 50px;">
                            <i class="fa-solid fa-circle-check text-primary fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Tabs & Search Bar --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                {{-- Tabs --}}
                <ul class="nav nav-pills gap-2" id="approvalTabs" role="tablist">
                    <li class="nav-item">
                        <a href="{{ route('gm.maintenance-approvals.index', ['tab' => 'pending', 'search' => $search]) }}" 
                           class="nav-link rounded-pill px-3 py-2 fw-semibold {{ $tab === 'pending' ? 'active shadow-sm' : 'text-dark bg-light' }}">
                            <i class="fa-solid fa-clock me-1"></i>All Pending
                            @if($totalPendingCount > 0)
                                <span class="badge {{ $tab === 'pending' ? 'bg-white text-primary' : 'bg-warning text-dark' }} rounded-pill ms-1">{{ $totalPendingCount }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('gm.maintenance-approvals.index', ['tab' => 'tickets', 'search' => $search]) }}" 
                           class="nav-link rounded-pill px-3 py-2 fw-semibold {{ $tab === 'tickets' ? 'active shadow-sm' : 'text-dark bg-light' }}">
                            <i class="fa-solid fa-wrench me-1 text-warning"></i>Maintenance Tickets
                            <span class="badge {{ $tab === 'tickets' ? 'bg-white text-primary' : 'bg-warning text-dark' }} rounded-pill ms-1">{{ $maintenanceTickets->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('gm.maintenance-approvals.index', ['tab' => 'expenses', 'search' => $search]) }}" 
                           class="nav-link rounded-pill px-3 py-2 fw-semibold {{ $tab === 'expenses' ? 'active shadow-sm' : 'text-dark bg-light' }}">
                            <i class="fa-solid fa-hand-holding-dollar me-1 text-success"></i>Ask Money (Expenses)
                            @if($pendingExpenses->count() > 0)
                                <span class="badge {{ $tab === 'expenses' ? 'bg-white text-primary' : 'bg-success' }} rounded-pill ms-1">{{ $pendingExpenses->count() }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('gm.maintenance-approvals.index', ['tab' => 'materials', 'search' => $search]) }}" 
                           class="nav-link rounded-pill px-3 py-2 fw-semibold {{ $tab === 'materials' ? 'active shadow-sm' : 'text-dark bg-light' }}">
                            <i class="fa-solid fa-boxes-packing me-1 text-primary"></i>Ask Material (Store)
                            @if($pendingMaterials->count() > 0)
                                <span class="badge {{ $tab === 'materials' ? 'bg-white text-primary' : 'bg-primary' }} rounded-pill ms-1">{{ $pendingMaterials->count() }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('gm.maintenance-approvals.index', ['tab' => 'history', 'search' => $search]) }}" 
                           class="nav-link rounded-pill px-3 py-2 fw-semibold {{ $tab === 'history' ? 'active shadow-sm' : 'text-dark bg-light' }}">
                            <i class="fa-solid fa-clock-rotate-left me-1"></i>Decided History
                            <span class="badge {{ $tab === 'history' ? 'bg-white text-primary' : 'bg-secondary' }} rounded-pill ms-1">{{ $totalDecidedCount }}</span>
                        </a>
                    </li>
                </ul>

                {{-- Search Box --}}
                <form action="{{ route('gm.maintenance-approvals.index') }}" method="GET" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0 rounded-start-pill"><i class="fa-solid fa-search text-muted"></i></span>
                        <input type="text" name="search" value="{{ $search }}" class="form-control border-start-0 rounded-end-pill" placeholder="Search ticket #, asset, or note..." style="min-width: 220px;">
                    </div>
                    @if($search)
                        <a href="{{ route('gm.maintenance-approvals.index', ['tab' => $tab]) }}" class="btn btn-sm btn-light border rounded-pill" title="Clear Search">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    @endif
                </form>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════════
         TAB CONTENT: 1. PENDING (MAINTENANCE TICKETS + ASK MONEY + ASK MATERIAL)
    ═══════════════════════════════════════════════════════════════════════════════ --}}
    @if(in_array($tab, ['pending', 'tickets', 'expenses', 'materials']))

        {{-- SECTION 0: INCOMING MAINTENANCE & REPAIR REPORTS (MAINTENANCE TICKETS) --}}
        @if($tab === 'pending' || $tab === 'tickets')
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-warning bg-opacity-25" style="width:38px;height:38px;">
                            <i class="fa-solid fa-wrench text-warning"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold text-dark">Incoming Maintenance &amp; Repair Reports</h5>
                            <small class="text-muted">Direct issue reports submitted by employees &amp; equipment operators awaiting GM executive approval &amp; routing.</small>
                        </div>
                    </div>
                    <span class="badge bg-warning bg-opacity-15 text-dark rounded-pill px-3 py-1 fw-bold">
                        {{ $maintenanceTickets->count() }} Awaiting GM Action
                    </span>
                </div>

                <div class="card-body p-0">
                    @if($maintenanceTickets->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                                <thead class="table-light text-secondary small text-uppercase" style="font-size: 0.75rem;">
                                    <tr>
                                        <th class="ps-4 py-3">Request #</th>
                                        <th class="py-3">Reported By / Asset</th>
                                        <th class="py-3">Issue Type &amp; Urgency</th>
                                        <th class="py-3">Status</th>
                                        <th class="py-3">Assigned To</th>
                                        <th class="py-3">Linked Requisitions</th>
                                        <th class="py-3 pe-4 text-end">GM Executive Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($maintenanceTickets as $mReq)
                                    @php
                                        $urgencyBadge = match($mReq->urgency) {
                                            'critical' => ['class' => 'bg-danger text-white', 'icon' => 'fa-fire', 'label' => 'Critical'],
                                            'urgent'   => ['class' => 'bg-warning text-dark', 'icon' => 'fa-triangle-exclamation', 'label' => 'Urgent'],
                                            'normal'   => ['class' => 'bg-info bg-opacity-25 text-info-emphasis border border-info border-opacity-25', 'icon' => 'fa-info-circle', 'label' => 'Normal'],
                                            default    => ['class' => 'bg-secondary bg-opacity-25 text-secondary', 'icon' => 'fa-circle-down', 'label' => 'Low'],
                                        };
                                        $statusBadge = match($mReq->status) {
                                            'pending'               => ['class' => 'bg-warning text-dark', 'icon' => 'fa-clock', 'label' => 'Pending'],
                                            'in_progress'           => ['class' => 'bg-primary text-white', 'icon' => 'fa-wrench', 'label' => 'In Progress'],
                                            'sent_to_store_manager' => ['class' => 'bg-info text-white', 'icon' => 'fa-warehouse', 'label' => 'Sent to Store'],
                                            'resolved'              => ['class' => 'bg-success text-white', 'icon' => 'fa-circle-check', 'label' => 'Resolved'],
                                            'closed'                => ['class' => 'bg-secondary text-white', 'icon' => 'fa-lock', 'label' => 'Closed'],
                                            'rejected'              => ['class' => 'bg-danger text-white', 'icon' => 'fa-circle-xmark', 'label' => 'Rejected'],
                                            default                 => ['class' => 'bg-light text-dark', 'icon' => 'fa-circle', 'label' => ucfirst(str_replace('_', ' ', $mReq->status))],
                                        };
                                    @endphp
                                    <tr>
                                        {{-- Request # --}}
                                        <td class="ps-4 py-3">
                                            <a href="{{ route('general-service.maintenance.show', $mReq->id) }}" class="fw-bold text-dark font-monospace text-decoration-none">
                                                {{ $mReq->request_no }}
                                            </a>
                                            <div class="text-muted" style="font-size:0.75rem;">{{ $mReq->created_at->diffForHumans() }}</div>
                                        </td>

                                        {{-- Asset & Reporter --}}
                                        <td class="py-3">
                                            <div class="fw-bold text-dark">{{ $mReq->asset_name }}</div>
                                            @if($mReq->asset_code)
                                                <span class="badge bg-dark font-monospace px-2 py-0 me-1" style="font-size:0.7rem;">{{ $mReq->asset_code }}</span>
                                            @endif
                                            <span class="text-muted small">
                                                <i class="fa-solid fa-user me-1 text-primary"></i>{{ $mReq->employee->full_name ?? ($mReq->reportedBy->name ?? 'Staff') }}
                                            </span>
                                        </td>

                                        {{-- Issue Type & Urgency --}}
                                        <td class="py-3">
                                            <div class="d-flex flex-column gap-1">
                                                <span class="badge bg-light text-dark border px-2 py-1 text-capitalize align-self-start" style="font-size:0.75rem;">
                                                    <i class="fa-solid fa-tag me-1 text-warning"></i>{{ str_replace('_', ' ', $mReq->issue_type) }}
                                                </span>
                                                <span class="badge {{ $urgencyBadge['class'] }} px-2 py-0 align-self-start" style="font-size:0.7rem;">
                                                    <i class="fa-solid {{ $urgencyBadge['icon'] }} me-1"></i>{{ $urgencyBadge['label'] }}
                                                </span>
                                            </div>
                                        </td>

                                        {{-- Status --}}
                                        <td class="py-3">
                                            <span class="badge {{ $statusBadge['class'] }} px-2 py-1">
                                                <i class="fa-solid {{ $statusBadge['icon'] }} me-1"></i>{{ $statusBadge['label'] }}
                                            </span>
                                        </td>

                                        {{-- Assigned To --}}
                                        <td class="py-3 text-muted small">
                                            @if($mReq->assignedTo)
                                                <div class="d-flex align-items-center gap-1 text-dark fw-semibold">
                                                    <i class="fa-solid fa-user-gear text-primary"></i>
                                                    <span>{{ $mReq->assignedTo->name }}</span>
                                                </div>
                                            @else
                                                <span class="text-muted fst-italic">Unassigned</span>
                                            @endif
                                        </td>

                                        {{-- Linked Requisitions (Ask Money / Ask Material) --}}
                                        <td class="py-3">
                                            <div class="d-flex flex-column gap-1">
                                                {{-- Money --}}
                                                @if($mReq->expenseRequests->isNotEmpty())
                                                    @foreach($mReq->expenseRequests as $lkExp)
                                                        <div class="small">
                                                            <span class="badge bg-success bg-opacity-10 text-success fw-bold font-monospace">
                                                                <i class="fa-solid fa-hand-holding-dollar me-1"></i>{{ number_format($lkExp->amount, 2) }} ETB
                                                            </span>
                                                            <span class="badge bg-light text-muted border small">{{ ucfirst(str_replace('_', ' ', $lkExp->status)) }}</span>
                                                        </div>
                                                    @endforeach
                                                @else
                                                    <span class="text-muted small" style="font-size: 0.75rem;">No Money Asked</span>
                                                @endif

                                                {{-- Material --}}
                                                @if($mReq->materialRequests->isNotEmpty())
                                                    <div class="small">
                                                        <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold">
                                                            <i class="fa-solid fa-boxes-stacked me-1"></i>{{ $mReq->materialRequests->count() }} Material Req(s)
                                                        </span>
                                                    </div>
                                                @endif
                                            </div>
                                        </td>

                                        {{-- GM Actions --}}
                                        <td class="py-3 pe-4 text-end">
                                            <div class="d-flex justify-content-end gap-2 flex-wrap">
                                                @if($mReq->gs_status === 'submitted_to_gm')
                                                    <button type="button" class="btn btn-warning btn-sm text-dark fw-bold rounded-pill px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#gmInitialModal{{ $mReq->id }}">
                                                        <i class="fa-solid fa-gavel me-1"></i>Review Permission
                                                    </button>
                                                @elseif($mReq->gs_status === 'pending_gm_final')
                                                    <button type="button" class="btn btn-primary btn-sm text-white fw-bold rounded-pill px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#gmFinalModal{{ $mReq->id }}">
                                                        <i class="fa-solid fa-check-double me-1"></i>Approve Petty Cash
                                                    </button>
                                                @else
                                                    <button type="button" class="btn btn-secondary btn-sm text-white fw-bold rounded-pill px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#grantGmApprovalModal{{ $mReq->id }}">
                                                        <i class="fa-solid fa-sliders me-1"></i>Manage Action
                                                    </button>
                                                @endif
                                                <a href="{{ route('general-service.maintenance.show', $mReq->id) }}" class="btn btn-outline-secondary btn-sm rounded-pill px-2" title="Open Full Maintenance Details">
                                                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-4 bg-light rounded-bottom-4 text-center">
                            <i class="fa-solid fa-circle-check text-success fs-3 mb-2"></i>
                            <h6 class="fw-bold text-dark mb-1">No Pending Maintenance Reports Awaiting Action</h6>
                            <p class="text-muted small mb-0">All submitted reports have been reviewed, routed, and locked into <a href="{{ route('gm.maintenance-approvals.index', ['tab' => 'history']) }}" class="fw-semibold text-decoration-none">Decision History</a>.</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- SECTION A: PENDING EXPENSES ("ASK MONEY") --}}
        @if($tab === 'pending' || $tab === 'expenses')
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-success bg-opacity-25" style="width:38px;height:38px;">
                            <i class="fa-solid fa-hand-holding-dollar text-success"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold text-dark">Maintenance Funding Requests (Ask Money)</h5>
                            <small class="text-muted">Budget requested for repair technicians, external labor, emergency parts, or local purchases.</small>
                        </div>
                    </div>
                    <span class="badge bg-success bg-opacity-15 text-success rounded-pill px-3 py-1 fw-bold">
                        {{ $pendingExpenses->count() }} Pending GM Sign-off
                    </span>
                </div>

                <div class="card-body p-0">
                    @if($pendingExpenses->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                                <thead class="table-light text-secondary small text-uppercase" style="font-size: 0.75rem;">
                                    <tr>
                                        <th class="ps-4 py-3">Request #</th>
                                        <th class="py-3">Maintenance Asset / Ticket</th>
                                        <th class="py-3">Requester</th>
                                        <th class="py-3">Amount Requested</th>
                                        <th class="py-3">Purpose &amp; Note</th>
                                        <th class="py-3">Status</th>
                                        <th class="py-3 pe-4 text-end">GM Executive Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pendingExpenses as $exp)
                                    <tr>
                                        {{-- Request # --}}
                                        <td class="ps-4 py-3">
                                            <a href="{{ route('expense-requests.show', $exp) }}" class="fw-bold font-monospace text-decoration-none text-dark">
                                                {{ $exp->request_number }}
                                            </a>
                                            <div class="text-muted" style="font-size: 0.75rem;">{{ $exp->created_at->diffForHumans() }}</div>
                                        </td>

                                        {{-- Asset / Maintenance Ticket --}}
                                        <td class="py-3">
                                            @if($exp->maintenanceRequest)
                                                <div class="fw-bold text-dark">{{ $exp->maintenanceRequest->asset_name }}</div>
                                                <a href="{{ route('general-service.maintenance.show', $exp->maintenanceRequest->id) }}" class="badge bg-light text-primary border text-decoration-none font-monospace">
                                                    {{ $exp->maintenanceRequest->request_no }}
                                                </a>
                                            @else
                                                <span class="badge bg-light text-dark border">General Maintenance</span>
                                            @endif
                                        </td>

                                        {{-- Requester --}}
                                        <td class="py-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="fa-solid fa-user-circle text-muted fs-5"></i>
                                                <div>
                                                    <span class="fw-semibold text-dark d-block">{{ $exp->user->name ?? 'Staff' }}</span>
                                                    <small class="text-muted">{{ $exp->employee->first_name ?? '' }} {{ $exp->employee->last_name ?? '' }}</small>
                                                </div>
                                            </div>
                                        </td>

                                        {{-- Amount --}}
                                        <td class="py-3">
                                            <span class="fs-6 fw-bold text-success font-monospace">{{ number_format($exp->amount, 2) }}</span>
                                            <small class="text-muted">ETB</small>
                                        </td>

                                        {{-- Description --}}
                                        <td class="py-3" style="max-width: 280px;">
                                            <p class="mb-0 text-dark small text-truncate" title="{{ $exp->description }}">{{ $exp->description }}</p>
                                            @if($exp->attachment)
                                                <a href="{{ asset($exp->attachment) }}" target="_blank" class="badge bg-light text-primary border text-decoration-none mt-1">
                                                    <i class="fa-solid fa-paperclip me-1"></i>Attachment
                                                </a>
                                            @endif
                                        </td>

                                        {{-- Status --}}
                                        <td class="py-3">
                                            <span class="badge bg-warning bg-opacity-25 text-warning-emphasis border border-warning border-opacity-25 px-2 py-1">
                                                <i class="fa-solid fa-hourglass-half me-1"></i>Pending GM
                                            </span>
                                        </td>

                                        {{-- Actions --}}
                                        <td class="text-end pe-4 py-3">
                                            <div class="d-flex justify-content-end gap-2 flex-wrap">
                                                {{-- Button 1: Approve to Coordinator --}}
                                                <button type="button" class="btn btn-sm btn-info text-white fw-bold rounded-pill px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#approveCoordinatorExpenseModal{{ $exp->id }}">
                                                    <i class="fa-solid fa-paper-plane me-1"></i>Approve → Coordinator
                                                </button>

                                                {{-- Button 2: Approve directly to Finance --}}
                                                <button type="button" class="btn btn-sm btn-success fw-bold rounded-pill px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#approveExpenseModal{{ $exp->id }}">
                                                    <i class="fa-solid fa-check me-1"></i>Finance
                                                </button>

                                                {{-- Button 3: Fulfill via Store --}}
                                                <button type="button" class="btn btn-sm btn-outline-primary fw-semibold rounded-pill px-2 shadow-xs" data-bs-toggle="modal" data-bs-target="#storeExpenseModal{{ $exp->id }}" title="Fulfill with store materials instead of paying cash">
                                                    <i class="fa-solid fa-warehouse"></i>
                                                </button>

                                                {{-- Button 4: Reject --}}
                                                <button type="button" class="btn btn-sm btn-outline-danger fw-semibold rounded-pill px-2 shadow-xs" data-bs-toggle="modal" data-bs-target="#rejectExpenseModal{{ $exp->id }}" title="Reject Request">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-4 bg-light rounded-bottom-4 text-center">
                            <i class="fa-solid fa-circle-check text-success fs-3 mb-2"></i>
                            <h6 class="fw-bold text-dark mb-1">No Pending Expense Requests (Ask Money)</h6>
                            <p class="text-muted small mb-0">All maintenance funding requests have been reviewed and processed.</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- SECTION B: PENDING MATERIALS ("ASK MATERIAL") --}}
        @if($tab === 'pending' || $tab === 'materials')
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary bg-opacity-25" style="width:38px;height:38px;">
                            <i class="fa-solid fa-boxes-packing text-primary"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold text-dark">Maintenance Material &amp; Spare Parts Requests (Ask Material)</h5>
                            <small class="text-muted">Spare parts and consumables requested from the store or procurement for equipment repairs.</small>
                        </div>
                    </div>
                    <span class="badge bg-primary bg-opacity-15 text-primary rounded-pill px-3 py-1 fw-bold">
                        {{ $pendingMaterials->count() }} Pending GM Decision
                    </span>
                </div>

                <div class="card-body p-0">
                    @if($pendingMaterials->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                                <thead class="table-light text-secondary small text-uppercase" style="font-size: 0.75rem;">
                                    <tr>
                                        <th class="ps-4 py-3">Reference #</th>
                                        <th class="py-3">Maintenance Asset / Ticket</th>
                                        <th class="py-3">Destination Store</th>
                                        <th class="py-3">Items Requested</th>
                                        <th class="py-3">Urgency &amp; Required By</th>
                                        <th class="py-3">Status</th>
                                        <th class="py-3 pe-4 text-end">GM Executive Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pendingMaterials as $mr)
                                    <tr>
                                        {{-- Ref # --}}
                                        <td class="ps-4 py-3">
                                            <a href="{{ route('material-requests.show', $mr) }}" class="fw-bold font-monospace text-decoration-none text-dark">
                                                {{ $mr->reference_number }}
                                            </a>
                                            <div class="text-muted" style="font-size: 0.75rem;">{{ $mr->created_at->diffForHumans() }}</div>
                                        </td>

                                        {{-- Asset / Maintenance --}}
                                        <td class="py-3">
                                            @if($mr->maintenanceRequest)
                                                <div class="fw-bold text-dark">{{ $mr->maintenanceRequest->asset_name }}</div>
                                                <a href="{{ route('general-service.maintenance.show', $mr->maintenanceRequest->id) }}" class="badge bg-light text-primary border text-decoration-none font-monospace">
                                                    {{ $mr->maintenanceRequest->request_no }}
                                                </a>
                                            @else
                                                <span class="badge bg-light text-dark border">General Maintenance</span>
                                            @endif
                                        </td>

                                        {{-- Store --}}
                                        <td class="py-3">
                                            <span class="badge bg-light text-dark border">
                                                <i class="fa-solid fa-warehouse me-1 text-primary"></i>{{ $mr->store->name ?? 'Default Store' }}
                                            </span>
                                        </td>

                                        {{-- Items --}}
                                        <td class="py-3">
                                            <div class="d-flex flex-column gap-1">
                                                @foreach($mr->items->take(3) as $item)
                                                    <div class="small">
                                                        <strong class="text-dark">{{ $item->product->name ?? 'Part' }}</strong>
                                                        <span class="badge bg-light text-secondary border ms-1">{{ $item->quantity_requested }} {{ $item->product->unit ?? 'pcs' }}</span>
                                                    </div>
                                                @endforeach
                                                @if($mr->items->count() > 3)
                                                    <small class="text-muted fst-italic">+{{ $mr->items->count() - 3 }} more item(s)</small>
                                                @endif
                                            </div>
                                        </td>

                                        {{-- Urgency & Date --}}
                                        <td class="py-3">
                                            <div class="text-dark small fw-semibold">
                                                <i class="fa-regular fa-calendar me-1 text-muted"></i>{{ $mr->required_date ? $mr->required_date->format('M d, Y') : 'Immediate' }}
                                            </div>
                                            <small class="text-muted">By {{ $mr->creator->name ?? 'General Service' }}</small>
                                        </td>

                                        {{-- Status --}}
                                        <td class="py-3">
                                            <span class="badge bg-primary bg-opacity-25 text-primary border border-primary border-opacity-25 px-2 py-1">
                                                <i class="fa-solid fa-boxes-stacked me-1"></i>Pending GM
                                            </span>
                                        </td>

                                        {{-- Actions --}}
                                        <td class="text-end pe-4 py-3">
                                            <div class="d-flex justify-content-end gap-2 flex-wrap">
                                                {{-- Button 1: Send to Store Manager (Add to PR Cycle) --}}
                                                <button type="button" class="btn btn-sm btn-primary fw-bold rounded-pill px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#approveMaterialModal{{ $mr->id }}">
                                                    <i class="fa-solid fa-warehouse me-1"></i>Approve → Store Manager
                                                </button>

                                                {{-- Button 2: Direct to PR Cycle --}}
                                                <button type="button" class="btn btn-sm btn-outline-info fw-semibold rounded-pill px-2 shadow-xs" data-bs-toggle="modal" data-bs-target="#approveMaterialPrModal{{ $mr->id }}" title="Directly add to Procurement (PR) cycle">
                                                    <i class="fa-solid fa-cart-shopping me-1"></i>Add to PR
                                                </button>

                                                {{-- Button 3: Reject --}}
                                                <button type="button" class="btn btn-sm btn-outline-danger fw-semibold rounded-pill px-2 shadow-xs" data-bs-toggle="modal" data-bs-target="#rejectMaterialModal{{ $mr->id }}" title="Reject Request">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-4 bg-light rounded-bottom-4 text-center">
                            <i class="fa-solid fa-circle-check text-success fs-3 mb-2"></i>
                            <h6 class="fw-bold text-dark mb-1">No Pending Material Requests (Ask Material)</h6>
                            <p class="text-muted small mb-0">All maintenance spare parts and material requests have been reviewed and processed.</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif

    @endif

    {{-- ═══════════════════════════════════════════════════════════════════════════
         TAB CONTENT: 2. DECIDED HISTORY
    ═══════════════════════════════════════════════════════════════════════════════ --}}
    @if($tab === 'history')
        {{-- Decided Maintenance Tickets (Locked Decision History) --}}
        <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
            <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-secondary bg-opacity-10 text-secondary" style="width:38px;height:38px;">
                        <i class="fa-solid fa-lock text-dark"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold text-dark">Decided Maintenance &amp; Repair Reports (Decision History)</h5>
                        <small class="text-muted">Tickets reviewed, authorized and locked by the General Manager with financial &amp; store routing records.</small>
                    </div>
                </div>
                <span class="badge bg-secondary text-white rounded-pill px-3 py-1 fw-bold">
                    {{ $decidedTickets->count() }} Decided Tickets
                </span>
            </div>

            <div class="card-body p-0">
                @if($decidedTickets->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                            <thead class="table-light text-secondary small text-uppercase" style="font-size: 0.75rem;">
                                <tr>
                                    <th class="ps-4 py-3">Request #</th>
                                    <th class="py-3">Reported By / Asset</th>
                                    <th class="py-3">Executive Decision</th>
                                    <th class="py-3">Money Routing (Finance)</th>
                                    <th class="py-3">Material Routing (Store PR)</th>
                                    <th class="py-3">Decided At / Approver</th>
                                    <th class="py-3">Lock Status</th>
                                    <th class="py-3 pe-4 text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($decidedTickets as $mReq)
                                <tr>
                                    {{-- Request # --}}
                                    <td class="ps-4 py-3">
                                        <a href="{{ route('general-service.maintenance.show', $mReq->id) }}" class="fw-bold text-dark font-monospace text-decoration-none">
                                            {{ $mReq->request_no }}
                                        </a>
                                        <div class="text-muted" style="font-size:0.75rem;">{{ $mReq->created_at->format('d M Y') }}</div>
                                    </td>

                                    {{-- Asset & Reporter --}}
                                    <td class="py-3">
                                        <div class="fw-bold text-dark">{{ $mReq->asset_name }}</div>
                                        @if($mReq->asset_code)
                                            <span class="badge bg-dark font-monospace px-2 py-0 me-1" style="font-size:0.7rem;">{{ $mReq->asset_code }}</span>
                                        @endif
                                        <small class="text-muted d-block">
                                            {{ $mReq->employee->full_name ?? ($mReq->reportedBy->name ?? 'Staff') }}
                                        </small>
                                    </td>

                                    {{-- Executive Decision --}}
                                    <td class="py-3">
                                        @if($mReq->status === 'rejected')
                                            <span class="badge bg-danger text-white rounded-pill px-2.5 py-1">
                                                <i class="fa-solid fa-ban me-1"></i>Rejected
                                            </span>
                                            @if($mReq->rejection_reason)
                                                <small class="text-danger d-block mt-0.5" style="font-size:0.73rem; max-width: 220px;">{{ Str::limit($mReq->rejection_reason, 45) }}</small>
                                            @endif
                                        @elseif($mReq->status === 'sent_to_store_manager')
                                            <span class="badge bg-info text-white rounded-pill px-2.5 py-1">
                                                <i class="fa-solid fa-warehouse me-1"></i>Sent to Store Manager (PR)
                                            </span>
                                        @elseif($mReq->status === 'resolved')
                                            <span class="badge bg-success text-white rounded-pill px-2.5 py-1">
                                                <i class="fa-solid fa-circle-check me-1"></i>Approved &amp; Resolved
                                            </span>
                                        @elseif($mReq->status === 'closed')
                                            <span class="badge bg-secondary text-white rounded-pill px-2.5 py-1">
                                                <i class="fa-solid fa-lock me-1"></i>Closed
                                            </span>
                                        @else
                                            <span class="badge bg-primary text-white rounded-pill px-2.5 py-1">
                                                <i class="fa-solid fa-wrench me-1"></i>Approved (Under Repair)
                                            </span>
                                        @endif
                                        @if($mReq->assignedTo)
                                            <small class="text-muted d-block mt-0.5" style="font-size:0.73rem;">
                                                <i class="fa-solid fa-user-gear text-secondary me-1"></i>Tech: {{ $mReq->assignedTo->name }}
                                            </small>
                                        @endif
                                    </td>

                                    {{-- Money Routing (Finance) --}}
                                    <td class="py-3">
                                        @if($mReq->expenseRequests->isNotEmpty())
                                            @foreach($mReq->expenseRequests as $lkExp)
                                                <div class="mb-1">
                                                    <span class="badge bg-success bg-opacity-10 text-success fw-bold font-monospace">
                                                        {{ number_format($lkExp->amount, 2) }} ETB
                                                    </span>
                                                    <span class="badge bg-light text-success border border-success border-opacity-25 small">
                                                        <i class="fa-solid fa-check-double me-1"></i>Sent to Finance to Pay
                                                    </span>
                                                </div>
                                            @endforeach
                                        @else
                                            <span class="text-muted small">No Money Asked</span>
                                        @endif
                                    </td>

                                    {{-- Material Routing (Store PR) --}}
                                    <td class="py-3">
                                        @if($mReq->materialRequests->isNotEmpty())
                                            @foreach($mReq->materialRequests as $lkMat)
                                                <div class="mb-1">
                                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fw-semibold">
                                                        <i class="fa-solid fa-boxes-stacked me-1"></i>{{ $lkMat->items->count() }} Part(s)
                                                    </span>
                                                    <span class="badge bg-light text-primary border border-primary border-opacity-25 small">
                                                        <i class="fa-solid fa-cart-shopping me-1"></i>Store PR Cycle
                                                    </span>
                                                </div>
                                            @endforeach
                                        @elseif($mReq->status === 'sent_to_store_manager')
                                            <span class="badge bg-info bg-opacity-10 text-info-emphasis border border-info border-opacity-25 small">
                                                <i class="fa-solid fa-warehouse me-1"></i>Routed to Store
                                            </span>
                                        @else
                                            <span class="text-muted small">No Parts Needed</span>
                                        @endif
                                    </td>

                                    {{-- Decided At / Approver --}}
                                    <td class="py-3 small text-muted">
                                        <div><i class="fa-regular fa-calendar-check me-1 text-secondary"></i>{{ $mReq->gm_approved_at ? $mReq->gm_approved_at->format('d M Y, h:i A') : $mReq->updated_at->format('d M Y') }}</div>
                                        <div class="text-dark fw-semibold" style="font-size:0.75rem;">
                                            By {{ $mReq->gmApprover->name ?? 'Executive GM' }}
                                        </div>
                                    </td>

                                    {{-- Lock Status --}}
                                    <td class="py-3">
                                        <span class="badge bg-secondary text-white rounded-pill px-2.5 py-1" style="font-size:0.75rem;">
                                            <i class="fa-solid fa-lock me-1 text-warning"></i>Decision Locked
                                        </span>
                                    </td>

                                    {{-- Action --}}
                                    <td class="py-3 pe-4 text-end">
                                        <div class="d-flex justify-content-end align-items-center gap-1">
                                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-2.5 py-1" data-bs-toggle="modal" data-bs-target="#grantGmApprovalModal{{ $mReq->id }}" title="Inspect Locked Decision Record">
                                                <i class="fa-solid fa-eye me-1"></i>Inspect
                                            </button>
                                            <form action="{{ route('gm.maintenance-approvals.unlock-ticket', $mReq) }}" method="POST" onsubmit="return confirm('Unlock decision for ticket #{{ $mReq->request_no }} and return to incoming queue?');" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-warning btn-sm rounded-pill px-2 py-1" title="Unlock Decision &amp; Return to Incoming Queue">
                                                    <i class="fa-solid fa-lock-open"></i>
                                                </button>
                                            </form>
                                            <a href="{{ route('general-service.maintenance.show', $mReq->id) }}" class="btn btn-light border btn-sm rounded-pill px-2 py-1" title="Open Ticket Details" target="_blank">
                                                <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:0.75rem;"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-4 bg-light rounded-bottom-4 text-center">
                        <i class="fa-solid fa-folder-open text-muted fs-3 mb-2"></i>
                        <h6 class="fw-bold text-dark mb-1">No Decided Maintenance Tickets Yet</h6>
                        <p class="text-muted small mb-0">Decisions finalized by GM will appear here locked with complete routing breakdown.</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="row g-4 mb-4">
            {{-- Decided Expenses History --}}
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                    <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                        <div class="fw-bold text-dark"><i class="fa-solid fa-hand-holding-dollar text-success me-2"></i>Decided Expense Requests</div>
                        <span class="badge bg-secondary rounded-pill">{{ $decidedExpenses->count() }} records</span>
                    </div>
                    <div class="card-body p-0">
                        @if($decidedExpenses->isNotEmpty())
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light text-secondary small text-uppercase" style="font-size:0.75rem;">
                                        <tr>
                                            <th class="ps-4">Request #</th>
                                            <th>Amount</th>
                                            <th>Decision / Status</th>
                                            <th class="text-end pe-4">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($decidedExpenses as $dExp)
                                        <tr>
                                            <td class="ps-4">
                                                <strong class="font-monospace text-dark">{{ $dExp->request_number }}</strong>
                                                @if($dExp->maintenanceRequest)
                                                    <small class="text-muted d-block">{{ $dExp->maintenanceRequest->asset_name }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="fw-bold font-monospace text-success">{{ number_format($dExp->amount, 2) }}</span> <small class="text-muted">ETB</small>
                                            </td>
                                            <td>
                                                @if(in_array($dExp->status, [App\Models\ExpenseRequest::STATUS_APPROVED_ASSIGNED, App\Models\ExpenseRequest::STATUS_ASSIGNED]))
                                                    <span class="badge bg-success text-white rounded-pill px-2 py-1"><i class="fa-solid fa-check me-1"></i>Approved → Finance</span>
                                                @elseif($dExp->status === App\Models\ExpenseRequest::STATUS_SENT_TO_STORE)
                                                    <span class="badge bg-primary text-white rounded-pill px-2 py-1"><i class="fa-solid fa-warehouse me-1"></i>Routed to Store</span>
                                                @elseif($dExp->status === App\Models\ExpenseRequest::STATUS_PAID)
                                                    <span class="badge bg-dark text-white rounded-pill px-2 py-1"><i class="fa-solid fa-receipt me-1"></i>Paid</span>
                                                @elseif($dExp->status === App\Models\ExpenseRequest::STATUS_REJECTED)
                                                    <span class="badge bg-danger text-white rounded-pill px-2 py-1"><i class="fa-solid fa-ban me-1"></i>Rejected</span>
                                                    @if($dExp->rejection_reason)
                                                        <small class="text-muted d-block" style="font-size:0.72rem;">{{ Str::limit($dExp->rejection_reason, 40) }}</small>
                                                    @endif
                                                @else
                                                    {!! $dExp->status_badge !!}
                                                @endif
                                            </td>
                                            <td class="text-end pe-4 small text-muted">
                                                {{ $dExp->updated_at->format('d M Y') }}
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="p-4 text-center text-muted small">No past expense decisions found.</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Decided Materials History --}}
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                    <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                        <div class="fw-bold text-dark"><i class="fa-solid fa-boxes-packing text-primary me-2"></i>Decided Material Requests</div>
                        <span class="badge bg-secondary rounded-pill">{{ $decidedMaterials->count() }} records</span>
                    </div>
                    <div class="card-body p-0">
                        @if($decidedMaterials->isNotEmpty())
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light text-secondary small text-uppercase" style="font-size:0.75rem;">
                                        <tr>
                                            <th class="ps-4">Request #</th>
                                            <th>Items</th>
                                            <th>Decision / Status</th>
                                            <th class="text-end pe-4">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($decidedMaterials as $dMat)
                                        <tr>
                                            <td class="ps-4">
                                                <strong class="font-monospace text-dark">{{ $dMat->reference_number }}</strong>
                                                @if($dMat->maintenanceRequest)
                                                    <small class="text-muted d-block">{{ $dMat->maintenanceRequest->asset_name }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border">{{ $dMat->items->count() }} item(s)</span>
                                            </td>
                                            <td>
                                                @if($dMat->status === 'sent_to_store_manager')
                                                    <span class="badge bg-info text-white rounded-pill px-2 py-1"><i class="fa-solid fa-warehouse me-1"></i>Approved → Store Manager</span>
                                                @elseif(in_array($dMat->status, ['needs_purchase', 'sent_to_pr']))
                                                    <span class="badge bg-primary text-white rounded-pill px-2 py-1"><i class="fa-solid fa-cart-shopping me-1"></i>Approved → Procurement (PR)</span>
                                                @elseif($dMat->status === 'issued')
                                                    <span class="badge bg-success text-white rounded-pill px-2 py-1"><i class="fa-solid fa-check-circle me-1"></i>Issued from Store</span>
                                                @elseif($dMat->status === 'rejected')
                                                    <span class="badge bg-danger text-white rounded-pill px-2 py-1"><i class="fa-solid fa-ban me-1"></i>Rejected</span>
                                                @else
                                                    <span class="badge bg-secondary rounded-pill px-2 py-1">{{ ucfirst(str_replace('_', ' ', $dMat->status)) }}</span>
                                                @endif
                                            </td>
                                            <td class="text-end pe-4 small text-muted">
                                                {{ $dMat->updated_at->format('d M Y') }}
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="p-4 text-center text-muted small">No past material decisions found.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>

{{-- ═══════════════════════════════════════════════════════════════════════════════
     ALL MODALS (Rendered outside tables to prevent backdrop stacking bugs)
═══════════════════════════════════════════════════════════════════════════════ --}}

{{-- ── 1. Maintenance Ticket Approval Modals ───────────────────────────────── --}}
@php
    $allTicketModals = ($maintenanceTickets ?? collect())->concat($decidedTickets ?? collect())->unique('id');
@endphp
@foreach($allTicketModals as $mReq)

{{-- ── A. STEP 2: GM INITIAL DECISION MODAL (APPROVE VS RETURN) ─────────────── --}}
<div class="modal fade" id="gmInitialModal{{ $mReq->id }}" tabindex="-1" aria-hidden="true" data-bs-backdrop="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header py-3 px-4 text-white" style="background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%) !important;">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-warning" style="width:40px;height:40px;background:rgba(245,158,11,0.18);border:1px solid rgba(245,158,11,0.35);flex-shrink:0;">
                        <i class="fa-solid fa-gavel fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0">Step 2: Executive Review — Ticket #{{ $mReq->request_no }}</h5>
                        <small class="text-white-50">General Service asks: "Go ahead and maintain this material?"</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                {{-- Asset & Issue Summary --}}
                <div class="p-3 bg-white rounded-3 border mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="text-dark fs-6">{{ $mReq->asset_name }}</strong>
                        @if($mReq->asset_code)
                            <span class="badge bg-dark font-monospace">{{ $mReq->asset_code }}</span>
                        @endif
                    </div>
                    <div class="small text-muted mb-2">
                        Reported by: <strong>{{ $mReq->employee->full_name ?? ($mReq->reportedBy->name ?? 'Staff') }}</strong> ({{ $mReq->employee->role_title ?? $mReq->employee->department ?? 'General' }})
                    </div>
                    <div class="p-2.5 rounded bg-light border-start border-4 border-warning small text-dark" style="white-space: pre-wrap;">
                        {{ $mReq->description }}
                    </div>
                    @if($mReq->admin_notes)
                        <div class="mt-2 pt-2 border-top small text-muted" style="white-space: pre-wrap;">
                            <strong class="text-secondary"><i class="fa-solid fa-note-sticky me-1"></i>General Service Notes:</strong>
                            {{ $mReq->admin_notes }}
                        </div>
                    @endif
                </div>

                <div class="row g-3">
                    {{-- 1. Approve Path --}}
                    <div class="col-md-6">
                        <div class="card h-100 border-success shadow-xs rounded-3 bg-white p-3 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-2 text-success">
                                    <i class="fa-solid fa-circle-check fs-4"></i>
                                    <h6 class="fw-bold mb-0 text-success">Approve Permission</h6>
                                </div>
                                <p class="small text-muted mb-3">
                                    Authorize General Service to go ahead and proceed with maintenance. The request returns to GS to add technician details and assign a Petty Cash Owner for your final approval.
                                </p>
                            </div>
                            <form action="{{ route('gm.maintenance-approvals.initial-decision', $mReq) }}" method="POST">
                                @csrf
                                <input type="hidden" name="action" value="approve">
                                <div class="mb-3">
                                    <label class="form-label small text-secondary fw-semibold">GM Directives / Remarks (Optional):</label>
                                    <input type="text" name="gm_notes" class="form-control form-control-sm rounded-3" placeholder="e.g. Approved. Expedite repair within 2 days.">
                                </div>
                                <button type="submit" class="btn btn-success fw-bold w-100 rounded-pill py-2 shadow-sm">
                                    <i class="fa-solid fa-check me-1"></i>Approve — Go Ahead &amp; Maintain
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- 2. Return Path --}}
                    <div class="col-md-6">
                        <div class="card h-100 border-danger shadow-xs rounded-3 bg-white p-3 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-2 text-danger">
                                    <i class="fa-solid fa-rotate-left fs-4"></i>
                                    <h6 class="fw-bold mb-0 text-danger">Return Request</h6>
                                </div>
                                <p class="small text-muted mb-3">
                                    Send request back to General Service. GS will be required to provide <strong>Money</strong>, <strong>Time</strong>, and <strong>Material</strong> requirements before resubmitting.
                                </p>
                            </div>
                            <form action="{{ route('gm.maintenance-approvals.initial-decision', $mReq) }}" method="POST">
                                @csrf
                                <input type="hidden" name="action" value="return">
                                <div class="mb-3">
                                    <label class="form-label small text-danger fw-semibold">Return Reason / Feedback <span class="text-danger">*</span>:</label>
                                    <textarea name="return_reason" class="form-control form-control-sm border-danger rounded-3" rows="2" placeholder="Specify why this is returned and request money/time/material estimates..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-outline-danger fw-bold w-100 rounded-pill py-2">
                                    <i class="fa-solid fa-rotate-left me-1"></i>Return to General Service
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-2.5 px-4">
                <button type="button" class="btn btn-light border rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- ── B. STEP 4 & 5: GM FINAL APPROVAL MODAL (PETTY CASH & FINANCE/STORE ROUTING) --}}
<div class="modal fade" id="gmFinalModal{{ $mReq->id }}" tabindex="-1" aria-hidden="true" data-bs-backdrop="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form action="{{ route('gm.maintenance-approvals.final-approval', $mReq) }}" method="POST" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            @csrf
            <div class="modal-header py-3 px-4 text-white" style="background: linear-gradient(135deg, #10b981 0%, #064e3b 100%) !important;">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white" style="width:40px;height:40px;background:rgba(255,255,255,0.2);flex-shrink:0;">
                        <i class="fa-solid fa-file-signature fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0">Step 4 &amp; 5: Executive Approval of Petty Cash — Ticket #{{ $mReq->request_no }}</h5>
                        <small class="text-white-50">Approve Petty Cash assignment &amp; route to Finance Expenses &amp; Store PR</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                
                {{-- If from Returned flow, highlight Money, Time, and Material --}}
                @if($mReq->is_returned_flow || $mReq->money_amount || $mReq->estimated_time || $mReq->material_description)
                    <div class="card border-primary border-opacity-50 shadow-xs rounded-3 bg-white p-3 mb-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-primary text-white rounded-pill px-2.5 py-1">Returned Request Details</span>
                            <span class="small text-muted">Provided by General Service upon return</span>
                        </div>
                        <div class="row g-2 text-dark">
                            <div class="col-md-4">
                                <div class="p-2.5 rounded bg-light border">
                                    <small class="text-muted d-block text-uppercase fw-semibold" style="font-size:0.7rem;">Money Needed</small>
                                    <strong class="text-success fs-6 font-monospace">ETB {{ number_format($mReq->money_amount ?? 0, 2) }}</strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-2.5 rounded bg-light border">
                                    <small class="text-muted d-block text-uppercase fw-semibold" style="font-size:0.7rem;">Time Estimate</small>
                                    <strong class="text-dark fs-6">{{ $mReq->estimated_time ?? 'Standard' }}</strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-2.5 rounded bg-light border">
                                    <small class="text-muted d-block text-uppercase fw-semibold" style="font-size:0.7rem;">Petty Cash Custodian</small>
                                    <strong class="text-primary fs-6">{{ $mReq->pettyCashOwner->name ?? 'Unassigned' }}</strong>
                                </div>
                            </div>
                            @if($mReq->material_description)
                                <div class="col-12 mt-2">
                                    <div class="p-2.5 rounded bg-light border">
                                        <small class="text-muted d-block text-uppercase fw-semibold" style="font-size:0.7rem;">Material / Spare Parts Requirements (Will appear in Store PR)</small>
                                        <div class="small text-dark fw-semibold" style="white-space: pre-wrap;">{{ $mReq->material_description }}</div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Personnel Contact Info (Record-keeping only) --}}
                <div class="card border-0 shadow-xs rounded-3 bg-white p-3 mb-3">
                    <h6 class="fw-bold text-secondary mb-2 small text-uppercase"><i class="fa-solid fa-address-card me-1"></i>Technician Contact Info (For Record-Keeping Only)</h6>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <div class="small text-muted">Technician Name:</div>
                            <strong class="text-dark">{{ $mReq->maintenance_person_name ?? 'N/A' }}</strong>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted">Account Name:</div>
                            <strong class="text-dark">{{ $mReq->maintenance_person_account ?? 'N/A' }}</strong>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted">Phone Number:</div>
                            <strong class="text-dark">{{ $mReq->maintenance_person_phone ?? 'N/A' }}</strong>
                        </div>
                    </div>
                </div>

                {{-- Routing Confirmation Cards --}}
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="p-3 bg-success bg-opacity-10 border border-success border-opacity-25 rounded-3 h-100">
                            <div class="d-flex align-items-center gap-2 mb-1 text-success">
                                <i class="fa-solid fa-file-invoice-dollar fs-5"></i>
                                <strong class="small text-uppercase">1. Finance &rarr; Expenses</strong>
                            </div>
                            <p class="small text-muted mb-0">
                                Once approved, an Expense Request is confirmed under <strong>Maintenance</strong> for ETB {{ number_format($mReq->money_amount ?? 0, 2) }}, assigned to Petty Cash Custodian <strong>{{ $mReq->pettyCashOwner->name ?? 'Custodian' }}</strong>.
                            </p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded-3 h-100">
                            <div class="d-flex align-items-center gap-2 mb-1 text-primary">
                                <i class="fa-solid fa-boxes-stacked fs-5"></i>
                                <strong class="small text-uppercase">2. Store Manager &rarr; PR Section</strong>
                            </div>
                            <p class="small text-muted mb-0">
                                Material details are sent directly into the Store Manager's Purchase Request (PR) cycle for procurement or stock issue.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary text-uppercase">Assign to Finance Officer / Cashier (Optional):</label>
                    <select name="assigned_finance_staff_id" class="form-select rounded-3">
                        <option value="">— Finance Disbursement Queue —</option>
                        @foreach($financeStaff as $fStf)
                            <option value="{{ $fStf->id }}">{{ $fStf->name }} ({{ $fStf->roles->pluck('name')->implode(', ') ?: 'Finance' }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-0">
                    <label class="form-label small fw-bold text-secondary text-uppercase">Executive Notes / Approval Directive (Optional):</label>
                    <textarea name="gm_notes" class="form-control rounded-3" rows="2" placeholder="e.g. Petty cash assignment approved for immediate payout."></textarea>
                </div>

            </div>
            <div class="modal-footer bg-white border-top py-3 px-4 d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-light border rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success fw-bold rounded-pill px-5 shadow-sm">
                    <i class="fa-solid fa-check-double me-2"></i>Approve Petty Cash &amp; Authorize Maintenance
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="grantGmApprovalModal{{ $mReq->id }}" tabindex="-1" aria-hidden="true" data-bs-backdrop="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width: 1140px; margin: 1.5rem auto;">
        <form action="{{ route('gm.maintenance-approvals.approve-ticket', $mReq) }}" method="POST" id="gm_ticket_form_{{ $mReq->id }}" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" style="max-height: 90vh; display: flex; flex-direction: column;">
            @csrf
            
            {{-- Header (Pinned at top) --}}
            <div class="modal-header py-3 px-4 text-white flex-shrink-0" style="background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%) !important;">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-warning" style="width:40px;height:40px;background:rgba(245,158,11,0.18);border:1px solid rgba(245,158,11,0.35);flex-shrink:0;">
                        <i class="fa-solid fa-gavel fs-5"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h5 class="modal-title fw-bold text-white mb-0">Executive Decision: Ticket #{{ $mReq->request_no }}</h5>
                            <span class="badge {{ $mReq->status_badge['class'] }} rounded-pill px-2.5 py-0.5" style="font-size:0.75rem;">{{ $mReq->status_badge['label'] }}</span>
                            @if($mReq->gm_decision_locked || $mReq->gm_approved_at)
                                <span class="badge bg-secondary text-white rounded-pill px-2 py-0.5" style="font-size:0.72rem;">
                                    <i class="fa-solid fa-lock me-1 text-warning"></i>Locked in History
                                </span>
                            @endif
                        </div>
                        <small class="text-white-50">Review equipment specifications, historical maintenance records, and authorize status</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            {{-- Body (Smooth Scrollable Content) --}}
            <div class="modal-body p-3 p-md-4 bg-light" style="overflow-y: auto !important; flex: 1 1 auto; max-height: calc(90vh - 135px); -webkit-overflow-scrolling: touch;">
                
                {{-- Decision History Banner if Already Locked --}}
                @if($mReq->gm_decision_locked || $mReq->gm_approved_at)
                    <div class="alert alert-secondary border-0 shadow-xs rounded-3 p-3 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-lock text-secondary fs-4"></i>
                            <div>
                                <strong class="text-dark">Decision Finalized &amp; Locked in Decision History</strong>
                                <div class="small text-muted">
                                    Authorized on {{ $mReq->gm_approved_at ? $mReq->gm_approved_at->format('M d, Y h:i A') : $mReq->updated_at->format('M d, Y') }}
                                    @if($mReq->gmApprover) by {{ $mReq->gmApprover->name }} @endif
                                </div>
                                @if($mReq->gm_decision_summary)
                                    <div class="small text-dark fw-semibold mt-1"><i class="fa-solid fa-check-double text-success me-1"></i>{{ $mReq->gm_decision_summary }}</div>
                                @endif
                            </div>
                        </div>
                        <span class="badge bg-secondary text-white rounded-pill px-3 py-1.5"><i class="fa-solid fa-shield me-1"></i>Locked</span>
                    </div>
                @endif
                
                {{-- ── 1. MATERIAL / ASSET SPECIFICATIONS & FAILURE PROFILE ── --}}
                <div class="card border-0 shadow-xs rounded-3 bg-white p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-3 p-2 text-warning d-flex align-items-center justify-content-center" style="width:36px;height:36px;background:rgba(245,158,11,0.12);">
                                <i class="fa-solid fa-toolbox fs-5"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0 fs-6">
                                    {{ $mReq->asset_name }}
                                    @if($mReq->asset_code)
                                        <span class="badge bg-dark font-monospace ms-1">{{ $mReq->asset_code }}</span>
                                    @endif
                                </h6>
                                <small class="text-muted">Material &amp; Asset Specification Details</small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-light text-dark border text-capitalize px-2.5 py-1">
                                <i class="fa-solid fa-tag me-1 text-warning"></i>{{ $mReq->issue_type_label }}
                            </span>
                            <span class="badge {{ $mReq->urgency_badge['class'] }} px-2.5 py-1">
                                <i class="fa-solid {{ $mReq->urgency_badge['icon'] }} me-1"></i>{{ $mReq->urgency_badge['label'] }}
                            </span>
                        </div>
                    </div>

                    {{-- Detailed Asset Specifications Grid --}}
                    <div class="row g-2 mb-3" style="font-size: 0.85rem;">
                        <div class="col-sm-6 col-md-3">
                            <div class="p-2 rounded bg-light border h-100">
                                <span class="text-muted small d-block text-uppercase fw-semibold" style="font-size: 0.7rem;">Catalog Asset</span>
                                <strong class="text-dark">{{ $mReq->fixedAssetUnit?->parentAsset?->name ?? 'Direct Item' }}</strong>
                                <small class="text-muted d-block" style="font-size:0.75rem;">Category: {{ $mReq->fixedAssetUnit?->parentAsset?->category ?? 'General Asset' }}</small>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="p-2 rounded bg-light border h-100">
                                <span class="text-muted small d-block text-uppercase fw-semibold" style="font-size: 0.7rem;">Brand &amp; Model</span>
                                <strong class="text-dark">{{ $mReq->fixedAssetUnit?->brand ?? 'N/A' }} {{ $mReq->fixedAssetUnit?->model ?? '' }}</strong>
                                <small class="text-muted d-block font-monospace" style="font-size:0.75rem;">Serial: {{ $mReq->fixedAssetUnit?->serial_number ?? ($mReq->fixedAssetUnit?->unit_code ?? 'N/A') }}</small>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="p-2 rounded bg-light border h-100">
                                <span class="text-muted small d-block text-uppercase fw-semibold" style="font-size: 0.7rem;">Location &amp; Station</span>
                                <strong class="text-dark">{{ $mReq->fixedAssetUnit?->current_location ?? ($mReq->employee?->department ?? 'General Service') }}</strong>
                                @if($mReq->fixedAssetUnit?->plate_number)
                                    <small class="text-muted d-block" style="font-size:0.75rem;">Plate: {{ $mReq->fixedAssetUnit->plate_number }}</small>
                                @else
                                    <small class="text-muted d-block" style="font-size:0.75rem;">User: {{ $mReq->fixedAssetUnit?->assignedEmployee?->full_name ?? ($mReq->employee?->full_name ?? 'Staff') }}</small>
                                @endif
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="p-2 rounded bg-light border h-100">
                                <span class="text-muted small d-block text-uppercase fw-semibold" style="font-size: 0.7rem;">Purchase / Warranty</span>
                                <strong class="text-dark">{{ $mReq->fixedAssetUnit?->purchase_price ? number_format($mReq->fixedAssetUnit->purchase_price, 2) . ' ETB' : 'N/A' }}</strong>
                                <small class="text-muted d-block" style="font-size:0.75rem;">Warranty: {{ $mReq->fixedAssetUnit?->warranty_expiry ? $mReq->fixedAssetUnit->warranty_expiry->format('d M Y') : 'N/A' }}</small>
                            </div>
                        </div>
                    </div>

                    {{-- Full Problem Description and Findings --}}
                    <div class="p-3 rounded-3 bg-white border mb-2" style="border-left: 4px solid #f59e0b !important;">
                        <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-1">
                            <span class="fw-bold small text-dark text-uppercase" style="font-size:0.75rem;">
                                <i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>Reported Issue &amp; Repair Details
                            </span>
                            <small class="text-muted" style="font-size:0.75rem;">
                                Reported by <strong>{{ $mReq->employee?->full_name ?? ($mReq->reportedBy?->name ?? 'Staff') }}</strong> ({{ $mReq->employee?->role_title ?? $mReq->employee?->department ?? 'Employee' }}) on {{ $mReq->created_at->format('M d, Y h:i A') }}
                            </small>
                        </div>
                        <div class="text-dark" style="white-space: pre-wrap; font-size: 0.92rem; line-height: 1.5;">{{ $mReq->description }}</div>
                    </div>

                    @if($mReq->admin_notes)
                        <div class="p-2.5 rounded-3 bg-light border small text-muted mb-0" style="white-space: pre-wrap; font-size: 0.8rem;">
                            <strong class="text-secondary"><i class="fa-solid fa-clipboard-list me-1"></i>Diagnostic &amp; Lifecycle Notes:</strong>
                            {{ $mReq->admin_notes }}
                        </div>
                    @endif
                </div>

                {{-- ── 2. MATERIAL MAINTENANCE & REPAIR HISTORY ── --}}
                @php
                    $historyTickets = $mReq->maintenance_history;
                @endphp
                <div class="card border-0 shadow-xs rounded-3 bg-white p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-3 p-2 text-primary d-flex align-items-center justify-content-center" style="width:36px;height:36px;background:rgba(13,110,253,0.12);">
                                <i class="fa-solid fa-clock-rotate-left fs-5"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0 fs-6">Material Maintenance History</h6>
                                <small class="text-muted">Previous service logs, breakdown records &amp; repair expenses for <strong>{{ $mReq->asset_name }}</strong></small>
                            </div>
                        </div>
                        <span class="badge bg-primary bg-opacity-15 text-primary border border-primary border-opacity-25 rounded-pill px-3 py-1 fw-bold">
                            {{ $historyTickets->count() }} Past Record{{ $historyTickets->count() === 1 ? '' : 's' }}
                        </span>
                    </div>

                    @if($historyTickets->isNotEmpty())
                        <div class="table-responsive rounded-3 border">
                            <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.82rem;">
                                <thead class="table-light text-secondary text-uppercase" style="font-size: 0.72rem;">
                                    <tr>
                                        <th class="ps-3 py-2">Ticket #</th>
                                        <th class="py-2">Date</th>
                                        <th class="py-2">Issue / Problem</th>
                                        <th class="py-2">Repair Expense</th>
                                        <th class="py-2">Parts / Materials</th>
                                        <th class="py-2">Assigned Tech</th>
                                        <th class="py-2">Outcome</th>
                                        <th class="py-2 pe-3 text-end">Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($historyTickets as $hTick)
                                    @php
                                        $hExpSum = (float)$hTick->expenseRequests->sum('amount');
                                        $hMatCount = $hTick->materialRequests->sum(fn($mr) => $mr->items->count());
                                    @endphp
                                    <tr>
                                        <td class="ps-3 font-monospace fw-bold text-primary">{{ $hTick->request_no }}</td>
                                        <td class="text-muted text-nowrap">{{ $hTick->created_at->format('M d, Y') }}</td>
                                        <td>
                                            <div class="fw-semibold text-dark">{{ $hTick->issue_type_label }}</div>
                                            <small class="text-muted text-truncate d-block" style="max-width: 220px;" title="{{ $hTick->description }}">
                                                {{ $hTick->description }}
                                            </small>
                                        </td>
                                        <td class="font-monospace">
                                            @if($hExpSum > 0)
                                                <span class="badge bg-success bg-opacity-10 text-success fw-bold">
                                                    {{ number_format($hExpSum, 2) }} ETB
                                                </span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($hMatCount > 0)
                                                <span class="badge bg-info bg-opacity-10 text-dark border">
                                                    <i class="fa-solid fa-boxes-stacked me-1 text-primary"></i>{{ $hMatCount }} Part(s)
                                                </span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-muted">{{ $hTick->assignedTo?->name ?? 'Unassigned' }}</td>
                                        <td>
                                            <span class="badge {{ $hTick->status_badge['class'] }} px-2 py-0.5">
                                                {{ $hTick->status_badge['label'] }}
                                            </span>
                                        </td>
                                        <td class="pe-3 text-end">
                                            <a href="{{ route('general-service.maintenance.show', $hTick->id) }}" target="_blank" class="btn btn-outline-secondary btn-sm py-0 px-2" title="Inspect full record">
                                                <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:0.75rem;"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-3 bg-white rounded-3 text-center border">
                            <i class="fa-solid fa-circle-check text-success me-1"></i>
                            <span class="small text-muted">No prior maintenance tickets recorded for <strong>{{ $mReq->asset_name }}</strong> (Code: {{ $mReq->asset_code ?? 'N/A' }}). This is the initial reported maintenance record.</span>
                        </div>
                    @endif
                </div>

                {{-- ── 3. EXECUTIVE STATUS DECISION & UNLOCKED REJECT OPTION ── --}}
                <div class="card border-0 shadow-xs rounded-3 bg-white p-3 mb-3">
                    <label class="form-label fw-bold text-dark small text-uppercase d-flex justify-content-between align-items-center">
                        <span>Executive Status Decision <span class="text-danger">*</span></span>
                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" id="gm_rej_badge_{{ $mReq->id }}" style="display: {{ $mReq->status === 'rejected' ? 'inline-block' : 'none' }};">
                            <i class="fa-solid fa-ban me-1"></i>Rejection Mode Active
                        </span>
                    </label>
                    <select name="status" id="gm_status_select_{{ $mReq->id }}" class="form-select form-select-lg rounded-3 fw-bold" required onchange="handleGmDecisionChange_{{ $mReq->id }}(this.value);">
                        <option value="in_progress" {{ $mReq->status === 'in_progress' ? 'selected' : '' }}>🔧 Approved → In Progress / Under Repair</option>
                        <option value="sent_to_store_manager" {{ $mReq->status === 'sent_to_store_manager' ? 'selected' : '' }}>📦 Approved → Send to Store Manager (Replacement Unit / PR Cycle)</option>
                        <option value="resolved" {{ $mReq->status === 'resolved' ? 'selected' : '' }}>✅ Approved → Resolved / Fixed</option>
                        <option value="closed" {{ $mReq->status === 'closed' ? 'selected' : '' }}>🔒 Closed</option>
                        <option value="pending" {{ $mReq->status === 'pending' ? 'selected' : '' }}>⏳ Keep Pending Review</option>
                        <option value="rejected" class="text-danger fw-bold" {{ $mReq->status === 'rejected' ? 'selected' : '' }}>❌ Reject Maintenance Request</option>
                    </select>

                    {{-- UNLOCKED: Mandatory Rejection Reason Block --}}
                    <div class="mt-3 p-3 rounded-3 border border-danger bg-danger bg-opacity-10 shadow-xs" id="gm_rejection_box_{{ $mReq->id }}" style="display: {{ $mReq->status === 'rejected' ? 'block' : 'none' }};">
                        <label class="form-label fw-bold text-danger small text-uppercase mb-1">
                            <i class="fa-solid fa-ban me-1"></i>Executive Rejection Reason <span class="text-danger">*</span>
                        </label>
                        <p class="text-danger small mb-2" style="font-size:0.8rem;">
                            Please state the reason for rejecting this maintenance ticket. Linked pending expense requests and material requisitions will be cancelled automatically.
                        </p>
                        <textarea name="rejection_reason" id="gm_rejection_reason_{{ $mReq->id }}" class="form-control border-danger" rows="3" placeholder="Specify why this repair/maintenance request is rejected (e.g., equipment beyond economical repair, unapproved vendor, duplicate ticket, unauthorized service)...">{{ $mReq->rejection_reason }}</textarea>
                    </div>

                    {{-- Replacement Condition (If Sent to Store Manager) --}}
                    <div class="mt-3" id="gm_rep_opts_{{ $mReq->id }}" style="{{ $mReq->status === 'sent_to_store_manager' ? '' : 'display:none;' }}">
                        <label class="form-label fw-bold text-dark small text-uppercase">Replacement Unit Category</label>
                        <div class="d-flex gap-3">
                            <div class="form-check border p-2 rounded-3 bg-light flex-fill ps-4">
                                <input class="form-check-input" type="radio" name="replacement_condition" id="rep_cond_maint_{{ $mReq->id }}" value="in_maintenance" checked>
                                <label class="form-check-label small fw-bold text-dark" for="rep_cond_maint_{{ $mReq->id }}">
                                    Temporary Replacement Unit <small class="text-muted d-block fw-normal">Equipment under repair; temporary swap while fixed</small>
                                </label>
                            </div>
                            <div class="form-check border p-2 rounded-3 bg-light flex-fill ps-4">
                                <input class="form-check-input" type="radio" name="replacement_condition" id="rep_cond_dmg_{{ $mReq->id }}" value="unrepairable_damage">
                                <label class="form-check-label small fw-bold text-danger" for="rep_cond_dmg_{{ $mReq->id }}">
                                    Permanent Replacement <small class="text-muted d-block fw-normal">Equipment unrepairable; write-off &amp; buy new</small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── 4. WORKFLOW ROUTINGS: MONEY & MATERIALS ── --}}
                <div id="gm_routing_bypassed_note_{{ $mReq->id }}" class="alert alert-warning border-0 small py-2 mb-3" style="display: {{ $mReq->status === 'rejected' ? 'block' : 'none' }};">
                    <i class="fa-solid fa-circle-exclamation me-1"></i>Expense approval routing and Store Manager requisition generation are automatically bypassed when request is rejected.
                </div>

                <div class="row g-3 mb-3" id="gm_routing_container_{{ $mReq->id }}" style="display: {{ $mReq->status === 'rejected' ? 'none' : 'flex' }};">
                    {{-- 💰 Ask Money -> Finance Payment Routing --}}
                    <div class="col-md-6">
                        <div class="card border-0 shadow-xs rounded-3 bg-white p-3 h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="fa-solid fa-hand-holding-dollar text-success fs-5"></i>
                                <h6 class="fw-bold text-dark mb-0 small text-uppercase">Finance Payment &amp; Disbursement (Ask Money)</h6>
                            </div>
                            @if($mReq->expenseRequests->isNotEmpty())
                                <p class="text-muted small mb-2">Linked expense requests will be approved and routed directly to Finance to pay / disburse.</p>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="route_money_to_finance" value="1" id="route_money_{{ $mReq->id }}" checked>
                                    <label class="form-check-label small fw-bold text-success" for="route_money_{{ $mReq->id }}">
                                        <i class="fa-solid fa-money-bill-wave me-1"></i>Send {{ $mReq->expenseRequests->count() }} linked Expense Request(s) to Finance to Pay
                                    </label>
                                </div>
                                @foreach($mReq->expenseRequests as $exp)
                                    <div class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 mb-1">
                                        #{{ $exp->request_number }}: {{ number_format($exp->amount, 2) }} ETB ({{ ucfirst($exp->status) }})
                                    </div>
                                @endforeach

                                <div class="mt-2 pt-2 border-top">
                                    <label class="form-label text-muted small mb-1" style="font-size:0.75rem;">Assign to Finance Officer / Cashier (Optional):</label>
                                    <select name="assigned_finance_staff_id" class="form-select form-select-sm rounded-3">
                                        <option value="">— Finance Disbursement Queue —</option>
                                        @foreach($financeStaff as $fStf)
                                            <option value="{{ $fStf->id }}">{{ $fStf->name }} ({{ $fStf->roles->pluck('name')->implode(', ') ?: 'Finance' }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            @else
                                <p class="text-muted small mb-2">No money request is linked to this ticket yet.</p>
                                <a class="small text-success text-decoration-none fw-semibold" data-bs-toggle="collapse" href="#collapseNewExpense{{ $mReq->id }}" role="button">
                                    <i class="fa-solid fa-plus-circle me-1"></i>Authorize New Repair Budget for Finance to Pay
                                </a>
                                <div class="collapse mt-2" id="collapseNewExpense{{ $mReq->id }}">
                                    <div class="input-group input-group-sm mb-1">
                                        <span class="input-group-text">ETB</span>
                                        <input type="number" step="0.01" name="create_expense_amount" class="form-control" placeholder="Amount (e.g. 5000)">
                                    </div>
                                    <input type="text" name="create_expense_notes" class="form-control form-control-sm mb-1" placeholder="Repair funding purpose...">
                                    <select name="assigned_finance_staff_id" class="form-select form-select-sm rounded-3">
                                        <option value="">— Finance Disbursement Queue —</option>
                                        @foreach($financeStaff as $fStf)
                                            <option value="{{ $fStf->id }}">{{ $fStf->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- 📦 Ask Material -> Store Manager Routing & PR Cycle --}}
                    <div class="col-md-6">
                        <div class="card border-0 shadow-xs rounded-3 bg-white p-3 h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="fa-solid fa-boxes-packing text-primary fs-5"></i>
                                <h6 class="fw-bold text-dark mb-0 small text-uppercase">Store Manager &amp; PR Cycle (Ask Material)</h6>
                            </div>
                            @if($mReq->materialRequests->isNotEmpty())
                                <p class="text-muted small mb-2">Linked material requests will be sent to Store Manager for stock fulfillment or the Purchase Request (PR) cycle.</p>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="route_material_to_store" value="1" id="route_mat_{{ $mReq->id }}" checked>
                                    <label class="form-check-label small fw-bold text-primary" for="route_mat_{{ $mReq->id }}">
                                        <i class="fa-solid fa-cart-shopping me-1"></i>Send {{ $mReq->materialRequests->count() }} Material Request(s) to Store in PR Cycle
                                    </label>
                                </div>
                                @foreach($mReq->materialRequests as $mr)
                                    <div class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 mb-1">
                                        #{{ $mr->reference_number }}: {{ $mr->items->count() }} item(s) ({{ ucfirst($mr->status) }})
                                    </div>
                                @endforeach
                            @else
                                <p class="text-muted small mb-2">No material request is linked yet.</p>
                                <div class="form-text small">
                                    If you choose <strong>Send to Store Manager</strong> above, a requisition is automatically created for the Store Manager to fulfill or add into the Purchase Request (PR) cycle.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- ── 5. ASSIGN TECHNICIAN & REMARKS ── --}}
                <div class="card border-0 shadow-xs rounded-3 bg-white p-3 mb-3">
                    <label class="form-label fw-bold small text-uppercase text-secondary">Assign Technician / Lead</label>
                    <select name="assigned_to_user_id" class="form-select rounded-3">
                        <option value="">— Unassigned —</option>
                        @foreach($staff as $stf)
                            <option value="{{ $stf->id }}" {{ $mReq->assigned_to_user_id == $stf->id ? 'selected' : '' }}>
                                {{ $stf->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="card border-0 shadow-xs rounded-3 bg-white p-3">
                    <label class="form-label fw-bold small text-uppercase text-secondary">GM Directives / Remarks</label>
                    <textarea name="gm_notes" class="form-control rounded-3" rows="3" placeholder="Enter executive feedback, expected completion timeline, or maintenance instructions..."></textarea>
                </div>
            </div>

            {{-- Footer (Pinned at bottom) --}}
            <div class="modal-footer bg-white border-top py-3 px-4 rounded-bottom-4 flex-shrink-0 d-flex justify-content-between align-items-center">
                <div>
                    <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-bold" onclick="quickRejectTicket_{{ $mReq->id }}();">
                        <i class="fa-solid fa-ban me-1"></i>Quick Reject
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light border rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="gm_submit_btn_{{ $mReq->id }}" class="btn {{ $mReq->status === 'rejected' ? 'btn-danger text-white' : 'btn-warning text-dark' }} fw-bold rounded-pill px-4 shadow-sm">
                        @if($mReq->status === 'rejected')
                            <i class="fa-solid fa-ban me-2"></i>Confirm Rejection &amp; Lock
                        @elseif($mReq->gm_decision_locked || $mReq->gm_approved_at)
                            <i class="fa-solid fa-floppy-disk me-2"></i>Update Decision &amp; Keep Locked
                        @else
                            <i class="fa-solid fa-gavel me-2"></i>Apply GM Approval &amp; Lock Decision
                        @endif
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Interactive Client Scripts for Modal Actions --}}
<script>
function handleGmDecisionChange_{{ $mReq->id }}(status) {
    const repOpts = document.getElementById('gm_rep_opts_{{ $mReq->id }}');
    const rejBox = document.getElementById('gm_rejection_box_{{ $mReq->id }}');
    const rejBadge = document.getElementById('gm_rej_badge_{{ $mReq->id }}');
    const rejInput = document.getElementById('gm_rejection_reason_{{ $mReq->id }}');
    const routingContainer = document.getElementById('gm_routing_container_{{ $mReq->id }}');
    const routingBypassed = document.getElementById('gm_routing_bypassed_note_{{ $mReq->id }}');
    const submitBtn = document.getElementById('gm_submit_btn_{{ $mReq->id }}');

    if (repOpts) {
        repOpts.style.display = (status === 'sent_to_store_manager') ? 'block' : 'none';
    }

    if (status === 'rejected') {
        if (rejBox) rejBox.style.display = 'block';
        if (rejBadge) rejBadge.style.display = 'inline-block';
        if (rejInput) rejInput.required = true;
        if (routingContainer) routingContainer.style.display = 'none';
        if (routingBypassed) routingBypassed.style.display = 'block';
        if (submitBtn) {
            submitBtn.className = 'btn btn-danger text-white fw-bold rounded-pill px-4 shadow-sm';
            submitBtn.innerHTML = '<i class="fa-solid fa-ban me-2"></i>Confirm Rejection';
        }
    } else {
        if (rejBox) rejBox.style.display = 'none';
        if (rejBadge) rejBadge.style.display = 'none';
        if (rejInput) rejInput.required = false;
        if (routingContainer) routingContainer.style.display = 'flex';
        if (routingBypassed) routingBypassed.style.display = 'none';
        if (submitBtn) {
            submitBtn.className = 'btn btn-warning text-dark fw-bold rounded-pill px-4 shadow-sm';
            submitBtn.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i>Apply GM Approval';
        }
    }
}

function quickRejectTicket_{{ $mReq->id }}() {
    const select = document.getElementById('gm_status_select_{{ $mReq->id }}');
    if (select) {
        select.value = 'rejected';
        handleGmDecisionChange_{{ $mReq->id }}('rejected');
        const rejInput = document.getElementById('gm_rejection_reason_{{ $mReq->id }}');
        if (rejInput) {
            rejInput.focus();
        }
    }
}
</script>
@endforeach

{{-- ── 2. Expense Request Modals (Ask Money) ────────────────────────────────── --}}
@foreach($pendingExpenses as $exp)
{{-- MODAL A: Approve & Send to Coordinator --}}
<div class="modal fade" id="approveCoordinatorExpenseModal{{ $exp->id }}" tabindex="-1" aria-hidden="true" data-bs-backdrop="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header bg-info text-white py-3 px-4 rounded-top-4" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%) !important;">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-paper-plane me-2"></i>Approve &amp; Send to Coordinator
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('gm.maintenance-approvals.approve-expense', $exp) }}" method="POST">
                @csrf
                <input type="hidden" name="action" value="approve_coordinator">
                <div class="modal-body p-4 bg-light">
                    <div class="alert alert-info bg-info bg-opacity-10 border-info border-opacity-25 rounded-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold text-info-emphasis">Pass to Coordinator Expenses Approval</div>
                                <div class="small text-muted">Request #{{ $exp->request_number }} for <strong>{{ $exp->maintenanceRequest->asset_name ?? 'Maintenance' }}</strong></div>
                            </div>
                            <div class="fw-bold fs-4 text-info">{{ number_format($exp->amount, 2) }} ETB</div>
                        </div>
                    </div>
                    <p class="text-secondary small">
                        This action approves the maintenance budget request and forwards it directly to the <strong>Coordinator Expenses Approval</strong> section (Pending HR/Coordinator Review) for operational execution and authorization.
                    </p>
                    <div class="mb-2">
                        <label class="form-label fw-semibold small text-uppercase text-secondary">GM Directives for Coordinator (Optional)</label>
                        <textarea name="gm_notes" class="form-control rounded-3" rows="3" placeholder="Add directives for the Coordinator (e.g., 'Approved. Coordinate with technician and ensure receipts are collected.')..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-white border-0 py-3 px-4 rounded-bottom-4">
                    <button type="button" class="btn btn-light border rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white fw-bold rounded-pill px-4 shadow-sm">
                        <i class="fa-solid fa-paper-plane me-2"></i>Send to Coordinator
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL B: Approve & Pass to Finance --}}
<div class="modal fade" id="approveExpenseModal{{ $exp->id }}" tabindex="-1" aria-hidden="true" data-bs-backdrop="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header bg-success text-white py-3 px-4 rounded-top-4" style="background: linear-gradient(135deg, #059669 0%, #047857 100%) !important;">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-check-circle me-2"></i>GM Approval → Pass to Finance Section
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('gm.maintenance-approvals.approve-expense', $exp) }}" method="POST">
                @csrf
                <input type="hidden" name="action" value="approve_finance">
                <div class="modal-body p-4 bg-light">
                    <div class="alert alert-success bg-success bg-opacity-10 border-success border-opacity-25 rounded-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold text-success-emphasis">Sign off &amp; Authorize Cash Disbursement</div>
                                <div class="small text-muted">Request #{{ $exp->request_number }} for <strong>{{ $exp->maintenanceRequest->asset_name ?? 'Maintenance' }}</strong></div>
                            </div>
                            <div class="fw-bold fs-4 text-success">{{ number_format($exp->amount, 2) }} ETB</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-uppercase text-secondary">Assign Finance Officer / Staff (Optional)</label>
                        <select name="assigned_finance_staff_id" class="form-select rounded-3">
                            <option value="">— Route to Finance Head Queue (Default) —</option>
                            @foreach($financeStaff as $fs)
                                <option value="{{ $fs->id }}">{{ $fs->name }} ({{ $fs->roles->pluck('name')->implode(', ') }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold small text-uppercase text-secondary">Executive Directives / GM Approval Notes</label>
                        <textarea name="gm_notes" class="form-control rounded-3" rows="3" placeholder="Add directives for Finance (e.g., 'Approved for emergency repair. Ensure tax invoice is collected.')"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-white border-0 py-3 px-4 rounded-bottom-4">
                    <button type="button" class="btn btn-light border rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold rounded-pill px-4 shadow-sm">
                        <i class="fa-solid fa-check me-2"></i>Sign &amp; Pass to Finance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL C: Fulfill via Store Instead of Cash --}}
<div class="modal fade" id="storeExpenseModal{{ $exp->id }}" tabindex="-1" aria-hidden="true" data-bs-backdrop="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header bg-primary text-white py-3 px-4 rounded-top-4" style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-warehouse me-2"></i>GM Directive → Fulfill via Store Manager
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('gm.maintenance-approvals.approve-expense', $exp) }}" method="POST">
                @csrf
                <input type="hidden" name="action" value="send_to_store">
                <div class="modal-body p-4 bg-light">
                    <div class="alert alert-primary bg-primary bg-opacity-10 border-primary border-opacity-25 rounded-3 mb-3">
                        <div class="small text-primary-emphasis">
                            <i class="fa-solid fa-circle-info me-1"></i>Instead of cash payment, you can instruct Store Manager to fulfill needed spare parts / materials directly from company inventory.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-uppercase text-secondary">Target Store / Warehouse</label>
                        <select name="destination_store_id" class="form-select rounded-3">
                            @foreach($stores as $st)
                                <option value="{{ $st->id }}">{{ $st->name }} ({{ $st->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold small text-uppercase text-secondary">GM Directives for Store Manager</label>
                        <textarea name="gm_notes" class="form-control rounded-3" rows="3" placeholder="Instruct store manager (e.g., 'Fulfill replacement spare parts from Central Workshop stock rather than cash purchase.')"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-white border-0 py-3 px-4 rounded-bottom-4">
                    <button type="button" class="btn btn-light border rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm">
                        <i class="fa-solid fa-paper-plane me-2"></i>Pass to Store Manager
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL D: Reject Expense Request --}}
<div class="modal fade" id="rejectExpenseModal{{ $exp->id }}" tabindex="-1" aria-hidden="true" data-bs-backdrop="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header bg-danger text-white py-3 px-4 rounded-top-4" style="background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%) !important;">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>Reject Expense Request #{{ $exp->request_number }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('gm.maintenance-approvals.approve-expense', $exp) }}" method="POST">
                @csrf
                <input type="hidden" name="action" value="reject">
                <div class="modal-body p-4 bg-light">
                    <p class="text-secondary small mb-3">Please provide a clear reason for rejecting this maintenance funding request. The requester and General Service will be notified.</p>
                    <div class="mb-2">
                        <label class="form-label fw-semibold small text-uppercase text-secondary">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control rounded-3" rows="3" required placeholder="Specify why this funding request was rejected..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-white border-0 py-3 px-4 rounded-bottom-4">
                    <button type="button" class="btn btn-light border rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-bold rounded-pill px-4 shadow-sm">
                        <i class="fa-solid fa-ban me-2"></i>Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

{{-- ── 3. Material Request Modals (Ask Material) ────────────────────────────── --}}
@foreach($pendingMaterials as $mr)
{{-- MODAL A: Approve & Send to Store Manager --}}
<div class="modal fade" id="approveMaterialModal{{ $mr->id }}" tabindex="-1" aria-hidden="true" data-bs-backdrop="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header bg-primary text-white py-3 px-4 rounded-top-4" style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-warehouse me-2"></i>GM Approval → Pass to Store Manager
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('gm.maintenance-approvals.approve-material', $mr) }}" method="POST">
                @csrf
                <input type="hidden" name="action" value="send_to_store">
                <div class="modal-body p-4 bg-light">
                    <div class="alert alert-primary bg-primary bg-opacity-10 border-primary border-opacity-25 rounded-3 mb-3">
                        <div class="fw-bold text-primary-emphasis">Approve and send to Store Manager for fulfillment</div>
                        <div class="small text-muted">Request #{{ $mr->reference_number }} with <strong>{{ $mr->items->count() }} item(s)</strong> for {{ $mr->maintenanceRequest->asset_name ?? 'Maintenance' }}.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-uppercase text-secondary">Destination Store</label>
                        <select name="destination_store_id" class="form-select rounded-3">
                            @foreach($stores as $st)
                                <option value="{{ $st->id }}" {{ $mr->destination_store_id == $st->id ? 'selected' : '' }}>
                                    {{ $st->name }} ({{ $st->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold small text-uppercase text-secondary">GM Directives for Store Manager (Optional)</label>
                        <textarea name="gm_notes" class="form-control rounded-3" rows="3" placeholder="Add directives (e.g., 'Approved. Issue available parts from stock; if unavailable, initiate immediate PR.')"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-white border-0 py-3 px-4 rounded-bottom-4">
                    <button type="button" class="btn btn-light border rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm">
                        <i class="fa-solid fa-check me-2"></i>Sign &amp; Pass to Store Manager
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL B: Approve & Direct to PR Cycle --}}
<div class="modal fade" id="approveMaterialPrModal{{ $mr->id }}" tabindex="-1" aria-hidden="true" data-bs-backdrop="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header bg-info text-dark py-3 px-4 rounded-top-4" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%) !important;">
                <h5 class="modal-title fw-bold text-white">
                    <i class="fa-solid fa-cart-shopping me-2 text-white"></i>GM Approval → Add to PR Cycle
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('gm.maintenance-approvals.approve-material', $mr) }}" method="POST">
                @csrf
                <input type="hidden" name="action" value="send_to_pr">
                <div class="modal-body p-4 bg-light">
                    <div class="alert alert-info bg-info bg-opacity-10 border-info border-opacity-25 rounded-3 mb-3">
                        <div class="fw-bold text-info-emphasis">Direct to Procurement Team (PR Cycle)</div>
                        <div class="small text-muted">Bypasses store stock check and directly flags this request as <strong>Needs Purchase</strong> for the Procurement Team to raise a Purchase Request (PR).</div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold small text-uppercase text-secondary">GM Directives for Procurement Team</label>
                        <textarea name="gm_notes" class="form-control rounded-3" rows="3" placeholder="Add purchasing directives (e.g., 'Approved for emergency purchase from authorized supplier.')..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-white border-0 py-3 px-4 rounded-bottom-4">
                    <button type="button" class="btn btn-light border rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white fw-bold rounded-pill px-4 shadow-sm">
                        <i class="fa-solid fa-paper-plane me-2"></i>Pass to PR Cycle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL C: Reject Material Request --}}
<div class="modal fade" id="rejectMaterialModal{{ $mr->id }}" tabindex="-1" aria-hidden="true" data-bs-backdrop="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header bg-danger text-white py-3 px-4 rounded-top-4" style="background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%) !important;">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>Reject Material Request #{{ $mr->reference_number }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('gm.maintenance-approvals.approve-material', $mr) }}" method="POST">
                @csrf
                <input type="hidden" name="action" value="reject">
                <div class="modal-body p-4 bg-light">
                    <p class="text-secondary small mb-3">Please provide a clear reason for rejecting this maintenance spare parts / materials request.</p>
                    <div class="mb-2">
                        <label class="form-label fw-semibold small text-uppercase text-secondary">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control rounded-3" rows="3" required placeholder="Specify why this material request was rejected..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-white border-0 py-3 px-4 rounded-bottom-4">
                    <button type="button" class="btn btn-light border rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-bold rounded-pill px-4 shadow-sm">
                        <i class="fa-solid fa-ban me-2"></i>Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

@endsection
