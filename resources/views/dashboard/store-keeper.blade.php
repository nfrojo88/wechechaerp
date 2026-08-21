@extends('layouts.app')
@section('title', 'Store Keeper Dashboard')

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- ── Assigned Store Banner ──────────────────────────────────────────────── --}}
    @if($assignedStore)
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white" style="border-left: 5px solid #0284c7 !important;">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 rounded-3 bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-warehouse fa-2x"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h4 class="mb-0 fw-bold text-dark">{{ $assignedStore->name }}</h4>
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
                    <a href="{{ route('store-manager.transfers.create') }}" class="btn btn-primary btn-sm shadow-sm">
                        <i class="fa-solid fa-exchange-alt me-1"></i> New Transfer
                    </a>
                    <a href="{{ \Illuminate\Support\Facades\Route::has('store-keeper.weekly-material-demand') ? route('store-keeper.weekly-material-demand') : (\Illuminate\Support\Facades\Route::has('store-manager.weekly-material-demand') ? route('store-manager.weekly-material-demand') : url('/store-keeper/weekly-material-demand')) }}" class="btn btn-warning text-dark btn-sm shadow-sm">
                        <i class="fa-solid fa-calendar-check me-1"></i> Weekly Material Demand
                    </a>
                    <a href="{{ route('store-manager.material-requests.index') }}" class="btn btn-danger btn-sm shadow-sm">
                        <i class="fa-solid fa-clipboard-list me-1"></i> Material Requests
                    </a>
                    <a href="{{ route('store-manager.inventory.all') }}" class="btn btn-outline-secondary btn-sm shadow-sm">
                        <i class="fa-solid fa-boxes-stacked me-1"></i> View Stock
                    </a>
                </div>
            </div>
        </div>
    </div>
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
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #0284c7 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted fw-semibold mb-1" style="font-size: 0.72rem; letter-spacing: .06em; text-transform: uppercase;">In-Store Items</p>
                            <h3 class="fw-bold text-dark mb-0">{{ number_format($kpi['total_items']) }}</h3>
                        </div>
                        <div class="p-2 rounded-3" style="background: rgba(2,132,199,.12);">
                            <i class="fa-solid fa-boxes-stacked fa-lg text-primary"></i>
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block" style="font-size:0.73rem;">Unique catalog products in store</small>
                </div>
            </div>
        </div>

        {{-- Total Units on Hand --}}
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #10b981 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted fw-semibold mb-1" style="font-size: 0.72rem; letter-spacing: .06em; text-transform: uppercase;">Stock Units on Hand</p>
                            <h3 class="fw-bold text-success mb-0">{{ number_format($kpi['total_stock_qty']) }}</h3>
                        </div>
                        <div class="p-2 rounded-3" style="background: rgba(16,185,129,.12);">
                            <i class="fa-solid fa-cubes-stacked fa-lg text-success"></i>
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block" style="font-size:0.73rem;">Total inventory units stored</small>
                </div>
            </div>
        </div>

        {{-- Low Stock Alerts --}}
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid {{ $kpi['low_stock_count'] > 0 ? '#ef4444' : '#64748b' }} !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted fw-semibold mb-1" style="font-size: 0.72rem; letter-spacing: .06em; text-transform: uppercase;">Low Stock Alerts</p>
                            <h3 class="fw-bold {{ $kpi['low_stock_count'] > 0 ? 'text-danger' : 'text-dark' }} mb-0">{{ $kpi['low_stock_count'] }}</h3>
                        </div>
                        <div class="p-2 rounded-3" style="background: {{ $kpi['low_stock_count'] > 0 ? 'rgba(239,68,68,.12)' : 'rgba(100,116,139,.12)' }};">
                            <i class="fa-solid fa-triangle-exclamation fa-lg {{ $kpi['low_stock_count'] > 0 ? 'text-danger' : 'text-secondary' }}"></i>
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block" style="font-size:0.73rem;">Items below minimum stock</small>
                </div>
            </div>
        </div>

        {{-- Active Transfers --}}
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #f59e0b !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted fw-semibold mb-1" style="font-size: 0.72rem; letter-spacing: .06em; text-transform: uppercase;">Site Transfers</p>
                            <h3 class="fw-bold text-warning mb-0">{{ $kpi['pending_incoming'] + $kpi['pending_outgoing'] }}</h3>
                        </div>
                        <div class="p-2 rounded-3" style="background: rgba(245,158,11,.12);">
                            <i class="fa-solid fa-truck-fast fa-lg text-warning"></i>
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block" style="font-size:0.73rem;">
                        <span class="text-success fw-bold">{{ $kpi['pending_incoming'] }} in</span> · 
                        <span class="text-primary fw-bold">{{ $kpi['pending_outgoing'] }} out</span>
                    </small>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Quick Actions Bar ────────────────────────────────────────────────── --}}
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-3 bg-white">
                <div class="card-body p-3">
                    <p class="text-muted fw-semibold mb-2" style="font-size:0.78rem; text-transform:uppercase; letter-spacing:.05em;">Store Keeper Quick Modules</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('store-manager.inventory.all') }}" class="btn btn-outline-info btn-sm px-3">
                            <i class="fa-solid fa-boxes-stacked me-1"></i> 1. Store Inventory
                        </a>
                        <a href="{{ route('store-manager.material-requests.index') }}" class="btn btn-outline-danger btn-sm px-3">
                            <i class="fa-solid fa-clipboard-list me-1"></i> 2. Material Requests (Issue Stock)
                        </a>
                        <a href="{{ route('store-manager.transfers.index') }}" class="btn btn-outline-warning btn-sm px-3">
                            <i class="fa-solid fa-truck-moving me-1"></i> 3. Transfers &amp; Slips
                        </a>
                        <a href="{{ \Illuminate\Support\Facades\Route::has('store-keeper.weekly-material-demand') ? route('store-keeper.weekly-material-demand') : (\Illuminate\Support\Facades\Route::has('store-manager.weekly-material-demand') ? route('store-manager.weekly-material-demand') : url('/store-keeper/weekly-material-demand')) }}" class="btn btn-outline-primary btn-sm px-3">
                            <i class="fa-solid fa-calendar-check me-1"></i> 4. Weekly Material Demand
                        </a>
                        <a href="{{ route('expense-requests.index') }}" class="btn btn-outline-success btn-sm px-3">
                            <i class="fa-solid fa-hand-holding-dollar me-1"></i> 5. Petty Cash (Site Purchase)
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
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0 fw-bold text-dark">
                            <i class="fa-solid fa-truck-arrow-right text-primary me-2"></i>Site Store Transfers &amp; Physical Slips
                        </h6>
                        <small class="text-muted">Incoming &amp; outgoing transfers for this store</small>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('store-manager.transfers.create') }}" class="btn btn-sm btn-primary">
                            <i class="fa-solid fa-plus me-1"></i>New Transfer
                        </a>
                        <a href="{{ route('store-manager.transfers.index') }}" class="btn btn-sm btn-outline-secondary">View All</a>
                    </div>
                </div>
                <div class="table-responsive">
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
                                $statusBadge = match($transfer->status ?? '') {
                                    'approved'    => 'bg-success',
                                    'in_transit'  => 'bg-info text-dark',
                                    'completed', 'received' => 'bg-dark',
                                    'rejected'    => 'bg-danger',
                                    'draft', 'pending' => 'bg-warning text-dark',
                                    default       => 'bg-secondary',
                                };
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <a href="{{ route('store-manager.transfers.show', $transfer) }}" class="fw-bold font-monospace text-primary text-decoration-none">
                                        {{ $transfer->transfer_no ?? 'TR-'.$transfer->id }}
                                    </a>
                                </td>
                                <td>
                                    @if($transfer->physical_slip_no)
                                        <span class="badge bg-success font-monospace" style="font-size:0.75rem;">{{ $transfer->physical_slip_no }}</span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td>
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
                                <td>
                                    <span class="fw-semibold text-dark small">
                                        {{ $isIncoming ? ($transfer->fromStore->name ?? 'Main Warehouse') : ($transfer->toStore->name ?? 'Destination') }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $statusBadge }} small">
                                        {{ ucfirst(str_replace('_', ' ', $transfer->status ?? 'Draft')) }}
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('store-manager.transfers.show', $transfer) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="fa-solid fa-eye me-1"></i>View
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
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
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0 fw-bold text-dark">
                            <i class="fa-solid fa-boxes-stacked text-success me-2"></i>In-Store Inventory Overview
                        </h6>
                        <small class="text-muted">Top products currently on hand in {{ $assignedStore->name ?? 'this store' }}</small>
                    </div>
                    <a href="{{ route('store-manager.inventory.all') }}" class="btn btn-sm btn-outline-primary">Full Stock List</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Product Name</th>
                                <th>Category / Unit</th>
                                <th class="text-center">On Hand Qty</th>
                                <th class="text-center">Min Stock</th>
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
                                <td class="ps-3">
                                    <span class="fw-semibold text-dark d-block">{{ $item->product->name ?? '—' }}</span>
                                    <small class="text-muted font-monospace">{{ $item->product->sku ?? $item->product->code ?? '' }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark">{{ $item->product->unit_of_measure ?? $item->product->unit ?? 'Units' }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold {{ $isOut ? 'text-danger' : ($isLow ? 'text-warning' : 'text-dark') }}" style="font-size:0.95rem;">
                                        {{ number_format($item->quantity_on_hand, 2) }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <small class="text-muted">{{ number_format($item->min_stock, 2) }}</small>
                                </td>
                                <td class="text-center">
                                    @if($isOut)
                                        <span class="badge bg-danger">Out of Stock</span>
                                    @elseif($isLow)
                                        <span class="badge bg-warning text-dark">Low Stock</span>
                                    @else
                                        <span class="badge bg-success">In Stock</span>
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
            <div class="card border-0 shadow-sm rounded-3 mb-4">
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
                        <li class="list-group-item px-3 py-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="fw-semibold d-block text-dark small">{{ $low->product->name ?? 'Product #'.$low->product_id }}</span>
                                    <small class="text-danger fw-semibold">
                                        Qty: {{ number_format($low->quantity_on_hand, 2) }} (Min: {{ number_format($low->min_stock, 2) }})
                                    </small>
                                </div>
                                <a href="{{ route('store-manager.transfers.create') }}" class="btn btn-xs btn-outline-primary">
                                    Request Transfer
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
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-clipboard-list text-info me-2"></i>Site Material Requests
                    </h6>
                    <a href="{{ route('store-manager.material-requests.index') }}" class="btn btn-sm btn-outline-info">View All</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($materialRequests as $req)
                        <li class="list-group-item px-3 py-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="fw-semibold d-block text-dark small">{{ $req->request_no ?? 'MR-'.$req->id }}</span>
                                    <small class="text-muted">{{ $req->project->name ?? 'Site Project' }} · {{ optional($req->created_at)->format('d M') }}</small>
                                </div>
                                <span class="badge bg-{{ $req->status === 'approved' ? 'success' : ($req->status === 'pending' ? 'warning' : 'secondary') }} text-{{ $req->status === 'pending' ? 'dark' : '' }}" style="font-size:0.7rem;">
                                    {{ ucfirst($req->status) }}
                                </span>
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
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-boxes-packing text-success me-2"></i>Recent Deliveries / GRN
                    </h6>
                    <a href="{{ route('store-manager.slips.index') }}" class="btn btn-sm btn-outline-success">Slips</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($recentDeliveryReceipts as $dr)
                        <li class="list-group-item px-3 py-2">
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

        </div>
    </div>

</div>
@endsection
