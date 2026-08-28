@extends('layouts.app')
@section('title', 'Petty Cash Replenishment Approvals - Finance Head')

@section('content')
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
                    <p class="text-muted small mb-0">Finance Head portal to review attached expenses, verify imprest cycles, and disburse top-up funds</p>
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
        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white" style="border-left: 5px solid #f59e0b !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-xs fw-bold text-uppercase text-warning" style="font-size:0.72rem; letter-spacing:0.5px;">Pending Finance Head Review</span>
                        <h3 class="fw-bold text-dark mb-0 mt-1">{{ $tabCounts['pending'] }} <span class="fs-6 text-muted fw-normal">Requests</span></h3>
                        <small class="text-danger fw-semibold">Total: ETB {{ number_format($pendingAmount, 2) }}</small>
                    </div>
                    <div class="p-3 rounded-circle" style="background: rgba(245, 158, 11, 0.1);">
                        <i class="fa-solid fa-clock fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
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

        <div class="col-xl-4 col-md-12">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white" style="border-left: 5px solid #6366f1 !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-xs fw-bold text-uppercase text-primary" style="font-size:0.72rem; letter-spacing:0.5px;">All Replenishment Cycles</span>
                        <h3 class="fw-bold text-dark mb-0 mt-1">{{ $tabCounts['all'] }} <span class="fs-6 text-muted fw-normal">Total Cycles</span></h3>
                        <small class="text-muted">Imprest System Top-ups</small>
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
                        <i class="fa-solid fa-hourglass-half me-1"></i> Pending Approval
                        <span class="badge {{ $activeTab === 'pending' ? 'bg-dark text-white' : 'bg-warning text-dark' }} ms-1">{{ $tabCounts['pending'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold py-2 {{ $activeTab === 'fulfilled' ? 'active shadow-sm bg-success text-white' : 'text-secondary bg-white' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'fulfilled', 'page' => 1]) }}">
                        <i class="fa-solid fa-circle-check me-1"></i> Fulfilled &amp; Disbursed
                        <span class="badge {{ $activeTab === 'fulfilled' ? 'bg-white text-success' : 'bg-success text-white' }} ms-1">{{ $tabCounts['fulfilled'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold py-2 {{ $activeTab === 'rejected' ? 'active shadow-sm bg-danger text-white' : 'text-secondary bg-white' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'rejected', 'page' => 1]) }}">
                        <i class="fa-solid fa-circle-xmark me-1"></i> Rejected
                        <span class="badge {{ $activeTab === 'rejected' ? 'bg-white text-danger' : 'bg-danger text-white' }} ms-1">{{ $tabCounts['rejected'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold py-2 {{ $activeTab === 'all' ? 'active shadow-sm bg-dark text-white' : 'text-secondary bg-white' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'all', 'page' => 1]) }}">
                        <i class="fa-solid fa-list me-1"></i> All Requests
                        <span class="badge {{ $activeTab === 'all' ? 'bg-primary text-white' : 'bg-secondary' }} ms-1">{{ $tabCounts['all'] }}</span>
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
                            <th class="py-3 text-center">Attached Expenses</th>
                            <th class="py-3 text-center">Status</th>
                            <th class="pe-4 py-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($replenishments as $rep)
                            <tr>
                                <td class="ps-4 py-3">
                                    <span class="fw-bold text-dark font-monospace d-block">{{ $rep->request_no }}</span>
                                    <small class="text-muted">{{ $rep->created_at->format('M d, Y H:i') }}</small>
                                </td>
                                <td class="py-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:34px; height:34px; font-size:0.85rem;">
                                            {{ substr($rep->requester->name ?? 'U', 0, 1) }}
                                        </div>
                                        <div>
                                            <strong class="text-dark d-block">{{ $rep->requester->name ?? 'Custodian' }}</strong>
                                            <small class="text-muted">{{ $rep->requester->roles->pluck('name')->implode(', ') ?: 'Finance Staff' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <strong class="text-dark d-block">[{{ $rep->chartOfAccount->code ?? 'N/A' }}] {{ $rep->chartOfAccount->name ?? 'Petty Cash' }}</strong>
                                    <small class="text-muted">{{ $rep->chartOfAccount->subtype ?? ucfirst($rep->chartOfAccount->type ?? 'Cash Asset') }}</small>
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
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle font-monospace px-2 py-1">
                                        <i class="fa-solid fa-receipt me-1"></i> {{ $rep->items->count() }} Records (ETB {{ number_format($rep->total_expenses_amount, 2) }})
                                    </span>
                                </td>
                                <td class="py-3 text-center">
                                    @if($rep->status === 'pending')
                                        <span class="badge bg-warning text-dark rounded-pill px-3 py-1.5 font-monospace">
                                            <i class="fa-solid fa-clock me-1"></i>Pending Approval
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
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-light border" data-bs-toggle="modal" data-bs-target="#viewModal{{ $rep->id }}" title="Audit & View Details">
                                            <i class="fa-solid fa-eye text-primary me-1"></i> Audit Details
                                        </button>
                                        @if($rep->status === 'pending' && auth()->check() && auth()->user()->hasAnyRole(['Finance head', 'finance_head', 'finance_manager', 'admin', 'global_admin']))
                                            <button type="button" class="btn btn-success text-white fw-bold shadow-xs px-3" data-bs-toggle="modal" data-bs-target="#fulfillModal{{ $rep->id }}">
                                                <i class="fa-solid fa-check me-1"></i> Review &amp; Fulfill
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $rep->id }}" title="Reject Request">
                                                <i class="fa-solid fa-times"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
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
<!-- MODALS FOR EACH REPLENISHMENT (Review, Fulfill, Reject, View History)      -->
<!-- ========================================================================= -->
@foreach($replenishments as $rep)

    <!-- 1. Fulfill & Review Modal -->
    @if($rep->status === 'pending')
    <div class="modal fade" id="fulfillModal{{ $rep->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-warning text-dark py-3 px-4">
                    <div>
                        <h5 class="modal-title fw-bold mb-0">
                            <i class="fa-solid fa-hand-holding-dollar me-2"></i>Review &amp; Approve Replenishment #{{ $rep->request_no }}
                        </h5>
                        <small class="text-dark opacity-75">Requested by {{ $rep->requester->name ?? 'Staff' }} on {{ $rep->created_at->format('M d, Y H:i') }}</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <form method="POST" action="{{ route('assigned-accounts.fulfill-replenishment', ['id' => $rep->chart_of_account_id, 'replenishmentId' => $rep->id]) }}">
                    @csrf
                    <div class="modal-body p-4 bg-white">

                        <!-- Top Balance Stats -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3 border">
                                    <span class="text-muted small text-uppercase fw-bold d-block">Current Petty Cash Balance</span>
                                    <h4 class="fw-bold text-dark font-monospace mb-0 mt-1">ETB {{ number_format($rep->current_balance_at_request, 2) }}</h4>
                                    <small class="text-muted">Account: {{ $rep->chartOfAccount->name ?? 'N/A' }}</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3 border">
                                    <span class="text-muted small text-uppercase fw-bold d-block">Attached Expenses Total</span>
                                    <h4 class="fw-bold text-danger font-monospace mb-0 mt-1">ETB {{ number_format($rep->total_expenses_amount, 2) }}</h4>
                                    <small class="text-muted">{{ $rep->items->count() }} Attached Vouchers</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-primary bg-opacity-10 rounded-3 border border-primary-subtle">
                                    <span class="text-primary small text-uppercase fw-bold d-block">Requested Top-Up Amount</span>
                                    <h4 class="fw-bold text-primary font-monospace mb-0 mt-1">ETB {{ number_format($rep->requested_amount, 2) }}</h4>
                                    <small class="text-primary">Imprest Cycle Restoration</small>
                                </div>
                            </div>
                        </div>

                        <!-- Attached Payment History Table -->
                        <!-- Attached Itemized Expenses Table -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label fw-bold text-dark mb-0">
                                    <i class="fa-solid fa-paperclip text-primary me-1"></i> Attached Expense Vouchers ({{ $rep->items->count() }} Records)
                                </label>
                                <span class="badge bg-light text-dark border font-monospace">Total: ETB {{ number_format($rep->total_expenses_amount, 2) }}</span>
                            </div>
                            <div class="border rounded-3 overflow-hidden shadow-xs" style="max-height: 420px; overflow-y: auto;">
                                <table class="table table-sm table-striped table-hover align-middle mb-0 small">
                                    <thead class="bg-light sticky-top shadow-xs">
                                        <tr>
                                            <th class="ps-3 py-2 text-nowrap">Date</th>
                                            <th class="py-2 text-nowrap">Voucher # / Ref</th>
                                            <th class="py-2 text-nowrap">Expense Category / Account</th>
                                            <th class="py-2" style="min-width: 280px;">Description &amp; Beneficiary Details</th>
                                            <th class="pe-3 py-2 text-end text-nowrap">Amount (ETB)</th>
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
                                                <td class="py-2">
                                                    <div style="word-break: break-word; white-space: normal; line-height: 1.4;">
                                                        {{ $item->description }}
                                                    </div>
                                                </td>
                                                <td class="pe-3 py-2 text-end fw-bold text-danger font-monospace text-nowrap">
                                                    ETB {{ number_format($item->amount, 2) }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center py-4 text-muted">No line item breakdown attached.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    @if($rep->items->count() > 0)
                                    <tfoot class="bg-light sticky-bottom fw-bold border-top">
                                        <tr>
                                            <td colspan="4" class="ps-3 py-2 text-dark">Total ({{ $rep->items->count() }} Vouchers):</td>
                                            <td class="pe-3 py-2 text-end text-danger font-monospace">ETB {{ number_format($rep->items->sum('amount') ?: $rep->total_expenses_amount, 2) }}</td>
                                        </tr>
                                    </tfoot>
                                    @endif
                                </table>
                            </div>
                        </div>

                        <!-- Custodian Notes & Attachment Link -->
                        <div class="row g-3 mb-4">
                            @if($rep->notes)
                            <div class="col-md-8">
                                <label class="form-label small fw-bold text-dark text-uppercase">Custodian Notes / Justification</label>
                                <div class="p-3 bg-light rounded-3 border text-dark">{{ $rep->notes }}</div>
                            </div>
                            @endif
                            @if($rep->attachment_path)
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-dark text-uppercase">Supporting Document</label>
                                <div>
                                    <a href="{{ \App\Services\FileUploadService::url($rep->attachment_path) }}" target="_blank" class="btn btn-outline-primary btn-sm w-100 py-2">
                                        <i class="fa-solid fa-download me-1"></i> View Scanned Receipts
                                    </a>
                                </div>
                            </div>
                            @endif
                        </div>

                        <hr class="my-4">

                        <!-- FULFILLMENT FORM -->
                        <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-money-bill-transfer text-success me-1"></i> Disburse Top-Up Funds</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark text-uppercase">Source Funding Account (Bank / Central Cash) <span class="text-danger">*</span></label>
                                <select name="source_coa_id" class="form-select" required>
                                    <option value="">-- Select Source Account to Deduct Funds --</option>
                                    @foreach($sourceAccounts as $src)
                                        @if($src->id !== $rep->chart_of_account_id)
                                            <option value="{{ $src->id }}">
                                                [{{ $src->code }}] {{ $src->name }} (Balance: ETB {{ number_format($src->current_balance, 2) }})
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark text-uppercase">Fulfilled / Disbursed Amount (ETB) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="fulfilled_amount" class="form-control font-monospace fw-bold text-primary" value="{{ $rep->requested_amount }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark text-uppercase">Transaction Reference / Cheque #</label>
                                <input type="text" name="reference" class="form-control" placeholder="e.g. TR-20260827-01 / Cheque #4920">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark text-uppercase">Finance Head Remarks</label>
                                <input type="text" name="finance_notes" class="form-control" placeholder="Optional notes for custodian">
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer bg-light border-0 py-3 px-4">
                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" onclick="return confirm('Confirm and disburse replenishment top-up for {{ $rep->chartOfAccount->name }}?')">
                            <i class="fa-solid fa-check-circle me-1"></i> Approve &amp; Disburse Funds
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
                <form method="POST" action="{{ route('assigned-accounts.reject-replenishment', ['id' => $rep->chart_of_account_id, 'replenishmentId' => $rep->id]) }}">
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
    @endif

    <!-- 3. View Details Modal (For all states) -->
    <div class="modal fade" id="viewModal{{ $rep->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
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
                                        <span class="badge bg-warning text-dark px-3 py-1.5 rounded-pill">Pending Approval</span>
                                    @elseif($rep->status === 'fulfilled')
                                        <span class="badge bg-success text-white px-3 py-1.5 rounded-pill">Fulfilled &amp; Disbursed</span>
                                    @elseif($rep->status === 'rejected')
                                        <span class="badge bg-danger text-white px-3 py-1.5 rounded-pill">Rejected</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Attached Items Table -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-bold text-dark mb-0">
                                <i class="fa-solid fa-list-check text-primary me-1"></i> Attached Expense Vouchers ({{ $rep->items->count() }})
                            </label>
                            <span class="badge bg-light text-dark border font-monospace">Total: ETB {{ number_format($rep->total_expenses_amount, 2) }}</span>
                        </div>
                        <div class="border rounded-3 overflow-hidden shadow-xs" style="max-height: 460px; overflow-y: auto;">
                            <table class="table table-sm table-striped table-hover align-middle mb-0 small">
                                <thead class="bg-light sticky-top shadow-xs">
                                    <tr>
                                        <th class="ps-3 py-2 text-nowrap">Date</th>
                                        <th class="py-2 text-nowrap">Voucher # / Ref</th>
                                        <th class="py-2 text-nowrap">Expense Category / Account</th>
                                        <th class="py-2" style="min-width: 280px;">Description &amp; Beneficiary Details</th>
                                        <th class="pe-3 py-2 text-end text-nowrap">Amount (ETB)</th>
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
                                            <td class="py-2">
                                                <div style="word-break: break-word; white-space: normal; line-height: 1.4;">
                                                    {{ $item->description }}
                                                </div>
                                            </td>
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
                                @if($rep->items->count() > 0)
                                <tfoot class="bg-light sticky-bottom fw-bold border-top">
                                    <tr>
                                        <td colspan="4" class="ps-3 py-2 text-dark">Total ({{ $rep->items->count() }} Vouchers):</td>
                                        <td class="pe-3 py-2 text-end text-danger font-monospace">ETB {{ number_format($rep->items->sum('amount') ?: $rep->total_expenses_amount, 2) }}</td>
                                    </tr>
                                </tfoot>
                                @endif
                            </table>
                        </div>
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

@endsection
