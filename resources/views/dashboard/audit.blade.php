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
<div class="wc-container py-3 py-md-4">

    <!-- Page Header -->
    <x-page-header
        title="Audit &amp; Compliance Dashboard"
        subtitle="Real-time oversight of petty cash replenishment cycles, money movements, expense vouchers, and system audit trails"
        badge="Audit &amp; Compliance"
    >
        <x-slot name="actions">
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <a href="{{ route('finance.replenishments.index', ['tab' => 'under_audit']) }}" class="btn btn-warning text-dark btn-sm rounded-pill px-3 shadow-xs fw-bold" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-hand-holding-dollar me-1"></i> Petty Cash Clearance ({{ $kpi['under_audit_count'] }})
                </a>
                <a href="{{ route('audit.fulfillment', ['tab' => 'paid_not_received']) }}" class="btn btn-danger btn-sm rounded-pill px-3 shadow-xs fw-bold" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> Paid Not Received ({{ $storeAuditKpi['paid_not_received_count'] }})
                </a>
                <a href="{{ route('audit.findings.index') }}" class="btn btn-primary btn-sm rounded-pill px-3 shadow-xs fw-semibold" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-clipboard-question me-1"></i> Audit Findings ({{ $storeAuditKpi['open_findings_count'] }})
                </a>
            </div>
        </x-slot>
    </x-page-header>

    <!-- Store & Financial Audit Quick Navigation Bar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
        <div class="card-body p-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary-subtle text-primary fw-bold px-2.5 py-1.5 rounded-3">
                        <i class="fa-solid fa-shield-halved me-1"></i> Audit Modules
                    </span>
                    <small class="text-muted d-none d-md-inline">Internal audit workspaces and stock controls</small>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('finance.replenishments.index', ['tab' => 'under_audit']) }}" class="btn btn-light border btn-sm rounded-pill px-3 text-dark fw-semibold" style="min-height: 34px; display: inline-flex; align-items: center;">
                        <i class="fa-solid fa-hand-holding-dollar text-warning me-1"></i> Petty Cash Audit
                    </a>
                    <a href="{{ route('audit.inventory') }}" class="btn btn-light border btn-sm rounded-pill px-3 text-dark fw-semibold" style="min-height: 34px; display: inline-flex; align-items: center;">
                        <i class="fa-solid fa-warehouse text-info me-1"></i> Inventory Overview
                    </a>
                    <a href="{{ route('audit.stock-movements') }}" class="btn btn-light border btn-sm rounded-pill px-3 text-dark fw-semibold" style="min-height: 34px; display: inline-flex; align-items: center;">
                        <i class="fa-solid fa-arrows-split-up-and-left text-success me-1"></i> Movement Ledger
                    </a>
                    <a href="{{ route('audit.daily-usage') }}" class="btn btn-light border btn-sm rounded-pill px-3 text-dark fw-semibold" style="min-height: 34px; display: inline-flex; align-items: center;">
                        <i class="fa-solid fa-clipboard-list text-primary me-1"></i> Daily Usage
                    </a>
                    <a href="{{ route('audit.transfers') }}" class="btn btn-light border btn-sm rounded-pill px-3 text-dark fw-semibold" style="min-height: 34px; display: inline-flex; align-items: center;">
                        <i class="fa-solid fa-truck-moving text-warning me-1"></i> Transfers
                    </a>
                    <a href="{{ route('audit.fulfillment') }}" class="btn btn-light border btn-sm rounded-pill px-3 text-dark fw-bold" style="min-height: 34px; display: inline-flex; align-items: center;">
                        <i class="fa-solid fa-boxes-packing text-danger me-1"></i> Intake Fulfillment
                    </a>
                    <a href="{{ route('audit.findings.index') }}" class="btn btn-light border btn-sm rounded-pill px-3 text-dark fw-semibold" style="min-height: 34px; display: inline-flex; align-items: center;">
                        <i class="fa-solid fa-clipboard-question text-secondary me-1"></i> Queries &amp; Findings
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Store & Petty Cash Audit KPI Row -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-2">
            <x-kpi-card
                title="Replenishments Under Audit"
                :value="$kpi['under_audit_count'] . ' Cycles'"
                :subtitle="'ETB ' . number_format($kpi['under_audit_amount'], 2)"
                icon="fa-solid fa-hand-holding-dollar"
                tone="warning"
            />
        </div>

        <div class="col-12 col-sm-6 col-xl-2">
            <x-kpi-card
                title="Unfulfilled Materials"
                :value="$storeAuditKpi['unfulfilled_count'] . ' Lines'"
                :subtitle="'ETB ' . number_format($storeAuditKpi['unfulfilled_value'], 2)"
                icon="fa-solid fa-boxes-stacked"
                tone="info"
            />
        </div>

        <div class="col-12 col-sm-6 col-xl-2">
            <x-kpi-card
                title="🚨 Paid But Not Received"
                :value="$storeAuditKpi['paid_not_received_count'] . ' Items'"
                :subtitle="'Risk: ETB ' . number_format($storeAuditKpi['paid_not_received_value'], 2)"
                icon="fa-solid fa-triangle-exclamation"
                tone="danger"
            />
        </div>

        <div class="col-12 col-sm-6 col-xl-2">
            <x-kpi-card
                title="Transfers In-Transit >48h"
                :value="$storeAuditKpi['transfers_over_48h'] . ' Dispatches'"
                :subtitle="'Total In-Transit: ' . $storeAuditKpi['transfers_pending']"
                icon="fa-solid fa-truck-clock"
                tone="danger"
            />
        </div>

        <div class="col-12 col-sm-6 col-xl-2">
            <x-kpi-card
                title="Stock Discrepancies"
                :value="$storeAuditKpi['negative_stock_count'] . ' Negative'"
                :subtitle="'Dormant (>30d): ' . $storeAuditKpi['dormant_stock_count']"
                icon="fa-solid fa-scale-unbalanced"
                tone="warning"
            />
        </div>

        <div class="col-12 col-sm-6 col-xl-2">
            <x-kpi-card
                title="Open Audit Findings"
                :value="$storeAuditKpi['open_findings_count'] . ' Open'"
                :subtitle="'Overdue (to GM): ' . $storeAuditKpi['overdue_findings_count']"
                icon="fa-solid fa-clipboard-question"
                tone="primary"
            />
        </div>
    </div>

    <!-- PENDING AUDIT CLEARANCE SECTION (Action Required for Internal Auditor) -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white border-top border-4 border-info">
        <div class="card-header bg-white border-bottom py-3 px-3 px-md-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-0 text-dark fs-6">
                    <i class="fa-solid fa-shield-halved text-info me-2"></i>Petty Cash Cycles Routed to Audit (Action Required)
                </h5>
                <small class="text-muted">Replenishments reviewed and forwarded by Finance Head requiring Internal Audit clearance before final disbursement</small>
            </div>
            <span class="badge bg-info text-white font-monospace px-3 py-1.5 rounded-pill fs-6">
                {{ $underAuditReplenishments->count() }} Awaiting Clearance
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive wc-table-mobile-cards">
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
                                <td data-label="Request #" class="ps-4 py-3">
                                    <span class="fw-bold text-dark font-monospace d-block">{{ $rep->request_no }}</span>
                                    <small class="text-muted">{{ $rep->created_at->format('M d, Y H:i') }}</small>
                                </td>
                                <td data-label="Custodian" class="py-3">
                                    <strong>{{ $rep->requester->name ?? 'Staff' }}</strong>
                                </td>
                                <td data-label="Account" class="py-3">
                                    <span class="d-block text-dark fw-semibold">[{{ $rep->chartOfAccount->code ?? 'N/A' }}] {{ $rep->chartOfAccount->name ?? 'Petty Cash' }}</span>
                                    <small class="text-muted font-monospace font-tabular">Bal: ETB {{ number_format($rep->current_balance_at_request, 2) }}</small>
                                </td>
                                <td data-label="Requested Amount" class="py-3 text-end font-tabular">
                                    <strong class="text-primary font-monospace fs-6">
                                        ETB {{ number_format($rep->requested_amount, 2) }}
                                    </strong>
                                </td>
                                <td data-label="Reviewer" class="py-3">
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
                                <td data-label="Vouchers" class="py-3 text-center">
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle font-monospace px-2.5 py-1">
                                        {{ $rep->items->count() }} Vouchers (ETB {{ number_format($rep->total_expenses_amount, 2) }})
                                    </span>
                                </td>
                                <td data-label="Audit Action" class="pe-4 py-3 text-end">
                                    <div class="btn-group btn-group-sm shadow-xs">
                                        <button type="button" class="btn btn-info text-white fw-bold px-3 shadow-xs" style="min-height: 34px;" data-bs-toggle="modal" data-bs-target="#dashboardAuditModal{{ $rep->id }}" title="Audit Review &amp; Clearance Decision">
                                            <i class="fa-solid fa-shield-halved me-1"></i> Review &amp; Decision
                                        </button>
                                        <button type="button" class="btn btn-outline-danger px-2.5" style="min-height: 34px;" data-bs-toggle="modal" data-bs-target="#dashboardAuditRejectModal{{ $rep->id }}" title="Reject Replenishment Cycle">
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
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-header bg-white border-bottom py-3 px-3 px-md-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fa-solid fa-hand-holding-dollar text-warning me-2"></i>Recent Replenishments &amp; Fund Movements
                        </h6>
                        <small class="text-muted">Audit records of money requests, approvals, source account disbursements, and attached expenses</small>
                    </div>
                    <a href="{{ route('finance.replenishments.index') }}" class="btn btn-light btn-sm border rounded-pill px-3" style="min-height: 34px; display: inline-flex; align-items: center;">
                        View All <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive wc-table-mobile-cards">
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
                                        <td data-label="Request #" class="ps-4 py-3">
                                            <span class="fw-bold text-dark font-monospace d-block">{{ $rep->request_no }}</span>
                                            <small class="text-muted">{{ $rep->created_at->format('M d, Y H:i') }}</small>
                                        </td>
                                        <td data-label="Custodian" class="py-3">
                                            <strong>{{ $rep->requester->name ?? 'Staff' }}</strong>
                                        </td>
                                        <td data-label="Account" class="py-3">
                                            <span class="d-block text-dark fw-semibold">[{{ $rep->chartOfAccount->code ?? 'N/A' }}] {{ $rep->chartOfAccount->name ?? 'Petty Cash' }}</span>
                                            <small class="text-muted font-monospace font-tabular">Bal: ETB {{ number_format($rep->current_balance_at_request, 2) }}</small>
                                        </td>
                                        <td data-label="Disbursed Top-Up" class="py-3 text-end font-tabular">
                                            <strong class="text-primary font-monospace fs-6">
                                                ETB {{ number_format($rep->fulfilled_amount ?? $rep->requested_amount, 2) }}
                                            </strong>
                                        </td>
                                        <td data-label="Source Account" class="py-3">
                                            @if($rep->sourceCoa)
                                                <span class="badge bg-light text-dark border">
                                                    [{{ $rep->sourceCoa->code }}] {{ $rep->sourceCoa->name }}
                                                </span>
                                            @else
                                                <span class="text-muted fst-italic">Pending selection</span>
                                            @endif
                                        </td>
                                        <td data-label="Vouchers" class="py-3 text-center">
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle font-monospace px-2 py-1">
                                                {{ $rep->items->count() }} (ETB {{ number_format($rep->total_expenses_amount, 2) }})
                                            </span>
                                        </td>
                                        <td data-label="Status" class="py-3 text-center">
                                            <x-status-badge :status="$rep->status" />
                                        </td>
                                        <td data-label="Action" class="pe-4 py-3 text-end">
                                            <a href="{{ route('finance.replenishments.index', ['search' => $rep->request_no]) }}" class="btn btn-outline-primary btn-sm rounded-pill px-3" style="min-height: 32px; display: inline-flex; align-items: center;">
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


        <!-- Petty Cash & Site Imprest Balances Oversight -->
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 overflow-hidden bg-white">
                <div class="card-header bg-white border-bottom py-3 px-3 px-md-4">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-coins text-warning me-2"></i>Petty Cash &amp; Imprest Balances
                    </h6>
                    <small class="text-muted">Active site petty cash funds monitored for replenishment</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive wc-table-mobile-cards" style="max-height: 380px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="bg-light text-muted text-uppercase" style="font-size: 0.75rem;">
                                <tr>
                                    <th class="ps-4 py-2.5">Account</th>
                                    <th class="py-2.5">Custodian</th>
                                    <th class="pe-4 py-2.5 text-end">Balance (ETB)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($imprestCashAccounts as $acc)
                                    <tr>
                                        <td data-label="Account" class="ps-4 py-2.5">
                                            <strong class="text-dark d-block">[{{ $acc->code }}] {{ $acc->name }}</strong>
                                            <small class="text-muted">{{ $acc->subtype ?? 'Cash Account' }}</small>
                                        </td>
                                        <td data-label="Custodian" class="py-2.5">
                                            <small class="text-dark fw-semibold">{{ $acc->manager->name ?? 'Custodian' }}</small>
                                        </td>
                                        <td data-label="Balance" class="pe-4 py-2.5 text-end font-monospace font-tabular fw-bold {{ $acc->current_balance < 0 ? 'text-danger' : 'text-success' }}">
                                            ETB {{ number_format($acc->current_balance, 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-3 text-muted">No petty cash accounts found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- CRITICAL AUDIT RISK: Paid But Not Received -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white border-top border-4 border-danger">
        <div class="card-header bg-white border-bottom py-3 px-3 px-md-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="fw-bold mb-0 text-danger">
                    <i class="fa-solid fa-triangle-exclamation text-danger me-2"></i>Critical Audit Alert: Paid But Not Received
                </h6>
                <small class="text-muted">Procurement lines where supplier payments have disbursed but goods are unfulfilled at destination store</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-danger text-white font-monospace px-3 py-1.5 rounded-pill fs-6">
                    {{ $storeAuditKpi['paid_not_received_count'] }} Critical Line Items
                </span>
                <a href="{{ route('audit.fulfillment', ['tab' => 'paid_not_received']) }}" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold" style="min-height: 34px; display: inline-flex; align-items: center;">
                    View All Paid-Not-Received <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive wc-table-mobile-cards">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="bg-light text-muted text-uppercase" style="font-size: 0.75rem;">
                        <tr>
                            <th class="ps-4 py-2.5">PR # &amp; Date</th>
                            <th class="py-2.5">Item &amp; SKU</th>
                            <th class="py-2.5">Project / Store</th>
                            <th class="py-2.5 text-center">Purchased / Received</th>
                            <th class="py-2.5 text-end">Remaining Qty</th>
                            <th class="py-2.5 text-center">Payment Status</th>
                            <th class="pe-4 py-2.5 text-end">Audit Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($paidNotReceivedAlerts as $item)
                            <tr class="table-danger bg-opacity-10">
                                <td data-label="PR #" class="ps-4 py-3">
                                    <span class="fw-bold font-monospace text-dark d-block">{{ $item->purchaseRequest?->request_number }}</span>
                                    <small class="text-muted">{{ $item->purchaseRequest?->created_at?->format('M d, Y') }}</small>
                                </td>
                                <td data-label="Item" class="py-3">
                                    <strong class="text-dark d-block">{{ $item->product?->name ?? 'Item' }}</strong>
                                    <small class="text-muted font-monospace">{{ $item->product?->sku ?? 'N/A' }}</small>
                                </td>
                                <td data-label="Project / Store" class="py-3">
                                    <span class="d-block text-dark fw-semibold">{{ $item->purchaseRequest?->project?->name ?? 'Head Office' }}</span>
                                    <small class="text-muted">{{ $item->purchaseRequest?->store?->name ?? 'Main Store' }}</small>
                                </td>
                                <td data-label="Purchased / Received" class="py-3 text-center">
                                    <span class="badge bg-light text-dark border font-monospace">
                                        {{ number_format((float)($item->purchased_quantity ?: $item->quantity), 2) }} / {{ number_format((float)$item->received_quantity, 2) }} {{ $item->unit ?? 'pcs' }}
                                    </span>
                                </td>
                                <td data-label="Remaining" class="py-3 text-end font-monospace font-tabular">
                                    <span class="badge bg-danger text-white fs-6">
                                        {{ number_format(max(0, (float)($item->purchased_quantity ?: $item->quantity) - (float)$item->received_quantity), 2) }} {{ $item->unit ?? 'pcs' }}
                                    </span>
                                </td>
                                <td data-label="Payment" class="py-3 text-center">
                                    <span class="badge bg-success bg-opacity-25 text-success border border-success-subtle font-monospace px-2.5 py-1">
                                        <i class="fa-solid fa-check-circle me-1"></i>DISBURSED / PAID
                                    </span>
                                </td>
                                <td data-label="Audit Action" class="pe-4 py-3 text-end">
                                    <a href="{{ route('audit.fulfillment', ['search' => $item->purchaseRequest?->request_number]) }}" class="btn btn-sm btn-outline-danger rounded-pill px-3 shadow-xs fw-semibold" style="min-height: 32px; display: inline-flex; align-items: center;">
                                        <i class="fa-solid fa-magnifying-glass me-1"></i> Inspect Line
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-shield-check text-success fa-2x mb-2 d-block"></i>
                                    All clear! No critical "Paid But Not Received" items detected in procurement fulfillment.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MATERIAL TRANSFERS IN TRANSIT > 48 HOURS -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white border-top border-4 border-warning">
        <div class="card-header bg-white border-bottom py-3 px-3 px-md-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-truck-clock text-warning me-2"></i>Material Transfers In Transit &gt; 48 Hours
                </h6>
                <small class="text-muted">Inter-store shipments dispatched over 48 hours ago without destination confirmation receipt</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-warning text-dark font-monospace px-3 py-1.5 rounded-pill fs-6">
                    {{ $storeAuditKpi['transfers_over_48h'] }} Dispatches Overdue
                </span>
                <a href="{{ route('audit.transfers', ['flag' => 'in_transit_over_48h']) }}" class="btn btn-sm btn-outline-warning text-dark rounded-pill px-3 fw-semibold" style="min-height: 34px; display: inline-flex; align-items: center;">
                    View All Transfers Audit <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive wc-table-mobile-cards">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="bg-light text-muted text-uppercase" style="font-size: 0.75rem;">
                        <tr>
                            <th class="ps-4 py-2.5">Transfer #</th>
                            <th class="py-2.5">Origin Store</th>
                            <th class="py-2.5">Destination Store</th>
                            <th class="py-2.5">Dispatched Time</th>
                            <th class="py-2.5">Driver &amp; Vehicle</th>
                            <th class="py-2.5 text-center">Status</th>
                            <th class="pe-4 py-2.5 text-end">Audit Inspection</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transfersOver48hList as $tr)
                            <tr class="table-warning bg-opacity-10">
                                <td data-label="Transfer #" class="ps-4 py-3">
                                    <span class="fw-bold font-monospace text-primary d-block">{{ $tr->transfer_no }}</span>
                                    <small class="text-muted">{{ $tr->physical_slip_no ?? 'No slip #' }}</small>
                                </td>
                                <td data-label="Origin" class="py-3">
                                    <strong class="text-dark">{{ $tr->fromStore?->name ?? 'Origin Store' }}</strong>
                                </td>
                                <td data-label="Destination" class="py-3">
                                    <strong class="text-dark">{{ $tr->toStore?->name ?? 'Destination Store' }}</strong>
                                </td>
                                <td data-label="Dispatched" class="py-3">
                                    <span class="text-danger fw-bold d-block">{{ $tr->dispatched_at?->format('M d, Y H:i') }}</span>
                                    <small class="text-muted">({{ $tr->dispatched_at?->diffForHumans() }})</small>
                                </td>
                                <td data-label="Driver" class="py-3">
                                    <span class="d-block text-dark fw-semibold">{{ $tr->vehicle_plate_no ?? 'Vehicle' }}</span>
                                    <small class="text-muted">{{ $tr->driver_employee_id ?? 'N/A' }}</small>
                                </td>
                                <td data-label="Status" class="py-3 text-center">
                                    <span class="badge bg-danger rounded-pill px-2.5 py-1">
                                        <i class="fa-solid fa-clock me-1"></i>&gt;48H IN TRANSIT
                                    </span>
                                </td>
                                <td data-label="Action" class="pe-4 py-3 text-end">
                                    <a href="{{ route('audit.transfers', ['search' => $tr->transfer_no]) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-xs" style="min-height: 32px; display: inline-flex; align-items: center;">
                                        <i class="fa-solid fa-eye me-1"></i> Inspect Transfer
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-circle-check text-success fa-2x mb-2 d-block"></i>
                                    No transfers are currently delayed in transit longer than 48 hours.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- RECENT AUDIT FINDINGS & QUERIES -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white border-top border-4 border-info">
        <div class="card-header bg-white border-bottom py-3 px-3 px-md-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-clipboard-question text-info me-2"></i>Active Audit Queries &amp; Observations
                </h6>
                <small class="text-muted">Open findings issued by Internal Audit requiring storekeeper or procurement response</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-info text-white font-monospace px-3 py-1.5 rounded-pill fs-6">
                    {{ $storeAuditKpi['open_findings_count'] }} Active Findings
                </span>
                <a href="{{ route('audit.findings.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold" style="min-height: 34px; display: inline-flex; align-items: center;">
                    View Findings Hub <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive wc-table-mobile-cards">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="bg-light text-muted text-uppercase" style="font-size: 0.75rem;">
                        <tr>
                            <th class="ps-4 py-2.5">Finding #</th>
                            <th class="py-2.5">Subject / Title</th>
                            <th class="py-2.5">Severity</th>
                            <th class="py-2.5">Source Module</th>
                            <th class="py-2.5">Assigned To</th>
                            <th class="py-2.5">Due Date</th>
                            <th class="pe-4 py-2.5 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentFindings as $f)
                            <tr>
                                <td data-label="Finding #" class="ps-4 py-3">
                                    <span class="fw-bold font-monospace text-primary d-block">{{ $f->finding_number }}</span>
                                    <small class="text-muted">{{ $f->created_at?->format('M d, Y') }}</small>
                                </td>
                                <td data-label="Title" class="py-3">
                                    <strong class="text-dark d-block">{{ $f->title }}</strong>
                                    <small class="text-muted">{{ Str::limit($f->observation, 60) }}</small>
                                </td>
                                <td data-label="Severity" class="py-3">
                                    @php
                                        $sevBadge = match($f->severity) {
                                            'critical' => 'danger',
                                            'high'     => 'warning',
                                            'medium'   => 'info',
                                            default    => 'secondary',
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $sevBadge }} text-uppercase font-monospace px-2.5 py-1">
                                        {{ $f->severity }}
                                    </span>
                                </td>
                                <td data-label="Source" class="py-3">
                                    <span class="badge bg-light text-dark border">
                                        {{ ucfirst(str_replace('_', ' ', $f->source_type)) }}
                                    </span>
                                </td>
                                <td data-label="Assigned To" class="py-3">
                                    <span class="text-dark fw-semibold d-block">{{ $f->assignedTo?->name ?? 'Unassigned' }}</span>
                                </td>
                                <td data-label="Due Date" class="py-3">
                                    @if($f->is_overdue)
                                        <span class="badge bg-danger rounded-pill px-2 py-1">OVERDUE</span>
                                    @else
                                        <span class="text-muted">{{ $f->due_date ? $f->due_date->format('M d, Y') : 'N/A' }}</span>
                                    @endif
                                </td>
                                <td data-label="Action" class="pe-4 py-3 text-end">
                                    <a href="{{ route('audit.findings.show', $f->id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-xs" style="min-height: 32px; display: inline-flex; align-items: center;">
                                        <i class="fa-solid fa-eye me-1"></i> Review
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    No audit findings recorded yet.
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
                <form method="POST" action="{{ url('/assigned-accounts/' . $rep->chart_of_account_id . '/replenishments/' . $rep->id . '/audit-approve') }}" class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
                    @csrf
                    <div class="modal-header text-white py-3 px-4 flex-shrink-0" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-bottom: 3px solid #0284c7;">
                        <div class="d-flex align-items-center gap-3">
                            <div class="p-2 bg-info bg-opacity-25 rounded-3 text-info">
                                <i class="fa-solid fa-shield-halved fa-lg"></i>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge rounded-pill px-2.5 py-1 fw-bold text-uppercase" style="background: #0284c7; color: #ffffff; font-size: 10px; letter-spacing: 0.5px;">Internal Audit</span>
                                    <h5 class="modal-title fw-bold mb-0 text-white fs-6">Review &amp; Clearance #{{ $rep->request_no }}</h5>
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
                                    <h4 class="fw-bold text-dark font-monospace font-tabular mb-0 mt-1">ETB {{ number_format($rep->current_balance_at_request, 2) }}</h4>
                                    <small class="text-muted">Account: {{ $rep->chartOfAccount->name ?? 'N/A' }}</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-white rounded-3 border shadow-xs" style="border-left: 4px solid #ef4444 !important;">
                                    <span class="text-muted small text-uppercase fw-bold d-block" style="font-size: 11px;">Valid Attached Expenses</span>
                                    <h4 class="fw-bold text-danger font-monospace font-tabular mb-0 mt-1">ETB {{ number_format($rep->total_expenses_amount, 2) }}</h4>
                                    <small class="text-muted">{{ $rep->items->count() }} Attached Vouchers</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-white rounded-3 border shadow-xs" style="border-left: 4px solid #0284c7 !important;">
                                    <span class="text-muted small text-uppercase fw-bold d-block" style="font-size: 11px;">Requested Top-Up Amount</span>
                                    <h4 class="fw-bold font-monospace font-tabular mb-0 mt-1" style="color: #0284c7 !important;">ETB {{ number_format($rep->requested_amount, 2) }}</h4>
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

                        <!-- Attached Itemized Expenses Table (With Select, Reject, Ask Description) -->
                        <div class="card border-0 shadow-sm rounded-3 p-3 mb-3 bg-white">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <div>
                                    <label class="form-label fw-bold text-dark mb-0">
                                        <i class="fa-solid fa-list-check text-primary me-1"></i> Attached Expense Vouchers ({{ $rep->items->count() }} Records)
                                    </label>
                                    <small class="text-muted d-block" style="font-size:11px;">Internal Auditor: Examine line vouchers, request custodian notes, or exclude invalid vouchers before clearance</small>
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    <span class="badge bg-light text-dark border font-monospace px-3 py-1.5 fs-6 font-tabular">Valid Total: ETB {{ number_format($rep->total_expenses_amount, 2) }}</span>
                                </div>
                            </div>

                            <div class="voucher-scroll-box border rounded-top-3 shadow-xs" style="max-height: 280px; overflow-y: auto; overflow-x: auto;">
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
                                                <td class="py-2 text-end fw-bold {{ $item->status === 'rejected' ? 'text-decoration-line-through text-muted' : 'text-danger' }} font-monospace font-tabular text-nowrap">
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
                                <span class="text-danger font-monospace fs-6 font-tabular">Grand Total: ETB {{ number_format($rep->items->sum('amount') ?: $rep->total_expenses_amount, 2) }}</span>
                            </div>
                            @endif
                        </div>

                        <!-- AUDIT VERIFIED REPLENISHMENT AMOUNT & ROUTING TO GM -->
                        <div class="card border-0 shadow-sm rounded-3 p-3 mb-3 bg-white" style="border-left: 5px solid #10b981 !important;">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-7">
                                    <label class="form-label small fw-bold text-dark text-uppercase mb-1.5 d-flex align-items-center gap-1.5">
                                        <i class="fa-solid fa-coins text-warning"></i>
                                        <span>Verified Replenishment Amount (ETB)</span>
                                        <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group input-group-lg shadow-xs">
                                        <span class="input-group-text bg-light fw-bold text-success border-end-0 px-3">ETB</span>
                                        <input type="number" step="0.01" min="0.01" name="replenishment_amount" class="form-control font-monospace fw-bold text-dark fs-4 border-start-0 ps-1 font-tabular" value="{{ (float)($rep->requested_amount ?: $rep->total_expenses_amount) }}" required style="background: #ffffff;">
                                    </div>
                                    <small class="text-muted mt-1.5 d-block" style="font-size: 11.5px;">
                                        <i class="fa-solid fa-circle-info text-info me-1"></i> Specify the audit-cleared top-up amount to route to the General Manager (GM) for final approval.
                                    </small>
                                </div>
                                <div class="col-md-5">
                                    <div class="p-3 bg-light rounded-3 border shadow-xs">
                                        <div class="d-flex justify-content-between small text-muted mb-1.5 font-tabular">
                                            <span>Total Valid Expenses:</span>
                                            <span class="font-monospace fw-bold text-danger">ETB {{ number_format($rep->total_expenses_amount, 2) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between small text-muted mb-1.5 font-tabular">
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
                        <div class="card border-0 shadow-sm rounded-3 p-3 mb-2 bg-white" style="border-left: 5px solid #0284c7 !important;">
                            <label class="form-label small fw-bold text-dark text-uppercase mb-1.5 d-flex align-items-center gap-1.5">
                                <i class="fa-solid fa-clipboard-check text-info"></i>
                                <span>Audit Verification Notes / Compliance Clearance Statement</span>
                            </label>
                            <textarea name="audit_notes" class="form-control rounded-3 border" rows="3" placeholder="Enter Internal Audit verification findings, sample test results, or clearance instructions...">{{ $rep->audit_notes }}</textarea>
                        </div>

                    </div>
                    <div class="modal-footer border-top py-3 px-4 flex-shrink-0 d-flex justify-content-between align-items-center" style="background: #f8fafc !important;">
                        <div>
                            <button type="button" class="btn btn-outline-danger rounded-pill px-3.5 py-2 shadow-xs fw-semibold" style="min-height: 38px;" data-bs-toggle="modal" data-bs-target="#dashboardAuditRejectModal{{ $rep->id }}">
                                <i class="fa-solid fa-ban me-1.5"></i> Reject Replenishment Cycle
                            </button>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <button type="button" class="btn btn-light border rounded-pill px-4 py-2 text-secondary fw-semibold" style="min-height: 38px;" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-success rounded-pill px-4 py-2 fw-bold shadow-xs" style="background: #10b981; border-color: #10b981; min-height: 38px;" onclick="return confirm('Pass Audit & Send Replenishment #{{ $rep->request_no }} to GM for Approval?')">
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
                <form method="POST" action="{{ url('/assigned-accounts/' . $rep->chart_of_account_id . '/replenishments/' . $rep->id . '/audit-reject') }}" class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
                    @csrf
                    <div class="modal-header bg-danger text-white py-3 px-4 flex-shrink-0">
                        <h5 class="modal-title fw-bold mb-0 fs-6"><i class="fa-solid fa-shield-xmark me-2"></i>Audit Reject: #{{ $rep->request_no }}</h5>
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
                        <button type="button" class="btn btn-light border rounded-pill px-4" data-bs-dismiss="modal" style="min-height: 36px;">Cancel</button>
                        <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold shadow-xs" style="min-height: 36px;">
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
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
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
                    <button type="button" class="btn btn-light btn-sm rounded-pill" data-bs-dismiss="modal" style="min-height: 32px;">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm rounded-pill fw-bold" style="min-height: 32px;">Reject Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="askDescriptionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
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
                    <button type="button" class="btn btn-light btn-sm rounded-pill" data-bs-dismiss="modal" style="min-height: 32px;">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark btn-sm rounded-pill fw-bold" style="min-height: 32px;">Send Inquiry</button>
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
