@extends('layouts.app')
@section('title', 'Purchase → Intake Fulfillment Audit')

@section('content')
<div class="wc-container py-3 py-md-4">

    <!-- Page Header -->
    <x-page-header
        title="Purchase &rarr; Store Intake Fulfillment Audit"
        subtitle="Line-by-line reconciliation of Requested &rarr; Approved &rarr; Purchased &rarr; Store Received &rarr; Remaining quantities and supplier payment surveillance"
        badge="Audit &bull; Strictly Read-Only"
    >
        <x-slot name="actions">
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-outline-success btn-sm rounded-pill px-3 shadow-xs fw-semibold" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-file-excel me-1"></i> Export to Excel / CSV
                </a>
                <button type="button" class="btn btn-warning text-dark btn-sm rounded-pill px-3 shadow-xs fw-bold" style="min-height: 38px; display: inline-flex; align-items: center;" data-bs-toggle="modal" data-bs-target="#raiseFindingModal" data-source-type="fulfillment">
                    <i class="fa-solid fa-plus-circle me-1"></i> Raise Audit Finding
                </button>
            </div>
        </x-slot>
    </x-page-header>

    <!-- Audit Navigation Pills -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
        <div class="card-body p-2.5">
            <ul class="nav nav-pills gap-1 flex-wrap">
                <li class="nav-item">
                    <a class="nav-link rounded-pill fw-semibold px-3 py-1.5 text-dark" href="{{ route('audit.inventory') }}">
                        <i class="fa-solid fa-warehouse me-1 text-info"></i> Inventory Overview
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill fw-semibold px-3 py-1.5 text-dark" href="{{ route('audit.stock-movements') }}">
                        <i class="fa-solid fa-arrows-split-up-and-left me-1 text-success"></i> Movement Ledger
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill fw-semibold px-3 py-1.5 text-dark" href="{{ route('audit.daily-usage') }}">
                        <i class="fa-solid fa-clipboard-list me-1 text-primary"></i> Daily Usage
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill fw-semibold px-3 py-1.5 text-dark" href="{{ route('audit.transfers') }}">
                        <i class="fa-solid fa-truck-moving me-1 text-warning"></i> Material Transfers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active rounded-pill fw-semibold px-3 py-1.5" href="{{ route('audit.fulfillment') }}">
                        <i class="fa-solid fa-boxes-packing me-1 text-danger"></i> Intake Fulfillment
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill fw-semibold px-3 py-1.5 text-dark" href="{{ route('audit.findings.index') }}">
                        <i class="fa-solid fa-clipboard-question me-1 text-secondary"></i> Findings &amp; Queries
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Top KPI Row -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Unfulfilled Materials"
                :value="number_format($kpiUnfulfilledCount) . ' Items'"
                :subtitle="'Outstanding: ETB ' . number_format($kpiUnfulfilledValue, 2)"
                icon="fa-solid fa-hourglass-half"
                tone="warning"
            />
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="🚨 Paid But Not Received"
                :value="number_format($kpiPaidNotReceivedCount) . ' Items'"
                :subtitle="'Disbursed Risk: ETB ' . number_format($kpiPaidNotReceivedValue, 2)"
                icon="fa-solid fa-triangle-exclamation"
                :tone="$kpiPaidNotReceivedCount > 0 ? 'danger' : 'success'"
            />
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <a href="{{ request()->fullUrlWithQuery(['tab' => 'completed', 'page' => 1]) }}" class="text-decoration-none">
                <x-kpi-card
                    title="Completed &amp; Fulfilled"
                    :value="number_format($kpiCompletedCount ?? $kpiFulfilledCount) . ' Lines'"
                    subtitle="Closed or verified store intake"
                    icon="fa-solid fa-circle-check"
                    tone="success"
                />
            </a>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <a href="{{ request()->fullUrlWithQuery(['tab' => 'all', 'page' => 1]) }}" class="text-decoration-none">
                <x-kpi-card
                    title="Total Lines Tracked"
                    :value="number_format($kpiTotalLines) . ' Lines'"
                    subtitle="Active procurement purchase items"
                    icon="fa-solid fa-boxes-stacked"
                    tone="primary"
                />
            </a>
        </div>
    </div>

    <!-- Fulfillment Primary Filter & Category Tabs -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div class="btn-group shadow-xs rounded-pill p-1 bg-white border">
            <a href="{{ request()->fullUrlWithQuery(['tab' => 'unfulfilled', 'page' => 1]) }}" class="btn btn-sm rounded-pill px-3 fw-bold {{ $tab === 'unfulfilled' ? 'btn-warning text-dark' : 'btn-light text-muted border-0' }}">
                <i class="fa-solid fa-boxes-stacked me-1"></i> Unfulfilled Materials
                <span class="badge bg-dark text-white rounded-pill ms-1">{{ $kpiUnfulfilledCount }}</span>
            </a>
            <a href="{{ request()->fullUrlWithQuery(['tab' => 'paid_not_received', 'page' => 1]) }}" class="btn btn-sm rounded-pill px-3 fw-bold {{ $tab === 'paid_not_received' ? 'btn-danger text-white' : 'btn-light text-danger border-0' }}">
                <i class="fa-solid fa-triangle-exclamation me-1"></i> 🚨 Paid But Not Received
                <span class="badge bg-white text-danger rounded-pill ms-1 fw-bold">{{ $kpiPaidNotReceivedCount }}</span>
            </a>
            <a href="{{ request()->fullUrlWithQuery(['tab' => 'completed', 'page' => 1]) }}" class="btn btn-sm rounded-pill px-3 fw-bold {{ $tab === 'completed' ? 'btn-success text-white' : 'btn-light text-muted border-0' }}">
                <i class="fa-solid fa-circle-check me-1"></i> Completed &amp; Fulfilled
                <span class="badge bg-white text-success rounded-pill ms-1 fw-bold">{{ $kpiCompletedCount ?? $kpiFulfilledCount }}</span>
            </a>
            <a href="{{ request()->fullUrlWithQuery(['tab' => 'all', 'page' => 1]) }}" class="btn btn-sm rounded-pill px-3 fw-bold {{ $tab === 'all' ? 'btn-primary' : 'btn-light text-muted border-0' }}">
                <i class="fa-solid fa-list-check me-1"></i> All Materials
                <span class="badge bg-secondary rounded-pill ms-1">{{ $kpiTotalLines }}</span>
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('audit.fulfillment') }}" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="{{ $tab }}">

                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">PROJECT / SITE</label>
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">All Projects</option>
                        @foreach($projects as $pj)
                            <option value="{{ $pj->id }}" {{ request('project_id') == $pj->id ? 'selected' : '' }}>
                                {{ $pj->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">DESTINATION STORE</label>
                    <select name="store_id" class="form-select form-select-sm">
                        <option value="">All Destination Stores</option>
                        @foreach($stores as $st)
                            <option value="{{ $st->id }}" {{ request('store_id') == $st->id ? 'selected' : '' }}>
                                {{ $st->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">FULFILLMENT STATUS</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="not_received" {{ request('status') == 'not_received' ? 'selected' : '' }}>Not Received (0% intake)</option>
                        <option value="partially_fulfilled" {{ request('status') == 'partially_fulfilled' ? 'selected' : '' }}>Partially Fulfilled</option>
                        <option value="fully_fulfilled" {{ request('status') == 'fully_fulfilled' ? 'selected' : '' }}>Fully Fulfilled (100%)</option>
                        <option value="closed_not_accepted" {{ request('status') == 'closed_not_accepted' ? 'selected' : '' }}>Closed (Not Accepted)</option>
                        <option value="partially_accepted_closed" {{ request('status') == 'partially_accepted_closed' ? 'selected' : '' }}>Closed (Partial Intake)</option>
                        <option value="over_received" {{ request('status') == 'over_received' ? 'selected' : '' }}>Over-Received (&gt;100%)</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">SEARCH</label>
                    <input type="text" name="search" class="form-control form-select-sm" placeholder="PR #, Item name, SKU..." value="{{ request('search') }}">
                </div>

                <div class="col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill w-100 fw-semibold" style="height: 31px;">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    @if(request()->hasAny(['project_id', 'store_id', 'status', 'search']))
                        <a href="{{ route('audit.fulfillment', ['tab' => $tab]) }}" class="btn btn-light btn-sm border rounded-pill" title="Reset Filters" style="height: 31px;">
                            <i class="fa-solid fa-rotate-left"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Fulfillment Table -->
    <div class="card border-0 shadow-sm rounded-3 bg-white mb-4">
        <div class="card-header bg-white border-bottom py-3 px-3 px-md-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-boxes-packing text-primary me-2"></i>Purchase &rarr; Store Intake Fulfillment Ledger
                </h6>
                <small class="text-muted">Showing {{ $paginatedItems->firstItem() ?? 0 }} to {{ $paginatedItems->lastItem() ?? 0 }} of {{ $paginatedItems->total() }} matching lines</small>
            </div>
            <span class="badge bg-light text-dark border font-monospace px-3 py-1.5 rounded-pill">
                Page {{ $paginatedItems->currentPage() }} of {{ $paginatedItems->lastPage() }}
            </span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive wc-table-mobile-cards">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="bg-light text-muted text-uppercase" style="font-size: 0.75rem;">
                        <tr>
                            <th class="ps-4 py-2.5">PR # &amp; Date</th>
                            <th class="py-2.5">Item &amp; SKU</th>
                            <th class="py-2.5">Project / Store</th>
                            <th class="py-2.5 text-end">Requested</th>
                            <th class="py-2.5 text-end">Purchased</th>
                            <th class="py-2.5 text-end">Received at Store</th>
                            <th class="py-2.5 text-end">Remaining Qty</th>
                            <th class="py-2.5 text-center">Fulfillment Status</th>
                            <th class="py-2.5 text-center">Payment Status</th>
                            <th class="pe-4 py-2.5 text-end">Audit Inspection</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($paginatedItems as $row)
                            @php
                                $isPaidNotRecv = $row->is_paid_but_not_received;
                            @endphp
                            <tr class="{{ $isPaidNotRecv ? 'table-danger bg-opacity-10' : '' }}">
                                <td data-label="PR #" class="ps-4 py-3">
                                    <span class="fw-bold font-monospace text-primary d-block">{{ $row->pr?->pr_no ?? $row->pr?->request_number }}</span>
                                    <small class="text-muted">{{ $row->pr?->created_at?->format('M d, Y') }} ({{ $row->days_since_purchase }}d ago)</small>
                                </td>
                                <td data-label="Item" class="py-3">
                                    <strong class="text-dark d-block">{{ $row->product?->name ?? 'Unknown Product' }}</strong>
                                    <small class="text-muted font-monospace">SKU: {{ $row->product?->sku ?? 'N/A' }}</small>
                                    @if($row->item->supplier)
                                        <small class="text-secondary d-block"><i class="fa-solid fa-truck-field me-1"></i>{{ $row->item->supplier->name }}</small>
                                    @endif
                                </td>
                                <td data-label="Project / Store" class="py-3">
                                    <span class="d-block text-dark fw-semibold">{{ $row->pr?->project?->name ?? 'Head Office' }}</span>
                                    <small class="text-muted"><i class="fa-solid fa-warehouse me-1"></i>{{ $row->pr?->store?->name ?? 'Main Store' }}</small>
                                </td>
                                <td data-label="Requested" class="py-3 text-end font-monospace font-tabular text-muted">
                                    {{ number_format($row->requested_qty, 2) }} {{ $row->product?->unit ?? 'pcs' }}
                                </td>
                                <td data-label="Purchased" class="py-3 text-end font-monospace font-tabular text-dark fw-semibold">
                                    {{ number_format($row->purchased_qty, 2) }}
                                </td>
                                <td data-label="Received" class="py-3 text-end font-monospace font-tabular">
                                    <strong class="{{ $row->received_qty > 0 ? 'text-success' : 'text-muted' }}">
                                        {{ number_format($row->received_qty, 2) }}
                                    </strong>
                                    @if(($row->unaccepted_qty ?? 0) > 0)
                                        <div class="mt-1">
                                            <span class="badge bg-danger bg-opacity-15 text-danger border border-danger-subtle rounded-pill font-monospace px-2 py-0.5 d-inline-block" title="{{ $row->rejection_reason ?? 'Not accepted during store intake' }}">
                                                <i class="fa-solid fa-ban me-1"></i> Not Accepted: {{ number_format($row->unaccepted_qty, 2) }}
                                            </span>
                                            @if($row->rejection_reason)
                                                <small class="text-danger d-block fst-italic" style="font-size: 0.7rem; max-width: 140px; margin-left: auto;">{{ Str::limit($row->rejection_reason, 30) }}</small>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td data-label="Remaining" class="py-3 text-end font-monospace font-tabular">
                                    <strong class="fs-6 {{ $row->remaining_qty > 0 && !$row->is_fulfillment_closed ? 'text-danger' : 'text-muted' }}">
                                        {{ number_format($row->remaining_qty, 2) }}
                                    </strong>
                                    @if($row->outstanding_value > 0 && !$row->is_fulfillment_closed)
                                        <small class="text-muted d-block font-monospace">ETB {{ number_format($row->outstanding_value, 2) }}</small>
                                    @endif
                                </td>
                                <td data-label="Fulfillment" class="py-3 text-center">
                                    @if($row->is_fulfillment_closed)
                                        @if(($row->unaccepted_qty ?? 0) > 0 && $row->received_qty == 0)
                                            <span class="badge bg-danger bg-opacity-20 text-danger border border-danger-subtle rounded-pill px-2.5 py-1">
                                                <i class="fa-solid fa-ban me-1"></i>Closed (Not Accepted)
                                            </span>
                                        @elseif(($row->unaccepted_qty ?? 0) > 0 && $row->received_qty > 0)
                                            <span class="badge bg-warning bg-opacity-25 text-dark border border-warning-subtle rounded-pill px-2.5 py-1">
                                                <i class="fa-solid fa-check-double me-1"></i>Closed (Partial Intake)
                                            </span>
                                        @else
                                            <span class="badge bg-success bg-opacity-25 text-success border border-success-subtle rounded-pill px-2.5 py-1">
                                                <i class="fa-solid fa-circle-check me-1"></i>Fulfilled
                                            </span>
                                        @endif
                                    @elseif($row->fulfillment_status === 'fully_fulfilled')
                                        <span class="badge bg-success bg-opacity-25 text-success border border-success-subtle rounded-pill px-2.5 py-1">
                                            <i class="fa-solid fa-check me-1"></i>Fully Fulfilled
                                        </span>
                                    @elseif($row->fulfillment_status === 'partially_fulfilled')
                                        <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1">
                                            Partially Fulfilled
                                        </span>
                                    @elseif($row->fulfillment_status === 'not_received')
                                        <span class="badge bg-danger rounded-pill px-2.5 py-1">
                                            Not Received
                                        </span>
                                    @elseif($row->fulfillment_status === 'over_received')
                                        <span class="badge bg-info rounded-pill px-2.5 py-1">
                                            Over-Received
                                        </span>
                                    @else
                                        <span class="badge bg-secondary rounded-pill px-2.5 py-1">Pending</span>
                                    @endif
                                </td>
                                <td data-label="Payment" class="py-3 text-center">
                                    @if($row->is_paid)
                                        <span class="badge bg-success bg-opacity-15 text-success border border-success-subtle font-monospace px-2 py-1 d-block mb-1">
                                            <i class="fa-solid fa-check-circle me-1"></i>DISBURSED (ETB {{ number_format($row->paid_amount, 2) }})
                                        </span>
                                        @if($isPaidNotRecv)
                                            <span class="badge bg-danger text-white rounded-pill font-monospace px-2.5 py-1">
                                                🚨 PAID NOT RECEIVED
                                            </span>
                                        @endif
                                    @else
                                        <span class="badge bg-light text-muted border font-monospace px-2 py-1">
                                            Pending Payment
                                        </span>
                                    @endif
                                </td>
                                <td data-label="Action" class="pe-4 py-3 text-end">
                                    <div class="btn-group btn-group-sm shadow-xs">
                                        @if(!$row->is_fulfillment_closed && $row->remaining_qty > 0)
                                            <button type="button" class="btn btn-success fw-bold px-2.5 btn-open-fulfill-modal" style="min-height: 32px;"
                                                data-item-id="{{ $row->item->id }}"
                                                data-pr-id="{{ $row->pr?->id }}"
                                                data-pr-no="{{ $row->pr?->pr_no ?? $row->pr?->request_number ?? ('PR #' . $row->pr?->id) }}"
                                                data-product-name="{{ $row->product?->name ?? 'Material' }}"
                                                data-sku="{{ $row->product?->sku ?? '' }}"
                                                data-unit="{{ $row->product?->unit ?? 'pcs' }}"
                                                data-target-qty="{{ $row->purchased_qty > 0 ? $row->purchased_qty : $row->approved_qty }}"
                                                data-received-qty="{{ $row->received_qty }}"
                                                data-unaccepted-qty="{{ $row->unaccepted_qty ?? 0 }}"
                                                data-remaining-qty="{{ $row->remaining_qty }}"
                                                data-unit-cost="{{ $row->unit_cost }}"
                                                data-store-id="{{ $row->pr?->store_id }}"
                                                data-store-name="{{ $row->pr?->store?->name ?? 'Main Store' }}"
                                                title="Fulfill / Close intake for this line"
                                            >
                                                <i class="fa-solid fa-boxes-packing me-1"></i> Fulfill
                                            </button>
                                        @else
                                            <span class="badge bg-success bg-opacity-15 text-success border border-success-subtle px-2 py-1.5 rounded-pill d-inline-flex align-items-center" title="Fulfillment closed">
                                                <i class="fa-solid fa-circle-check me-1"></i> Closed
                                            </span>
                                        @endif

                                        <button type="button" class="btn btn-outline-primary px-3 fw-semibold view-fulfillment-details" style="min-height: 32px;" data-pr-id="{{ $row->pr?->id }}" title="View Intake Slips & Photos">
                                            <i class="fa-solid fa-receipt me-1"></i> Trail
                                        </button>
                                        <button type="button" class="btn btn-outline-warning text-dark px-2.5" style="min-height: 32px;"
                                            data-bs-toggle="modal"
                                            data-bs-target="#raiseFindingModal"
                                            data-source-type="fulfillment"
                                            data-source-id="{{ $row->item->id }}"
                                            data-store-id="{{ $row->pr?->store_id }}"
                                            data-project-id="{{ $row->pr?->project_id }}"
                                            data-title="Fulfillment Observation: PR #{{ $row->pr?->pr_no ?? $row->pr?->request_number }} - {{ $row->product?->name }}"
                                            data-severity="{{ $isPaidNotRecv ? 'critical' : ($row->remaining_qty > 0 ? 'high' : 'medium') }}"
                                            data-observation="{{ $isPaidNotRecv ? 'CRITICAL AUDIT RISK: Item ' . $row->product?->name . ' on PR #' . ($row->pr?->pr_no ?? $row->pr?->request_number) . ' has been PAID in finance, but remaining quantity of ' . number_format($row->remaining_qty, 2) . ' is unfulfilled at ' . ($row->pr?->store?->name ?? 'store') . '. Days elapsed since purchase: ' . $row->days_since_purchase . '. Please provide delivery receipt or refund.' : 'Observation regarding fulfillment of ' . $row->product?->name . ' on PR #' . ($row->pr?->pr_no ?? $row->pr?->request_number) }}"
                                            title="Raise Audit Query"
                                        >
                                            <i class="fa-solid fa-clipboard-question"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-boxes-packing fa-2x mb-2 d-block text-secondary"></i>
                                    No procurement items found matching your filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($paginatedItems->hasPages())
                <div class="p-3 border-top d-flex justify-content-center">
                    {{ $paginatedItems->links() }}
                </div>
            @endif
        </div>
    </div>

</div>

<!-- Fulfillment Audit Trail & Intake Slips Modal -->
<div class="modal fade" id="fulfillmentDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            <div class="modal-header text-white py-3 px-4 flex-shrink-0" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-bottom: 3px solid #0284c7;">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2 bg-info bg-opacity-25 rounded-3 text-info">
                        <i class="fa-solid fa-receipt fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white fs-6" id="modalPrTitle">
                            Intake Slips &amp; Stock Movements Trail
                        </h5>
                        <small class="text-white-50" id="modalPrSubtitle">Verifying physical slips, sequential numbers, store stock movements, and disbursements</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4 bg-light" id="fulfillmentModalBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2">Loading audit trail, store slips &amp; stock movements...</p>
                </div>
            </div>

            <div class="modal-footer bg-white py-3 px-4 d-flex justify-content-between align-items-center">
                <div id="modalActionContainer"></div>
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const detailModal = new bootstrap.Modal(document.getElementById('fulfillmentDetailModal'));
    const modalBody   = document.getElementById('fulfillmentModalBody');
    const modalTitle  = document.getElementById('modalPrTitle');
    const modalSub    = document.getElementById('modalPrSubtitle');
    const actionContainer = document.getElementById('modalActionContainer');

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    document.querySelectorAll('.view-fulfillment-details').forEach(btn => {
        btn.addEventListener('click', function () {
            const prId = this.getAttribute('data-pr-id');
            if (!prId) return;

            actionContainer.innerHTML = '';
            modalBody.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2">Loading audit trail, store slips &amp; stock movements...</p>
                </div>
            `;
            detailModal.show();

            fetch(`/audit/fulfillment/${prId}/details`)
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        modalBody.innerHTML = `<div class="alert alert-danger">Failed to load details.</div>`;
                        return;
                    }

                    const prRef = data.pr.pr_no || data.pr.request_number || ('PR #' + data.pr.id);
                    modalTitle.textContent = `PR #${prRef} — ${data.pr.title || 'Purchase Fulfillment Trail'}`;
                    modalSub.textContent  = `Project: ${data.pr.project_name} | Store: ${data.pr.store_name} | Supplier: ${data.pr.supplier_name}`;

                    // Modal action button (Raise Audit Query)
                    actionContainer.innerHTML = `
                        <button type="button" class="btn btn-outline-warning text-dark rounded-pill px-3 shadow-xs" id="btnRaiseFindingFromModal">
                            <i class="fa-solid fa-clipboard-question me-1 text-warning"></i> Raise Audit Finding on PR #${escapeHtml(prRef)}
                        </button>
                    `;
                    document.getElementById('btnRaiseFindingFromModal')?.addEventListener('click', function() {
                        detailModal.hide();
                        setTimeout(() => {
                            const fModalEl = document.getElementById('raiseFindingModal');
                            if (fModalEl) {
                                const fModal = new bootstrap.Modal(fModalEl);
                                const form = fModalEl.querySelector('form');
                                if (form) {
                                    const srcType = form.querySelector('[name="source_type"]');
                                    const srcId = form.querySelector('[name="source_id"]');
                                    const tInput = form.querySelector('[name="title"]');
                                    if (srcType) srcType.value = 'fulfillment';
                                    if (srcId) srcId.value = data.pr.id;
                                    if (tInput) tInput.value = `Fulfillment Observation: PR #${prRef} (${data.pr.title})`;
                                }
                                fModal.show();
                            }
                        }, 350);
                    });

                    let html = '';

                    // Top PR Overview Card with KPI summary
                    html += `
                        <div class="card border-0 shadow-xs rounded-3 mb-3 bg-white">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="badge bg-primary fs-6 px-2.5 py-1 font-monospace">PR #${escapeHtml(prRef)}</span>
                                            <span class="badge bg-secondary rounded-pill px-2.5 py-1">${escapeHtml(data.pr.status || 'Active')}</span>
                                        </div>
                                        <h6 class="fw-bold mb-1 text-dark">${escapeHtml(data.pr.title || 'No Title')}</h6>
                                        <div class="text-muted small d-flex flex-wrap gap-3">
                                            <span><i class="fa-solid fa-diagram-project text-secondary me-1"></i><strong>Project:</strong> ${escapeHtml(data.pr.project_name)}</span>
                                            <span><i class="fa-solid fa-warehouse text-primary me-1"></i><strong>Store:</strong> ${escapeHtml(data.pr.store_name)}</span>
                                            <span><i class="fa-solid fa-truck-field text-secondary me-1"></i><strong>Supplier:</strong> ${escapeHtml(data.pr.supplier_name)}</span>
                                            <span><i class="fa-solid fa-user me-1"></i><strong>Requester:</strong> ${escapeHtml(data.pr.requested_by)}</span>
                                            <span><i class="fa-solid fa-calendar me-1"></i><strong>Date:</strong> ${escapeHtml(data.pr.created_at)}</span>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="text-center px-3 py-1.5 bg-light rounded-3 border">
                                            <span class="d-block fs-5 fw-bold text-primary font-monospace">${data.intake_slips.length}</span>
                                            <small class="text-muted" style="font-size: 0.72rem;">Intake Slips</small>
                                        </div>
                                        <div class="text-center px-3 py-1.5 bg-light rounded-3 border">
                                            <span class="d-block fs-5 fw-bold text-info font-monospace">${data.movements.length}</span>
                                            <small class="text-muted" style="font-size: 0.72rem;">Stock Moves</small>
                                        </div>
                                        <div class="text-center px-3 py-1.5 bg-light rounded-3 border">
                                            <span class="d-block fs-5 fw-bold text-success font-monospace">${data.payments.length}</span>
                                            <small class="text-muted" style="font-size: 0.72rem;">Payments</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;

                    // Nav Pills for Tabs
                    html += `
                        <ul class="nav nav-pills nav-fill bg-white p-1 rounded-3 border shadow-xs mb-3" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active rounded-pill fw-semibold py-2" id="pill-slips-tab" data-bs-toggle="pill" data-bs-target="#pill-slips" type="button" role="tab" aria-selected="true">
                                    <i class="fa-solid fa-receipt me-1.5 text-primary"></i>Store Keeper Physical Slips
                                    <span class="badge bg-primary text-white ms-1.5 rounded-pill">${data.intake_slips.length}</span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link rounded-pill fw-semibold py-2" id="pill-movements-tab" data-bs-toggle="pill" data-bs-target="#pill-movements" type="button" role="tab" aria-selected="false">
                                    <i class="fa-solid fa-boxes-stacked me-1.5 text-info"></i>Warehouse Stock Movements
                                    <span class="badge bg-info text-dark ms-1.5 rounded-pill">${data.movements.length}</span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link rounded-pill fw-semibold py-2" id="pill-payments-tab" data-bs-toggle="pill" data-bs-target="#pill-payments" type="button" role="tab" aria-selected="false">
                                    <i class="fa-solid fa-money-bill-transfer me-1.5 text-success"></i>Finance Payments
                                    <span class="badge bg-success text-white ms-1.5 rounded-pill">${data.payments.length}</span>
                                </button>
                            </li>
                        </ul>
                    `;

                    // Tab Content Wrapper
                    html += `<div class="tab-content" id="fulfillmentTabContent">`;

                    // ─────────────────────────────────────────────────────────────
                    // TAB 1: STORE KEEPER PHYSICAL SLIPS
                    // ─────────────────────────────────────────────────────────────
                    html += `<div class="tab-pane fade show active" id="pill-slips" role="tabpanel">`;

                    if (data.intake_slips.length === 0) {
                        html += `
                            <div class="alert alert-warning border-warning-subtle shadow-xs rounded-3 p-4 text-center mb-0">
                                <i class="fa-solid fa-triangle-exclamation fa-2x text-warning mb-2 d-block"></i>
                                <h6 class="fw-bold mb-1">No Delivery Intake Slips Recorded</h6>
                                <p class="text-muted small mb-0">Goods have not been received or verified by any storekeeper for this Purchase Request.</p>
                            </div>
                        `;
                    } else {
                        data.intake_slips.forEach((slip, idx) => {
                            html += `
                                <div class="card border rounded-3 shadow-xs mb-3 bg-white overflow-hidden">
                                    <div class="card-header bg-white border-bottom py-2.5 px-3">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                            <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                                <span class="badge bg-primary text-white font-monospace px-2.5 py-1">
                                                    <i class="fa-solid fa-receipt me-1"></i>Slip #${escapeHtml(slip.slip_no || 'Missing No')}
                                                </span>
                                                <span class="badge bg-light text-dark border font-monospace py-1">
                                                    DR #${escapeHtml(slip.dr_no)}
                                                </span>
                                                ${slip.is_void ? `
                                                    <span class="badge bg-danger text-white py-1">
                                                        <i class="fa-solid fa-ban me-1"></i>VOID SLIP
                                                    </span>
                                                ` : `
                                                    <span class="badge bg-success bg-opacity-15 text-success border border-success-subtle py-1">
                                                        <i class="fa-solid fa-circle-check me-1"></i>Active Receipt
                                                    </span>
                                                `}
                                                ${slip.is_store_only ? `
                                                    <span class="badge bg-warning bg-opacity-25 text-dark border border-warning-subtle py-1" title="Physical Slip designated for Store internal records only - excluded from VAT declarations">
                                                        <i class="fa-solid fa-store me-1 text-dark"></i>Store-Only (Non-VAT)
                                                    </span>
                                                ` : `
                                                    <span class="badge bg-info bg-opacity-15 text-dark border border-info-subtle py-1" title="Standard intake slip with VAT eligibility">
                                                        <i class="fa-solid fa-file-invoice-dollar me-1 text-primary"></i>Standard VAT Slip
                                                    </span>
                                                `}
                                            </div>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <span class="badge bg-light text-muted border py-1">
                                                    <i class="fa-solid fa-warehouse text-primary me-1"></i>${escapeHtml(slip.store_name)}
                                                </span>
                                                <span class="badge bg-light text-muted border py-1">
                                                    <i class="fa-solid fa-calendar text-muted me-1"></i>${escapeHtml(slip.received_date)}
                                                </span>
                                                <span class="badge bg-light text-dark border py-1">
                                                    <i class="fa-solid fa-user-check text-success me-1"></i>Storekeeper: <strong>${escapeHtml(slip.received_by)}</strong>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body p-3 bg-light">
                            `;

                            // Compliance / Quality Flags
                            if (slip.flags && slip.flags.length > 0) {
                                html += `<div class="mb-3">`;
                                slip.flags.forEach(fl => {
                                    html += `
                                        <div class="alert alert-${fl.type} py-1.5 px-3 mb-1.5 small d-flex align-items-center rounded-3">
                                            <i class="fa-solid fa-triangle-exclamation me-2"></i>
                                            <div><strong>${escapeHtml(fl.title)}:</strong> ${escapeHtml(fl.message)}</div>
                                        </div>
                                    `;
                                });
                                html += `</div>`;
                            }

                            // Items Table
                            html += `
                                <div class="bg-white border rounded-3 p-0 mb-3 overflow-hidden shadow-xs">
                                    <div class="p-2 px-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                                        <strong class="text-dark small"><i class="fa-solid fa-list-check text-secondary me-1.5"></i>Goods Delivered on Slip #${escapeHtml(slip.slip_no || slip.dr_no)}</strong>
                                        <span class="badge bg-light text-muted border">${slip.items.length} Line Items</span>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0 small">
                                            <thead class="bg-light text-muted text-uppercase" style="font-size: 0.72rem;">
                                                <tr>
                                                    <th class="ps-3 py-2">Product Name</th>
                                                    <th>SKU / Code</th>
                                                    <th class="text-end">Received Qty</th>
                                                    <th class="text-end">Accepted Qty</th>
                                                    <th class="text-end pe-3">Rejected / Remarks</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                            `;

                            if (slip.items.length === 0) {
                                html += `<tr><td colspan="5" class="text-center py-2 text-muted">No items recorded in this receipt slip.</td></tr>`;
                            } else {
                                slip.items.forEach(it => {
                                    html += `
                                        <tr>
                                            <td class="ps-3 py-2 fw-semibold text-dark">${escapeHtml(it.product_name)}</td>
                                            <td class="font-monospace text-muted">${escapeHtml(it.product_sku)}</td>
                                            <td class="text-end font-monospace">${it.quantity_received.toLocaleString('en-US', {minimumFractionDigits: 2})} ${escapeHtml(it.unit)}</td>
                                            <td class="text-end font-monospace fw-bold text-success">${it.accepted_quantity.toLocaleString('en-US', {minimumFractionDigits: 2})} ${escapeHtml(it.unit)}</td>
                                            <td class="text-end pe-3">
                                                ${it.rejected_quantity > 0 ? `
                                                    <span class="badge bg-danger text-white font-monospace">${it.rejected_quantity} ${escapeHtml(it.unit)} rejected</span>
                                                    ${it.rejection_reason ? `<small class="text-muted d-block">${escapeHtml(it.rejection_reason)}</small>` : ''}
                                                ` : `<span class="badge bg-success bg-opacity-10 text-success border border-success-subtle">Fully Accepted</span>`}
                                            </td>
                                        </tr>
                                    `;
                                });
                            }

                            html += `</tbody></table></div></div>`;

                            // Storekeeper Notes
                            const slipNotes = slip.physical_slip_notes || slip.notes;
                            if (slipNotes) {
                                html += `
                                    <div class="p-2.5 bg-white border rounded-3 mb-3 small shadow-xs">
                                        <strong class="text-secondary"><i class="fa-solid fa-note-sticky text-warning me-1.5"></i>Storekeeper Slip Remarks:</strong>
                                        <span class="text-dark ms-1">${escapeHtml(slipNotes)}</span>
                                    </div>
                                `;
                            }

                            // Storekeeper Physical Slip Scan / Attachment
                            if (slip.slip_receipt_url) {
                                if (slip.is_image) {
                                    html += `
                                        <div class="card border rounded-3 p-3 bg-white shadow-xs">
                                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                                <span class="fw-bold text-dark small">
                                                    <i class="fa-solid fa-file-image text-primary me-1.5"></i>
                                                    Store Keeper Uploaded Physical Paper Slip (Model 19 / GRN Scan)
                                                </span>
                                                <div class="d-flex gap-2">
                                                    <a href="${slip.slip_receipt_url}" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-xs">
                                                        <i class="fa-solid fa-up-right-from-square me-1"></i> Open Full High-Res
                                                    </a>
                                                    <a href="${slip.slip_receipt_url}" download class="btn btn-sm btn-light border rounded-pill px-3 shadow-xs">
                                                        <i class="fa-solid fa-download me-1"></i> Download
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="text-center p-2 rounded-3 bg-light border position-relative overflow-hidden">
                                                <a href="${slip.slip_receipt_url}" target="_blank" title="Click to open physical slip photo in high resolution">
                                                    <img src="${slip.slip_receipt_url}" alt="Physical Delivery Slip Scan" class="img-fluid rounded shadow-xs" style="max-height: 300px; width: auto; object-fit: contain; cursor: zoom-in; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.015)'" onmouseout="this.style.transform='scale(1)'">
                                                </a>
                                            </div>
                                        </div>
                                    `;
                                } else {
                                    html += `
                                        <div class="card border rounded-3 p-3 bg-white shadow-xs d-flex flex-row justify-content-between align-items-center flex-wrap gap-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="fa-solid fa-file-pdf fa-2x text-danger"></i>
                                                <div>
                                                    <strong class="d-block text-dark small">Store Keeper Attached Physical Slip Document</strong>
                                                    <small class="text-muted">PDF or scanned file uploaded during intake verification</small>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <a href="${slip.slip_receipt_url}" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-xs">
                                                    <i class="fa-solid fa-eye me-1"></i> View Document
                                                </a>
                                                <a href="${slip.slip_receipt_url}" download class="btn btn-sm btn-light border rounded-pill px-3 shadow-xs">
                                                    <i class="fa-solid fa-download me-1"></i> Download
                                                </a>
                                            </div>
                                        </div>
                                    `;
                                }
                            } else {
                                html += `
                                    <div class="alert alert-danger py-2 px-3 mb-0 small d-flex align-items-center rounded-3 shadow-xs">
                                        <i class="fa-solid fa-triangle-exclamation me-2 fa-lg"></i>
                                        <div>
                                            <strong>Missing Physical Paper Slip Scan:</strong> Storekeeper has not uploaded an image or scan of the physical paper slip (Model 19 / GRN) for this delivery record.
                                        </div>
                                    </div>
                                `;
                            }

                            html += `</div></div>`;
                        });
                    }

                    html += `</div>`; // End Tab 1

                    // ─────────────────────────────────────────────────────────────
                    // TAB 2: INVENTORY & STOCK MOVEMENTS LEDGER
                    // ─────────────────────────────────────────────────────────────
                    html += `<div class="tab-pane fade" id="pill-movements" role="tabpanel">`;

                    if (data.movements.length === 0) {
                        html += `
                            <div class="alert alert-warning border-warning-subtle shadow-xs rounded-3 p-4 text-center mb-0">
                                <i class="fa-solid fa-boxes-stacked fa-2x text-warning mb-2 d-block"></i>
                                <h6 class="fw-bold mb-1">No Inventory Stock Movements Logged</h6>
                                <p class="text-muted small mb-0">No warehouse stock movements have been recorded in the ledger for this Purchase Request. Goods have not incremented store balance.</p>
                            </div>
                        `;
                    } else {
                        html += `
                            <div class="card border-0 shadow-xs rounded-3 bg-white overflow-hidden">
                                <div class="card-header bg-white border-bottom py-2.5 px-3 d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="fw-bold mb-0 text-dark">
                                            <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Warehouse Inventory Stock Movements
                                        </h6>
                                        <small class="text-muted">Audit log of inventory additions, store balances before &amp; after intake, and performers</small>
                                    </div>
                                    <span class="badge bg-primary text-white rounded-pill px-3 py-1.5">${data.movements.length} Movements Logged</span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm align-middle mb-0 small">
                                        <thead class="bg-light text-muted text-uppercase" style="font-size: 0.72rem;">
                                            <tr>
                                                <th class="ps-3 py-2.5">Date &amp; Time</th>
                                                <th>Store Location</th>
                                                <th>Product &amp; SKU</th>
                                                <th class="text-center">Movement Type</th>
                                                <th class="text-end">Quantity</th>
                                                <th class="text-center">Balance (Before &rarr; After)</th>
                                                <th>Performed By</th>
                                                <th class="pe-3">Movement Remarks / Ref</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                        `;

                        data.movements.forEach(m => {
                            let typeBadge = '';
                            if (m.type === 'in') {
                                typeBadge = '<span class="badge bg-success bg-opacity-25 text-success border border-success-subtle font-monospace"><i class="fa-solid fa-arrow-down me-1"></i>INTAKE / IN</span>';
                            } else if (m.type === 'out') {
                                typeBadge = '<span class="badge bg-danger bg-opacity-25 text-danger border border-danger-subtle font-monospace"><i class="fa-solid fa-arrow-up me-1"></i>ISSUE / OUT</span>';
                            } else if (m.type === 'transfer_in') {
                                typeBadge = '<span class="badge bg-info bg-opacity-25 text-info border border-info-subtle font-monospace"><i class="fa-solid fa-arrow-down-left me-1"></i>TRANSFER IN</span>';
                            } else if (m.type === 'transfer_out') {
                                typeBadge = '<span class="badge bg-warning bg-opacity-25 text-warning border border-warning-subtle font-monospace"><i class="fa-solid fa-arrow-up-right me-1"></i>TRANSFER OUT</span>';
                            } else {
                                typeBadge = `<span class="badge bg-secondary font-monospace">${escapeHtml(m.type_label)}</span>`;
                            }

                            let balanceDisplay = '<span class="text-muted">—</span>';
                            if (m.balance_before !== null && m.balance_after !== null) {
                                balanceDisplay = `
                                    <span class="font-monospace text-muted">${m.balance_before.toLocaleString('en-US', {minimumFractionDigits: 2})}</span>
                                    <i class="fa-solid fa-arrow-right text-secondary mx-1" style="font-size: 0.65rem;"></i>
                                    <strong class="font-monospace text-dark">${m.balance_after.toLocaleString('en-US', {minimumFractionDigits: 2})}</strong>
                                `;
                            }

                            html += `
                                <tr>
                                    <td class="ps-3 py-2.5 font-monospace text-dark">${escapeHtml(m.date)}</td>
                                    <td>
                                        <i class="fa-solid fa-warehouse text-primary me-1"></i>
                                        <span class="fw-semibold text-dark">${escapeHtml(m.store_name)}</span>
                                    </td>
                                    <td>
                                        <strong class="text-dark d-block">${escapeHtml(m.product_name)}</strong>
                                        <small class="text-muted font-monospace">SKU: ${escapeHtml(m.product_sku)}</small>
                                    </td>
                                    <td class="text-center">${typeBadge}</td>
                                    <td class="text-end font-monospace fw-bold ${m.quantity >= 0 ? 'text-success' : 'text-danger'}">
                                        ${m.quantity >= 0 ? '+' : ''}${m.quantity.toLocaleString('en-US', {minimumFractionDigits: 2})} ${escapeHtml(m.product_unit)}
                                    </td>
                                    <td class="text-center">${balanceDisplay}</td>
                                    <td>
                                        <i class="fa-solid fa-user-gear text-secondary me-1"></i>
                                        <span class="fw-semibold text-dark">${escapeHtml(m.performed_by)}</span>
                                    </td>
                                    <td class="pe-3 text-muted small">${escapeHtml(m.remarks)}</td>
                                </tr>
                            `;
                        });

                        html += `</tbody></table></div></div>`;
                    }

                    html += `</div>`; // End Tab 2

                    // ─────────────────────────────────────────────────────────────
                    // TAB 3: FINANCE PAYMENT DISBURSEMENTS
                    // ─────────────────────────────────────────────────────────────
                    html += `<div class="tab-pane fade" id="pill-payments" role="tabpanel">`;

                    html += `
                        <div class="card border-0 shadow-xs rounded-3 bg-white overflow-hidden">
                            <div class="card-header bg-white border-bottom py-2.5 px-3 d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-money-bill-transfer text-success me-2"></i>Finance Payment Disbursed</h6>
                                <span class="badge bg-success rounded-pill px-3 py-1.5">${data.payments.length} Payments</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-sm align-middle mb-0 small">
                                    <thead class="bg-light text-muted text-uppercase" style="font-size: 0.72rem;">
                                        <tr>
                                            <th class="ps-3 py-2.5">Paid At</th>
                                            <th>Method</th>
                                            <th>Status</th>
                                            <th>Disbursed By</th>
                                            <th class="text-end">Gross Amount</th>
                                            <th class="text-end">VAT Amount</th>
                                            <th class="text-end pe-3">Net Disbursed</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                    `;

                    if (data.payments.length === 0) {
                        html += `<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-circle-info me-1"></i> No finance payment records disbursed yet.</td></tr>`;
                    } else {
                        data.payments.forEach(p => {
                            html += `
                                <tr>
                                    <td class="ps-3 py-2.5 font-monospace text-dark">${escapeHtml(p.paid_at)}</td>
                                    <td><span class="badge bg-light text-dark border">${escapeHtml(p.method)}</span></td>
                                    <td><span class="badge bg-success bg-opacity-15 text-success border border-success-subtle">${escapeHtml(p.status || 'paid')}</span></td>
                                    <td>${escapeHtml(p.paid_by)}</td>
                                    <td class="text-end font-monospace">ETB ${p.gross.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                                    <td class="text-end font-monospace text-muted">ETB ${p.vat.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                                    <td class="text-end pe-3 font-monospace fw-bold text-success">ETB ${p.amount.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                                </tr>
                            `;
                        });
                    }

                    html += `</tbody></table></div></div></div>`; // End Tab 3

                    html += `</div>`; // End Tab Content

                    modalBody.innerHTML = html;
                })
                .catch(err => {
                    modalBody.innerHTML = `<div class="alert alert-danger">Error loading fulfillment audit trail.</div>`;
                });
        });
    });

    // ─── Audit Fulfillment Modal Handler ──────────────────────────────────────
    const fulfillModalEl = document.getElementById('auditFulfillModal');
    let fulfillModal = null;
    if (fulfillModalEl) {
        fulfillModal = new bootstrap.Modal(fulfillModalEl);
    }

    const fulfillForm              = document.getElementById('auditFulfillForm');
    const fulfillItemId            = document.getElementById('fulfillModalItemId');
    const fulfillActionMode        = document.getElementById('fulfillModalActionMode');
    const bannerPrNo               = document.getElementById('fulfillBannerPrNo');
    const bannerItemName           = document.getElementById('fulfillBannerItemName');
    const bannerSku                = document.getElementById('fulfillBannerSku');
    const bannerStore              = document.getElementById('fulfillBannerStore');
    const bannerTarget             = document.getElementById('fulfillBannerTarget');
    const bannerReceived           = document.getElementById('fulfillBannerReceived');
    const bannerUnaccepted         = document.getElementById('fulfillBannerUnaccepted');
    const bannerRemaining          = document.getElementById('fulfillBannerRemaining');
    const unitLabels               = document.querySelectorAll('.unit-label');

    const inputUnacceptedQty       = document.getElementById('inputUnacceptedQty');
    const inputIntakeQty           = document.getElementById('inputIntakeQty');
    const inputPartialIntakeQty    = document.getElementById('inputPartialIntakeQty');
    const inputPartialUnacceptedQty= document.getElementById('inputPartialUnacceptedQty');
    const selectPresetReason       = document.getElementById('selectPresetReason');
    const inputRejectionReason     = document.getElementById('inputRejectionReason');
    const inputPartialRejReason    = document.getElementById('inputPartialRejectionReason');
    const fulfillStoreSelect       = document.getElementById('fulfillStoreSelect');
    const fulfillPartialStoreSelect= document.getElementById('fulfillPartialStoreSelect');
    const btnSubmitFulfill         = document.getElementById('btnSubmitFulfill');

    const sectionNotAccepted       = document.getElementById('sectionNotAccepted');
    const sectionAcceptInventory   = document.getElementById('sectionAcceptInventory');
    const sectionPartialSplit      = document.getElementById('sectionPartialSplit');

    let currentRemaining = 0.0;

    function setFulfillMode(mode) {
        if (!fulfillActionMode) return;
        fulfillActionMode.value = mode;

        document.querySelectorAll('.fulfill-action-card').forEach(card => {
            const cardMode = card.getAttribute('data-mode');
            if (cardMode === mode) {
                card.classList.add('active');
                card.style.borderWidth = '2px';
                if (mode === 'not_accepted') {
                    card.style.borderColor = '#ef4444';
                    card.style.background = '#fff5f5';
                } else if (mode === 'accept_inventory') {
                    card.style.borderColor = '#10b981';
                    card.style.background = '#f0fdf4';
                } else {
                    card.style.borderColor = '#0284c7';
                    card.style.background = '#f0f9ff';
                }
            } else {
                card.classList.remove('active');
                card.style.borderWidth = '1px';
                card.style.borderColor = '#cbd5e1';
                card.style.background = '#ffffff';
            }
        });

        if (sectionNotAccepted) sectionNotAccepted.classList.add('d-none');
        if (sectionAcceptInventory) sectionAcceptInventory.classList.add('d-none');
        if (sectionPartialSplit) sectionPartialSplit.classList.add('d-none');

        if (mode === 'not_accepted') {
            if (sectionNotAccepted) sectionNotAccepted.classList.remove('d-none');
            if (inputUnacceptedQty) inputUnacceptedQty.value = currentRemaining.toFixed(2);
            if (inputIntakeQty) inputIntakeQty.value = '0';
        } else if (mode === 'accept_inventory') {
            if (sectionAcceptInventory) sectionAcceptInventory.classList.remove('d-none');
            if (inputIntakeQty) inputIntakeQty.value = currentRemaining.toFixed(2);
            if (inputUnacceptedQty) inputUnacceptedQty.value = '0';
        } else if (mode === 'partial') {
            if (sectionPartialSplit) sectionPartialSplit.classList.remove('d-none');
            const half = Math.floor((currentRemaining / 2) * 100) / 100;
            const remainder = Math.round((currentRemaining - half) * 100) / 100;
            if (inputPartialIntakeQty) inputPartialIntakeQty.value = half > 0 ? half.toFixed(2) : '0.00';
            if (inputPartialUnacceptedQty) inputPartialUnacceptedQty.value = remainder.toFixed(2);
        }
    }

    document.querySelectorAll('.fulfill-action-card').forEach(card => {
        card.addEventListener('click', function () {
            const mode = this.getAttribute('data-mode');
            setFulfillMode(mode);
        });
    });

    if (selectPresetReason) {
        selectPresetReason.addEventListener('change', function () {
            if (this.value === 'other') {
                if (inputRejectionReason) {
                    inputRejectionReason.value = '';
                    inputRejectionReason.focus();
                }
            } else {
                if (inputRejectionReason) inputRejectionReason.value = this.value;
            }
        });
    }

    // Open Fulfill modal on clicking table button
    document.querySelectorAll('.btn-open-fulfill-modal').forEach(btn => {
        btn.addEventListener('click', function () {
            const itemId    = this.getAttribute('data-item-id');
            const prNo      = this.getAttribute('data-pr-no');
            const pName     = this.getAttribute('data-product-name');
            const sku       = this.getAttribute('data-sku');
            const unit      = this.getAttribute('data-unit') || 'pcs';
            const targetQty = parseFloat(this.getAttribute('data-target-qty') || 0);
            const recvQty   = parseFloat(this.getAttribute('data-received-qty') || 0);
            const unaccQty  = parseFloat(this.getAttribute('data-unaccepted-qty') || 0);
            const remQty    = parseFloat(this.getAttribute('data-remaining-qty') || 0);
            const storeId   = this.getAttribute('data-store-id');
            const storeName = this.getAttribute('data-store-name') || 'Main Store';

            currentRemaining = remQty;

            if (fulfillItemId) fulfillItemId.value = itemId;
            if (bannerPrNo) bannerPrNo.textContent = prNo;
            if (bannerItemName) bannerItemName.textContent = pName;
            if (bannerSku) bannerSku.textContent = 'SKU: ' + (sku || 'N/A');
            if (bannerStore) bannerStore.textContent = storeName;
            if (bannerTarget) bannerTarget.textContent = targetQty.toFixed(2) + ' ' + unit;
            if (bannerReceived) bannerReceived.textContent = recvQty.toFixed(2) + ' ' + unit;
            if (bannerUnaccepted) bannerUnaccepted.textContent = unaccQty.toFixed(2) + ' ' + unit;
            if (bannerRemaining) bannerRemaining.textContent = remQty.toFixed(2) + ' ' + unit;

            unitLabels.forEach(el => el.textContent = unit);

            if (storeId && fulfillStoreSelect) {
                fulfillStoreSelect.value = storeId;
            }
            if (storeId && fulfillPartialStoreSelect) {
                fulfillPartialStoreSelect.value = storeId;
            }

            setFulfillMode('not_accepted');

            if (fulfillModal) {
                fulfillModal.show();
            }
        });
    });

    // Handle Fulfill Form Submit
    if (fulfillForm) {
        fulfillForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const mode = fulfillActionMode ? fulfillActionMode.value : 'not_accepted';
            let intakeVal = 0.0;
            let unacceptedVal = 0.0;
            let rejectionReason = '';
            let storeVal = fulfillStoreSelect ? fulfillStoreSelect.value : null;

            if (mode === 'not_accepted') {
                unacceptedVal = parseFloat(inputUnacceptedQty?.value || 0);
                intakeVal = 0.0;
                rejectionReason = inputRejectionReason?.value || 'Not accepted during audit fulfillment';
                if (unacceptedVal <= 0) {
                    alert('Please specify an unaccepted quantity greater than 0.');
                    return;
                }
            } else if (mode === 'accept_inventory') {
                intakeVal = parseFloat(inputIntakeQty?.value || 0);
                unacceptedVal = 0.0;
                if (intakeVal <= 0) {
                    alert('Please specify an intake quantity greater than 0.');
                    return;
                }
            } else if (mode === 'partial') {
                intakeVal = parseFloat(inputPartialIntakeQty?.value || 0);
                unacceptedVal = parseFloat(inputPartialUnacceptedQty?.value || 0);
                rejectionReason = inputPartialRejReason?.value || 'Partial balance not accepted';
                storeVal = fulfillPartialStoreSelect ? fulfillPartialStoreSelect.value : storeVal;
                if (intakeVal <= 0 && unacceptedVal <= 0) {
                    alert('Please specify either accepted or unaccepted quantity.');
                    return;
                }
            }

            const formData = new FormData(fulfillForm);
            formData.set('action_mode', mode);
            formData.set('intake_quantity', intakeVal);
            formData.set('unaccepted_quantity', unacceptedVal);
            formData.set('rejection_reason', rejectionReason);
            if (storeVal) formData.set('store_id', storeVal);

            if (btnSubmitFulfill) {
                btnSubmitFulfill.disabled = true;
                btnSubmitFulfill.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Processing...';
            }

            fetch(fulfillForm.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (fulfillModal) fulfillModal.hide();
                    alert(data.message || 'Fulfillment processed successfully.');
                    window.location.reload();
                } else {
                    alert(data.message || 'Error processing fulfillment.');
                    if (btnSubmitFulfill) {
                        btnSubmitFulfill.disabled = false;
                        btnSubmitFulfill.innerHTML = '<i class="fa-solid fa-check-double me-1"></i> Confirm Fulfillment';
                    }
                }
            })
            .catch(err => {
                alert('A network error occurred while processing fulfillment.');
                if (btnSubmitFulfill) {
                    btnSubmitFulfill.disabled = false;
                    btnSubmitFulfill.innerHTML = '<i class="fa-solid fa-check-double me-1"></i> Confirm Fulfillment';
                }
            });
        });
    }
});
</script>

@include('audit._fulfill_modal')
@include('audit._raise_finding_modal')
@endsection
