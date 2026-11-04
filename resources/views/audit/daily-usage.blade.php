@extends('layouts.app')
@section('title', 'Daily Usage Audit')

@section('content')
<div class="wc-container py-3 py-md-4">

    <!-- Page Header -->
    <x-page-header
        title="Daily Material Usage Audit"
        subtitle="Surveillance of daily site material consumption, planned vs. actual usage verification, and over-usage anomaly detection"
        badge="Audit &bull; Strictly Read-Only"
    >
        <x-slot name="actions">
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-outline-success btn-sm rounded-pill px-3 shadow-xs fw-semibold" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-file-excel me-1"></i> Export to Excel / CSV
                </a>
                <button type="button" class="btn btn-warning text-dark btn-sm rounded-pill px-3 shadow-xs fw-bold" style="min-height: 38px; display: inline-flex; align-items: center;" data-bs-toggle="modal" data-bs-target="#raiseFindingModal" data-source-type="daily_usage">
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
                    <a class="nav-link active rounded-pill fw-semibold px-3 py-1.5" href="{{ route('audit.daily-usage') }}">
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
        <div class="col-12 col-sm-6 col-xl-4">
            <x-kpi-card
                title="Total Usage Records"
                :value="number_format($totalRecords) . ' Slips'"
                subtitle="Daily construction usage submissions"
                icon="fa-solid fa-clipboard-check"
                tone="primary"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <x-kpi-card
                title="Total Value Consumed"
                :value="'ETB ' . number_format($totalUsageCost, 2)"
                subtitle="Calculated site material usage"
                icon="fa-solid fa-coins"
                tone="success"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <x-kpi-card
                title="Active Storekeepers"
                :value="$storekeepers->count() . ' Staff'"
                subtitle="Issuing materials at store sites"
                icon="fa-solid fa-users"
                tone="info"
            />
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('audit.daily-usage') }}" class="row g-2 align-items-end">
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
                    <label class="form-label small fw-bold text-muted mb-1">STORE</label>
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
                    <label class="form-label small fw-bold text-muted mb-1">STOREKEEPER</label>
                    <select name="storekeeper_id" class="form-select form-select-sm">
                        <option value="">All Storekeepers</option>
                        @foreach($storekeepers as $sk)
                            <option value="{{ $sk->id }}" {{ request('storekeeper_id') == $sk->id ? 'selected' : '' }}>
                                {{ $sk->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3 d-flex gap-1">
                    <div class="w-50">
                        <label class="form-label small fw-bold text-muted mb-1">FROM</label>
                        <input type="date" name="date_from" class="form-control form-select-sm" value="{{ request('date_from') }}">
                    </div>
                    <div class="w-50">
                        <label class="form-label small fw-bold text-muted mb-1">TO</label>
                        <input type="date" name="date_to" class="form-control form-select-sm" value="{{ request('date_to') }}">
                    </div>
                </div>

                <div class="col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill w-100 fw-semibold" style="height: 31px;">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    @if(request()->hasAny(['project_id', 'store_id', 'storekeeper_id', 'date_from', 'date_to']))
                        <a href="{{ route('audit.daily-usage') }}" class="btn btn-light btn-sm border rounded-pill" title="Reset Filters" style="height: 31px;">
                            <i class="fa-solid fa-rotate-left"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Daily Usage Table -->
    <div class="card border-0 shadow-sm rounded-3 bg-white mb-4">
        <div class="card-header bg-white border-bottom py-3 px-3 px-md-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-clipboard-list text-primary me-2"></i>Daily Material Consumption Records
                </h6>
                <small class="text-muted">Showing {{ $usages->firstItem() ?? 0 }} to {{ $usages->lastItem() ?? 0 }} of {{ $usages->total() }} recorded usage submissions</small>
            </div>
            <span class="badge bg-light text-dark border font-monospace px-3 py-1.5 rounded-pill">
                Page {{ $usages->currentPage() }} of {{ $usages->lastPage() }}
            </span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive wc-table-mobile-cards">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="bg-light text-muted text-uppercase" style="font-size: 0.75rem;">
                        <tr>
                            <th class="ps-4 py-2.5">Usage Date &amp; Slip</th>
                            <th class="py-2.5">Project / Site &amp; Store</th>
                            <th class="py-2.5">Storekeeper / Verifier</th>
                            <th class="py-2.5">Items Consumed &amp; Quantities</th>
                            <th class="py-2.5 text-end">Total Valuation</th>
                            <th class="py-2.5 text-center">Audit Anomaly Check</th>
                            <th class="pe-4 py-2.5 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($usages as $u)
                            @php
                                $totalUsageVal = (float) $u->items->sum('total_cost');
                                $totalQty = (float) $u->items->sum(fn($it) => (float)($it->used_quantity ?? $it->quantity ?? 0));
                                $isLargeUsage = ($totalQty > 500) || ($totalUsageVal > 50000);
                            @endphp
                            <tr class="{{ $isLargeUsage ? 'table-warning bg-opacity-10' : '' }}">
                                <td data-label="Date & Slip" class="ps-4 py-3">
                                    <strong class="text-dark d-block">{{ $u->usage_date ? $u->usage_date->format('M d, Y') : 'N/A' }}</strong>
                                    <span class="badge bg-light text-dark border font-monospace">{{ $u->slip_number ?? ('USG-' . $u->id) }}</span>
                                </td>
                                <td data-label="Site & Store" class="py-3">
                                    <strong class="text-dark d-block">{{ $u->project?->name ?? 'General Project' }}</strong>
                                    <small class="text-muted">{{ $u->store?->name ?? 'Main Store' }}</small>
                                </td>
                                <td data-label="Personnel" class="py-3">
                                    <span class="d-block text-dark fw-semibold"><i class="fa-solid fa-user me-1 text-secondary"></i>{{ $u->createdBy?->name ?? 'Storekeeper' }}</span>
                                    @if($u->verifiedBy)
                                        <small class="text-success"><i class="fa-solid fa-check-double me-1"></i>Verified: {{ $u->verifiedBy->name }}</small>
                                    @else
                                        <small class="text-muted fst-italic">Pending verification</small>
                                    @endif
                                </td>
                                <td data-label="Items Consumed" class="py-3">
                                    @foreach($u->items->take(3) as $it)
                                        <div class="mb-1">
                                            <span class="fw-semibold text-dark">{{ $it->product?->name ?? 'Item' }}:</span>
                                            <span class="font-monospace text-primary fw-bold">{{ number_format((float)($it->used_quantity ?? $it->quantity ?? 0), 2) }} {{ $it->product?->unit ?? 'pcs' }}</span>
                                            @if($it->total_cost)
                                                <small class="text-muted">(ETB {{ number_format((float)$it->total_cost, 2) }})</small>
                                            @endif
                                        </div>
                                    @endforeach
                                    @if($u->items->count() > 3)
                                        <small class="text-muted font-monospace">+{{ $u->items->count() - 3 }} more item(s)</small>
                                    @endif
                                </td>
                                <td data-label="Valuation" class="py-3 text-end font-monospace font-tabular">
                                    <strong class="text-dark fs-6">ETB {{ number_format($totalUsageVal, 2) }}</strong>
                                </td>
                                <td data-label="Anomaly Check" class="py-3 text-center">
                                    @if($isLargeUsage)
                                        <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1 font-monospace">
                                            <i class="fa-solid fa-bolt me-1"></i>High-Volume Usage
                                        </span>
                                    @else
                                        <span class="badge bg-success bg-opacity-25 text-success border border-success-subtle rounded-pill px-2.5 py-1">
                                            Normal Range
                                        </span>
                                    @endif
                                </td>
                                <td data-label="Action" class="pe-4 py-3 text-end">
                                    <button type="button" class="btn btn-sm btn-outline-warning text-dark rounded-pill px-3 shadow-xs fw-semibold" style="min-height: 32px;"
                                        data-bs-toggle="modal"
                                        data-bs-target="#raiseFindingModal"
                                        data-source-type="daily_usage"
                                        data-source-id="{{ $u->id }}"
                                        data-store-id="{{ $u->store_id }}"
                                        data-project-id="{{ $u->project_id }}"
                                        data-title="Query on Daily Usage Slip {{ $u->slip_number ?? ('#' . $u->id) }} ({{ $u->project?->name }})"
                                        data-severity="{{ $isLargeUsage ? 'high' : 'medium' }}"
                                        data-observation="{{ $isLargeUsage ? 'High-volume material consumption of ETB ' . number_format($totalUsageVal, 2) . ' recorded on ' . ($u->usage_date ? $u->usage_date->format('Y-m-d') : 'date') . '. Please provide plan task confirmation and BOQ allocation.' : 'Audit query regarding daily usage #' . $u->id }}"
                                    >
                                        <i class="fa-solid fa-clipboard-question me-1"></i> Query
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-clipboard-list fa-2x mb-2 d-block text-secondary"></i>
                                    No material usage records found matching your filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($usages->hasPages())
                <div class="p-3 border-top d-flex justify-content-center">
                    {{ $usages->links() }}
                </div>
            @endif
        </div>
    </div>

</div>

@include('audit._raise_finding_modal')
@endsection
