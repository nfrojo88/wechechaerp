@extends('layouts.app')
@section('title', 'Audit & Compliance Dashboard')

@section('content')
<style>
.modal {
    z-index: 1055 !important;
}
.modal-backdrop {
    z-index: 1050 !important;
}
.modal-dialog-scrollable {
    max-height: calc(100vh - 3.5rem);
}
.modal-dialog-scrollable .modal-content {
    max-height: calc(100vh - 3.5rem);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.modal-dialog-scrollable .modal-body {
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch;
    flex: 1 1 auto;
    min-height: 0;
}
.voucher-scroll-box {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 #f8fafc;
}
.voucher-scroll-box::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
.voucher-scroll-box::-webkit-scrollbar-track {
    background: #f8fafc;
    border-radius: 4px;
}
.voucher-scroll-box::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
</style>
<div class="container-fluid px-4 py-3">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <div class="d-flex align-items-center gap-2">
                <div class="p-2.5 rounded-3 text-white shadow-sm" style="background: linear-gradient(135deg, #0284c7, #0369a1);">
                    <i class="fa-solid fa-shield-halved fa-lg"></i>
                </div>
                <div>
                    <h1 class="h3 mb-0 fw-bold" style="color:var(--brand-800)">Audit &amp; Compliance Dashboard</h1>
                    <p class="text-muted small mb-0">Real-time oversight of petty cash replenishment cycles, money movements, expense vouchers, and system audit trails</p>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('expense-requests.index') }}" class="btn btn-outline-success btn-sm rounded-pill px-3 shadow-xs fw-semibold">
                <i class="fa-solid fa-hand-holding-dollar me-1"></i> Ask Money
            </a>
            <a href="{{ route('leave-requests.create') }}" class="btn btn-outline-info btn-sm rounded-pill px-3 shadow-xs fw-semibold">
                <i class="fa-solid fa-calendar-plus me-1"></i> Ask Leave
            </a>
            <a href="{{ route('finance.replenishments.index') }}" class="btn btn-warning text-dark btn-sm rounded-pill px-3 shadow-xs fw-bold">
                <i class="fa-solid fa-hand-holding-dollar me-1"></i> Petty Cash Approvals
            </a>
            <a href="{{ route('admin.activity-logs') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-list-ol me-1"></i> Full Activity Trail
            </a>
        </div>
    </div>

    <!-- Quick Employee Self-Service & Navigation Bar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
        <div class="card-body p-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary-subtle text-primary fw-bold px-2.5 py-1.5 rounded-3">
                        <i class="fa-solid fa-user-check me-1"></i> Employee &amp; Audit Quick Actions
                    </span>
                    <small class="text-muted">Direct access to staff requests and financial audits</small>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('expense-requests.index') }}" class="btn btn-light border btn-sm rounded-pill px-3 text-dark fw-semibold">
                        <i class="fa-solid fa-wallet text-success me-1"></i> Ask Money (Expenses)
                    </a>
                    <a href="{{ route('leave-requests.create') }}" class="btn btn-light border btn-sm rounded-pill px-3 text-dark fw-semibold">
                        <i class="fa-solid fa-calendar-check text-info me-1"></i> Request Leave
                    </a>
                    <a href="{{ route('letters.index') }}" class="btn btn-light border btn-sm rounded-pill px-3 text-dark fw-semibold">
                        <i class="fa-solid fa-envelope text-primary me-1"></i> Letters &amp; Correspondence
                    </a>
                    <a href="{{ route('expenses.index') }}" class="btn btn-light border btn-sm rounded-pill px-3 text-dark fw-semibold">
                        <i class="fa-solid fa-file-invoice text-danger me-1"></i> Expense Audit Hub
                    </a>
                    <a href="{{ route('coa.index') }}" class="btn btn-light border btn-sm rounded-pill px-3 text-dark fw-semibold">
                        <i class="fa-solid fa-sitemap text-secondary me-1"></i> COA Ledger
                    </a>
                </div>
            </div>
        </div>
    </div>


    <!-- KPI Summary Row -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white" style="border-left: 5px solid #0284c7 !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-xs fw-bold text-uppercase text-info" style="font-size:0.72rem; letter-spacing:0.5px;">Under Audit Review</span>
                        <h4 class="fw-bold text-dark mb-0 mt-1 font-monospace">{{ $kpi['under_audit_count'] }} <span class="fs-6 text-muted fw-normal">Cycles</span></h4>
                        <small class="text-danger fw-semibold">ETB {{ number_format($kpi['under_audit_amount'], 2) }}</small>
                    </div>
                    <div class="p-3 rounded-circle" style="background: rgba(2, 132, 199, 0.1);">
                        <i class="fa-solid fa-shield-halved fa-2x text-info"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white" style="border-left: 5px solid #10b981 !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-xs fw-bold text-uppercase text-success" style="font-size:0.72rem; letter-spacing:0.5px;">Fulfilled Top-Ups (This Month)</span>
                        <h4 class="fw-bold text-dark mb-0 mt-1 font-monospace">ETB {{ number_format($kpi['fulfilled_replenishments_month'], 2) }}</h4>
                        <small class="text-success fw-semibold"><i class="fa-solid fa-circle-check me-1"></i>Disbursed &amp; Reconciled</small>
                    </div>
                    <div class="p-3 rounded-circle" style="background: rgba(16, 185, 129, 0.1);">
                        <i class="fa-solid fa-money-bill-transfer fa-2x text-success"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white" style="border-left: 5px solid #6366f1 !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-xs fw-bold text-uppercase text-primary" style="font-size:0.72rem; letter-spacing:0.5px;">Reconciled Expense Vouchers</span>
                        <h4 class="fw-bold text-dark mb-0 mt-1 font-monospace">{{ number_format($kpi['total_reconciled_vouchers']) }} <span class="fs-6 text-muted fw-normal">Vouchers</span></h4>
                        <small class="text-primary fw-semibold">Total: ETB {{ number_format($kpi['total_reconciled_amount'], 2) }}</small>
                    </div>
                    <div class="p-3 rounded-circle" style="background: rgba(99, 102, 241, 0.1);">
                        <i class="fa-solid fa-receipt fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white" style="border-left: 5px solid #f59e0b !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-xs fw-bold text-uppercase text-warning" style="font-size:0.72rem; letter-spacing:0.5px;">Pending Finance Initial Review</span>
                        <h4 class="fw-bold text-dark mb-0 mt-1 font-monospace">{{ $kpi['pending_replenishments_count'] }} <span class="fs-6 text-muted fw-normal">Requests</span></h4>
                        <small class="text-muted">Total Activity Logs: {{ number_format($kpi['total_activity_logs']) }}</small>
                    </div>
                    <div class="p-3 rounded-circle" style="background: rgba(245, 158, 11, 0.1);">
                        <i class="fa-solid fa-clock fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PENDING AUDIT CLEARANCE SECTION (Action Required for Internal Auditor) -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="border-top: 4px solid #0284c7 !important;">
        <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-shield-halved text-info me-2"></i>Petty Cash Cycles Routed to Audit (Action Required)
                </h5>
                <small class="text-muted">Replenishments reviewed and forwarded by Finance Head requiring Internal Audit clearance before final disbursement</small>
            </div>
            <span class="badge bg-info text-white font-monospace px-3 py-1.5 rounded-pill fs-6">
                {{ $underAuditReplenishments->count() }} Awaiting Clearance
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="bg-light text-muted text-uppercase" style="font-size: 0.75rem;">
                        <tr>
                            <th class="ps-4 py-2.5">Request # &amp; Date</th>
                            <th class="py-2.5">Custodian</th>
                            <th class="py-2.5">Petty Cash Account</th>
                            <th class="py-2.5 text-end">Requested Amount</th>
                            <th class="py-2.5">Finance Reviewer</th>
                            <th class="py-2.5 text-center">Vouchers Attached</th>
                            <th class="pe-4 py-2.5 text-end">Audit Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($underAuditReplenishments as $rep)
                            <tr class="table-info bg-opacity-25">
                                <td class="ps-4 py-3">
                                    <span class="fw-bold text-dark font-monospace d-block">{{ $rep->request_no }}</span>
                                    <small class="text-muted">{{ $rep->created_at->format('M d, Y H:i') }}</small>
                                </td>
                                <td class="py-3">
                                    <strong>{{ $rep->requester->name ?? 'Staff' }}</strong>
                                </td>
                                <td class="py-3">
                                    <span class="d-block text-dark fw-semibold">[{{ $rep->chartOfAccount->code ?? 'N/A' }}] {{ $rep->chartOfAccount->name ?? 'Petty Cash' }}</span>
                                    <small class="text-muted font-monospace">Bal: ETB {{ number_format($rep->current_balance_at_request, 2) }}</small>
                                </td>
                                <td class="py-3 text-end">
                                    <strong class="text-primary font-monospace fs-6">
                                        ETB {{ number_format($rep->requested_amount, 2) }}
                                    </strong>
                                </td>
                                <td class="py-3">
                                    @if($rep->reviewer)
                                        <span class="badge bg-light text-dark border">
                                            <i class="fa-solid fa-user-check text-success me-1"></i>{{ $rep->reviewer->name }}
                                        </span>
                                        @if($rep->audit_notes)
                                            <div class="text-muted small mt-1" style="font-size:11px;">"{{ Str::limit($rep->audit_notes, 35) }}"</div>
                                        @endif
                                    @else
                                        <span class="text-muted fst-italic">Finance Head</span>
                                    @endif
                                </td>
                                <td class="py-3 text-center">
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle font-monospace px-2.5 py-1">
                                        {{ $rep->items->count() }} Vouchers (ETB {{ number_format($rep->total_expenses_amount, 2) }})
                                    </span>
                                </td>
                                <td class="pe-4 py-3 text-end">
                                    <div class="btn-group btn-group-sm shadow-xs">
                                        <button type="button" class="btn btn-info text-white fw-bold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#dashboardAuditModal{{ $rep->id }}" title="Audit Review & Clearance Decision">
                                            <i class="fa-solid fa-shield-halved me-1"></i> Review &amp; Decision
                                        </button>
                                        <button type="button" class="btn btn-outline-danger px-2.5" data-bs-toggle="modal" data-bs-target="#dashboardAuditRejectModal{{ $rep->id }}" title="Reject Replenishment Cycle">
                                            <i class="fa-solid fa-times text-danger"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-circle-check text-success fa-2x mb-2 d-block"></i>
                                    All clear! No replenishment requests are currently pending Internal Audit review.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="row g-4 mb-4">
        
        <!-- Petty Cash Replenishments & Money Movements -->
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="fa-solid fa-hand-holding-dollar text-warning me-2"></i>Recent Replenishments &amp; Fund Movements
                        </h5>
                        <small class="text-muted">Audit records of money requests, approvals, source account disbursements, and attached expenses</small>
                    </div>
                    <a href="{{ route('finance.replenishments.index') }}" class="btn btn-light btn-sm border rounded-pill px-3">
                        View All <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="bg-light text-muted text-uppercase" style="font-size: 0.75rem;">
                                <tr>
                                    <th class="ps-4 py-2.5">Request # &amp; Date</th>
                                    <th class="py-2.5">Custodian</th>
                                    <th class="py-2.5">Petty Cash Account</th>
                                    <th class="py-2.5 text-end">Disbursed Top-Up</th>
                                    <th class="py-2.5">Source Account</th>
                                    <th class="py-2.5 text-center">Vouchers</th>
                                    <th class="py-2.5 text-center">Status</th>
                                    <th class="pe-4 py-2.5 text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentReplenishments as $rep)
                                    <tr>
                                        <td class="ps-4 py-3">
                                            <span class="fw-bold text-dark font-monospace d-block">{{ $rep->request_no }}</span>
                                            <small class="text-muted">{{ $rep->created_at->format('M d, Y H:i') }}</small>
                                        </td>
                                        <td class="py-3">
                                            <strong>{{ $rep->requester->name ?? 'Staff' }}</strong>
                                        </td>
                                        <td class="py-3">
                                            <span class="d-block text-dark fw-semibold">[{{ $rep->chartOfAccount->code ?? 'N/A' }}] {{ $rep->chartOfAccount->name ?? 'Petty Cash' }}</span>
                                            <small class="text-muted font-monospace">Bal: ETB {{ number_format($rep->current_balance_at_request, 2) }}</small>
                                        </td>
                                        <td class="py-3 text-end">
                                            <strong class="text-primary font-monospace fs-6">
                                                ETB {{ number_format($rep->fulfilled_amount ?? $rep->requested_amount, 2) }}
                                            </strong>
                                        </td>
                                        <td class="py-3">
                                            @if($rep->sourceCoa)
                                                <span class="badge bg-light text-dark border">
                                                    [{{ $rep->sourceCoa->code }}] {{ $rep->sourceCoa->name }}
                                                </span>
                                            @else
                                                <span class="text-muted fst-italic">Pending selection</span>
                                            @endif
                                        </td>
                                        <td class="py-3 text-center">
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle font-monospace px-2 py-1">
                                                {{ $rep->items->count() }} (ETB {{ number_format($rep->total_expenses_amount, 2) }})
                                            </span>
                                        </td>
                                        <td class="py-3 text-center">
                                            @if($rep->status === 'pending')
                                                <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1">Pending Approval</span>
                                            @elseif($rep->status === 'under_audit')
                                                <span class="badge bg-info text-white rounded-pill px-2.5 py-1">Under Audit</span>
                                            @elseif($rep->status === 'fulfilled')
                                                <span class="badge bg-success text-white rounded-pill px-2.5 py-1">Fulfilled</span>
                                            @elseif($rep->status === 'rejected')
                                                <span class="badge bg-danger text-white rounded-pill px-2.5 py-1">Rejected</span>
                                            @endif
                                        </td>
                                        <td class="pe-4 py-3 text-end">
                                            <a href="{{ route('finance.replenishments.index', ['search' => $rep->request_no]) }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                                <i class="fa-solid fa-eye me-1"></i> Audit Details
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">
                                            No replenishment cycles recorded yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>


        <!-- Cash & Bank Account Balances Oversight -->
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                <div class="card-header bg-white border-0 py-3 px-4">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-building-columns text-primary me-2"></i>Cash &amp; Bank Liquidity Audit
                    </h5>
                    <small class="text-muted">Active asset accounts monitored for imprest funding</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="bg-light text-muted text-uppercase" style="font-size: 0.75rem;">
                                <tr>
                                    <th class="ps-4 py-2.5">Account</th>
                                    <th class="py-2.5">Custodian</th>
                                    <th class="pe-4 py-2.5 text-end">Balance (ETB)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($cashAndBankAccounts as $acc)
                                    <tr>
                                        <td class="ps-4 py-2.5">
                                            <strong class="text-dark d-block">[{{ $acc->code }}] {{ $acc->name }}</strong>
                                            <small class="text-muted">{{ $acc->subtype ?? ucfirst($acc->type) }}</small>
                                        </td>
                                        <td class="py-2.5">
                                            <small class="text-dark fw-semibold">{{ $acc->manager->name ?? 'Company General' }}</small>
                                        </td>
                                        <td class="pe-4 py-2.5 text-end font-monospace fw-bold {{ $acc->current_balance < 0 ? 'text-danger' : 'text-success' }}">
                                            ETB {{ number_format($acc->current_balance, 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-3 text-muted">No accounts found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Live System Audit & Activity Trail -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-list-check text-info me-2"></i>Live System Audit Trail &amp; Money Movement Logs
                </h5>
                <small class="text-muted">Automated chronological audit stream of financial transactions, approvals, disbursements, and user changes</small>
            </div>
            <a href="{{ route('admin.activity-logs') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-filter me-1"></i> Filter Full Trail
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="bg-light text-muted text-uppercase" style="font-size: 0.75rem;">
                        <tr>
                            <th class="ps-4 py-2.5">Timestamp</th>
                            <th class="py-2.5">User</th>
                            <th class="py-2.5">Action</th>
                            <th class="py-2.5">Module</th>
                            <th class="py-2.5">Audit Description &amp; Details</th>
                            <th class="pe-4 py-2.5 text-end">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentActivityLogs as $log)
                            <tr>
                                <td class="ps-4 py-3 text-muted" style="white-space:nowrap;">
                                    <strong class="text-dark d-block">{{ $log->created_at->format('M d, Y') }}</strong>
                                    <small>{{ $log->created_at->format('H:i:s') }} ({{ $log->created_at->diffForHumans() }})</small>
                                </td>
                                <td class="py-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:30px; height:30px; font-size:0.8rem;">
                                            {{ substr($log->user->name ?? 'S', 0, 1) }}
                                        </div>
                                        <div>
                                            <strong class="text-dark d-block">{{ $log->user->name ?? 'System' }}</strong>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <span class="badge bg-{{ $log->action_color }} rounded-pill px-2.5 py-1 font-monospace text-uppercase" style="font-size:0.7rem;">
                                        {{ $log->action }}
                                    </span>
                                </td>
                                <td class="py-3">
                                    <span class="badge bg-secondary-subtle text-dark border">
                                        {{ $log->module ?? 'General' }}
                                    </span>
                                </td>
                                <td class="py-3">
                                    <div class="text-dark">{{ $log->description }}</div>
                                    @if($log->changes && is_array($log->changes))
                                        <div class="mt-1 small text-muted font-monospace bg-light p-1.5 rounded border">
                                            @foreach($log->changes as $key => $val)
                                                <span class="me-2"><strong>{{ $key }}:</strong> {{ is_array($val) ? json_encode($val) : $val }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="pe-4 py-3 text-end text-muted font-monospace" style="font-size:0.75rem;">
                                    {{ $log->ip_address ?? 'N/A' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    No audit logs recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modals for Under-Audit Replenishments -->
    @foreach($underAuditReplenishments as $rep)

        <!-- 1. Audit Clearance Decision Modal -->
        <div class="modal fade" id="dashboardAuditModal{{ $rep->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <form method="POST" action="{{ url('/assigned-accounts/' . $rep->chart_of_account_id . '/replenishments/' . $rep->id . '/audit-approve') }}" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    @csrf
                    <div class="modal-header text-white py-3 px-4 flex-shrink-0" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-bottom: 3px solid #0284c7;">
                        <div>
                            <h5 class="modal-title fw-bold mb-0 text-white d-flex align-items-center gap-2">
                                <span class="badge bg-info-subtle text-info border border-info border-opacity-25 px-2.5 py-1 rounded-pill fs-6"><i class="fa-solid fa-shield-halved me-1"></i> Internal Audit</span>
                                <span>Review &amp; Clearance: #{{ $rep->request_no }}</span>
                            </h5>
                            <small class="text-white-50 d-flex flex-wrap gap-2 align-items-center mt-1">
                                <span>Custodian: <strong class="text-white">{{ $rep->requester->name ?? 'Staff' }}</strong></span>
                                <span>&bull;</span>
                                <span>Routed: <strong class="text-white-50">{{ $rep->reviewed_at ? $rep->reviewed_at->format('M d, Y H:i') : $rep->created_at->format('M d, Y H:i') }}</strong></span>
                                @if($rep->reviewer)
                                    <span>&bull;</span>
                                    <span>Reviewed by Finance Head: <strong class="text-info">{{ $rep->reviewer->name }}</strong></span>
                                @endif
                            </small>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body p-4 bg-white flex-grow-1" style="overflow-y: auto;">

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
                                    <small class="text-muted">Status: <span class="badge bg-info text-white border">Under Audit Review</span></small>
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

                            <div class="voucher-scroll-box border rounded-top-3 shadow-xs" style="max-height: 320px; overflow-y: auto; overflow-x: auto;">
                                <table class="table table-sm table-striped table-hover align-middle mb-0 small" style="min-width: 1050px;">
                                    <thead class="bg-light sticky-top shadow-xs" style="z-index: 5;">
                                        <tr>
                                            <th class="ps-3 py-2.5 text-center bg-light" style="width: 45px;">
                                                <input type="checkbox" class="form-check-input" id="selectAllDashboardAudit_{{ $rep->id }}" onchange="toggleSelectAllVouchers({{ $rep->id }}, this)">
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

                        <!-- AUDIT VERIFIED REPLENISHMENT AMOUNT & ROUTING TO GM -->
                        <div class="card border-primary border-opacity-25 bg-primary bg-opacity-10 p-3 mb-4 rounded-3">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-7">
                                    <label class="form-label small fw-bold text-dark text-uppercase mb-1">
                                        <i class="fa-solid fa-coins text-warning me-1"></i> Verified Replenishment Amount (ETB) <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white fw-bold text-primary">ETB</span>
                                        <input type="number" step="0.01" min="0.01" name="replenishment_amount" class="form-control form-control-lg font-monospace fw-bold text-primary" value="{{ (float)($rep->requested_amount ?: $rep->total_expenses_amount) }}" required>
                                    </div>
                                    <small class="text-muted" style="font-size: 11px;">
                                        Specify the audit-cleared top-up amount to route to the General Manager (GM) for approval in the Expense Approval section.
                                    </small>
                                </div>
                                <div class="col-md-5">
                                    <div class="p-2.5 bg-white rounded-3 border">
                                        <div class="d-flex justify-content-between small text-muted mb-1">
                                            <span>Total Valid Expenses:</span>
                                            <span class="font-monospace fw-bold text-danger">ETB {{ number_format($rep->total_expenses_amount, 2) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between small text-muted mb-1">
                                            <span>Current Petty Cash:</span>
                                            <span class="font-monospace fw-bold text-dark">ETB {{ number_format($rep->current_balance_at_request, 2) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between small text-muted">
                                            <span>Destination:</span>
                                            <span class="badge bg-primary text-white"><i class="fa-solid fa-user-shield me-1"></i> GM Expense Approvals</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- AUDIT OBSERVATIONS & CLEARANCE REMARKS -->
                        <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-clipboard-check text-info me-1"></i> Internal Audit Clearance Findings &amp; Observations</h6>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark text-uppercase">Audit Verification Notes / Compliance Clearance Statement</label>
                            <textarea name="audit_notes" class="form-control" rows="3" placeholder="Enter Internal Audit verification findings, sample test results, or clearance instructions...">{{ $rep->audit_notes }}</textarea>
                        </div>

                    </div>
                    <div class="modal-footer bg-light border-top py-3 px-4 flex-shrink-0 d-flex justify-content-between align-items-center">
                        <div>
                            <button type="button" class="btn btn-outline-danger rounded-pill px-3.5 py-2 shadow-xs fw-semibold" data-bs-toggle="modal" data-bs-target="#dashboardAuditRejectModal{{ $rep->id }}">
                                <i class="fa-solid fa-ban me-1.5"></i> Reject Replenishment Cycle
                            </button>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light border rounded-pill px-4 py-2 text-secondary fw-semibold" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-success rounded-pill px-4 py-2 fw-bold shadow-sm" onclick="return confirm('Pass Audit & Send Replenishment #{{ $rep->request_no }} to GM for Approval?')">
                                <i class="fa-solid fa-paper-plane me-1.5"></i> Pass Audit &amp; Send to GM for Approval
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- 2. Audit Rejection Modal -->
        <div class="modal fade" id="dashboardAuditRejectModal{{ $rep->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <form method="POST" action="{{ url('/assigned-accounts/' . $rep->chart_of_account_id . '/replenishments/' . $rep->id . '/audit-reject') }}" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    @csrf
                    <div class="modal-header bg-danger text-white py-3 px-4 flex-shrink-0">
                        <h5 class="modal-title fw-bold mb-0"><i class="fa-solid fa-shield-xmark me-2"></i>Audit Reject: #{{ $rep->request_no }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4 bg-white flex-grow-1" style="overflow-y: auto;">
                        <p class="text-dark mb-2">Specify the internal audit findings / reasons for rejecting <strong>{{ $rep->requester->name ?? 'Staff' }}</strong>'s replenishment request:</p>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark text-uppercase">Audit Rejection Reason <span class="text-danger">*</span></label>
                            <textarea name="rejection_reason" class="form-control" rows="4" placeholder="e.g. Non-compliant expense receipts, missing supporting documentation, exceeded spending ceiling..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-top py-3 px-4 flex-shrink-0 d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light border rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-ban me-1"></i> Confirm Audit Rejection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach

</div>

<!-- Voucher Action Prompt Modals -->
<div class="modal fade" id="rejectVoucherModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white py-2.5 px-3">
                <h6 class="modal-title fw-bold mb-0" id="rejectVoucherModalTitle"><i class="fa-solid fa-ban me-1"></i> Reject Voucher</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="rejectVoucherForm" method="POST" action="">
                @csrf
                <div class="modal-body p-3 bg-white">
                    <p class="small text-muted mb-2" id="rejectVoucherSubtitle">State reason for rejecting this voucher:</p>
                    <textarea name="rejection_reason" class="form-control form-control-sm" rows="3" placeholder="e.g. Invalid receipt, personal expense, duplicate item..." required></textarea>
                </div>
                <div class="modal-footer bg-light border-0 py-2 px-3">
                    <button type="button" class="btn btn-light btn-sm rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm rounded-pill fw-bold">Reject Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="askDescriptionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-warning text-dark py-2.5 px-3">
                <h6 class="modal-title fw-bold mb-0" id="askDescModalTitle"><i class="fa-solid fa-comment-dots me-1"></i> Request Clarification</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="askDescriptionForm" method="POST" action="">
                @csrf
                <div class="modal-body p-3 bg-white">
                    <p class="small text-muted mb-2" id="askDescSubtitle">Specify what clarification / note is required from custodian:</p>
                    <textarea name="inquiry_note" class="form-control form-control-sm" rows="3" placeholder="e.g. Please clarify business purpose or upload official tax invoice..." required></textarea>
                </div>
                <div class="modal-footer bg-light border-0 py-2 px-3">
                    <button type="button" class="btn btn-light btn-sm rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark btn-sm rounded-pill fw-bold">Send Inquiry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function rejectVoucherPrompt(itemId, ref) {
    const form = document.getElementById('rejectVoucherForm');
    form.action = '{{ url("/assigned-accounts/replenishment-items") }}/' + itemId + '/reject';
    document.getElementById('rejectVoucherSubtitle').innerText = 'State reason for rejecting ' + ref + ':';
    const modal = new bootstrap.Modal(document.getElementById('rejectVoucherModal'));
    modal.show();
}

function askDescriptionPrompt(itemId, ref) {
    const form = document.getElementById('askDescriptionForm');
    form.action = '{{ url("/assigned-accounts/replenishment-items") }}/' + itemId + '/ask-description';
    document.getElementById('askDescSubtitle').innerText = 'Inquiry / Note for ' + ref + ':';
    const modal = new bootstrap.Modal(document.getElementById('askDescriptionModal'));
    modal.show();
}

function approveVoucher(itemId) {
    if (confirm('Restore and include this voucher in the valid replenishment total?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ url("/assigned-accounts/replenishment-items") }}/' + itemId + '/approve';
        
        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = '{{ csrf_token() }}';
        form.appendChild(csrf);

        document.body.appendChild(form);
        form.submit();
    }
}

function toggleSelectAllVouchers(repId, masterCb) {
    const cbs = document.querySelectorAll('.voucher-cb-' + repId);
    cbs.forEach(cb => {
        cb.checked = masterCb.checked;
    });
}

function submitBulkVoucherAction(accountId, repId, action) {
    const checked = document.querySelectorAll('.voucher-cb-' + repId + ':checked');
    if (checked.length === 0) {
        alert('Please select at least one voucher.');
        return;
    }

    let note = '';
    if (action === 'reject') {
        note = prompt('Enter rejection reason for selected ' + checked.length + ' vouchers:');
        if (note === null) return;
    } else if (action === 'ask_description') {
        note = prompt('Enter description/clarification inquiry note for selected ' + checked.length + ' vouchers:');
        if (note === null) return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ url("/assigned-accounts") }}/' + accountId + '/replenishments/' + repId + '/bulk-voucher-action';

    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = '{{ csrf_token() }}';
    form.appendChild(csrf);

    const actInput = document.createElement('input');
    actInput.type = 'hidden';
    actInput.name = 'bulk_action';
    actInput.value = action;
    form.appendChild(actInput);

    if (note) {
        const noteInput = document.createElement('input');
        noteInput.type = 'hidden';
        noteInput.name = 'bulk_note';
        noteInput.value = note;
        form.appendChild(noteInput);
    }

    checked.forEach(cb => {
        const itemInput = document.createElement('input');
        itemInput.type = 'hidden';
        itemInput.name = 'voucher_ids[]';
        itemInput.value = cb.value;
        form.appendChild(itemInput);
    });

    document.body.appendChild(form);
    form.submit();
}
</script>
@endsection

