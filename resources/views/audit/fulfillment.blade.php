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
            <x-kpi-card
                title="Fully Fulfilled"
                :value="number_format($kpiFulfilledCount) . ' Lines'"
                subtitle="Complete store intake verified"
                icon="fa-solid fa-circle-check"
                tone="success"
            />
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Total Lines Tracked"
                :value="number_format($kpiTotalLines) . ' Lines'"
                subtitle="Active procurement purchase items"
                icon="fa-solid fa-boxes-stacked"
                tone="primary"
            />
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
                                    <span class="fw-bold font-monospace text-primary d-block">{{ $row->pr?->request_number }}</span>
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
                                    <strong class="{{ $row->received_qty > 0 ? 'text-success' : 'text-danger' }}">
                                        {{ number_format($row->received_qty, 2) }}
                                    </strong>
                                </td>
                                <td data-label="Remaining" class="py-3 text-end font-monospace font-tabular">
                                    <strong class="fs-6 {{ $row->remaining_qty > 0 ? 'text-danger' : 'text-muted' }}">
                                        {{ number_format($row->remaining_qty, 2) }}
                                    </strong>
                                    @if($row->outstanding_value > 0)
                                        <small class="text-muted d-block font-monospace">ETB {{ number_format($row->outstanding_value, 2) }}</small>
                                    @endif
                                </td>
                                <td data-label="Fulfillment" class="py-3 text-center">
                                    @if($row->fulfillment_status === 'fully_fulfilled')
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
                                            data-title="Fulfillment Observation: PR #{{ $row->pr?->request_number }} - {{ $row->product?->name }}"
                                            data-severity="{{ $isPaidNotRecv ? 'critical' : ($row->remaining_qty > 0 ? 'high' : 'medium') }}"
                                            data-observation="{{ $isPaidNotRecv ? 'CRITICAL AUDIT RISK: Item ' . $row->product?->name . ' on PR #' . $row->pr?->request_number . ' has been PAID in finance, but remaining quantity of ' . number_format($row->remaining_qty, 2) . ' is unfulfilled at ' . ($row->pr?->store?->name ?? 'store') . '. Days elapsed since purchase: ' . $row->days_since_purchase . '. Please provide delivery receipt or refund.' : 'Observation regarding fulfillment of ' . $row->product?->name . ' on PR #' . $row->pr?->request_number }}"
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
                            Intake Slips &amp; Procurement Audit Trail
                        </h5>
                        <small class="text-white-50" id="modalPrSubtitle">Verifying physical slips, sequential numbers, and payment disbursement</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4 bg-light" id="fulfillmentModalBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2">Loading audit trail &amp; delivery slip records...</p>
                </div>
            </div>

            <div class="modal-footer bg-white py-3 px-4">
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

    document.querySelectorAll('.view-fulfillment-details').forEach(btn => {
        btn.addEventListener('click', function () {
            const prId = this.getAttribute('data-pr-id');
            if (!prId) return;

            modalBody.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2">Loading audit trail & delivery slip records...</p>
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

                    modalTitle.textContent = `PR #${data.pr.request_number} — ${data.pr.title}`;
                    modalSub.textContent  = `Project: ${data.pr.project_name} | Store: ${data.pr.store_name} | Supplier: ${data.pr.supplier_name}`;

                    let html = '';

                    // Payments Section
                    html += `
                        <div class="card border-0 shadow-xs rounded-3 mb-4 bg-white">
                            <div class="card-header bg-white border-bottom py-2.5 px-3">
                                <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-money-bill-transfer text-success me-2"></i>Finance Payment Disbursed</h6>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm align-middle mb-0 small">
                                    <thead class="bg-light text-muted">
                                        <tr>
                                            <th class="ps-3 py-2">Paid At</th>
                                            <th>Method</th>
                                            <th>Disbursed By</th>
                                            <th class="text-end">Gross Amount</th>
                                            <th class="text-end">VAT Amount</th>
                                            <th class="text-end pe-3">Net Disbursed</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                    `;

                    if (data.payments.length === 0) {
                        html += `<tr><td colspan="6" class="text-center py-3 text-muted">No finance payment records disbursed yet.</td></tr>`;
                    } else {
                        data.payments.forEach(p => {
                            html += `
                                <tr>
                                    <td class="ps-3 py-2 font-monospace">${p.paid_at}</td>
                                    <td><span class="badge bg-light text-dark border">${p.method}</span></td>
                                    <td>${p.paid_by}</td>
                                    <td class="text-end font-monospace">ETB ${p.gross.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                                    <td class="text-end font-monospace text-muted">ETB ${p.vat.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                                    <td class="text-end pe-3 font-monospace fw-bold text-success">ETB ${p.amount.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                                </tr>
                            `;
                        });
                    }

                    html += `</tbody></table></div></div>`;

                    // Intake Slips Section
                    html += `
                        <div class="card border-0 shadow-xs rounded-3 mb-3 bg-white">
                            <div class="card-header bg-white border-bottom py-2.5 px-3 d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-warehouse text-primary me-2"></i>Store Delivery Slips (GRN Intake)</h6>
                                <span class="badge bg-primary text-white rounded-pill">${data.intake_slips.length} Slips Registered</span>
                            </div>
                            <div class="card-body p-3">
                    `;

                    if (data.intake_slips.length === 0) {
                        html += `<div class="alert alert-warning mb-0"><i class="fa-solid fa-circle-exclamation me-1"></i> No delivery intake slips have been recorded for this Purchase Request yet. Goods have NOT been received at store.</div>`;
                    } else {
                        data.intake_slips.forEach(slip => {
                            html += `
                                <div class="border rounded-3 p-3 mb-3 bg-light">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                        <div>
                                            <strong class="text-dark fs-6">Delivery Receipt #${slip.dr_no}</strong>
                                            <span class="badge bg-light text-dark border font-monospace ms-2">Slip #${slip.slip_no || 'Missing No'}</span>
                                        </div>
                                        <div>
                                            <span class="text-muted small me-2"><i class="fa-solid fa-calendar me-1"></i>${slip.received_date}</span>
                                            <span class="text-dark small fw-semibold"><i class="fa-solid fa-user me-1"></i>${slip.received_by}</span>
                                        </div>
                                    </div>
                            `;

                            // Slip Flags
                            if (slip.flags && slip.flags.length > 0) {
                                html += `<div class="mb-2">`;
                                slip.flags.forEach(fl => {
                                    html += `
                                        <div class="alert alert-${fl.type} py-1.5 px-3 mb-1 small d-flex align-items-center">
                                            <i class="fa-solid fa-triangle-exclamation me-2"></i>
                                            <strong>${fl.title}:</strong>&nbsp;${fl.message}
                                        </div>
                                    `;
                                });
                                html += `</div>`;
                            }

                            // Items in slip
                            html += `
                                <table class="table table-sm bg-white border rounded mb-2 small">
                                    <thead class="bg-light text-muted">
                                        <tr>
                                            <th class="ps-2 py-1">Delivered Product</th>
                                            <th class="text-end">Received Qty</th>
                                            <th class="text-end pe-2">Accepted Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                            `;
                            slip.items.forEach(it => {
                                html += `
                                    <tr>
                                        <td class="ps-2 py-1">${it.product_name}</td>
                                        <td class="text-end font-monospace">${it.quantity_received} ${it.unit}</td>
                                        <td class="text-end pe-2 font-monospace fw-bold text-success">${it.accepted_quantity} ${it.unit}</td>
                                    </tr>
                                `;
                            });
                            html += `</tbody></table>`;

                            // Photo preview
                            if (slip.slip_receipt_url) {
                                html += `
                                    <div class="d-flex align-items-center gap-2 mt-2">
                                        <span class="text-muted small fw-semibold"><i class="fa-solid fa-paperclip me-1"></i>Physical Slip Scan:</span>
                                        <a href="${slip.slip_receipt_url}" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-1">
                                            <i class="fa-solid fa-eye me-1"></i> View Physical Slip Photo
                                        </a>
                                    </div>
                                `;
                            } else {
                                html += `<div class="text-danger small mt-1"><i class="fa-solid fa-xmark me-1"></i><strong>Missing Physical Slip Scan:</strong> No photo attached to this delivery record.</div>`;
                            }

                            html += `</div>`;
                        });
                    }

                    html += `</div></div>`;
                    modalBody.innerHTML = html;
                })
                .catch(err => {
                    modalBody.innerHTML = `<div class="alert alert-danger">Error loading audit trail.</div>`;
                });
        });
    });
});
</script>

@include('audit._raise_finding_modal')
@endsection
