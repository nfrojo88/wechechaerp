@extends('layouts.app')
@section('title', 'GM Maintenance & Operations Approvals')

@section('content')
<style>
/* Fix Bootstrap modal stacking and backdrop darkening bug */
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
                        {{ $maintenanceTickets->count() }} Total Tickets
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
                                                <button type="button" class="btn btn-warning btn-sm text-dark fw-bold rounded-pill px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#grantGmApprovalModal{{ $mReq->id }}">
                                                    <i class="fa-solid fa-gavel me-1"></i>Grant Approval / Action
                                                </button>
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
                            <h6 class="fw-bold text-dark mb-1">No Maintenance Reports Found</h6>
                            <p class="text-muted small mb-0">No active maintenance issues or repair tickets submitted.</p>
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
@foreach($maintenanceTickets as $mReq)
<div class="modal fade" id="grantGmApprovalModal{{ $mReq->id }}" tabindex="-1" aria-hidden="true" data-bs-backdrop="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header py-3 px-4 bg-dark text-white rounded-top-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%) !important;">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle p-2 bg-warning bg-opacity-20 d-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                        <i class="fa-solid fa-gavel text-warning fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0">Executive Decision: Ticket #{{ $mReq->request_no }}</h5>
                        <small class="text-warning-emphasis">Review, authorize status, assign personnel, and route funds/materials</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('gm.maintenance-approvals.approve-ticket', $mReq) }}" method="POST">
                @csrf
                <div class="modal-body p-4 bg-light">
                    {{-- Asset & Ticket Overview Card --}}
                    <div class="card border-0 shadow-xs rounded-3 bg-white p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <h6 class="fw-bold text-dark mb-1">
                                    <i class="fa-solid fa-toolbox text-warning me-2"></i>{{ $mReq->asset_name }}
                                    @if($mReq->asset_code)
                                        <span class="badge bg-dark font-monospace ms-1">{{ $mReq->asset_code }}</span>
                                    @endif
                                </h6>
                                <p class="text-muted small mb-0">{{ $mReq->description }}</p>
                            </div>
                            <div class="d-flex gap-2">
                                <span class="badge bg-light text-dark border text-capitalize"><i class="fa-solid fa-tag me-1 text-warning"></i>{{ str_replace('_', ' ', $mReq->issue_type) }}</span>
                                <span class="badge bg-warning bg-opacity-20 text-dark"><i class="fa-solid fa-clock me-1"></i>{{ ucfirst($mReq->status) }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Decision Dropdown --}}
                    <div class="card border-0 shadow-xs rounded-3 bg-white p-3 mb-3">
                        <label class="form-label fw-bold text-dark small text-uppercase">
                            Executive Status Decision <span class="text-danger">*</span>
                        </label>
                        <select name="status" class="form-select form-select-lg rounded-3 fw-bold" required onchange="document.getElementById('gm_rep_opts_{{ $mReq->id }}').style.display = (this.value === 'sent_to_store_manager') ? 'block' : 'none';">
                            <option value="in_progress" {{ $mReq->status === 'in_progress' ? 'selected' : '' }}>🔧 Approved → In Progress / Under Repair</option>
                            <option value="sent_to_store_manager" {{ $mReq->status === 'sent_to_store_manager' ? 'selected' : '' }}>📦 Approved → Send to Store Manager (Replacement Unit / PR Cycle)</option>
                            <option value="resolved" {{ $mReq->status === 'resolved' ? 'selected' : '' }}>✅ Approved → Resolved / Fixed</option>
                            <option value="closed" {{ $mReq->status === 'closed' ? 'selected' : '' }}>🔒 Closed</option>
                            <option value="pending" {{ $mReq->status === 'pending' ? 'selected' : '' }}>⏳ Keep Pending Review</option>
                        </select>

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

                    {{-- Workflow Automations: Money to Coordinator & Material to Store Manager --}}
                    <div class="row g-3 mb-3">
                        {{-- 💰 Ask Money -> Coordinator Routing --}}
                        <div class="col-md-6">
                            <div class="card border-0 shadow-xs rounded-3 bg-white p-3 h-100">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <i class="fa-solid fa-hand-holding-dollar text-success fs-5"></i>
                                    <h6 class="fw-bold text-dark mb-0 small text-uppercase">Coordinator Expenses Approval</h6>
                                </div>
                                @if($mReq->expenseRequests->isNotEmpty())
                                    <p class="text-muted small mb-2">Linked expense requests will be sent to the Coordinator &amp; HR approval queue.</p>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="route_money_to_coordinator" value="1" id="route_money_{{ $mReq->id }}" checked>
                                        <label class="form-check-label small fw-bold text-success" for="route_money_{{ $mReq->id }}">
                                            Send {{ $mReq->expenseRequests->count() }} linked Expense Request(s) to Coordinator
                                        </label>
                                    </div>
                                    @foreach($mReq->expenseRequests as $exp)
                                        <div class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 mb-1">
                                            #{{ $exp->request_number }}: {{ number_format($exp->amount, 2) }} ETB ({{ ucfirst($exp->status) }})
                                        </div>
                                    @endforeach
                                @else
                                    <p class="text-muted small mb-2">No money request is linked to this ticket yet.</p>
                                    <a class="small text-primary text-decoration-none fw-semibold" data-bs-toggle="collapse" href="#collapseNewExpense{{ $mReq->id }}" role="button">
                                        <i class="fa-solid fa-plus-circle me-1"></i>Authorize New Repair Budget for Coordinator
                                    </a>
                                    <div class="collapse mt-2" id="collapseNewExpense{{ $mReq->id }}">
                                        <div class="input-group input-group-sm mb-1">
                                            <span class="input-group-text">ETB</span>
                                            <input type="number" step="0.01" name="create_expense_amount" class="form-control" placeholder="Amount (e.g. 5000)">
                                        </div>
                                        <input type="text" name="create_expense_notes" class="form-control form-control-sm" placeholder="Repair funding purpose...">
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- 📦 Ask Material -> Store Manager Routing & PR Cycle --}}
                        <div class="col-md-6">
                            <div class="card border-0 shadow-xs rounded-3 bg-white p-3 h-100">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <i class="fa-solid fa-boxes-packing text-primary fs-5"></i>
                                    <h6 class="fw-bold text-dark mb-0 small text-uppercase">Store Manager &amp; PR Cycle</h6>
                                </div>
                                @if($mReq->materialRequests->isNotEmpty())
                                    <p class="text-muted small mb-2">Linked material requests will be sent to Store Manager for inventory fulfillment or PR cycle.</p>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="route_material_to_store" value="1" id="route_mat_{{ $mReq->id }}" checked>
                                        <label class="form-check-label small fw-bold text-primary" for="route_mat_{{ $mReq->id }}">
                                            Send {{ $mReq->materialRequests->count() }} Material Request(s) to Store Manager (PR Cycle)
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

                    {{-- Assign Technician --}}
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

                    {{-- GM Directives --}}
                    <div class="card border-0 shadow-xs rounded-3 bg-white p-3">
                        <label class="form-label fw-bold small text-uppercase text-secondary">GM Directives / Remarks</label>
                        <textarea name="gm_notes" class="form-control rounded-3" rows="3" placeholder="Enter executive feedback, expected completion timeline, or maintenance instructions..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-white border-0 py-3 px-4 rounded-bottom-4">
                    <button type="button" class="btn btn-light border rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark fw-bold rounded-pill px-4 shadow-sm">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Apply GM Approval
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
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
