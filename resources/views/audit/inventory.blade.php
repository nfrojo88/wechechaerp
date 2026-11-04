@extends('layouts.app')
@section('title', 'Inventory Audit Overview')

@section('content')
<div class="wc-container py-3 py-md-4">

    <!-- Page Header -->
    <x-page-header
        title="Store &amp; Inventory Overview (Audit)"
        subtitle="Real-time multi-store stock ledger oversight, negative balance detection, and dormant inventory surveillance"
        badge="Audit &bull; Strictly Read-Only"
    >
        <x-slot name="actions">
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-outline-success btn-sm rounded-pill px-3 shadow-xs fw-semibold" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-file-excel me-1"></i> Export to Excel / CSV
                </a>
                <button type="button" class="btn btn-warning text-dark btn-sm rounded-pill px-3 shadow-xs fw-bold" style="min-height: 38px; display: inline-flex; align-items: center;" data-bs-toggle="modal" data-bs-target="#raiseFindingModal" data-source-type="inventory">
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
                    <a class="nav-link active rounded-pill fw-semibold px-3 py-1.5" href="{{ route('audit.inventory') }}">
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
        <div class="col-12 col-sm-6 col-xl-2">
            <x-kpi-card
                title="Items Monitored"
                :value="number_format($totalItemsCount) . ' SKUs'"
                subtitle="Across All Active Warehouses"
                icon="fa-solid fa-boxes-stacked"
                tone="primary"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Total Stock Valuation"
                :value="'ETB ' . number_format($totalValuation, 2)"
                subtitle="Calculated on Weighted Unit Cost"
                icon="fa-solid fa-money-bill-wave"
                tone="success"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <x-kpi-card
                title="Negative Stock Flags"
                :value="$negativeCount . ' Lines'"
                subtitle="Stock Depleted Below Zero"
                icon="fa-solid fa-circle-exclamation"
                :tone="$negativeCount > 0 ? 'danger' : 'success'"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Dormant Stock (&gt;30d)"
                :value="$dormantCount . ' Items'"
                subtitle="Zero Movement Recorded in 30+ Days"
                icon="fa-solid fa-hourglass-half"
                :tone="$dormantCount > 0 ? 'warning' : 'primary'"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <x-kpi-card
                title="Low Stock SKUs"
                :value="$lowStockCount . ' Items'"
                subtitle="Below Minimum Reorder Point"
                icon="fa-solid fa-arrow-down-short-wide"
                tone="warning"
            />
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('audit.inventory') }}" class="row g-2 align-items-end">
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
                    <label class="form-label small fw-bold text-muted mb-1">PROJECT</label>
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">All Projects</option>
                        @foreach($projects as $pj)
                            <option value="{{ $pj->id }}" {{ request('project_id') == $pj->id ? 'selected' : '' }}>
                                {{ $pj->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">CATEGORY</label>
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" {{ request('category') == $cat ? 'selected' : '' }}>
                                {{ $cat }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">AUDIT STATUS</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="negative" {{ request('status') == 'negative' ? 'selected' : '' }}>Negative Stock (&lt; 0)</option>
                        <option value="dormant_30" {{ request('status') == 'dormant_30' ? 'selected' : '' }}>Dormant &gt; 30 Days</option>
                        <option value="dormant_60" {{ request('status') == 'dormant_60' ? 'selected' : '' }}>Dormant &gt; 60 Days</option>
                        <option value="dormant_90" {{ request('status') == 'dormant_90' ? 'selected' : '' }}>Dormant &gt; 90 Days</option>
                        <option value="low_stock" {{ request('status') == 'low_stock' ? 'selected' : '' }}>Low Stock (&lt; Min)</option>
                        <option value="out_of_stock" {{ request('status') == 'out_of_stock' ? 'selected' : '' }}>Out of Stock (= 0)</option>
                        <option value="in_stock" {{ request('status') == 'in_stock' ? 'selected' : '' }}>In Stock (&gt; 0)</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">SEARCH ITEM</label>
                    <input type="text" name="search" class="form-control form-select-sm" placeholder="Name, SKU, Barcode..." value="{{ request('search') }}">
                </div>

                <div class="col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill w-100 fw-semibold" style="height: 31px;">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    @if(request()->hasAny(['store_id', 'project_id', 'category', 'status', 'search']))
                        <a href="{{ route('audit.inventory') }}" class="btn btn-light btn-sm border rounded-pill" title="Reset Filters" style="height: 31px;">
                            <i class="fa-solid fa-rotate-left"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Inventory Table Card -->
    <div class="card border-0 shadow-sm rounded-3 bg-white mb-4">
        <div class="card-header bg-white border-bottom py-3 px-3 px-md-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Inventory Audit Ledger
                </h6>
                <small class="text-muted">Showing {{ $inventories->firstItem() ?? 0 }} to {{ $inventories->lastItem() ?? 0 }} of {{ $inventories->total() }} recorded inventory balances</small>
            </div>
            <span class="badge bg-light text-dark border font-monospace px-3 py-1.5 rounded-pill">
                Page {{ $inventories->currentPage() }} of {{ $inventories->lastPage() }}
            </span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive wc-table-mobile-cards">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="bg-light text-muted text-uppercase" style="font-size: 0.75rem;">
                        <tr>
                            <th class="ps-4 py-2.5">Store &amp; Site</th>
                            <th class="py-2.5">Item Name &amp; SKU</th>
                            <th class="py-2.5">Category</th>
                            <th class="py-2.5 text-end">On-Hand Qty</th>
                            <th class="py-2.5 text-end">Unit Cost</th>
                            <th class="py-2.5 text-end">Total Valuation</th>
                            <th class="py-2.5 text-center">Last Movement</th>
                            <th class="py-2.5 text-center">Audit Status</th>
                            <th class="pe-4 py-2.5 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($inventories as $inv)
                            @php
                                $qty = (float) $inv->quantity_on_hand;
                                $unitCost = (float) ($inv->unit_cost ?? 0);
                                $valuation = $qty * $unitCost;
                                $isNegative = $qty < 0;
                                $isDormant30 = !$inv->last_movement_at || $inv->last_movement_at->lt(now()->subDays(30));
                                $isLowStock = $qty > 0 && $qty <= ($inv->min_stock ?? 0);
                            @endphp
                            <tr class="{{ $isNegative ? 'table-danger bg-opacity-10' : ($isDormant30 ? 'table-warning bg-opacity-10' : '') }}">
                                <td data-label="Store / Site" class="ps-4 py-3">
                                    <strong class="text-dark d-block">{{ $inv->store?->name ?? 'N/A' }}</strong>
                                    <small class="text-muted"><i class="fa-solid fa-location-dot me-1"></i>{{ $inv->store?->project?->name ?? 'General / Warehouse' }}</small>
                                </td>
                                <td data-label="Item" class="py-3">
                                    <strong class="text-dark d-block">{{ $inv->product?->name ?? 'Unknown Item' }}</strong>
                                    <small class="text-muted font-monospace">SKU: {{ $inv->product?->sku ?? 'N/A' }}</small>
                                </td>
                                <td data-label="Category" class="py-3">
                                    <span class="badge bg-secondary-subtle text-dark border">
                                        {{ $inv->product?->category ?? 'General' }}
                                    </span>
                                </td>
                                <td data-label="On-Hand" class="py-3 text-end font-monospace font-tabular">
                                    <strong class="fs-6 {{ $isNegative ? 'text-danger' : ($qty == 0 ? 'text-muted' : 'text-dark') }}">
                                        {{ number_format($qty, 2) }}
                                    </strong>
                                    <small class="text-muted d-block">{{ $inv->product?->unit ?? 'pcs' }}</small>
                                </td>
                                <td data-label="Unit Cost" class="py-3 text-end font-monospace font-tabular">
                                    ETB {{ number_format($unitCost, 2) }}
                                </td>
                                <td data-label="Valuation" class="py-3 text-end font-monospace font-tabular">
                                    <strong class="{{ $valuation < 0 ? 'text-danger' : 'text-success' }}">
                                        ETB {{ number_format($valuation, 2) }}
                                    </strong>
                                </td>
                                <td data-label="Last Movement" class="py-3 text-center">
                                    @if($inv->last_movement_at)
                                        <span class="text-dark fw-semibold d-block">{{ $inv->last_movement_at->format('M d, Y') }}</span>
                                        <small class="text-muted">({{ $inv->last_movement_at->diffForHumans() }})</small>
                                    @else
                                        <span class="badge bg-light text-muted border">No movement</span>
                                    @endif
                                </td>
                                <td data-label="Status" class="py-3 text-center">
                                    @if($isNegative)
                                        <span class="badge bg-danger rounded-pill px-2.5 py-1 font-monospace">
                                            <i class="fa-solid fa-triangle-exclamation me-1"></i>NEGATIVE STOCK
                                        </span>
                                    @elseif($qty == 0)
                                        <span class="badge bg-secondary rounded-pill px-2.5 py-1">Out of Stock</span>
                                    @elseif($isLowStock)
                                        <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1">Low Stock</span>
                                    @elseif($isDormant30)
                                        <span class="badge bg-warning bg-opacity-75 text-dark rounded-pill px-2.5 py-1 font-monospace">
                                            <i class="fa-solid fa-clock me-1"></i>Dormant (&gt;30d)
                                        </span>
                                    @else
                                        <span class="badge bg-success bg-opacity-25 text-success border border-success-subtle rounded-pill px-2.5 py-1">
                                            In Stock
                                        </span>
                                    @endif
                                </td>
                                <td data-label="Action" class="pe-4 py-3 text-end">
                                    <button type="button" class="btn btn-sm btn-outline-warning text-dark rounded-pill px-3 shadow-xs fw-semibold" style="min-height: 32px;"
                                        data-bs-toggle="modal"
                                        data-bs-target="#raiseFindingModal"
                                        data-source-type="inventory"
                                        data-source-id="{{ $inv->id }}"
                                        data-store-id="{{ $inv->store_id }}"
                                        data-project-id="{{ $inv->store?->project_id }}"
                                        data-title="Observation on {{ $inv->product?->name }} (Store: {{ $inv->store?->name }})"
                                        data-severity="{{ $isNegative ? 'critical' : ($isDormant30 ? 'medium' : 'low') }}"
                                        data-observation="{{ $isNegative ? 'Negative stock balance of ' . number_format($qty, 2) . ' detected on ' . $inv->product?->name . '. Immediate physical stock count and adjustment required.' : ($isDormant30 ? 'Item has remained dormant for over 30 days without movement. Please verify physical condition and reallocate if necessary.' : '') }}"
                                    >
                                        <i class="fa-solid fa-clipboard-question me-1"></i> Query
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-box-open fa-2x mb-2 d-block text-secondary"></i>
                                    No inventory balance records found matching your filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($inventories->hasPages())
                <div class="p-3 border-top d-flex justify-content-center">
                    {{ $inventories->links() }}
                </div>
            @endif
        </div>
    </div>

</div>

@include('audit._raise_finding_modal')
@endsection
