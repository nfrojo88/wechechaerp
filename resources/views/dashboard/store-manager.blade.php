@extends('layouts.app')
@section('title', 'Store Manager Dashboard')

@section('content')
<div class="wc-container py-3">

    {{-- Top Header --}}
    <x-page-header 
        title="Store &amp; Warehouse Command Center" 
        subtitle="Real-time stock valuations, inventory movements, replenishment alerts, and multi-warehouse balances."
        :breadcrumbs="['Dashboard' => url('/dashboard'), 'Store Management' => '']">
        <x-slot name="actions">
            @if(auth()->user()->can('inventory.edit') || auth()->user()->hasAnyRole(['admin', 'global_admin', 'store_manager', 'store_keeper']))
                <a href="{{ route('inventory.bulk-adjust') }}" class="wc-btn-touch wc-btn-outline">
                    <i class="fa-solid fa-sliders text-warning"></i>
                    <span>Manual Adjustment</span>
                </a>
            @endif
            <a href="{{ route('inventory.index') }}" class="wc-btn-touch wc-btn-primary">
                <i class="fa-solid fa-boxes-stacked"></i>
                <span>All Inventory</span>
            </a>
        </x-slot>
    </x-page-header>

    {{-- 4 Primary KPI Cards --}}
    @php
        $todayAdjVal = (float)($todayAdjustmentValue ?? 0);
        $adjPositive = $todayAdjVal >= 0;
        $lowStockCount = (int)($kpi['low_stock'] ?? 0);
    @endphp
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <x-kpi-card 
                label="Total Stock Value" 
                :value="number_format($kpi['total_value'] ?? 0, 0) . ' ETB'" 
                :delta="number_format($kpi['total_items'] ?? 0) . ' Items'" 
                trend="up" 
                icon="fa-solid fa-warehouse" 
                theme="primary" 
                subtext="Cumulative warehouse inventory" />
        </div>

        <div class="col-6 col-xl-3">
            <x-kpi-card 
                label="Today's Adjustments" 
                :value="($adjPositive ? '+' : '') . number_format($todayAdjVal, 0) . ' ETB'" 
                delta="Audit" 
                :trend="$adjPositive ? 'up' : 'down'" 
                icon="fa-solid fa-sliders" 
                :theme="$adjPositive ? 'success' : 'warning'" 
                subtext="Manual stock adjustments" />
        </div>

        <div class="col-6 col-xl-3">
            <x-kpi-card 
                label="Purchases This Month" 
                :value="number_format($monthlyReceiptsValue ?? 0, 0) . ' ETB'" 
                delta="Receipts" 
                trend="up" 
                icon="fa-solid fa-truck-ramp-box" 
                theme="success" 
                subtext="Goods Received Notes (GRN)" />
        </div>

        <div class="col-6 col-xl-3">
            <x-kpi-card 
                label="Low Stock Alerts" 
                :value="$lowStockCount" 
                :delta="$lowStockCount > 0 ? 'Action Needed' : 'Healthy'" 
                :trend="$lowStockCount > 0 ? 'down' : 'up'" 
                icon="fa-solid fa-triangle-exclamation" 
                :theme="$lowStockCount > 0 ? 'danger' : 'success'" 
                :subtext="$lowStockCount > 0 ? 'Items below reorder safety level' : 'All stock levels healthy'" />
        </div>
    </div>

    {{-- Financial Detail Row: Inventory Value by Store + Top 10 Products by Value --}}
    <div class="row g-3 mb-4">
        {{-- Value by Store Bars --}}
        <div class="col-xl-4">
            <div class="wc-card p-3 p-md-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="fa-solid fa-store text-primary me-2"></i>Stock Value by Store
                    </h6>
                    <span class="badge bg-light text-dark border">Distribution</span>
                </div>
                @php 
                    $storeList = $inventoryValueByStore ?? collect();
                    $maxStoreVal = $storeList->max('total_value') ?: 1; 
                @endphp
                <div class="d-flex flex-column gap-3">
                    @forelse($storeList as $sv)
                    <div>
                        <div class="d-flex justify-content-between align-items-baseline mb-1">
                            <span class="fw-semibold text-dark small">{{ $sv->store_name }}</span>
                            <span class="font-monospace fw-bold text-primary small">{{ number_format($sv->total_value, 0) }} ETB</span>
                        </div>
                        <div class="progress rounded" style="height: 6px; background-color: var(--wc-surface-subtle);">
                            <div class="progress-bar bg-primary" style="width: {{ ($sv->total_value / $maxStoreVal) * 100 }}%"></div>
                        </div>
                        <small class="text-muted" style="font-size: 0.72rem;">{{ $sv->product_count }} tracked product lines</small>
                    </div>
                    @empty
                    <div class="text-center text-muted py-4 small">No warehouse distribution data recorded.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Top Products by Value Table --}}
        <div class="col-xl-8">
            <div class="wc-card h-100">
                <div class="card-header bg-white py-3 px-3 px-md-4 d-flex align-items-center justify-content-between border-bottom">
                    <div>
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="fa-solid fa-trophy text-warning me-2"></i>Top High-Value Stock Lines
                        </h6>
                        <small class="text-muted">Ranked by total capital tied up</small>
                    </div>
                    <a href="{{ route('inventory.index') }}" class="text-primary small text-decoration-none fw-semibold">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                        <thead class="bg-light text-uppercase text-muted" style="font-size: var(--wc-text-xs);">
                            <tr>
                                <th class="ps-3 ps-md-4" style="width: 40px;">#</th>
                                <th>Product Line</th>
                                <th>Store</th>
                                <th class="text-end">On Hand</th>
                                <th class="text-end">Unit Cost</th>
                                <th class="text-end pe-3 pe-md-4">Total Value (ETB)</th>
                            </tr>
                        </thead>
                        <tbody style="font-size: var(--wc-text-sm);">
                            @php 
                                $topList = $topValueItems ?? collect();
                            @endphp
                            @forelse($topList as $idx => $item)
                            <tr>
                                <td data-label="Rank" class="ps-3 ps-md-4">
                                    <span class="badge rounded-circle d-inline-flex align-items-center justify-content-center font-monospace" 
                                          style="width: 24px; height: 24px; background-color: {{ $idx === 0 ? 'var(--wc-warning-bg)' : 'var(--wc-surface-subtle)' }}; color: {{ $idx === 0 ? 'var(--wc-warning-text)' : 'var(--wc-text-main)' }};">
                                        {{ $idx + 1 }}
                                    </span>
                                </td>
                                <td data-label="Product">
                                    <div class="fw-bold text-dark">{{ $item->product_name }}</div>
                                    <small class="text-muted font-monospace">{{ $item->sku }}</small>
                                </td>
                                <td data-label="Store"><span class="badge bg-light text-dark border">{{ $item->store_name }}</span></td>
                                <td data-label="Quantity" class="text-end wc-num font-monospace fw-semibold">{{ number_format($item->quantity_on_hand, 2) }} <small class="text-muted">{{ $item->unit }}</small></td>
                                <td data-label="Unit Cost" class="text-end wc-num font-monospace text-muted">{{ number_format($item->unit_cost, 2) }}</td>
                                <td data-label="Total Value" class="text-end pe-3 pe-md-4 wc-num font-monospace fw-bold text-success">ETB {{ number_format($item->line_value, 0) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-4 small">No priced inventory available yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Actions Row --}}
    <div class="wc-card p-3 p-md-4 mb-4">
        <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-bolt text-warning me-2"></i>Store Operations Quick Actions</h6>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('delivery-receipts.create') }}" class="wc-btn-touch wc-btn-primary">
                <i class="fa-solid fa-plus"></i> New Goods Receipt (GRN)
            </a>
            <a href="{{ route('transfers.create') }}" class="wc-btn-touch wc-btn-outline">
                <i class="fa-solid fa-arrow-right-arrow-left text-primary"></i> Inter-Store Transfer
            </a>
            <a href="{{ route('inventory.index') }}" class="wc-btn-touch wc-btn-outline">
                <i class="fa-solid fa-boxes-stacked text-secondary"></i> Stock Master Ledger
            </a>
            @if(auth()->user()->can('inventory.edit') || auth()->user()->hasAnyRole(['admin', 'global_admin', 'store_manager', 'store_keeper']))
                <a href="{{ route('inventory.bulk-adjust') }}" class="wc-btn-touch wc-btn-outline">
                    <i class="fa-solid fa-sliders text-success"></i> Stock Count Adjustment
                </a>
            @endif
            <a href="{{ route('material-requests.index') }}" class="wc-btn-touch wc-btn-outline">
                <i class="fa-solid fa-cart-flatbed text-info"></i> Material Requisitions
            </a>
            <a href="{{ route('store-manager.inventory.all') }}" class="wc-btn-touch wc-btn-outline">
                <i class="fa-solid fa-file-pdf text-danger"></i> Inventory Audit Report
            </a>
        </div>
    </div>

    {{-- Inventory Overview & Low Stock Alerts --}}
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="wc-card h-100">
                <div class="card-header bg-white py-3 px-3 px-md-4 d-flex justify-content-between align-items-center border-bottom">
                    <div>
                        <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-boxes text-primary me-2"></i>Warehouse Inventory Overview</h6>
                        <small class="text-muted">Current on-hand stock and valuations</small>
                    </div>
                    <a href="{{ route('inventory.index') }}" class="text-primary small text-decoration-none fw-semibold">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                        <thead class="bg-light text-uppercase text-muted" style="font-size: var(--wc-text-xs);">
                            <tr>
                                <th class="ps-3 ps-md-4">Product</th>
                                <th>Store</th>
                                <th class="text-end">On Hand</th>
                                <th class="text-end">Unit Cost</th>
                                <th class="text-end">Line Value</th>
                                <th class="text-center pe-3 pe-md-4">Status</th>
                            </tr>
                        </thead>
                        <tbody style="font-size: var(--wc-text-sm);">
                            @forelse($allInventory ?? [] as $item)
                            @php $lineVal = (float)$item->quantity_on_hand * (float)$item->unit_cost; @endphp
                            <tr>
                                <td data-label="Product" class="ps-3 ps-md-4">
                                    <div class="fw-bold text-dark">{{ $item->product->name ?? 'N/A' }}</div>
                                    <small class="text-muted font-monospace">{{ $item->product->code ?? '' }}</small>
                                </td>
                                <td data-label="Store"><span class="badge bg-light text-dark border">{{ $item->store->name ?? 'N/A' }}</span></td>
                                <td data-label="On Hand" class="text-end wc-num font-monospace fw-bold">{{ number_format($item->quantity_on_hand, 2) }}</td>
                                <td data-label="Unit Cost" class="text-end wc-num font-monospace text-muted">{{ $item->unit_cost ? number_format($item->unit_cost, 2) : '—' }}</td>
                                <td data-label="Line Value" class="text-end wc-num font-monospace fw-bold {{ $lineVal > 0 ? 'text-success' : 'text-muted' }}">
                                    {{ $lineVal > 0 ? number_format($lineVal, 0) . ' ETB' : '—' }}
                                </td>
                                <td data-label="Status" class="text-center pe-3 pe-md-4">
                                    @if($item->quantity_on_hand <= $item->min_stock)
                                        <x-status-badge status="critical" label="Low Stock" />
                                    @else
                                        <x-status-badge status="approved" label="OK" />
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center py-4 text-muted small">No inventory recorded.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="wc-card h-100">
                <div class="card-header bg-white py-3 px-3 px-md-4 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold text-danger mb-0">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>Critical Low Stock
                    </h6>
                    <span class="badge bg-danger text-white rounded-pill px-2.5 py-0.5">{{ count($lowStockItems ?? []) }}</span>
                </div>
                <div class="p-0">
                    <div class="divide-y divide-slate-100">
                        @forelse($lowStockItems ?? [] as $item)
                        <div class="p-3 border-bottom last:border-bottom-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold text-dark small">{{ $item->product->name ?? 'N/A' }}</div>
                                    <span class="badge bg-light text-muted border" style="font-size: 0.7rem;">{{ $item->store->name ?? 'N/A' }}</span>
                                </div>
                                <div class="text-end">
                                    <div class="text-danger fw-bold font-monospace">{{ number_format($item->quantity_on_hand ?? $item->quantity ?? 0, 2) }}</div>
                                    <small class="text-muted" style="font-size: 0.7rem;">Min: {{ number_format($item->min_stock ?? $item->min_level ?? 0, 2) }}</small>
                                </div>
                            </div>
                        </div>
                        @empty
                        <div class="text-center text-success py-4 small">
                            <i class="fa-solid fa-circle-check fs-4 mb-2 d-block"></i>
                            All inventory items are above minimum safety thresholds!
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
