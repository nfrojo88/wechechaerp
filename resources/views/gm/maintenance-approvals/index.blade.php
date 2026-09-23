@extends('layouts.app')
@section('title', 'GM Maintenance & Operations Approvals')

@section('content')
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
                Executive sign-off for General Service repair budgets (Ask Money) and store spare parts (Ask Material). Approved requests are routed immediately to Finance or Store.
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
        {{-- Total Pending --}}
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #f59e0b !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-uppercase text-secondary fw-bold small" style="font-size: 0.72rem; letter-spacing: 0.5px;">Pending Decisions</span>
                            <h3 class="fw-bold mb-0 text-dark mt-1">{{ $totalPendingCount }}</h3>
                            <small class="text-muted">{{ $pendingExpenses->count() }} Money · {{ $pendingMaterials->count() }} Material</small>
                        </div>
                        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background: rgba(245, 158, 11, 0.12); width: 50px; height: 50px;">
                            <i class="fa-solid fa-hourglass-half text-warning fs-4"></i>
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
                            <span class="text-uppercase text-secondary fw-bold small" style="font-size: 0.72rem; letter-spacing: 0.5px;">Decided / Passed to Section</span>
                            <h3 class="fw-bold mb-0 text-indigo mt-1">{{ $totalDecidedCount }}</h3>
                            <small class="text-muted">Passed to Finance or Store</small>
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
         TAB CONTENT: 1. PENDING (BOTH OR FILTERED)
    ═══════════════════════════════════════════════════════════════════════════════ --}}
    @if(in_array($tab, ['pending', 'expenses', 'materials']))

        {{-- SECTION A: PENDING EXPENSES ("ASK MONEY") --}}
        @if($tab === 'pending' || $tab === 'expenses')
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 rounded-3 bg-success bg-opacity-10 text-success">
                            <i class="fa-solid fa-hand-holding-dollar fs-5"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold text-dark">Maintenance Funding Requests (Ask Money)</h5>
                            <small class="text-muted">Budget requested for repair technicians, external labor, emergency parts, or local purchases.</small>
                        </div>
                    </div>
                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 fw-bold">
                        {{ $pendingExpenses->count() }} Pending GM Sign-off
                    </span>
                </div>

                <div class="card-body p-0">
                    @if($pendingExpenses->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-secondary small text-uppercase" style="font-size:0.75rem;">
                                    <tr>
                                        <th class="ps-4">Ticket / Request #</th>
                                        <th>Asset &amp; Maintenance Context</th>
                                        <th>Requester</th>
                                        <th>Amount Requested</th>
                                        <th>Attachment / Quote</th>
                                        <th>Date</th>
                                        <th class="text-end pe-4">GM Decision Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pendingExpenses as $exp)
                                    <tr>
                                        {{-- Request No --}}
                                        <td class="ps-4">
                                            <strong class="font-monospace text-primary fs-6">{{ $exp->request_number }}</strong>
                                            @if($exp->maintenanceRequest)
                                                <div class="mt-1">
                                                    <a href="{{ route('general-service.maintenance.show', $exp->maintenanceRequest) }}" target="_blank" class="badge bg-primary bg-opacity-10 text-primary text-decoration-none border shadow-xs" title="Open Maintenance Ticket">
                                                        <i class="fa-solid fa-wrench me-1"></i>{{ $exp->maintenanceRequest->request_no }}
                                                    </a>
                                                </div>
                                            @endif
                                        </td>

                                        {{-- Asset & Details --}}
                                        <td>
                                            @if($exp->maintenanceRequest)
                                                <div class="fw-bold text-dark">{{ $exp->maintenanceRequest->asset_name }}</div>
                                                <small class="text-muted d-block">
                                                    Code: <span class="font-monospace text-secondary">{{ $exp->maintenanceRequest->asset_code ?: '—' }}</span> ·
                                                    Urgency: <span class="badge bg-{{ $exp->maintenanceRequest->urgency === 'urgent' ? 'danger' : ($exp->maintenanceRequest->urgency === 'high' ? 'warning text-dark' : 'secondary') }} rounded-pill" style="font-size:0.68rem;">{{ ucfirst($exp->maintenanceRequest->urgency) }}</span>
                                                </small>
                                            @endif
                                            <div class="text-dark small mt-1" style="max-width: 280px;">
                                                <i class="fa-solid fa-quote-left text-muted me-1 small"></i>{{ $exp->description }}
                                            </div>
                                        </td>

                                        {{-- Requester --}}
                                        <td>
                                            <div class="fw-semibold text-dark">{{ $exp->user->name ?? 'General Service Staff' }}</div>
                                            <small class="text-muted">{{ $exp->user->email ?? '' }}</small>
                                        </td>

                                        {{-- Amount --}}
                                        <td>
                                            <div class="fw-bold text-success fs-5">{{ number_format($exp->amount, 2) }}</div>
                                            <small class="text-muted fw-semibold">ETB</small>
                                        </td>

                                        {{-- Attachment --}}
                                        <td>
                                            @if($exp->attachment)
                                                <a href="{{ $exp->attachment_url }}" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                                    <i class="fa-solid fa-paperclip me-1 text-primary"></i>View Receipt/Quote
                                                </a>
                                            @else
                                                <span class="text-muted small"><i class="fa-solid fa-file-excel me-1"></i>No Quote Attached</span>
                                            @endif
                                        </td>

                                        {{-- Date --}}
                                        <td>
                                            <span class="small text-muted">{{ $exp->created_at->format('d M Y') }}</span>
                                            <small class="text-muted d-block" style="font-size: 0.72rem;">{{ $exp->created_at->diffForHumans() }}</small>
                                        </td>

                                        {{-- Actions --}}
                                        <td class="text-end pe-4">
                                            <div class="d-flex justify-content-end gap-2 flex-wrap">
                                                {{-- Button 1: Approve to Finance --}}
                                                <button type="button" class="btn btn-sm btn-success fw-bold rounded-pill px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#approveExpenseModal{{ $exp->id }}">
                                                    <i class="fa-solid fa-check me-1"></i>Approve → Finance
                                                </button>

                                                {{-- Button 2: Fulfill via Store --}}
                                                <button type="button" class="btn btn-sm btn-outline-primary fw-semibold rounded-pill px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#storeExpenseModal{{ $exp->id }}" title="Fulfill with store materials instead of paying cash">
                                                    <i class="fa-solid fa-warehouse me-1"></i>Fulfill via Store
                                                </button>

                                                {{-- Button 3: Reject --}}
                                                <button type="button" class="btn btn-sm btn-outline-danger fw-semibold rounded-pill px-2 shadow-xs" data-bs-toggle="modal" data-bs-target="#rejectExpenseModal{{ $exp->id }}" title="Reject Request">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    {{-- MODAL 1: Approve Expense to Finance --}}
                                    <div class="modal fade" id="approveExpenseModal{{ $exp->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content rounded-4 border-0 shadow">
                                                <div class="modal-header bg-success text-white py-3 px-4 rounded-top-4">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="fa-solid fa-check-circle me-2"></i>GM Approval → Pass to Finance Section
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form action="{{ route('gm.maintenance-approvals.approve-expense', $exp) }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="action" value="approve_finance">
                                                    <div class="modal-body p-4">
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
                                                            <div class="form-text">Choose a specific finance officer for direct payment processing, or route to Finance Head.</div>
                                                        </div>

                                                        <div class="mb-2">
                                                            <label class="form-label fw-semibold small text-uppercase text-secondary">Executive Directives / GM Approval Notes</label>
                                                            <textarea name="gm_notes" class="form-control rounded-3" rows="3" placeholder="Add directives for Finance (e.g., 'Approved for emergency engine repair. Ensure tax invoice is collected before payment.')"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light border-0 py-3 px-4 rounded-bottom-4">
                                                        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-success fw-bold rounded-pill px-4 shadow-sm">
                                                            <i class="fa-solid fa-check me-2"></i>Sign &amp; Pass to Finance
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- MODAL 2: Fulfill via Store Instead of Cash --}}
                                    <div class="modal fade" id="storeExpenseModal{{ $exp->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content rounded-4 border-0 shadow">
                                                <div class="modal-header bg-primary text-white py-3 px-4 rounded-top-4">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="fa-solid fa-warehouse me-2"></i>GM Directive → Fulfill via Store Manager
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form action="{{ route('gm.maintenance-approvals.approve-expense', $exp) }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="action" value="send_to_store">
                                                    <div class="modal-body p-4">
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
                                                    <div class="modal-footer bg-light border-0 py-3 px-4 rounded-bottom-4">
                                                        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm">
                                                            <i class="fa-solid fa-paper-plane me-2"></i>Pass to Store Manager
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- MODAL 3: Reject Expense Request --}}
                                    <div class="modal fade" id="rejectExpenseModal{{ $exp->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content rounded-4 border-0 shadow">
                                                <div class="modal-header bg-danger text-white py-3 px-4 rounded-top-4">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="fa-solid fa-triangle-exclamation me-2"></i>Reject Expense Request #{{ $exp->request_number }}
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form action="{{ route('gm.maintenance-approvals.approve-expense', $exp) }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="action" value="reject">
                                                    <div class="modal-body p-4">
                                                        <p class="text-secondary small mb-3">Please provide a clear reason for rejecting this maintenance funding request. The requester and General Service will be notified.</p>
                                                        <div class="mb-2">
                                                            <label class="form-label fw-semibold small text-uppercase text-secondary">Rejection Reason <span class="text-danger">*</span></label>
                                                            <textarea name="rejection_reason" class="form-control rounded-3" rows="3" required placeholder="Specify why this funding request was rejected..."></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light border-0 py-3 px-4 rounded-bottom-4">
                                                        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger fw-bold rounded-pill px-4 shadow-sm">
                                                            <i class="fa-solid fa-ban me-2"></i>Confirm Rejection
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
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
                <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 rounded-3 bg-primary bg-opacity-10 text-primary">
                            <i class="fa-solid fa-boxes-stacked fs-5"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold text-dark">Maintenance Material &amp; Spare Parts Requests (Ask Material)</h5>
                            <small class="text-muted">Spare parts and consumables requested from the store or procurement for equipment repairs.</small>
                        </div>
                    </div>
                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 fw-bold">
                        {{ $pendingMaterials->count() }} Pending GM Decision
                    </span>
                </div>

                <div class="card-body p-0">
                    @if($pendingMaterials->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-secondary small text-uppercase" style="font-size:0.75rem;">
                                    <tr>
                                        <th class="ps-4">Request # / Ticket</th>
                                        <th>Asset &amp; Maintenance Context</th>
                                        <th>Destination Store</th>
                                        <th>Requested Material Items</th>
                                        <th>Required Date</th>
                                        <th class="text-end pe-4">GM Decision Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pendingMaterials as $mr)
                                    <tr>
                                        {{-- Ref No --}}
                                        <td class="ps-4">
                                            <strong class="font-monospace text-primary fs-6">{{ $mr->reference_number }}</strong>
                                            @if($mr->maintenanceRequest)
                                                <div class="mt-1">
                                                    <a href="{{ route('general-service.maintenance.show', $mr->maintenanceRequest) }}" target="_blank" class="badge bg-primary bg-opacity-10 text-primary text-decoration-none border shadow-xs" title="Open Maintenance Ticket">
                                                        <i class="fa-solid fa-wrench me-1"></i>{{ $mr->maintenanceRequest->request_no }}
                                                    </a>
                                                </div>
                                            @endif
                                            <small class="text-muted d-block mt-1" style="font-size:0.72rem;">{{ $mr->created_at->format('d M Y, H:i') }}</small>
                                        </td>

                                        {{-- Asset & Context --}}
                                        <td>
                                            @if($mr->maintenanceRequest)
                                                <div class="fw-bold text-dark">{{ $mr->maintenanceRequest->asset_name }}</div>
                                                <small class="text-muted d-block">
                                                    Condition: <span class="badge bg-secondary rounded-pill" style="font-size:0.68rem;">{{ ucfirst(str_replace('_', ' ', $mr->maintenanceRequest->replacement_condition ?? 'in_maintenance')) }}</span>
                                                </small>
                                            @endif
                                            @if($mr->notes)
                                                <div class="text-dark small mt-1" style="max-width: 250px;">
                                                    <i class="fa-solid fa-quote-left text-muted me-1 small"></i>{{ Str::limit($mr->notes, 80) }}
                                                </div>
                                            @endif
                                        </td>

                                        {{-- Store --}}
                                        <td>
                                            <div class="fw-semibold text-dark">{{ $mr->store->name ?? 'General Store' }}</div>
                                            <small class="text-muted">{{ $mr->store->code ?? '' }}</small>
                                        </td>

                                        {{-- Requested Items --}}
                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                @foreach($mr->items as $item)
                                                    <div class="small">
                                                        <span class="fw-semibold text-dark">{{ $item->product->name ?? 'Item' }}</span>:
                                                        <span class="badge bg-secondary bg-opacity-10 text-dark fw-bold font-monospace">{{ (float)$item->quantity_requested }} {{ $item->product->unit ?? 'pcs' }}</span>
                                                        @if($item->notes)
                                                            <span class="text-muted" style="font-size:0.75rem;">({{ $item->notes }})</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>

                                        {{-- Required Date --}}
                                        <td>
                                            <span class="small text-muted">{{ optional($mr->required_date)->format('d M Y') ?? '—' }}</span>
                                            @if($mr->required_date && $mr->required_date->isPast())
                                                <small class="text-danger d-block fw-semibold" style="font-size:0.72rem;"><i class="fa-solid fa-clock me-1"></i>Overdue</small>
                                            @endif
                                        </td>

                                        {{-- Actions --}}
                                        <td class="text-end pe-4">
                                            <div class="d-flex justify-content-end gap-2 flex-wrap">
                                                {{-- Button 1: Approve to Store Manager --}}
                                                <button type="button" class="btn btn-sm btn-primary fw-bold rounded-pill px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#approveMaterialStoreModal{{ $mr->id }}">
                                                    <i class="fa-solid fa-check me-1"></i>Approve → Store Manager
                                                </button>

                                                {{-- Button 2: Direct to Procurement PR --}}
                                                <button type="button" class="btn btn-sm btn-outline-info fw-semibold rounded-pill px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#approveMaterialPrModal{{ $mr->id }}" title="Route directly to Procurement for Purchase Request">
                                                    <i class="fa-solid fa-cart-shopping me-1"></i>Direct → Procurement (PR)
                                                </button>

                                                {{-- Button 3: Reject --}}
                                                <button type="button" class="btn btn-sm btn-outline-danger fw-semibold rounded-pill px-2 shadow-xs" data-bs-toggle="modal" data-bs-target="#rejectMaterialModal{{ $mr->id }}" title="Reject Request">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    {{-- MODAL 1: Approve Material to Store Manager --}}
                                    <div class="modal fade" id="approveMaterialStoreModal{{ $mr->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content rounded-4 border-0 shadow">
                                                <div class="modal-header bg-primary text-white py-3 px-4 rounded-top-4">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="fa-solid fa-warehouse me-2"></i>GM Approval → Pass to Store Manager
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form action="{{ route('gm.maintenance-approvals.approve-material', $mr) }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="action" value="send_to_store">
                                                    <div class="modal-body p-4">
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
                                                    <div class="modal-footer bg-light border-0 py-3 px-4 rounded-bottom-4">
                                                        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm">
                                                            <i class="fa-solid fa-check me-2"></i>Sign &amp; Pass to Store Manager
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- MODAL 2: Approve Material Directly to Procurement (PR) --}}
                                    <div class="modal fade" id="approveMaterialPrModal{{ $mr->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content rounded-4 border-0 shadow">
                                                <div class="modal-header bg-info text-dark py-3 px-4 rounded-top-4">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="fa-solid fa-cart-shopping me-2"></i>GM Approval → Pass to Procurement Section (PR)
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form action="{{ route('gm.maintenance-approvals.approve-material', $mr) }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="action" value="send_to_pr">
                                                    <div class="modal-body p-4">
                                                        <div class="alert alert-info bg-info bg-opacity-10 border-info border-opacity-25 rounded-3 mb-3">
                                                            <div class="fw-bold text-info-emphasis">Direct to Purchasing Team</div>
                                                            <div class="small text-muted">Bypasses store stock check and directly flags this request as <strong>Needs Purchase</strong> for the Procurement Team to raise a Purchase Request (PR).</div>
                                                        </div>

                                                        <div class="mb-2">
                                                            <label class="form-label fw-semibold small text-uppercase text-secondary">GM Directives for Procurement Team</label>
                                                            <textarea name="gm_notes" class="form-control rounded-3" rows="3" placeholder="Add purchasing directives (e.g., 'Approved for emergency commercial purchase from authorized dealer.')"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light border-0 py-3 px-4 rounded-bottom-4">
                                                        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-info text-dark fw-bold rounded-pill px-4 shadow-sm">
                                                            <i class="fa-solid fa-paper-plane me-2"></i>Pass to Procurement (PR)
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- MODAL 3: Reject Material Request --}}
                                    <div class="modal fade" id="rejectMaterialModal{{ $mr->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content rounded-4 border-0 shadow">
                                                <div class="modal-header bg-danger text-white py-3 px-4 rounded-top-4">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="fa-solid fa-triangle-exclamation me-2"></i>Reject Material Request #{{ $mr->reference_number }}
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form action="{{ route('gm.maintenance-approvals.approve-material', $mr) }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="action" value="reject">
                                                    <div class="modal-body p-4">
                                                        <p class="text-secondary small mb-3">Please provide a reason for rejecting this spare parts request. General Service will be notified.</p>
                                                        <div class="mb-2">
                                                            <label class="form-label fw-semibold small text-uppercase text-secondary">Rejection Reason <span class="text-danger">*</span></label>
                                                            <textarea name="rejection_reason" class="form-control rounded-3" rows="3" required placeholder="Specify why this material request was rejected..."></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light border-0 py-3 px-4 rounded-bottom-4">
                                                        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger fw-bold rounded-pill px-4 shadow-sm">
                                                            <i class="fa-solid fa-ban me-2"></i>Confirm Rejection
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
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

    @else
        {{-- ═══════════════════════════════════════════════════════════════════════════
             TAB CONTENT: 2. DECIDED HISTORY / LOG
        ═══════════════════════════════════════════════════════════════════════════════ --}}
        <div class="row g-4">
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
                                                <strong class="text-success">{{ number_format($dExp->amount, 2) }}</strong>
                                                <small class="text-muted">ETB</small>
                                            </td>
                                            <td>
                                                @if(in_array($dExp->status, [\App\Models\ExpenseRequest::STATUS_APPROVED_ASSIGNED, \App\Models\ExpenseRequest::STATUS_ASSIGNED]))
                                                    <span class="badge bg-primary text-white rounded-pill px-2 py-1"><i class="fa-solid fa-check me-1"></i>Approved → Finance</span>
                                                @elseif($dExp->status === \App\Models\ExpenseRequest::STATUS_SENT_TO_STORE)
                                                    <span class="badge bg-info text-white rounded-pill px-2 py-1"><i class="fa-solid fa-warehouse me-1"></i>Routed to Store</span>
                                                @elseif($dExp->status === \App\Models\ExpenseRequest::STATUS_PAID)
                                                    <span class="badge bg-success text-white rounded-pill px-2 py-1"><i class="fa-solid fa-coins me-1"></i>Disbursed / Paid</span>
                                                @elseif($dExp->status === \App\Models\ExpenseRequest::STATUS_REJECTED)
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
@endsection
