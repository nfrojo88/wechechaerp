@extends('layouts.app')
@section('title', 'Store Keeper Dashboard')

@section('content')
<div class="wc-container py-3 py-md-4">

    {{-- Top Header --}}
    <x-page-header
        title="Store Keeper Dashboard"
        subtitle="On-site storekeeping, stock receipts, material issuing &amp; physical transfer slips"
        badge="Site Store Operations"
    >
        <x-slot name="actions">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-white text-secondary border px-3 py-2 shadow-xs rounded-pill d-none d-sm-inline-flex align-items-center">
                    <i class="fa-regular fa-calendar text-primary me-2"></i>{{ now()->format('l, F j, Y') }}
                </span>
                <a href="{{ route('store-keeper.petty-cash-purchases.create') }}" class="btn btn-success btn-sm shadow-xs fw-bold" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-cart-shopping me-1"></i> Buy Material (Petty Cash)
                </a>
                <a href="{{ route('store-manager.transfers.create') }}" class="btn btn-primary btn-sm shadow-xs" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-exchange-alt me-1"></i> New Transfer
                </a>
            </div>
        </x-slot>
    </x-page-header>

    {{-- ── Assigned Store Banner ──────────────────────────────────────────────── --}}
    @if($assignedStore)
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white border-start border-4 border-info">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 rounded-3 bg-primary bg-opacity-10 text-primary flex-shrink-0" style="width: 52px; height: 52px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-warehouse fa-2x"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                            <h4 class="mb-0 fw-bold text-dark fs-5">{{ $assignedStore->name }}</h4>
                            <span class="badge bg-primary font-monospace">{{ $assignedStore->code ?? 'STORE' }}</span>
                            <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i>Assigned Site Store</span>
                        </div>
                        <p class="text-muted mb-0 small">
                            <i class="fa-solid fa-location-dot me-1 text-danger"></i>{{ $assignedStore->address ?: 'Site Location' }}
                            @if($assignedStore->project)
                                <span class="mx-2">·</span>
                                <i class="fa-solid fa-building me-1 text-secondary"></i>Project: <strong>{{ $assignedStore->project->name }}</strong>
                            @endif
                            <span class="mx-2">·</span>
                            <i class="fa-solid fa-tag me-1 text-info"></i>Type: {{ ucfirst($assignedStore->type ?? 'Site Warehouse') }}
                        </p>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ \Illuminate\Support\Facades\Route::has('store-keeper.weekly-material-demand') ? route('store-keeper.weekly-material-demand') : (\Illuminate\Support\Facades\Route::has('store-manager.weekly-material-demand') ? route('store-manager.weekly-material-demand') : url('/store-keeper/weekly-material-demand')) }}" class="btn btn-warning text-dark btn-sm shadow-xs" style="min-height: 36px; display: inline-flex; align-items: center;">
                        <i class="fa-solid fa-calendar-check me-1"></i> Weekly Demand
                    </a>
                    <a href="{{ route('store-manager.material-requests.index') }}" class="btn btn-danger btn-sm shadow-xs" style="min-height: 36px; display: inline-flex; align-items: center;">
                        <i class="fa-solid fa-clipboard-list me-1"></i> Material Requests
                    </a>
                    <a href="{{ route('store-manager.inventory.all') }}" class="btn btn-outline-secondary btn-sm shadow-xs" style="min-height: 36px; display: inline-flex; align-items: center;">
                        <i class="fa-solid fa-boxes-stacked me-1"></i> View Stock
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Emergency Material Requests Awaiting Petty Cash Purchase Alert --}}
    @if(isset($pendingPettyCashEmergencyMrs) && $pendingPettyCashEmergencyMrs->isNotEmpty())
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white border-start border-4 border-danger">
        <div class="card-body p-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-danger bg-opacity-10 p-2 text-danger d-flex align-items-center justify-content-center flex-shrink-0" style="width: 46px; height: 46px;">
                    <i class="fa-solid fa-bolt fa-lg text-danger"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark">
                        {{ $pendingPettyCashEmergencyMrs->count() }} Emergency Material Request(s) Approved for Petty Cash Purchase!
                    </div>
                    <small class="text-muted">
                        Planning Manager approved urgent site material request(s). Materials are pre-loaded—enter price &amp; slip number to buy.
                    </small>
                </div>
            </div>
            <a href="{{ route('store-keeper.petty-cash-purchases.index') }}" class="btn btn-danger btn-sm fw-bold shadow-xs px-3" style="min-height: 36px; display: inline-flex; align-items: center;">
                <i class="fa-solid fa-cart-shopping me-1"></i> Open Petty Cash Purchase Queue
            </a>
        </div>
    </div>
    @endif
    @else
    <div class="alert alert-warning border-0 shadow-sm rounded-3 mb-4 p-4">
        <div class="d-flex align-items-center gap-3">
            <i class="fa-solid fa-triangle-exclamation fa-2x text-warning"></i>
            <div>
                <h5 class="fw-bold mb-1">No Store Assigned</h5>
                <p class="mb-0 small">Your account is not currently assigned to a specific store location. Please contact your system administrator or Store Manager to link your account to your site store.</p>
            </div>
        </div>
    </div>
    @endif

    {{-- ── KPI Cards ────────────────────────────────────────────────────────── --}}
    <div class="row g-3 mb-4">
        {{-- Total SKUs in Store --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="In-Store Items"
                :value="number_format($kpi['total_items'])"
                subtitle="Unique catalog products in store"
                icon="fa-solid fa-boxes-stacked"
                tone="primary"
            />
        </div>

        {{-- Total Units on Hand --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Stock Units on Hand"
                :value="number_format($kpi['total_stock_qty'])"
                subtitle="Total inventory units stored"
                icon="fa-solid fa-cubes-stacked"
                tone="success"
            />
        </div>

        {{-- Low Stock Alerts --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Low Stock Alerts"
                :value="$kpi['low_stock_count']"
                subtitle="Items below minimum stock"
                icon="fa-solid fa-triangle-exclamation"
                :tone="$kpi['low_stock_count'] > 0 ? 'danger' : 'neutral'"
            />
        </div>

        {{-- Active Transfers --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Site Transfers"
                :value="$kpi['pending_incoming'] + $kpi['pending_outgoing']"
                :subtitle="$kpi['pending_incoming'] . ' incoming · ' . $kpi['pending_outgoing'] . ' outgoing'"
                icon="fa-solid fa-truck-fast"
                tone="warning"
            />
        </div>
    </div>

    {{-- ── Quick Actions Bar ────────────────────────────────────────────────── --}}
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-3 bg-white">
                <div class="card-body p-3">
                    <p class="text-muted fw-semibold mb-2" style="font-size:0.78rem; text-transform:uppercase; letter-spacing:.05em;">Store Keeper Quick Modules</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('store-manager.inventory.all') }}" class="btn btn-outline-info btn-sm px-3" style="min-height: 38px; display: inline-flex; align-items: center;">
                            <i class="fa-solid fa-boxes-stacked me-1"></i> 1. Store Inventory
                        </a>
                        <a href="{{ route('store-manager.material-requests.index') }}" class="btn btn-outline-danger btn-sm px-3" style="min-height: 38px; display: inline-flex; align-items: center;">
                            <i class="fa-solid fa-clipboard-list me-1"></i> 2. Material Requests (Issue Stock)
                        </a>
                        <a href="{{ route('store-manager.transfers.index') }}" class="btn btn-outline-warning text-dark btn-sm px-3" style="min-height: 38px; display: inline-flex; align-items: center;">
                            <i class="fa-solid fa-truck-moving me-1"></i> 3. Transfers &amp; Slips
                        </a>
                        <a href="{{ \Illuminate\Support\Facades\Route::has('store-keeper.weekly-material-demand') ? route('store-keeper.weekly-material-demand') : (\Illuminate\Support\Facades\Route::has('store-manager.weekly-material-demand') ? route('store-manager.weekly-material-demand') : url('/store-keeper/weekly-material-demand')) }}" class="btn btn-outline-primary btn-sm px-3" style="min-height: 38px; display: inline-flex; align-items: center;">
                            <i class="fa-solid fa-calendar-check me-1"></i> 4. Weekly Material Demand
                        </a>
                        <a href="{{ route('store-keeper.petty-cash-purchases.index') }}" class="btn btn-outline-success btn-sm px-3" style="min-height: 38px; display: inline-flex; align-items: center;">
                            <i class="fa-solid fa-cart-shopping me-1"></i> 5. Buy Material (Petty Cash)
                        </a>
                        <a href="{{ route('expense-requests.index') }}" class="btn btn-outline-secondary btn-sm px-3" style="min-height: 38px; display: inline-flex; align-items: center;">
                            <i class="fa-solid fa-hand-holding-dollar me-1"></i> 6. Expense Requests
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Main Layout: Transfers & Inventory ───────────────────────────────── --}}
    <div class="row g-4 mb-4">
        {{-- Left: Site Transfers (Incoming & Outgoing for this store) --}}
        <div class="col-lg-8">

            {{-- Transfers Card --}}
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="mb-0 fw-bold text-dark">
                            <i class="fa-solid fa-truck-arrow-right text-primary me-2"></i>Site Store Transfers &amp; Physical Slips
                        </h6>
                        <small class="text-muted">Incoming &amp; outgoing transfers for this store</small>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('store-manager.transfers.index', ['tab' => 'incoming']) }}" class="btn btn-sm btn-outline-success rounded-pill px-3" style="min-height: 34px; display: inline-flex; align-items: center;">
                            <i class="fa-solid fa-arrow-down me-1"></i>Incoming ({{ $kpi['pending_incoming'] ?? 0 }})
                        </a>
                        <a href="{{ route('store-manager.transfers.index', ['tab' => 'outgoing']) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3" style="min-height: 34px; display: inline-flex; align-items: center;">
                            <i class="fa-solid fa-arrow-up me-1"></i>Outgoing ({{ $kpi['pending_outgoing'] ?? 0 }})
                        </a>
                        <a href="{{ route('store-manager.transfers.create') }}" class="btn btn-sm btn-primary rounded-pill px-3" style="min-height: 34px; display: inline-flex; align-items: center;">
                            <i class="fa-solid fa-plus me-1"></i>New Transfer
                        </a>
                    </div>
                </div>
                <div class="table-responsive wc-table-mobile-cards">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Transfer #</th>
                                <th>Physical Slip #</th>
                                <th>Direction</th>
                                <th>Origin / Dest</th>
                                <th>Status</th>
                                <th class="text-end pe-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentTransfers as $transfer)
                            @php
                                $isIncoming = $storeId && ($transfer->to_store_id == $storeId);
                                $isOutgoing = $storeId && ($transfer->from_store_id == $storeId);
                            @endphp
                            <tr>
                                <td data-label="Transfer #" class="ps-3">
                                    <a href="{{ route('store-manager.transfers.show', $transfer) }}" class="fw-bold font-monospace text-primary text-decoration-none">
                                        {{ $transfer->transfer_no ?? 'TR-'.$transfer->id }}
                                    </a>
                                </td>
                                <td data-label="Slip #">
                                    @if($transfer->physical_slip_no)
                                        <span class="badge bg-success font-monospace" style="font-size:0.75rem;">{{ $transfer->physical_slip_no }}</span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td data-label="Direction">
                                    @if($isIncoming)
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                            <i class="fa-solid fa-arrow-down me-1"></i>Incoming
                                        </span>
                                    @elseif($isOutgoing)
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                                            <i class="fa-solid fa-arrow-up me-1"></i>Outgoing
                                        </span>
                                    @else
                                        <span class="badge bg-light text-dark">Transfer</span>
                                    @endif
                                </td>
                                <td data-label="Origin / Dest">
                                    <span class="fw-semibold text-dark small">
                                        {{ $isIncoming ? ($transfer->fromStore->name ?? 'Main Warehouse') : ($transfer->toStore->name ?? 'Destination') }}
                                    </span>
                                </td>
                                <td data-label="Status">
                                    <x-status-badge :status="$transfer->status ?? 'Draft'" />
                                </td>
                                <td data-label="Action" class="text-end pe-3">
                                    <a href="{{ route('store-manager.transfers.show', $transfer) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3" style="min-height: 34px; display: inline-flex; align-items: center;">
                                        <i class="fa-solid fa-eye me-1"></i>View
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-truck-moving fa-2x mb-2 d-block opacity-25"></i>
                                    No transfers recorded for this store yet.
                                    <a href="{{ route('store-manager.transfers.create') }}" class="d-block mt-1 small">Initiate a transfer →</a>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Store Inventory on Hand Card --}}
            <div class="card border-0 shadow-sm rounded-3 bg-white">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="mb-0 fw-bold text-dark">
                            <i class="fa-solid fa-boxes-stacked text-success me-2"></i>In-Store Inventory Overview
                        </h6>
                        <small class="text-muted">Top products currently on hand in {{ $assignedStore->name ?? 'this store' }}</small>
                    </div>
                    <a href="{{ route('store-manager.inventory.all') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3" style="min-height: 34px; display: inline-flex; align-items: center;">Full Stock List</a>
                </div>
                <div class="table-responsive wc-table-mobile-cards">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Product Name</th>
                                <th>Category / Unit</th>
                                <th class="text-end">On Hand Qty</th>
                                <th class="text-end">Min Stock</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($storeInventory as $item)
                            @php
                                $isLow = $item->quantity_on_hand <= $item->min_stock;
                                $isOut = $item->quantity_on_hand <= 0;
                            @endphp
                            <tr>
                                <td data-label="Product" class="ps-3">
                                    <span class="fw-semibold text-dark d-block">{{ $item->product->name ?? '—' }}</span>
                                    <small class="text-muted font-monospace">{{ $item->product->sku ?? $item->product->code ?? '' }}</small>
                                </td>
                                <td data-label="Unit">
                                    <span class="badge bg-light text-dark border">{{ $item->product->unit_of_measure ?? $item->product->unit ?? 'Units' }}</span>
                                </td>
                                <td data-label="On Hand" class="text-end font-tabular">
                                    <span class="fw-bold {{ $isOut ? 'text-danger' : ($isLow ? 'text-warning' : 'text-dark') }}">
                                        {{ number_format($item->quantity_on_hand, 2) }}
                                    </span>
                                </td>
                                <td data-label="Min Stock" class="text-end font-tabular text-muted">
                                    {{ number_format($item->min_stock, 2) }}
                                </td>
                                <td data-label="Status" class="text-center">
                                    @if($isOut)
                                        <x-status-badge status="Out of Stock" tone="danger" />
                                    @elseif($isLow)
                                        <x-status-badge status="Low Stock" tone="warning" />
                                    @else
                                        <x-status-badge status="In Stock" tone="success" />
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    No inventory items recorded in this store yet.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        {{-- Right Panel: Alerts & Material Requests --}}
        <div class="col-lg-4">

            {{-- Low Stock Urgent Alerts --}}
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-danger">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>Low Stock Alerts
                        @if($kpi['low_stock_count'] > 0)
                            <span class="badge bg-danger rounded-pill ms-1">{{ $kpi['low_stock_count'] }}</span>
                        @endif
                    </h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($lowStockItems as $low)
                        <li class="list-group-item px-3 py-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="fw-semibold d-block text-dark small">{{ $low->product->name ?? 'Product #'.$low->product_id }}</span>
                                    <small class="text-danger fw-semibold">
                                        Qty: {{ number_format($low->quantity_on_hand, 2) }} (Min: {{ number_format($low->min_stock, 2) }})
                                    </small>
                                </div>
                                <a href="{{ route('store-manager.transfers.create') }}" class="btn btn-sm btn-outline-primary rounded-pill px-2" style="min-height: 32px; display: inline-flex; align-items: center;">
                                    Transfer
                                </a>
                            </div>
                        </li>
                        @empty
                        <li class="list-group-item text-center py-3 text-muted small">
                            <i class="fa-solid fa-check-circle text-success me-1"></i>All store inventory levels are healthy!
                        </li>
                        @endforelse
                    </ul>
                </div>
            </div>

            {{-- Site Material Requests --}}
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-clipboard-list text-info me-2"></i>Site Material Requests
                    </h6>
                    <a href="{{ route('store-manager.material-requests.index') }}" class="btn btn-sm btn-outline-info rounded-pill px-3" style="min-height: 32px; display: inline-flex; align-items: center;">View All</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($materialRequests as $req)
                        <li class="list-group-item px-3 py-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="fw-semibold d-block text-dark small">{{ $req->request_no ?? 'MR-'.$req->id }}</span>
                                    <small class="text-muted">{{ $req->project->name ?? 'Site Project' }} · {{ optional($req->created_at)->format('d M') }}</small>
                                </div>
                                <x-status-badge :status="$req->status" />
                            </div>
                        </li>
                        @empty
                        <li class="list-group-item text-center py-3 text-muted small">
                            No pending site material requests.
                        </li>
                        @endforelse
                    </ul>
                </div>
            </div>

            {{-- Recent Deliveries Received --}}
            <div class="card border-0 shadow-sm rounded-3 bg-white mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-boxes-packing text-success me-2"></i>Recent Deliveries / GRN
                    </h6>
                    <a href="{{ route('store-manager.slips.index') }}" class="btn btn-sm btn-outline-success rounded-pill px-3" style="min-height: 32px; display: inline-flex; align-items: center;">Slips</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($recentDeliveryReceipts as $dr)
                        <li class="list-group-item px-3 py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fw-semibold d-block text-dark small">{{ $dr->receipt_no ?? 'GRN-'.$dr->id }}</span>
                                    <small class="text-muted">{{ $dr->supplier->name ?? 'Supplier' }}</small>
                                </div>
                                <small class="text-muted font-monospace">{{ optional($dr->received_date ?? $dr->created_at)->format('d M') }}</small>
                            </div>
                        </li>
                        @empty
                        <li class="list-group-item text-center py-3 text-muted small">
                            No recent deliveries recorded.
                        </li>
                        @endforelse
                    </ul>
                </div>
            </div>

            {{-- Recent Petty Cash Purchases --}}
            <div class="card border-0 shadow-sm rounded-3 bg-white">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-cart-shopping text-success me-2"></i>Recent Petty Cash Buys
                    </h6>
                    <a href="{{ route('store-keeper.petty-cash-purchases.index') }}" class="btn btn-sm btn-outline-success rounded-pill px-3" style="min-height: 32px; display: inline-flex; align-items: center;">View All</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($recentPettyCashPurchases ?? [] as $pcp)
                        <li class="list-group-item px-3 py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <a href="{{ route('store-keeper.petty-cash-purchases.show', $pcp) }}" class="fw-bold font-monospace text-primary text-decoration-none small d-block">
                                        {{ $pcp->purchase_no }}
                                    </a>
                                    <small class="text-muted">{{ $pcp->supplier_name ?: 'Supplier' }} &bull; {{ $pcp->items->count() }} items</small>
                                </div>
                                <div class="text-end">
                                    <strong class="text-success font-monospace font-tabular small d-block">ETB {{ number_format($pcp->total_amount, 2) }}</strong>
                                    <small class="text-muted" style="font-size:0.7rem;">{{ optional($pcp->purchase_date)->format('d M') }}</small>
                                </div>
                            </div>
                        </li>
                        @empty
                        <li class="list-group-item text-center py-3 text-muted small">
                            No petty cash purchases recorded yet.
                        </li>
                        @endforelse
                    </ul>
                </div>
            </div>

        </div>
    </div>

</div>
@endsection
