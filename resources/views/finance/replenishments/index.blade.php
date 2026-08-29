@extends('layouts.app')
@section('title', 'Petty Cash Replenishment Approvals - Finance Head')

@section('content')
<style>
.voucher-scroll-box {
    scrollbar-width: thin;
    scrollbar-color: #adb5bd #f8f9fa;
}
.voucher-scroll-box::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
.voucher-scroll-box::-webkit-scrollbar-track {
    background: #f8f9fa;
    border-radius: 4px;
}
.voucher-scroll-box::-webkit-scrollbar-thumb {
    background: #ced4da;
    border-radius: 4px;
}
.voucher-scroll-box::-webkit-scrollbar-thumb:hover {
    background: #adb5bd;
}
</style>
<div class="container-fluid px-4 py-3">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <div class="d-flex align-items-center gap-2">
                <div class="p-2.5 rounded-3 text-white shadow-sm" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                    <i class="fa-solid fa-hand-holding-dollar fa-lg"></i>
                </div>
                <div>
                    <h1 class="h3 mb-0 fw-bold" style="color:var(--brand-800)">Petty Cash Replenishment Approvals</h1>
                    <p class="text-muted small mb-0">Finance Head portal to review attached expense vouchers, verify cycles, request descriptions, and route to audit</p>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('assigned-accounts.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-briefcase me-1"></i> My Assigned Accounts
            </a>
            <a href="{{ route('expenses.index') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-file-invoice-dollar me-1"></i> Expense Hub
            </a>
        </div>
    </div>

    <!-- KPI Summary Row -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white" style="border-left: 5px solid #f59e0b !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-xs fw-bold text-uppercase text-warning" style="font-size:0.72rem; letter-spacing:0.5px;">Pending Finance Review</span>
                        <h3 class="fw-bold text-dark mb-0 mt-1">{{ $tabCounts['pending'] }} <span class="fs-6 text-muted fw-normal">Requests</span></h3>
                        <small class="text-danger fw-semibold">Total: ETB {{ number_format($pendingAmount, 2) }}</small>
                    </div>
                    <div class="p-3 rounded-circle" style="background: rgba(245, 158, 11, 0.1);">
                        <i class="fa-solid fa-clock fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white" style="border-left: 5px solid #0284c7 !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-xs fw-bold text-uppercase text-info" style="font-size:0.72rem; letter-spacing:0.5px;">Under Audit</span>
                        <h3 class="fw-bold text-dark mb-0 mt-1">{{ $tabCounts['under_audit'] ?? 0 }} <span class="fs-6 text-muted fw-normal">In Review</span></h3>
                        <small class="text-muted">Routed to Internal Audit</small>
                    </div>
                    <div class="p-3 rounded-circle" style="background: rgba(2, 132, 199, 0.1);">
                        <i class="fa-solid fa-magnifying-glass fa-2x text-info"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white" style="border-left: 5px solid #10b981 !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-xs fw-bold text-uppercase text-success" style="font-size:0.72rem; letter-spacing:0.5px;">Fulfilled This Month</span>
                        <h3 class="fw-bold text-dark mb-0 mt-1">{{ $tabCounts['fulfilled'] }} <span class="fs-6 text-muted fw-normal">Completed</span></h3>
                        <small class="text-success fw-semibold">Disbursed: ETB {{ number_format($fulfilledMonthAmount, 2) }}</small>
                    </div>
                    <div class="p-3 rounded-circle" style="background: rgba(16, 185, 129, 0.1);">
                        <i class="fa-solid fa-circle-check fa-2x text-success"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white" style="border-left: 5px solid #6366f1 !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-xs fw-bold text-uppercase text-primary" style="font-size:0.72rem; letter-spacing:0.5px;">All Cycles</span>
                        <h3 class="fw-bold text-dark mb-0 mt-1">{{ $tabCounts['all'] }} <span class="fs-6 text-muted fw-normal">Total</span></h3>
                        <small class="text-muted">Imprest Replenishments</small>
                    </div>
                    <div class="p-3 rounded-circle" style="background: rgba(99, 102, 241, 0.1);">
                        <i class="fa-solid fa-receipt fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Navigation Tabs -->
    <div class="card border-0 shadow-sm mb-4 rounded-4 overflow-hidden">
        <div class="card-body p-2 bg-light">
            <ul class="nav nav-pills nav-fill gap-1 flex-nowrap overflow-auto" style="white-space: nowrap;">
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold py-2 {{ $activeTab === 'pending' ? 'active shadow-sm bg-warning text-dark' : 'text-secondary bg-white' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'pending', 'page' => 1]) }}">
                        <i class="fa-solid fa-hourglass-half me-1"></i> Pending Review
                        <span class="badge {{ $activeTab === 'pending' ? 'bg-dark text-white' : 'bg-warning text-dark' }} ms-1">{{ $tabCounts['pending'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold py-2 {{ $activeTab === 'under_audit' ? 'active shadow-sm bg-info text-white' : 'text-secondary bg-white' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'under_audit', 'page' => 1]) }}">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Under Audit
                        <span class="badge {{ $activeTab === 'under_audit' ? 'bg-dark text-white' : 'bg-info text-dark' }} ms-1">{{ $tabCounts['under_audit'] ?? 0 }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold py-2 {{ $activeTab === 'fulfilled' ? 'active shadow-sm bg-success text-white' : 'text-secondary bg-white' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'fulfilled', 'page' => 1]) }}">
                        <i class="fa-solid fa-circle-check me-1"></i> Fulfilled &amp; Disbursed
                        <span class="badge {{ $activeTab === 'fulfilled' ? 'bg-white text-success' : 'bg-success text-white' }} ms-1">{{ $tabCounts['fulfilled'] }}</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Search & Filter Card -->
    <div class="card border-0 shadow-sm mb-4 rounded-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('finance.replenishments.index') }}" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="{{ $activeTab }}">
                
                <div class="col-md-5">
                    <label class="form-label small text-muted mb-1 fw-bold">Search</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Request #, custodian name, account..." value="{{ request('search') }}">
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1 fw-bold">Date Range</label>
                    <div class="d-flex gap-1">
                        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                    </div>
                </div>

                <div class="col-md-3 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm rounded-3 w-100 fw-bold">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    <a href="{{ route('finance.replenishments.index', ['tab' => $activeTab]) }}" class="btn btn-light border btn-sm rounded-3 text-muted" title="Reset Filters">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Replenishments Table Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4 py-3">Request # &amp; Date</th>
                            <th class="py-3">Custodian / Staff</th>
                            <th class="py-3">Petty Cash Account</th>
                            <th class="py-3 text-end">Current Balance</th>
                            <th class="py-3 text-end">Requested Amount</th>
                            <th class="py-3 text-center">Status</th>
                            <th class="pe-4 py-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($replenishments as $rep)
                            <tr>
                                <td class="ps-4 py-3">
                                    <strong class="text-dark font-monospace d-block">{{ $rep->request_no }}</strong>
                                    <small class="text-muted">{{ $rep->created_at->format('M d, Y H:i') }}</small>
                                </td>
                                <td class="py-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar avatar-sm rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" style="width:32px;height:32px;">
                                            {{ strtoupper(substr($rep->requester->name ?? 'S', 0, 1)) }}
                                        </div>
                                        <div>
                                            <strong class="text-dark d-block">{{ $rep->requester->name ?? 'Custodian' }}</strong>
                                            <small class="text-muted">{{ $rep->requester->roles->pluck('name')->implode(', ') ?: 'Finance Staff' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <strong class="text-dark d-block">[{{ $rep->chartOfAccount->code ?? 'N/A' }}] {{ $rep->chartOfAccount->name ?? 'Petty Cash' }}</strong>
                                </td>
                                <td class="py-3 text-end">
                                    <span class="badge bg-light text-dark border font-monospace fs-6 px-2.5">
                                        ETB {{ number_format($rep->current_balance_at_request, 2) }}
                                    </span>
                                </td>
                                <td class="py-3 text-end">
                                    <strong class="text-primary font-monospace fs-5">
                                        ETB {{ number_format($rep->requested_amount, 2) }}
                                    </strong>
                                </td>
                                <td class="py-3 text-center">
                                    @if($rep->status === 'pending')
                                        <span class="badge bg-warning text-dark rounded-pill px-3 py-1.5 font-monospace">
                                            <i class="fa-solid fa-clock me-1"></i>Pending Review
                                        </span>
                                    @elseif($rep->status === 'under_audit')
                                        <span class="badge bg-info text-white rounded-pill px-3 py-1.5 font-monospace">
                                            <i class="fa-solid fa-magnifying-glass me-1"></i>Under Audit
                                        </span>
                                    @elseif($rep->status === 'fulfilled')
                                        <span class="badge bg-success text-white rounded-pill px-3 py-1.5 font-monospace">
                                            <i class="fa-solid fa-circle-check me-1"></i>Fulfilled
                                        </span>
                                    @elseif($rep->status === 'rejected')
                                        <span class="badge bg-danger text-white rounded-pill px-3 py-1.5 font-monospace">
                                            <i class="fa-solid fa-circle-xmark me-1"></i>Rejected
                                        </span>
                                    @endif
                                </td>
                                <td class="pe-4 py-3 text-end">
                                    <div class="btn-group btn-group-sm shadow-xs">
                                        @if($rep->status === 'under_audit' && auth()->check() && auth()->user()->hasAnyRole(['auditor', 'audit', 'internal_auditor', 'admin', 'global_admin', 'Finance head', 'finance_head', 'finance_manager']))
                                            <button type="button" class="btn btn-info text-white fw-bold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#auditDecisionModal{{ $rep->id }}" title="Audit Review & Decision">
                                                <i class="fa-solid fa-shield-halved me-1"></i> Audit Review &amp; Decision
                                            </button>
                                            <button type="button" class="btn btn-outline-danger px-2.5" data-bs-toggle="modal" data-bs-target="#auditRejectModal{{ $rep->id }}" title="Reject Replenishment Cycle">
                                                <i class="fa-solid fa-times text-danger"></i>
                                            </button>
                                        @elseif($rep->status === 'pending' && auth()->check() && auth()->user()->hasAnyRole(['Finance head', 'finance_head', 'finance_manager', 'admin', 'global_admin']))
                                            <button type="button" class="btn btn-success text-white fw-bold px-3" data-bs-toggle="modal" data-bs-target="#reviewModal{{ $rep->id }}" title="Review Vouchers and Send to Audit">
                                                <i class="fa-solid fa-magnifying-glass-dollar me-1"></i> Review &amp; Send to Audit
                                            </button>
                                            <button type="button" class="btn btn-outline-danger px-2.5" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $rep->id }}" title="Reject Replenishment Cycle">
                                                <i class="fa-solid fa-times text-danger"></i>
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-light border" data-bs-toggle="modal" data-bs-target="#viewModal{{ $rep->id }}" title="Audit & View Details">
                                                <i class="fa-solid fa-eye text-primary me-1"></i> Audit Details
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>

                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-inbox fa-3x mb-3 opacity-25"></i>
                                    <p class="mb-0 fw-semibold">No petty cash replenishment requests found in this view.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($replenishments->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $replenishments->links() }}
            </div>
        @endif
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODALS FOR EACH REPLENISHMENT                                              -->
<!-- ========================================================================= -->
@foreach($replenishments as $rep)

    <!-- 1. Review & Send to Audit Modal -->
    <div class="modal fade" id="reviewModal{{ $rep->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header text-white py-3 px-4" style="background: #1e293b; border-bottom: 3px solid #f59e0b;">
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white">
                            <i class="fa-solid fa-magnifying-glass-dollar text-warning me-2"></i>Review Vouchers &amp; Route to Audit #{{ $rep->request_no }}
                        </h5>
                        <small class="text-white-50">Custodian: <strong class="text-white">{{ $rep->requester->name ?? 'Staff' }}</strong> &bull; Requested on {{ $rep->created_at->format('M d, Y H:i') }}</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <form method="POST" action="{{ url('/assigned-accounts/' . $rep->chart_of_account_id . '/replenishments/' . $rep->id . '/send-to-audit') }}">
                    @csrf

                    <div class="modal-body p-4 bg-white">

                        <!-- Top Balance Stats -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3 border" style="border-left: 4px solid #64748b !important;">
                                    <span class="text-muted small text-uppercase fw-bold d-block">Current Petty Cash Balance</span>
                                    <h4 class="fw-bold text-dark font-monospace mb-0 mt-1">ETB {{ number_format($rep->current_balance_at_request, 2) }}</h4>
                                    <small class="text-muted">Account: {{ $rep->chartOfAccount->name ?? 'N/A' }}</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3 border" style="border-left: 4px solid #ef4444 !important;">
                                    <span class="text-muted small text-uppercase fw-bold d-block">Valid Attached Expenses</span>
                                    <h4 class="fw-bold text-danger font-monospace mb-0 mt-1">ETB {{ number_format($rep->total_expenses_amount, 2) }}</h4>
                                    <small class="text-muted">{{ $rep->items->count() }} Attached Vouchers</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3 border" style="border-left: 4px solid #f59e0b !important;">
                                    <span class="text-muted small text-uppercase fw-bold d-block">Requested Top-Up Amount</span>
                                    <h4 class="fw-bold font-monospace mb-0 mt-1" style="color: #d97706 !important;">ETB {{ number_format($rep->requested_amount, 2) }}</h4>
                                    <small class="text-muted">Status: <span class="badge bg-warning-subtle text-dark border">{{ ucfirst(str_replace('_', ' ', $rep->status)) }}</span></small>
                                </div>
                            </div>
                        </div>

                        <!-- Attached Itemized Expenses Table (With Select, Reject, Ask Description) -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <div>
                                    <label class="form-label fw-bold text-dark mb-0">
                                        <i class="fa-solid fa-list-check text-primary me-1"></i> Attached Expense Vouchers ({{ $rep->items->count() }} Records)
                                    </label>
                                    <small class="text-muted d-block" style="font-size:11px;">Select vouchers to perform bulk actions or use individual buttons to reject or request description</small>
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    <span class="badge bg-light text-dark border font-monospace px-3 py-1.5 fs-6">Valid Total: ETB {{ number_format($rep->total_expenses_amount, 2) }}</span>
                                </div>
                            </div>

                            <div class="voucher-scroll-box border rounded-top-3 shadow-xs" style="max-height: 380px; overflow-y: auto; overflow-x: auto;">
                                <table class="table table-sm table-striped table-hover align-middle mb-0 small" style="min-width: 1050px;">
                                    <thead class="bg-light sticky-top shadow-xs" style="z-index: 5;">
                                        <tr>
                                            <th class="ps-3 py-2.5 text-center bg-light" style="width: 45px;">
                                                <input type="checkbox" class="form-check-input" id="selectAllVouchers_{{ $rep->id }}" onchange="toggleSelectAllVouchers({{ $rep->id }}, this)">
                                            </th>
                                            <th class="py-2.5 text-nowrap bg-light" style="width: 110px;">Date</th>
                                            <th class="py-2.5 text-nowrap bg-light" style="width: 160px;">Voucher # / Ref</th>
                                            <th class="py-2.5 text-nowrap bg-light" style="width: 170px;">Category / Account</th>
                                            <th class="py-2.5 bg-light" style="min-width: 260px;">Description &amp; Beneficiary</th>
                                            <th class="py-2.5 text-end text-nowrap bg-light" style="width: 130px;">Amount (ETB)</th>
                                            <th class="py-2.5 text-center text-nowrap bg-light" style="width: 140px;">Review Status</th>
                                            <th class="pe-3 py-2.5 text-end text-nowrap bg-light" style="width: 190px;">Voucher Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($rep->items as $item)
                                            <tr class="{{ $item->status === 'rejected' ? 'table-danger opacity-75' : ($item->status === 'clarification_needed' ? 'table-warning' : '') }}">
                                                <td class="ps-3 py-2 text-center">
                                                    <input type="checkbox" name="voucher_ids[]" value="{{ $item->id }}" class="form-check-input voucher-cb-{{ $rep->id }}">
                                                </td>
                                                <td class="py-2 text-muted text-nowrap">
                                                    {{ $item->entry_date ? \Carbon\Carbon::parse($item->entry_date)->format('M d, Y') : ($item->created_at ? $item->created_at->format('M d, Y') : 'N/A') }}
                                                </td>
                                                <td class="py-2 text-nowrap">
                                                    <span class="badge bg-light text-primary border font-monospace">
                                                        {{ $item->reference ?: ($item->journal_entry_line_id ? 'JL #' . $item->journal_entry_line_id : 'EXP-' . $item->id) }}
                                                    </span>
                                                </td>
                                                <td class="py-2 text-nowrap">
                                                    <span class="badge bg-secondary-subtle text-dark border">
                                                        {{ $item->target_account_name ?: 'Petty Cash Expense' }}
                                                    </span>
                                                </td>
                                                <td class="py-2">
                                                    <div style="word-break: break-word; white-space: normal; line-height: 1.4;">
                                                        {{ $item->description }}
                                                    </div>
                                                </td>
                                                <td class="py-2 text-end fw-bold {{ $item->status === 'rejected' ? 'text-decoration-line-through text-muted' : 'text-danger' }} font-monospace text-nowrap">
                                                    ETB {{ number_format($item->amount, 2) }}
                                                </td>
                                                <td class="py-2 text-center text-nowrap">
                                                    @if($item->status === 'rejected')
                                                        <span class="badge bg-danger text-white px-2 py-1"><i class="fa-solid fa-ban me-1"></i>Rejected</span>
                                                        @if($item->rejection_reason)
                                                            <div class="text-danger small mt-0.5" style="font-size:11px;">{{ Str::limit($item->rejection_reason, 30) }}</div>
                                                        @endif
                                                    @elseif($item->status === 'clarification_needed')
                                                        <span class="badge bg-warning text-dark px-2 py-1"><i class="fa-solid fa-circle-question me-1"></i>Need Clarification</span>
                                                        @if($item->inquiry_note)
                                                            <div class="text-dark small mt-0.5" style="font-size:11px;">"{{ Str::limit($item->inquiry_note, 30) }}"</div>
                                                        @endif
                                                    @else
                                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1"><i class="fa-solid fa-check me-1"></i>Valid</span>
                                                    @endif
                                                </td>
                                                <td class="pe-3 py-2 text-end text-nowrap">
                                                    <div class="d-flex gap-1 justify-content-end align-items-center">
                                                        <button type="button" class="btn btn-sm btn-outline-warning text-dark fw-semibold px-2 py-0.5" style="font-size:0.75rem;" onclick="askDescriptionPrompt({{ $item->id }}, '{{ addslashes($item->reference ?: 'Voucher #' . $item->id) }}')" title="Ask Description / Clarification from Custodian">
                                                            <i class="fa-solid fa-comment-dots text-warning me-1"></i> Ask Note
                                                        </button>
                                                        @if($item->status === 'rejected')
                                                            <button type="button" class="btn btn-sm btn-outline-success px-2 py-0.5" style="font-size:0.75rem;" onclick="approveVoucher({{ $item->id }})" title="Restore and Include in Replenishment">
                                                                <i class="fa-solid fa-rotate-left me-1"></i> Include
                                                            </button>
                                                        @else
                                                            <button type="button" class="btn btn-sm btn-outline-danger px-2 py-0.5" style="font-size:0.75rem;" onclick="rejectVoucherPrompt({{ $item->id }}, '{{ addslashes($item->reference ?: 'Voucher #' . $item->id) }}')" title="Reject this Voucher">
                                                                <i class="fa-solid fa-ban me-1"></i> Reject
                                                            </button>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center py-4 text-muted">No line item breakdown attached.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            
                            @if($rep->items->count() > 0)
                            <div class="d-flex justify-content-between align-items-center bg-light border border-top-0 rounded-bottom-3 px-3 py-2.5 fw-bold small flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-dark"><i class="fa-solid fa-receipt text-secondary me-1"></i> Total: <span class="badge bg-dark rounded-pill">{{ $rep->items->count() }} Vouchers</span></span>
                                    <span class="text-muted">|</span>
                                    <button type="button" class="btn btn-xs btn-outline-danger rounded-pill px-2.5 py-1" onclick="submitBulkVoucherAction({{ $rep->chart_of_account_id }}, {{ $rep->id }}, 'reject')">
                                        <i class="fa-solid fa-ban me-1"></i> Reject Selected
                                    </button>
                                    <button type="button" class="btn btn-xs btn-outline-warning text-dark fw-semibold rounded-pill px-2.5 py-1" onclick="submitBulkVoucherAction({{ $rep->chart_of_account_id }}, {{ $rep->id }}, 'ask_description')">
                                        <i class="fa-solid fa-comment-dots me-1"></i> Ask Description on Selected
                                    </button>
                                </div>
                                <span class="text-danger font-monospace fs-6">Grand Total: ETB {{ number_format($rep->items->sum('amount') ?: $rep->total_expenses_amount, 2) }}</span>
                            </div>
                            @endif
                        </div>


                        <hr class="my-4">

                        <!-- ROUTE TO AUDIT / FINANCE REMARKS -->
                        <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-magnifying-glass text-warning me-1"></i> Review Remarks &amp; Send to Audit Team</h6>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark text-uppercase">Audit Instructions / Observations for Internal Auditor</label>
                            <textarea name="audit_notes" class="form-control" rows="3" placeholder="Enter notes for the Internal Audit Team regarding verified vouchers, excluded items, or special instructions...">{{ $rep->audit_notes }}</textarea>
                        </div>

                    </div>
                    <div class="modal-footer bg-light border-0 py-3 px-4 d-flex justify-content-between">
                        <div>
                            <button type="button" class="btn btn-outline-danger rounded-pill px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $rep->id }}">
                                <i class="fa-solid fa-times me-1"></i> Reject Replenishment Cycle
                            </button>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-warning text-dark rounded-pill px-4 fw-bold shadow-sm" onclick="return confirm('Send Replenishment #{{ $rep->request_no }} to Internal Audit Team?')">
                                <i class="fa-solid fa-paper-plane me-1"></i> Send to Audit Team
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 1b. Audit Review & Clearance Decision Modal (For Internal Auditor) -->
    <div class="modal fade" id="auditDecisionModal{{ $rep->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header text-white py-3 px-4" style="background: #1e293b; border-bottom: 3px solid #0284c7;">
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white">
                            <i class="fa-solid fa-shield-halved text-info me-2"></i>Internal Audit Review &amp; Clearance #{{ $rep->request_no }}
                        </h5>
                        <small class="text-white-50">
                            Custodian: <strong class="text-white">{{ $rep->requester->name ?? 'Staff' }}</strong> &bull; 
                            Routed to Audit on {{ $rep->reviewed_at ? $rep->reviewed_at->format('M d, Y H:i') : $rep->created_at->format('M d, Y H:i') }}
                            @if($rep->reviewer)
                                &bull; Reviewed by Finance Head: <strong class="text-info">{{ $rep->reviewer->name }}</strong>
                            @endif
                        </small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <form method="POST" action="{{ url('/assigned-accounts/' . $rep->chart_of_account_id . '/replenishments/' . $rep->id . '/audit-approve') }}">
                    @csrf

                    <div class="modal-body p-4 bg-white">

                        <!-- Top Balance Stats -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3 border" style="border-left: 4px solid #64748b !important;">
                                    <span class="text-muted small text-uppercase fw-bold d-block">Current Petty Cash Balance</span>
                                    <h4 class="fw-bold text-dark font-monospace mb-0 mt-1">ETB {{ number_format($rep->current_balance_at_request, 2) }}</h4>
                                    <small class="text-muted">Account: {{ $rep->chartOfAccount->name ?? 'N/A' }}</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3 border" style="border-left: 4px solid #ef4444 !important;">
                                    <span class="text-muted small text-uppercase fw-bold d-block">Valid Attached Expenses</span>
                                    <h4 class="fw-bold text-danger font-monospace mb-0 mt-1">ETB {{ number_format($rep->total_expenses_amount, 2) }}</h4>
                                    <small class="text-muted">{{ $rep->items->count() }} Attached Vouchers</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3 border" style="border-left: 4px solid #0284c7 !important;">
                                    <span class="text-muted small text-uppercase fw-bold d-block">Requested Top-Up Amount</span>
                                    <h4 class="fw-bold font-monospace mb-0 mt-1" style="color: #0284c7 !important;">ETB {{ number_format($rep->requested_amount, 2) }}</h4>
                                    <small class="text-muted">Audit State: <span class="badge bg-info text-white border">Under Audit</span></small>
                                </div>
                            </div>
                        </div>

                        <!-- Finance Head Review Notes Banner -->
                        @if($rep->audit_notes)
                        <div class="alert alert-info py-2.5 px-3 mb-4 rounded-3 border-start border-4 border-info">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-clipboard-check text-info fs-5"></i>
                                <div>
                                    <strong class="text-dark d-block">Finance Head Review Instructions / Notes:</strong>
                                    <span class="text-dark">{{ $rep->audit_notes }}</span>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Attached Itemized Expenses Table (With Select, Reject, Ask Description) -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <div>
                                    <label class="form-label fw-bold text-dark mb-0">
                                        <i class="fa-solid fa-list-check text-primary me-1"></i> Attached Expense Vouchers ({{ $rep->items->count() }} Records)
                                    </label>
                                    <small class="text-muted d-block" style="font-size:11px;">Internal Auditor: Examine line vouchers, request custodian notes, or exclude invalid vouchers before clearance</small>
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    <span class="badge bg-light text-dark border font-monospace px-3 py-1.5 fs-6">Valid Total: ETB {{ number_format($rep->total_expenses_amount, 2) }}</span>
                                </div>
                            </div>

                            <div class="voucher-scroll-box border rounded-top-3 shadow-xs" style="max-height: 380px; overflow-y: auto; overflow-x: auto;">
                                <table class="table table-sm table-striped table-hover align-middle mb-0 small" style="min-width: 1050px;">
                                    <thead class="bg-light sticky-top shadow-xs" style="z-index: 5;">
                                        <tr>
                                            <th class="ps-3 py-2.5 text-center bg-light" style="width: 45px;">
                                                <input type="checkbox" class="form-check-input" id="selectAllAuditVouchers_{{ $rep->id }}" onchange="toggleSelectAllVouchers({{ $rep->id }}, this)">
                                            </th>
                                            <th class="py-2.5 text-nowrap bg-light" style="width: 110px;">Date</th>
                                            <th class="py-2.5 text-nowrap bg-light" style="width: 160px;">Voucher # / Ref</th>
                                            <th class="py-2.5 text-nowrap bg-light" style="width: 170px;">Category / Account</th>
                                            <th class="py-2.5 bg-light" style="min-width: 260px;">Description &amp; Beneficiary</th>
                                            <th class="py-2.5 text-end text-nowrap bg-light" style="width: 130px;">Amount (ETB)</th>
                                            <th class="py-2.5 text-center text-nowrap bg-light" style="width: 140px;">Review Status</th>
                                            <th class="pe-3 py-2.5 text-end text-nowrap bg-light" style="width: 190px;">Audit Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($rep->items as $item)
                                            <tr class="{{ $item->status === 'rejected' ? 'table-danger opacity-75' : ($item->status === 'clarification_needed' ? 'table-warning' : '') }}">
                                                <td class="ps-3 py-2 text-center">
                                                    <input type="checkbox" name="voucher_ids[]" value="{{ $item->id }}" class="form-check-input voucher-cb-{{ $rep->id }}">
                                                </td>
                                                <td class="py-2 text-muted text-nowrap">
                                                    {{ $item->entry_date ? \Carbon\Carbon::parse($item->entry_date)->format('M d, Y') : ($item->created_at ? $item->created_at->format('M d, Y') : 'N/A') }}
                                                </td>
                                                <td class="py-2 text-nowrap">
                                                    <span class="badge bg-light text-primary border font-monospace">
                                                        {{ $item->reference ?: ($item->journal_entry_line_id ? 'JL #' . $item->journal_entry_line_id : 'EXP-' . $item->id) }}
                                                    </span>
                                                </td>
                                                <td class="py-2 text-nowrap">
                                                    <span class="badge bg-secondary-subtle text-dark border">
                                                        {{ $item->target_account_name ?: 'Petty Cash Expense' }}
                                                    </span>
                                                </td>
                                                <td class="py-2">
                                                    <div style="word-break: break-word; white-space: normal; line-height: 1.4;">
                                                        {{ $item->description }}
                                                    </div>
                                                </td>
                                                <td class="py-2 text-end fw-bold {{ $item->status === 'rejected' ? 'text-decoration-line-through text-muted' : 'text-danger' }} font-monospace text-nowrap">
                                                    ETB {{ number_format($item->amount, 2) }}
                                                </td>
                                                <td class="py-2 text-center text-nowrap">
                                                    @if($item->status === 'rejected')
                                                        <span class="badge bg-danger text-white px-2 py-1"><i class="fa-solid fa-ban me-1"></i>Rejected</span>
                                                        @if($item->rejection_reason)
                                                            <div class="text-danger small mt-0.5" style="font-size:11px;">{{ Str::limit($item->rejection_reason, 30) }}</div>
                                                        @endif
                                                    @elseif($item->status === 'clarification_needed')
                                                        <span class="badge bg-warning text-dark px-2 py-1"><i class="fa-solid fa-circle-question me-1"></i>Need Clarification</span>
                                                        @if($item->inquiry_note)
                                                            <div class="text-dark small mt-0.5" style="font-size:11px;">"{{ Str::limit($item->inquiry_note, 30) }}"</div>
                                                        @endif
                                                    @else
                                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1"><i class="fa-solid fa-check me-1"></i>Valid</span>
                                                    @endif
                                                </td>
                                                <td class="pe-3 py-2 text-end text-nowrap">
                                                    <div class="d-flex gap-1 justify-content-end align-items-center">
                                                        <button type="button" class="btn btn-sm btn-outline-warning text-dark fw-semibold px-2 py-0.5" style="font-size:0.75rem;" onclick="askDescriptionPrompt({{ $item->id }}, '{{ addslashes($item->reference ?: 'Voucher #' . $item->id) }}')" title="Ask Description / Clarification from Custodian">
                                                            <i class="fa-solid fa-comment-dots text-warning me-1"></i> Ask Note
                                                        </button>
                                                        @if($item->status === 'rejected')
                                                            <button type="button" class="btn btn-sm btn-outline-success px-2 py-0.5" style="font-size:0.75rem;" onclick="approveVoucher({{ $item->id }})" title="Restore and Include in Replenishment">
                                                                <i class="fa-solid fa-rotate-left me-1"></i> Include
                                                            </button>
                                                        @else
                                                            <button type="button" class="btn btn-sm btn-outline-danger px-2 py-0.5" style="font-size:0.75rem;" onclick="rejectVoucherPrompt({{ $item->id }}, '{{ addslashes($item->reference ?: 'Voucher #' . $item->id) }}')" title="Reject this Voucher">
                                                                <i class="fa-solid fa-ban me-1"></i> Reject
                                                            </button>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center py-4 text-muted">No line item breakdown attached.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            
                            @if($rep->items->count() > 0)
                            <div class="d-flex justify-content-between align-items-center bg-light border border-top-0 rounded-bottom-3 px-3 py-2.5 fw-bold small flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-dark"><i class="fa-solid fa-receipt text-secondary me-1"></i> Total: <span class="badge bg-dark rounded-pill">{{ $rep->items->count() }} Vouchers</span></span>
                                    <span class="text-muted">|</span>
                                    <button type="button" class="btn btn-xs btn-outline-danger rounded-pill px-2.5 py-1" onclick="submitBulkVoucherAction({{ $rep->chart_of_account_id }}, {{ $rep->id }}, 'reject')">
                                        <i class="fa-solid fa-ban me-1"></i> Reject Selected
                                    </button>
                                    <button type="button" class="btn btn-xs btn-outline-warning text-dark fw-semibold rounded-pill px-2.5 py-1" onclick="submitBulkVoucherAction({{ $rep->chart_of_account_id }}, {{ $rep->id }}, 'ask_description')">
                                        <i class="fa-solid fa-comment-dots me-1"></i> Ask Description on Selected
                                    </button>
                                </div>
                                <span class="text-danger font-monospace fs-6">Grand Total: ETB {{ number_format($rep->items->sum('amount') ?: $rep->total_expenses_amount, 2) }}</span>
                            </div>
                            @endif
                        </div>

                        <hr class="my-4">

                        <!-- AUDIT OBSERVATIONS & CLEARANCE REMARKS -->
                        <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-clipboard-check text-info me-1"></i> Internal Audit Clearance Findings &amp; Observations</h6>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark text-uppercase">Audit Verification Notes / Compliance Clearance Statement</label>
                            <textarea name="audit_notes" class="form-control" rows="3" placeholder="Enter Internal Audit verification findings, sample test results, or clearance instructions...">{{ $rep->audit_notes }}</textarea>
                        </div>

                    </div>
                    <div class="modal-footer bg-light border-0 py-3 px-4 d-flex justify-content-between">
                        <div>
                            <button type="button" class="btn btn-outline-danger rounded-pill px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#auditRejectModal{{ $rep->id }}">
                                <i class="fa-solid fa-times me-1"></i> Reject Replenishment Cycle
                            </button>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" onclick="return confirm('Grant Audit Clearance for Replenishment #{{ $rep->request_no }}?')">
                                <i class="fa-solid fa-circle-check me-1"></i> Pass &amp; Clear Audit
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 1c. Audit Rejection Modal -->
    <div class="modal fade" id="auditRejectModal{{ $rep->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-danger text-white py-3 px-4">
                    <h5 class="modal-title fw-bold mb-0"><i class="fa-solid fa-shield-xmark me-2"></i>Audit Reject: #{{ $rep->request_no }}</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ url('/assigned-accounts/' . $rep->chart_of_account_id . '/replenishments/' . $rep->id . '/audit-reject') }}">
                    @csrf

                    <div class="modal-body p-4 bg-white">
                        <p class="text-dark mb-2">Specify the internal audit findings / reasons for rejecting <strong>{{ $rep->requester->name ?? 'Staff' }}</strong>'s replenishment request:</p>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark text-uppercase">Audit Rejection Reason <span class="text-danger">*</span></label>
                            <textarea name="rejection_reason" class="form-control" rows="4" placeholder="e.g. Non-compliant expense receipts, missing supporting documentation, exceeded spending ceiling..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0 py-3 px-4">
                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-ban me-1"></i> Confirm Audit Rejection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- 2. Reject Modal -->
    <div class="modal fade" id="rejectModal{{ $rep->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-danger text-white py-3 px-4">
                    <h5 class="modal-title fw-bold mb-0"><i class="fa-solid fa-circle-xmark me-2"></i>Reject Replenishment #{{ $rep->request_no }}</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ url('/assigned-accounts/' . $rep->chart_of_account_id . '/replenishments/' . $rep->id . '/reject') }}">
                    @csrf

                    <div class="modal-body p-4 bg-white">
                        <p class="text-dark mb-2">Please specify the reason for rejecting <strong>{{ $rep->requester->name ?? 'Staff' }}</strong>'s replenishment request:</p>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark text-uppercase">Rejection Reason <span class="text-danger">*</span></label>
                            <textarea name="rejection_reason" class="form-control" rows="4" placeholder="e.g. Incomplete receipts attached, unapproved vouchers included..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0 py-3 px-4">
                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-times me-1"></i> Confirm Rejection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 3. View Details Modal -->
    <div class="modal fade" id="viewModal{{ $rep->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-dark text-white py-3 px-4">
                    <div>
                        <h5 class="modal-title fw-bold mb-0">
                            <i class="fa-solid fa-receipt me-2 text-primary"></i>Replenishment Cycle #{{ $rep->request_no }}
                        </h5>
                        <small class="text-white-50">Custodian: {{ $rep->requester->name ?? 'Staff' }} &bull; {{ $rep->created_at->format('M d, Y H:i') }}</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="p-3 bg-light rounded-3 border">
                                <span class="text-muted small text-uppercase fw-bold d-block">Requested Amount</span>
                                <h4 class="fw-bold text-primary font-monospace mb-0 mt-1">ETB {{ number_format($rep->requested_amount, 2) }}</h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 bg-light rounded-3 border">
                                <span class="text-muted small text-uppercase fw-bold d-block">Attached Expenses</span>
                                <h4 class="fw-bold text-danger font-monospace mb-0 mt-1">ETB {{ number_format($rep->total_expenses_amount, 2) }}</h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 bg-light rounded-3 border">
                                <span class="text-muted small text-uppercase fw-bold d-block">Fulfilled Amount</span>
                                <h4 class="fw-bold text-success font-monospace mb-0 mt-1">ETB {{ number_format($rep->fulfilled_amount ?? 0, 2) }}</h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 bg-light rounded-3 border">
                                <span class="text-muted small text-uppercase fw-bold d-block">Cycle Status</span>
                                <div class="mt-1">
                                    @if($rep->status === 'pending')
                                        <span class="badge bg-warning text-dark px-3 py-1.5 rounded-pill">Pending Review</span>
                                    @elseif($rep->status === 'under_audit')
                                        <span class="badge bg-info text-white px-3 py-1.5 rounded-pill">Under Audit</span>
                                    @elseif($rep->status === 'fulfilled')
                                        <span class="badge bg-success text-white px-3 py-1.5 rounded-pill">Fulfilled &amp; Disbursed</span>
                                    @elseif($rep->status === 'rejected')
                                        <span class="badge bg-danger text-white px-3 py-1.5 rounded-pill">Rejected</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-bold text-dark mb-0">
                                <i class="fa-solid fa-list-check text-primary me-1"></i> Attached Expense Vouchers
                            </label>
                            <span class="badge bg-light text-dark border font-monospace">Total: ETB {{ number_format($rep->total_expenses_amount, 2) }}</span>
                        </div>
                        <div class="voucher-scroll-box border rounded-top-3 shadow-xs" style="max-height: 380px; overflow-y: auto; overflow-x: auto;">
                            <table class="table table-sm table-striped table-hover align-middle mb-0 small">
                                <thead class="bg-light sticky-top shadow-xs" style="z-index: 5;">
                                    <tr>
                                        <th class="ps-3 py-2.5 text-nowrap bg-light">Date</th>
                                        <th class="py-2.5 text-nowrap bg-light">Voucher # / Ref</th>
                                        <th class="py-2.5 text-nowrap bg-light">Category / Account</th>
                                        <th class="py-2.5 bg-light" style="min-width: 300px;">Description &amp; Beneficiary Details</th>
                                        <th class="pe-3 py-2.5 text-end text-nowrap bg-light">Amount (ETB)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($rep->items as $item)
                                        <tr>
                                            <td class="ps-3 py-2 text-muted text-nowrap">
                                                {{ $item->entry_date ? \Carbon\Carbon::parse($item->entry_date)->format('M d, Y') : ($item->created_at ? $item->created_at->format('M d, Y') : 'N/A') }}
                                            </td>
                                            <td class="py-2 text-nowrap">
                                                <span class="badge bg-light text-primary border font-monospace">
                                                    {{ $item->reference ?: ($item->journal_entry_line_id ? 'JL #' . $item->journal_entry_line_id : 'EXP-' . $item->id) }}
                                                </span>
                                            </td>
                                            <td class="py-2 text-nowrap">
                                                <span class="badge bg-secondary-subtle text-dark border">
                                                    {{ $item->target_account_name ?: 'Petty Cash Expense' }}
                                                </span>
                                            </td>
                                            <td class="py-2">{{ $item->description }}</td>
                                            <td class="pe-3 py-2 text-end fw-bold text-danger font-monospace text-nowrap">
                                                ETB {{ number_format($item->amount, 2) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">No line item records.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if($rep->items->count() > 0)
                        <div class="d-flex justify-content-between align-items-center bg-light border border-top-0 rounded-bottom-3 px-3 py-2 fw-bold small">
                            <span class="text-dark"><i class="fa-solid fa-receipt text-secondary me-1"></i> Total Vouchers: <span class="badge bg-dark rounded-pill">{{ $rep->items->count() }}</span></span>
                            <span class="text-danger font-monospace fs-6">Grand Total: ETB {{ number_format($rep->items->sum('amount') ?: $rep->total_expenses_amount, 2) }}</span>
                        </div>
                        @endif
                    </div>

                    @if($rep->attachment_path)
                    <div class="mb-4 p-3 bg-light rounded-3 border d-flex justify-content-between align-items-center">
                        <div>
                            <strong class="text-dark d-block"><i class="fa-solid fa-file-invoice text-primary me-1"></i> Attached Physical Receipts &amp; Vouchers</strong>
                            <small class="text-muted">Scanned supporting proof uploaded by custodian</small>
                        </div>
                        <a href="{{ \App\Services\FileUploadService::url($rep->attachment_path) }}" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-xs">
                            <i class="fa-solid fa-download me-1"></i> Download Receipts
                        </a>
                    </div>
                    @endif

                    @if($rep->audit_notes)
                    <div class="mb-4 p-3 bg-info bg-opacity-10 rounded-3 border border-info border-opacity-25">
                        <strong class="text-dark d-block"><i class="fa-solid fa-magnifying-glass text-info me-1"></i> Audit Review Instructions</strong>
                        <p class="mb-0 text-dark small">{{ $rep->audit_notes }}</p>
                    </div>
                    @endif

                    @if($rep->status === 'fulfilled')
                    <div class="p-3 bg-success bg-opacity-10 border border-success-subtle rounded-3 text-dark small">
                        <div class="row g-2">
                            <div class="col-md-6"><strong>Disbursed By:</strong> {{ $rep->financeHead->name ?? 'Finance Head' }} on {{ optional($rep->fulfilled_at)->format('d M Y, h:i A') }}</div>
                            <div class="col-md-6"><strong>Source Account:</strong> [{{ $rep->sourceCoa->code ?? 'N/A' }}] {{ $rep->sourceCoa->name ?? 'Bank Account' }}</div>
                            @if($rep->fulfillment_reference)
                            <div class="col-md-6"><strong>Transaction Reference:</strong> {{ $rep->fulfillment_reference }}</div>
                            @endif
                            @if($rep->journal_entry_id)
                            <div class="col-md-6"><strong>GL Journal Entry ID:</strong> <span class="badge bg-dark font-monospace">JE #{{ $rep->journal_entry_id }}</span></div>
                            @endif
                            @if($rep->finance_notes)
                            <div class="col-12"><strong>Finance Head Remarks:</strong> {{ $rep->finance_notes }}</div>
                            @endif
                        </div>
                    </div>
                    @elseif($rep->status === 'rejected')
                    <div class="p-3 bg-danger bg-opacity-10 border border-danger-subtle rounded-3 text-dark small">
                        <strong>Rejected By:</strong> {{ $rep->financeHead->name ?? 'Finance Head' }} on {{ optional($rep->rejected_at)->format('d M Y, h:i A') }}
                        <div class="mt-1 text-danger"><strong>Reason:</strong> {{ $rep->rejection_reason }}</div>
                    </div>
                    @endif

                </div>
                <div class="modal-footer bg-light border-0 py-3 px-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

@endforeach

<!-- ========================================================================= -->
<!-- GLOBAL MODAL: REJECT INDIVIDUAL VOUCHER                                    -->
<!-- ========================================================================= -->
<div class="modal fade" id="rejectIndividualVoucherModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white py-3 px-4">
                <h5 class="modal-title fw-bold mb-0">
                    <i class="fa-solid fa-ban me-2"></i>Reject Voucher: <span id="rejectVoucherRefText"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="rejectIndividualVoucherForm" method="POST" action="">
                @csrf
                <div class="modal-body p-4 bg-white">
                    <p class="text-dark small mb-3">Rejecting this voucher will exclude its amount from the approved replenishment total.</p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark text-uppercase">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="3" placeholder="e.g. Unapproved expense, missing original receipt, duplicate claim..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3 px-4">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm">
                        <i class="fa-solid fa-ban me-1"></i> Confirm Reject Voucher
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- GLOBAL MODAL: ASK DESCRIPTION ON VOUCHER                                   -->
<!-- ========================================================================= -->
<div class="modal fade" id="askDescriptionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-warning text-dark py-3 px-4">
                <h5 class="modal-title fw-bold mb-0">
                    <i class="fa-solid fa-circle-question me-2"></i>Ask Description / Clarification: <span id="askVoucherRefText"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="askDescriptionForm" method="POST" action="">
                @csrf
                <div class="modal-body p-4 bg-white">
                    <p class="text-dark small mb-3">Ask the custodian for further explanation, purpose description, or proof on this voucher.</p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark text-uppercase">Inquiry / Clarification Request <span class="text-danger">*</span></label>
                        <textarea name="inquiry_note" class="form-control" rows="3" placeholder="e.g. Please explain the purpose of this travel, specify site location and names of beneficiaries..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3 px-4">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark rounded-pill px-4 fw-bold shadow-sm">
                        <i class="fa-solid fa-paper-plane me-1"></i> Send Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden Bulk Action Form -->
<form id="bulkVoucherActionForm" method="POST" action="" style="display:none;">
    @csrf
    <input type="hidden" name="bulk_action" id="bulkActionType">
    <input type="hidden" name="bulk_note" id="bulkActionNote">
    <div id="bulkVoucherIdsContainer"></div>
</form>

<!-- Hidden Restore Voucher Form -->
<form id="restoreVoucherForm" method="POST" action="" style="display:none;">
    @csrf
</form>

<script>
function toggleSelectAllVouchers(repId, masterCb) {
    const checkboxes = document.querySelectorAll('.voucher-cb-' + repId);
    checkboxes.forEach(cb => cb.checked = masterCb.checked);
}

function rejectVoucherPrompt(itemId, refText) {
    document.getElementById('rejectVoucherRefText').innerText = refText;
    document.getElementById('rejectIndividualVoucherForm').action = "{{ url('/assigned-accounts/replenishment-items') }}/" + itemId + "/reject";
    const modal = new bootstrap.Modal(document.getElementById('rejectIndividualVoucherModal'));
    modal.show();
}

function askDescriptionPrompt(itemId, refText) {
    document.getElementById('askVoucherRefText').innerText = refText;
    document.getElementById('askDescriptionForm').action = "{{ url('/assigned-accounts/replenishment-items') }}/" + itemId + "/ask-description";
    const modal = new bootstrap.Modal(document.getElementById('askDescriptionModal'));
    modal.show();
}

function approveVoucher(itemId) {
    if (confirm('Restore and include this voucher in the replenishment cycle?')) {
        const form = document.getElementById('restoreVoucherForm');
        form.action = "{{ url('/assigned-accounts/replenishment-items') }}/" + itemId + "/approve";
        form.submit();
    }
}

function submitBulkVoucherAction(accountId, repId, actionType) {
    const checkboxes = document.querySelectorAll('.voucher-cb-' + repId + ':checked');
    if (checkboxes.length === 0) {
        alert('Please select at least one voucher using the checkboxes.');
        return;
    }

    let promptText = actionType === 'reject' ? 'Enter rejection reason for selected vouchers:' : 'Enter description / clarification note for selected vouchers:';
    let note = prompt(promptText);
    if (note === null) return; // User cancelled

    const form = document.getElementById('bulkVoucherActionForm');
    form.action = "{{ url('/assigned-accounts') }}/" + accountId + "/replenishments/" + repId + "/bulk-voucher-action";
    document.getElementById('bulkActionType').value = actionType;
    document.getElementById('bulkActionNote').value = note;

    const container = document.getElementById('bulkVoucherIdsContainer');
    container.innerHTML = '';
    checkboxes.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'voucher_ids[]';
        input.value = cb.value;
        container.appendChild(input);
    });

    form.submit();
}
</script>

@endsection
