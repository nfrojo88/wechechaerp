@extends('layouts.app')
@section('title', 'Stock Movement Ledger (Audit)')

@section('content')
<div class="wc-container py-3 py-md-4">

    <!-- Page Header -->
    <x-page-header
        title="Stock Movement Ledger (Audit)"
        subtitle="Append-only immutable record of material intakes, site issues, transfers, and ledger running balance verification"
        badge="Audit &bull; Strictly Read-Only"
    >
        <x-slot name="actions">
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-outline-success btn-sm rounded-pill px-3 shadow-xs fw-semibold" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-file-excel me-1"></i> Export to Excel / CSV
                </a>
                <button type="button" class="btn btn-warning text-dark btn-sm rounded-pill px-3 shadow-xs fw-bold" style="min-height: 38px; display: inline-flex; align-items: center;" data-bs-toggle="modal" data-bs-target="#raiseFindingModal" data-source-type="stock_movement">
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
                    <a class="nav-link active rounded-pill fw-semibold px-3 py-1.5" href="{{ route('audit.stock-movements') }}">
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
                    <a class="nav-link rounded-pill fw-semibold px-3 py-1.5 text-dark" href="{{ route('audit.fulfillment') }}">
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
                title="Total Movements Logged"
                :value="number_format($totalMovements) . ' Entries'"
                subtitle="Append-only immutable audit trail"
                icon="fa-solid fa-scroll"
                tone="primary"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Inbound Receipts / Intakes"
                :value="number_format($inboundCount) . ' Intakes'"
                subtitle="Deliveries &amp; Transfers-In"
                icon="fa-solid fa-arrow-down-long"
                tone="success"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Outbound Site Issues"
                :value="number_format($outboundCount) . ' Dispatches'"
                subtitle="Material Usages &amp; Transfers-Out"
                icon="fa-solid fa-arrow-up-long"
                tone="warning"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Manual Adjustments"
                :value="number_format($adjustCount) . ' Actions'"
                subtitle="Audit-flagged inventory corrections"
                icon="fa-solid fa-sliders"
                tone="danger"
            />
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('audit.stock-movements') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">STORE / WAREHOUSE</label>
                    <select name="store_id" class="form-select form-select-sm">
                        <option value="">All Stores</option>
                        @foreach($stores as $st)
                            <option value="{{ $st->id }}" {{ request('store_id') == $st->id ? 'selected' : '' }}>
                                {{ $st->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">MOVEMENT TYPE</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        <option value="intake" {{ request('type') == 'intake' ? 'selected' : '' }}>Intake / GRN</option>
                        <option value="issue" {{ request('type') == 'issue' ? 'selected' : '' }}>Site Issue / Usage</option>
                        <option value="transfer_in" {{ request('type') == 'transfer_in' ? 'selected' : '' }}>Transfer In</option>
                        <option value="transfer_out" {{ request('type') == 'transfer_out' ? 'selected' : '' }}>Transfer Out</option>
                        <option value="adjustment" {{ request('type') == 'adjustment' ? 'selected' : '' }}>Adjustment</option>
                        <option value="return" {{ request('type') == 'return' ? 'selected' : '' }}>Return</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">DATE FROM</label>
                    <input type="date" name="date_from" class="form-control form-select-sm" value="{{ request('date_from') }}">
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">DATE TO</label>
                    <input type="date" name="date_to" class="form-control form-select-sm" value="{{ request('date_to') }}">
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">SEARCH</label>
                    <input type="text" name="search" class="form-control form-select-sm" placeholder="SKU, item, ref #..." value="{{ request('search') }}">
                </div>

                <div class="col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill w-100 fw-semibold" style="height: 31px;">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    @if(request()->hasAny(['store_id', 'type', 'date_from', 'date_to', 'search']))
                        <a href="{{ route('audit.stock-movements') }}" class="btn btn-light btn-sm border rounded-pill" title="Reset Filters" style="height: 31px;">
                            <i class="fa-solid fa-rotate-left"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Movement Ledger Table -->
    <div class="card border-0 shadow-sm rounded-3 bg-white mb-4">
        <div class="card-header bg-white border-bottom py-3 px-3 px-md-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-arrows-split-up-and-left text-success me-2"></i>Stock Movement Ledger
                </h6>
                <small class="text-muted">Showing {{ $movements->firstItem() ?? 0 }} to {{ $movements->lastItem() ?? 0 }} of {{ $movements->total() }} ledger entries</small>
            </div>
            <span class="badge bg-light text-dark border font-monospace px-3 py-1.5 rounded-pill">
                Page {{ $movements->currentPage() }} of {{ $movements->lastPage() }}
            </span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive wc-table-mobile-cards">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="bg-light text-muted text-uppercase" style="font-size: 0.75rem;">
                        <tr>
                            <th class="ps-4 py-2.5">Timestamp</th>
                            <th class="py-2.5">Store &amp; Site</th>
                            <th class="py-2.5">Item &amp; SKU</th>
                            <th class="py-2.5 text-center">Movement Type</th>
                            <th class="py-2.5 text-end">Quantity</th>
                            <th class="py-2.5 text-end">Balance Before</th>
                            <th class="py-2.5 text-end">Balance After</th>
                            <th class="py-2.5 text-center">Reconciliation Check</th>
                            <th class="py-2.5">Reference Document</th>
                            <th class="pe-4 py-2.5 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($movements as $mov)
                            @php
                                $isIn = in_array($mov->type, ['intake', 'in', 'transfer_in', 'return']);
                                $isOut = in_array($mov->type, ['issue', 'out', 'transfer_out']);
                                $expected = $isIn ? ($mov->balance_before + $mov->quantity) : ($isOut ? ($mov->balance_before - $mov->quantity) : $mov->balance_after);
                                $hasDiscrepancy = abs((float)$expected - (float)$mov->balance_after) > 0.001;

                                $typeBadge = match($mov->type) {
                                    'intake', 'in'     => 'success',
                                    'issue', 'out'     => 'warning',
                                    'transfer_in'      => 'info',
                                    'transfer_out'     => 'primary',
                                    'adjustment'       => 'danger',
                                    default            => 'secondary',
                                };
                            @endphp
                            <tr class="{{ $hasDiscrepancy ? 'table-danger bg-opacity-10' : '' }}">
                                <td data-label="Timestamp" class="ps-4 py-3 text-muted">
                                    <strong class="text-dark d-block">{{ $mov->created_at?->format('M d, Y') }}</strong>
                                    <small>{{ $mov->created_at?->format('H:i:s') }}</small>
                                </td>
                                <td data-label="Store" class="py-3">
                                    <strong class="text-dark d-block">{{ $mov->inventory?->store?->name ?? 'N/A' }}</strong>
                                    <small class="text-muted">{{ $mov->inventory?->store?->project?->name ?? 'Site' }}</small>
                                </td>
                                <td data-label="Item" class="py-3">
                                    <strong class="text-dark d-block">{{ $mov->inventory?->product?->name ?? 'N/A' }}</strong>
                                    <small class="text-muted font-monospace">SKU: {{ $mov->inventory?->product?->sku ?? 'N/A' }}</small>
                                </td>
                                <td data-label="Type" class="py-3 text-center">
                                    <span class="badge bg-{{ $typeBadge }} text-uppercase font-monospace px-2.5 py-1">
                                        {{ str_replace('_', ' ', $mov->type) }}
                                    </span>
                                </td>
                                <td data-label="Quantity" class="py-3 text-end font-monospace font-tabular">
                                    <strong class="fs-6 {{ $isIn ? 'text-success' : ($isOut ? 'text-danger' : 'text-dark') }}">
                                        {{ $isIn ? '+' : ($isOut ? '-' : '') }}{{ number_format((float)$mov->quantity, 2) }}
                                    </strong>
                                </td>
                                <td data-label="Balance Before" class="py-3 text-end font-monospace font-tabular text-muted">
                                    {{ number_format((float)$mov->balance_before, 2) }}
                                </td>
                                <td data-label="Balance After" class="py-3 text-end font-monospace font-tabular">
                                    <strong class="text-dark">{{ number_format((float)$mov->balance_after, 2) }}</strong>
                                </td>
                                <td data-label="Reconciliation" class="py-3 text-center">
                                    @if($hasDiscrepancy)
                                        <span class="badge bg-danger rounded-pill px-2.5 py-1 font-monospace" title="Expected: {{ number_format($expected, 2) }}">
                                            <i class="fa-solid fa-triangle-exclamation me-1"></i>DISCREPANCY (Exp: {{ number_format($expected, 2) }})
                                        </span>
                                    @else
                                        <span class="badge bg-success bg-opacity-25 text-success border border-success-subtle rounded-pill px-2.5 py-1">
                                            <i class="fa-solid fa-check me-1"></i>Reconciled
                                        </span>
                                    @endif
                                </td>
                                <td data-label="Reference" class="py-3">
                                    @if($mov->reference_type && $mov->reference_id)
                                        <span class="badge bg-light text-dark border font-monospace">
                                            {{ class_basename($mov->reference_type) }} #{{ $mov->reference_id }}
                                        </span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                    @if($mov->remarks || $mov->notes)
                                        <small class="text-muted d-block mt-1">{{ Str::limit($mov->remarks ?: $mov->notes, 35) }}</small>
                                    @endif
                                </td>
                                <td data-label="Action" class="pe-4 py-3 text-end">
                                    <button type="button" class="btn btn-sm btn-outline-warning text-dark rounded-pill px-3 shadow-xs fw-semibold" style="min-height: 32px;"
                                        data-bs-toggle="modal"
                                        data-bs-target="#raiseFindingModal"
                                        data-source-type="stock_movement"
                                        data-source-id="{{ $mov->id }}"
                                        data-store-id="{{ $mov->inventory?->store_id }}"
                                        data-project-id="{{ $mov->inventory?->store?->project_id }}"
                                        data-title="Stock Movement Query #{{ $mov->id }} - {{ $mov->inventory?->product?->name }}"
                                        data-severity="{{ $hasDiscrepancy ? 'high' : 'medium' }}"
                                        data-observation="{{ $hasDiscrepancy ? 'Running balance discrepancy detected on movement #' . $mov->id . '. Balance before: ' . number_format($mov->balance_before, 2) . ', Delta: ' . number_format($mov->quantity, 2) . ', Expected after: ' . number_format($expected, 2) . ', Stored after: ' . number_format($mov->balance_after, 2) : 'Observation regarding stock movement #' . $mov->id }}"
                                    >
                                        <i class="fa-solid fa-clipboard-question me-1"></i> Query
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-arrows-split-up-and-left fa-2x mb-2 d-block text-secondary"></i>
                                    No stock movements recorded matching your filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($movements->hasPages())
                <div class="p-3 border-top d-flex justify-content-center">
                    {{ $movements->links() }}
                </div>
            @endif
        </div>
    </div>

</div>

@include('audit._raise_finding_modal')
@endsection
