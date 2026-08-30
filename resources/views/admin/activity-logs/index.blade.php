@extends('layouts.app')
@section('title', 'Audit & System Activity Trail')

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
                    <i class="fa-solid fa-list-check fa-lg"></i>
                </div>
                <div>
                    <h1 class="h3 mb-0 fw-bold" style="color:var(--brand-800)">Audit &amp; System Activity Trail</h1>
                    <p class="text-muted small mb-0">Live audit stream of petty cash routing, financial clearances, user activities, and money movements</p>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('dashboard.audit') }}" class="btn btn-outline-info btn-sm rounded-pill px-3 shadow-xs fw-semibold">
                <i class="fa-solid fa-chart-pie me-1"></i> Audit Dashboard
            </a>
            <a href="{{ route('finance.replenishments.index') }}" class="btn btn-outline-warning text-dark btn-sm rounded-pill px-3 shadow-xs fw-bold">
                <i class="fa-solid fa-hand-holding-dollar me-1"></i> Petty Cash Hub
            </a>
        </div>
    </div>

    <!-- PENDING AUDIT CLEARANCE QUEUE (When Finance Head sends replenishment to Audit) -->
    @if(isset($underAuditReplenishments) && $underAuditReplenishments->count() > 0)
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden bg-white" style="border-top: 4px solid #0284c7 !important;">
        <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-shield-halved text-info me-2"></i>Petty Cash Cycles Routed to Audit by Finance Head (Action Required)
                </h5>
                <small class="text-muted">Replenishments reviewed and forwarded by Finance Head requiring Internal Audit clearance before GM approval</small>
            </div>
            <span class="badge bg-info text-white font-monospace px-3 py-1.5 rounded-pill fs-6">
                {{ $underAuditReplenishments->count() }} Awaiting Your Clearance
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="bg-light text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                        <tr>
                            <th class="ps-4 py-2.5">Request # &amp; Date</th>
                            <th class="py-2.5">Custodian</th>
                            <th class="py-2.5">Petty Cash Account</th>
                            <th class="py-2.5 text-end">Requested Amount</th>
                            <th class="py-2.5">Finance Reviewer &amp; Notes</th>
                            <th class="py-2.5 text-center">Vouchers</th>
                            <th class="pe-4 py-2.5 text-end">Audit Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($underAuditReplenishments as $rep)
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
                                        <button type="button" class="btn btn-info text-white fw-bold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#activityAuditModal{{ $rep->id }}" title="Audit Review & Clearance Decision">
                                            <i class="fa-solid fa-shield-halved me-1"></i> Review &amp; Decision
                                        </button>
                                        <button type="button" class="btn btn-outline-danger px-2.5" data-bs-toggle="modal" data-bs-target="#activityAuditRejectModal{{ $rep->id }}" title="Reject Replenishment Cycle">
                                            <i class="fa-solid fa-times text-danger"></i>
                                        </button>
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

    <!-- Activity Log Search & Filter Card -->
    <div class="card border-0 shadow-sm mb-4 rounded-4 bg-white">
        <div class="card-body p-3">
            <form action="{{ route('admin.activity-logs') }}" method="GET" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-bold text-uppercase" style="font-size: 11px;">User</label>
                    <select name="user_id" class="form-select form-select-sm">
                        <option value="">All Users</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-bold text-uppercase" style="font-size: 11px;">Action</label>
                    <select name="action" class="form-select form-select-sm">
                        <option value="">All Actions</option>
                        @foreach($actions as $act)
                            <option value="{{ $act }}" {{ request('action') == $act ? 'selected' : '' }}>{{ ucfirst($act) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-bold text-uppercase" style="font-size: 11px;">Module</label>
                    <select name="module" class="form-select form-select-sm">
                        <option value="">All Modules</option>
                        @foreach($modules as $mod)
                            <option value="{{ $mod }}" {{ request('module') == $mod ? 'selected' : '' }}>{{ $mod }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1 fw-bold text-uppercase" style="font-size: 11px;">Date Range</label>
                    <div class="d-flex gap-1">
                        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}" placeholder="From Date">
                        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}" placeholder="To Date">
                    </div>
                </div>
                <div class="col-md-3 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm rounded-3 w-100 fw-bold">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    <a href="{{ route('admin.activity-logs') }}" class="btn btn-light border btn-sm rounded-3 text-muted" title="Reset Filters">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Activity Trail Table Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mb-4">
        <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>System Activity Logs
                </h5>
                <small class="text-muted">Chronological audit stream of user modifications, state changes, and financial reviews</small>
            </div>
            <span class="badge bg-light text-dark border font-monospace px-3 py-1.5 rounded-pill">
                Total {{ $logs->total() }} Log Records
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="bg-light text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                        <tr>
                            <th class="ps-4 py-3">Time</th>
                            <th class="py-3">User</th>
                            <th class="py-3">Action</th>
                            <th class="py-3">Module</th>
                            <th class="py-3" style="min-width: 320px;">Description</th>
                            <th class="py-3 text-end">Audit Follow-up</th>
                            <th class="pe-4 py-3 text-end">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                        <tr>
                            <td class="ps-4 py-3 text-nowrap text-muted">
                                <strong class="text-dark d-block">{{ $log->created_at->format('Y-m-d') }}</strong>
                                <small>{{ $log->created_at->format('H:i:s') }} ({{ $log->created_at->diffForHumans() }})</small>
                            </td>
                            <td class="py-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar avatar-sm rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" style="width:30px;height:30px; font-size: 0.78rem;">
                                        {{ strtoupper(substr($log->user->name ?? 'S', 0, 1)) }}
                                    </div>
                                    <div>
                                        <strong class="text-dark d-block">{{ $log->user->name ?? 'System' }}</strong>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3">
                                <span class="badge bg-{{ $log->action_color }} rounded-pill px-2.5 py-1 font-monospace text-uppercase" style="font-size:0.7rem;">
                                    <i class="fa-solid {{ $log->action_icon }} me-1"></i>{{ ucfirst($log->action) }}
                                </span>
                            </td>
                            <td class="py-3">
                                <span class="badge bg-light text-dark border">{{ $log->module ?? '-' }}</span>
                            </td>
                            <td class="py-3">
                                <div style="line-height: 1.45; word-break: break-word;">
                                    {{ $log->description }}
                                </div>
                            </td>
                            <td class="py-3 text-end">
                                @php
                                    $pcrNo = null;
                                    if (preg_match('/(PCR-\d{8}-\d{4})/', $log->description, $matches)) {
                                        $pcrNo = $matches[1];
                                    }
                                    $matchedRep = null;
                                    if ($pcrNo && isset($underAuditReplenishments)) {
                                        $matchedRep = $underAuditReplenishments->firstWhere('request_no', $pcrNo);
                                    }
                                @endphp

                                @if($matchedRep)
                                    <button type="button" class="btn btn-xs btn-info text-white rounded-pill px-2.5 py-1 fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#activityAuditModal{{ $matchedRep->id }}" title="Take Audit Decision">
                                        <i class="fa-solid fa-shield-halved me-1"></i> Clear Audit
                                    </button>
                                @elseif($pcrNo)
                                    <a href="{{ route('finance.replenishments.index', ['search' => $pcrNo, 'tab' => 'history']) }}" class="btn btn-xs btn-light border rounded-pill px-2.5 py-1 text-primary fw-semibold" title="View Replenishment Details">
                                        <i class="fa-solid fa-eye me-1"></i> View PCR
                                    </a>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td class="pe-4 py-3 text-end">
                                <small class="text-muted font-monospace">{{ $log->ip_address ?? '-' }}</small>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-inbox fa-3x mb-3 opacity-25"></i>
                                <p class="mb-0 fw-semibold">No activity logs found matching the criteria.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($logs->hasPages())
        <div class="card-footer bg-white border-0 py-3">
            {{ $logs->links() }}
        </div>
        @endif
    </div>
</div>

<!-- ========================================================================= -->
<!-- AUDIT CLEARANCE MODALS FOR UNDER-AUDIT REPLENISHMENTS                      -->
<!-- ========================================================================= -->
@if(isset($underAuditReplenishments))
    @foreach($underAuditReplenishments as $rep)

        <!-- 1. Audit Clearance Decision Modal -->
        <div class="modal fade" id="activityAuditModal{{ $rep->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <form method="POST" action="{{ url('/assigned-accounts/' . $rep->chart_of_account_id . '/replenishments/' . $rep->id . '/audit-approve') }}" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    @csrf
                    <div class="modal-header text-white py-3 px-4 flex-shrink-0" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-bottom: 3px solid #0284c7;">
                        <div class="d-flex align-items-center gap-3">
                            <div class="p-2 bg-info bg-opacity-25 rounded-3 text-info">
                                <i class="fa-solid fa-shield-halved fa-lg"></i>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge rounded-pill px-2.5 py-1 fw-bold text-uppercase" style="background: #0284c7; color: #ffffff; font-size: 10px; letter-spacing: 0.5px;">Internal Audit</span>
                                    <h5 class="modal-title fw-bold mb-0 text-white">Review &amp; Clearance #{{ $rep->request_no }}</h5>
                                </div>
                                <small class="text-white-50 d-flex flex-wrap gap-2 align-items-center mt-0.5">
                                    <span>Custodian: <strong class="text-white">{{ $rep->requester->name ?? 'Staff' }}</strong></span>
                                    <span>&bull;</span>
                                    <span>Routed: <strong class="text-white-50">{{ $rep->reviewed_at ? $rep->reviewed_at->format('M d, Y H:i') : $rep->created_at->format('M d, Y H:i') }}</strong></span>
                                    @if($rep->reviewer)
                                        <span>&bull;</span>
                                        <span>Reviewed by Finance Head: <strong class="text-info">{{ $rep->reviewer->name }}</strong></span>
                                    @endif
                                </small>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body p-4 bg-light flex-grow-1" style="overflow-y: auto;">

                        <!-- Top Balance Stats -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <div class="p-3 bg-white rounded-3 border shadow-xs" style="border-left: 4px solid #64748b !important;">
                                    <span class="text-muted small text-uppercase fw-bold d-block" style="font-size: 11px;">Current Petty Cash Balance</span>
                                    <h4 class="fw-bold text-dark font-monospace mb-0 mt-1">ETB {{ number_format($rep->current_balance_at_request, 2) }}</h4>
                                    <small class="text-muted">Account: {{ $rep->chartOfAccount->name ?? 'N/A' }}</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-white rounded-3 border shadow-xs" style="border-left: 4px solid #ef4444 !important;">
                                    <span class="text-muted small text-uppercase fw-bold d-block" style="font-size: 11px;">Valid Attached Expenses</span>
                                    <h4 class="fw-bold text-danger font-monospace mb-0 mt-1">ETB {{ number_format($rep->total_expenses_amount, 2) }}</h4>
                                    <small class="text-muted">{{ $rep->items->count() }} Attached Vouchers</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-white rounded-3 border shadow-xs" style="border-left: 4px solid #0284c7 !important;">
                                    <span class="text-muted small text-uppercase fw-bold d-block" style="font-size: 11px;">Requested Top-Up Amount</span>
                                    <h4 class="fw-bold font-monospace mb-0 mt-1" style="color: #0284c7 !important;">ETB {{ number_format($rep->requested_amount, 2) }}</h4>
                                    <small class="text-muted">Status: <span class="badge bg-info text-white border rounded-pill px-2">Under Audit</span></small>
                                </div>
                            </div>
                        </div>

                        <!-- Finance Head Review Notes Banner -->
                        @if($rep->audit_notes)
                        <div class="alert alert-info py-2.5 px-3 mb-3 rounded-3 border-start border-4 border-info shadow-xs bg-white">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-clipboard-check text-info fs-5"></i>
                                <div>
                                    <strong class="text-dark d-block">Finance Head Review Instructions / Notes:</strong>
                                    <span class="text-dark">{{ $rep->audit_notes }}</span>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Attached Itemized Expenses Table -->
                        <div class="card border-0 shadow-sm rounded-4 p-3 mb-3 bg-white">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <div>
                                    <label class="form-label fw-bold text-dark mb-0">
                                        <i class="fa-solid fa-list-check text-primary me-1"></i> Attached Expense Vouchers ({{ $rep->items->count() }} Records)
                                    </label>
                                    <small class="text-muted d-block" style="font-size:11px;">Internal Auditor: Examine line vouchers before clearance</small>
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    <span class="badge bg-light text-dark border font-monospace px-3 py-1.5 fs-6">Valid Total: ETB {{ number_format($rep->total_expenses_amount, 2) }}</span>
                                </div>
                            </div>

                            <div class="voucher-scroll-box border rounded-top-3 shadow-xs" style="max-height: 280px; overflow-y: auto; overflow-x: auto;">
                                <table class="table table-sm table-striped table-hover align-middle mb-0 small" style="min-width: 900px;">
                                    <thead class="bg-light sticky-top shadow-xs" style="z-index: 5;">
                                        <tr>
                                            <th class="ps-3 py-2.5 text-nowrap bg-light" style="width: 110px;">Date</th>
                                            <th class="py-2.5 text-nowrap bg-light" style="width: 160px;">Voucher # / Ref</th>
                                            <th class="py-2.5 text-nowrap bg-light" style="width: 170px;">Category / Account</th>
                                            <th class="py-2.5 bg-light" style="min-width: 260px;">Description &amp; Beneficiary</th>
                                            <th class="py-2.5 text-end text-nowrap bg-light" style="width: 130px;">Amount (ETB)</th>
                                            <th class="pe-3 py-2.5 text-center text-nowrap bg-light" style="width: 140px;">Review Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($rep->items as $item)
                                            <tr class="{{ $item->status === 'rejected' ? 'table-danger opacity-75' : ($item->status === 'clarification_needed' ? 'table-warning' : '') }}">
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
                                                <td class="py-2">
                                                    <div style="word-break: break-word; white-space: normal; line-height: 1.4;">
                                                        {{ $item->description }}
                                                    </div>
                                                </td>
                                                <td class="py-2 text-end fw-bold {{ $item->status === 'rejected' ? 'text-decoration-line-through text-muted' : 'text-danger' }} font-monospace text-nowrap">
                                                    ETB {{ number_format($item->amount, 2) }}
                                                </td>
                                                <td class="pe-3 py-2 text-center text-nowrap">
                                                    @if($item->status === 'rejected')
                                                        <span class="badge bg-danger text-white px-2 py-1"><i class="fa-solid fa-ban me-1"></i>Rejected</span>
                                                    @elseif($item->status === 'clarification_needed')
                                                        <span class="badge bg-warning text-dark px-2 py-1"><i class="fa-solid fa-circle-question me-1"></i>Clarification</span>
                                                    @else
                                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1"><i class="fa-solid fa-check me-1"></i>Valid</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center py-4 text-muted">No line item breakdown attached.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- AUDIT VERIFIED REPLENISHMENT AMOUNT & ROUTING TO GM -->
                        <div class="card border-0 shadow-sm rounded-4 p-3 mb-3 bg-white" style="border-left: 5px solid #10b981 !important;">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-7">
                                    <label class="form-label small fw-bold text-dark text-uppercase mb-1.5 d-flex align-items-center gap-1.5">
                                        <i class="fa-solid fa-coins text-warning"></i>
                                        <span>Verified Replenishment Amount (ETB)</span>
                                        <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group input-group-lg shadow-xs">
                                        <span class="input-group-text bg-light fw-bold text-success border-end-0 px-3">ETB</span>
                                        <input type="number" step="0.01" min="0.01" name="replenishment_amount" class="form-control font-monospace fw-bold text-dark fs-4 border-start-0 ps-1" value="{{ (float)($rep->requested_amount ?: $rep->total_expenses_amount) }}" required style="background: #ffffff;">
                                    </div>
                                    <small class="text-muted mt-1.5 d-block" style="font-size: 11.5px;">
                                        <i class="fa-solid fa-circle-info text-info me-1"></i> Specify the audit-cleared top-up amount to route to the General Manager (GM) for final approval.
                                    </small>
                                </div>
                                <div class="col-md-5">
                                    <div class="p-3 bg-light rounded-3 border shadow-xs">
                                        <div class="d-flex justify-content-between small text-muted mb-1.5">
                                            <span>Total Valid Expenses:</span>
                                            <span class="font-monospace fw-bold text-danger">ETB {{ number_format($rep->total_expenses_amount, 2) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between small text-muted mb-1.5">
                                            <span>Current Petty Cash:</span>
                                            <span class="font-monospace fw-bold text-dark">ETB {{ number_format($rep->current_balance_at_request, 2) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between small text-muted align-items-center pt-1 border-top">
                                            <span>Destination Queue:</span>
                                            <span class="badge bg-primary text-white rounded-pill px-2.5 py-1 fw-semibold"><i class="fa-solid fa-user-shield me-1"></i> GM Approvals</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- AUDIT OBSERVATIONS & CLEARANCE REMARKS -->
                        <div class="card border-0 shadow-sm rounded-4 p-3 mb-2 bg-white" style="border-left: 5px solid #0284c7 !important;">
                            <label class="form-label small fw-bold text-dark text-uppercase mb-1.5 d-flex align-items-center gap-1.5">
                                <i class="fa-solid fa-clipboard-check text-info"></i>
                                <span>Audit Verification Notes / Compliance Clearance Statement</span>
                            </label>
                            <textarea name="audit_notes" class="form-control rounded-3 border" rows="3" placeholder="Enter Internal Audit verification findings, sample test results, or clearance instructions...">{{ $rep->audit_notes }}</textarea>
                        </div>

                    </div>
                    <div class="modal-footer border-top py-3 px-4 flex-shrink-0 d-flex justify-content-between align-items-center" style="background: #f8fafc !important;">
                        <div>
                            <button type="button" class="btn btn-outline-danger rounded-pill px-3.5 py-2 shadow-xs fw-semibold" data-bs-toggle="modal" data-bs-target="#activityAuditRejectModal{{ $rep->id }}">
                                <i class="fa-solid fa-ban me-1.5"></i> Reject Replenishment Cycle
                            </button>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <button type="button" class="btn btn-light border rounded-pill px-4 py-2 text-secondary fw-semibold" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-success rounded-pill px-4 py-2 fw-bold shadow-sm" style="background: #10b981; border-color: #10b981;" onclick="return confirm('Pass Audit & Send Replenishment #{{ $rep->request_no }} to GM for Approval?')">
                                <i class="fa-solid fa-paper-plane me-1.5"></i> Pass Audit &amp; Send to GM for Approval
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- 2. Audit Rejection Modal -->
        <div class="modal fade" id="activityAuditRejectModal{{ $rep->id }}" tabindex="-1" aria-hidden="true">
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
@endif

@endsection
