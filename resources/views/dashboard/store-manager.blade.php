@extends('layouts.app')
@section('title', 'Store Manager Dashboard')
@section('content')
<style>
/* ── Premium dashboard shell ─────────────────────────────────── */
.dash-header {
    background: linear-gradient(135deg, #0f2944 0%, #1a4a7a 60%, #1e6091 100%);
    border-radius: 16px; padding: 22px 28px; color: #fff; margin-bottom: 24px;
}
.dash-header h4 { font-size: 1.35rem; font-weight: 700; margin: 0; }
.dash-header p  { margin: 3px 0 0; opacity: .75; font-size: .85rem; }

/* ── KPI cards ───────────────────────────────────────────────── */
.kpi-card {
    border-radius: 14px; border: none;
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    transition: transform .18s, box-shadow .18s;
    overflow: hidden; position: relative;
}
.kpi-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.12); }
.kpi-card .kpi-icon {
    width: 52px; height: 52px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem;
}
.kpi-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .7px; font-weight: 700; margin-bottom: 2px; }
.kpi-value { font-size: 1.55rem; font-weight: 800; line-height: 1.1; }
.kpi-sub   { font-size: .75rem; margin-top: 4px; }

/* ── Financial strip ─────────────────────────────────────────── */
.fin-strip {
    background: linear-gradient(135deg, #064e3b 0%, #065f46 100%);
    border-radius: 14px; padding: 20px 24px; color: #fff; margin-bottom: 24px;
}
.fin-strip .fin-item { border-right: 1px solid rgba(255,255,255,.18); padding: 0 24px; }
.fin-strip .fin-item:first-child { padding-left: 0; }
.fin-strip .fin-item:last-child  { border-right: none; }
.fin-label { font-size: .68rem; text-transform: uppercase; letter-spacing: .7px; opacity: .72; font-weight: 700; }
.fin-value { font-size: 1.5rem; font-weight: 800; line-height: 1.2; }
.fin-sub   { font-size: .76rem; opacity: .8; margin-top: 2px; }
.fin-delta-pos { color: #6ee7b7; }
.fin-delta-neg { color: #fca5a5; }

/* ── Value-by-store bars ─────────────────────────────────────── */
.store-bar-wrap { margin-bottom: 12px; }
.store-bar-label { font-size: .8rem; font-weight: 600; margin-bottom: 3px; display: flex; justify-content: space-between; }
.store-bar-track { background: #f1f5f9; border-radius: 8px; height: 8px; overflow: hidden; }
.store-bar-fill  { height: 100%; border-radius: 8px; background: linear-gradient(90deg, #2563eb, #0ea5e9); transition: width .6s ease; }

/* ── Top-value table ─────────────────────────────────────────── */
.val-table th { font-size: .72rem; text-transform: uppercase; letter-spacing: .55px; color: #64748b; font-weight: 700; background: #f8fafc; border-bottom: 2px solid #e2e8f0; padding: 10px 12px; }
.val-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; font-size: .84rem; }
.val-table tr:last-child td { border-bottom: none; }
.val-table tr:hover td { background: #f8fbff; }
.rank-badge { width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: .68rem; font-weight: 800; }
.rank-1 { background: #fef3c7; color: #92400e; }
.rank-2 { background: #e5e7eb; color: #374151; }
.rank-3 { background: #fee2e2; color: #7f1d1d; }
.rank-n { background: #f1f5f9; color: #64748b; }
.pct-bar { height: 4px; border-radius: 4px; background: #e2e8f0; margin-top: 3px; }
.pct-fill { height: 100%; border-radius: 4px; background: linear-gradient(90deg, #10b981, #059669); }

/* ── Section card ────────────────────────────────────────────── */
.section-card { border-radius: 14px; border: none; box-shadow: 0 2px 12px rgba(0,0,0,.07); overflow: hidden; }
.section-card .card-header { font-size: .85rem; font-weight: 700; background: #fff; border-bottom: 1.5px solid #f1f5f9; padding: 14px 18px; }

/* ── Quick actions ───────────────────────────────────────────── */
.qa-btn {
    border-radius: 10px; font-weight: 600; font-size: .85rem;
    padding: 9px 18px; transition: all .15s;
}
.qa-btn:hover { transform: translateY(-1px); }
</style>

<div class="container-fluid">
    {{-- ── Header ────────────────────────────────────────────────── --}}
    <div class="dash-header">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
            <div>
                <h4><i class="fas fa-warehouse me-2"></i>Store Manager Dashboard</h4>
                <p>Real-time inventory values, stock movements, and financial summary</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                @if(auth()->user()->can('inventory.edit') || auth()->user()->hasAnyRole(['admin', 'global_admin', 'store_manager', 'store_keeper']))
                <a href="{{ route('inventory.bulk-adjust') }}" class="btn btn-warning btn-sm fw-semibold px-3" style="border-radius:8px;">
                    <i class="fa-solid fa-sliders me-1"></i> Manual Adjustment
                </a>
                @endif
                <a href="{{ route('inventory.index') }}" class="btn btn-light btn-sm px-3" style="border-radius:8px;">
                    <i class="fas fa-boxes me-1"></i> All Inventory
                </a>
            </div>
        </div>
    </div>

    {{-- ── Financial Strip ───────────────────────────────────────── --}}
    <div class="fin-strip mb-4">
        <div class="row g-0">
            {{-- Total Inventory Value --}}
            <div class="col-6 col-md-3 fin-item">
                <div class="fin-label"><i class="fas fa-warehouse me-1"></i> Total Stock Value</div>
                <div class="fin-value">{{ number_format($kpi['total_value'] ?? 0, 0) }} ETB</div>
                <div class="fin-sub">{{ number_format($kpi['total_items'] ?? 0) }} product lines</div>
            </div>

            {{-- Today's Adjustment Value Change --}}
            <div class="col-6 col-md-3 fin-item">
                <div class="fin-label"><i class="fas fa-sliders-h me-1"></i> Today's Adjustments</div>
                @php $adjPositive = ($todayAdjustmentValue ?? 0) >= 0; @endphp
                <div class="fin-value {{ $adjPositive ? 'fin-delta-pos' : 'fin-delta-neg' }}">
                    {{ $adjPositive ? '+' : '' }}{{ number_format($todayAdjustmentValue ?? 0, 0) }} ETB
                </div>
                <div class="fin-sub">Value change from manual adjustments</div>
            </div>

            {{-- This Month Receipts --}}
            <div class="col-6 col-md-3 fin-item">
                <div class="fin-label"><i class="fas fa-shopping-cart me-1"></i> Purchases This Month</div>
                <div class="fin-value fin-delta-pos">{{ number_format($monthlyReceiptsValue ?? 0, 0) }} ETB</div>
                <div class="fin-sub">
                    @php
                        $receiptsDiff = ($monthlyReceiptsValue ?? 0) - ($lastMonthReceiptsValue ?? 0);
                        $receiptsUp   = $receiptsDiff >= 0;
                    @endphp
                    <span class="{{ $receiptsUp ? 'fin-delta-pos' : 'fin-delta-neg' }}">
                        <i class="fas fa-arrow-{{ $receiptsUp ? 'up' : 'down' }}"></i>
                        {{ number_format(abs($receiptsDiff), 0) }} ETB vs last month
                    </span>
                </div>
            </div>

            {{-- Low Stock Alerts --}}
            <div class="col-6 col-md-3 fin-item">
                <div class="fin-label"><i class="fas fa-exclamation-triangle me-1"></i> Low Stock Alerts</div>
                <div class="fin-value {{ ($kpi['low_stock'] ?? 0) > 0 ? 'text-warning' : '' }}">
                    {{ number_format($kpi['low_stock'] ?? 0) }}
                </div>
                <div class="fin-sub">
                    {{ ($kpi['low_stock'] ?? 0) > 0 ? 'Items need restocking' : '✓ All levels healthy' }}
                </div>
            </div>
        </div>
    </div>

    {{-- ── KPI Count Cards ───────────────────────────────────────── --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="kpi-card card h-100 p-3" style="border-left: 4px solid #2563eb;">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:#eff6ff;color:#2563eb;"><i class="fas fa-boxes"></i></div>
                    <div>
                        <div class="kpi-label text-muted">Total Items in Stock</div>
                        <div class="kpi-value text-primary">{{ number_format($kpi['total_items'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card card h-100 p-3" style="border-left: 4px solid #ef4444;">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:#fef2f2;color:#ef4444;"><i class="fas fa-triangle-exclamation"></i></div>
                    <div>
                        <div class="kpi-label text-muted">Low Stock Alerts</div>
                        <div class="kpi-value text-danger">{{ number_format($kpi['low_stock'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card card h-100 p-3" style="border-left: 4px solid #f59e0b;">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:#fffbeb;color:#f59e0b;"><i class="fas fa-exchange-alt"></i></div>
                    <div>
                        <div class="kpi-label text-muted">Pending Transfers</div>
                        <div class="kpi-value text-warning">{{ number_format($kpi['pending_transfers'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card card h-100 p-3" style="border-left: 4px solid #10b981;">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:#ecfdf5;color:#10b981;"><i class="fas fa-truck-ramp-box"></i></div>
                    <div>
                        <div class="kpi-label text-muted">Receipts This Month</div>
                        <div class="kpi-value text-success">{{ number_format($kpi['recent_receipts'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Financial Detail Row ──────────────────────────────────── --}}
    <div class="row g-3 mb-4">
        {{-- Value by Store bars --}}
        <div class="col-xl-4">
            <div class="section-card card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span><i class="fas fa-store me-2 text-primary"></i>Inventory Value by Store</span>
                </div>
                <div class="card-body p-3">
                    @php 
                        $storeList = $inventoryValueByStore ?? collect();
                        $maxStoreVal = $storeList->max('total_value') ?: 1; 
                    @endphp
                    @forelse($storeList as $sv)
                    <div class="store-bar-wrap">
                        <div class="store-bar-label">
                            <span>{{ $sv->store_name }}</span>
                            <span class="text-primary fw-bold">{{ number_format($sv->total_value, 0) }} ETB</span>
                        </div>
                        <div class="store-bar-track">
                            <div class="store-bar-fill" style="width:{{ ($sv->total_value / $maxStoreVal) * 100 }}%"></div>
                        </div>
                        <div class="text-muted" style="font-size:.72rem;margin-top:2px;">{{ $sv->product_count }} products</div>
                    </div>
                    @empty
                    <div class="text-center text-muted py-4" style="font-size:.85rem;">No inventory data yet</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Top 10 Products by Value --}}
        <div class="col-xl-8">
            <div class="section-card card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span><i class="fas fa-trophy me-2 text-warning"></i>Top Products by Inventory Value</span>
                    <a href="{{ route('inventory.index') }}" class="btn btn-sm btn-outline-primary" style="border-radius:7px;font-size:.78rem;">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table val-table mb-0">
                        <thead>
                            <tr>
                                <th style="width:36px;">#</th>
                                <th>Product</th>
                                <th>Store</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Unit Cost</th>
                                <th class="text-end">Total Value</th>
                                <th style="width:80px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @php 
                                $topList = $topValueItems ?? collect();
                                $maxVal = $topList->max('line_value') ?: 1; 
                            @endphp
                            @forelse($topList as $idx => $item)
                            <tr>
                                <td>
                                    <span class="rank-badge {{ $idx === 0 ? 'rank-1' : ($idx === 1 ? 'rank-2' : ($idx === 2 ? 'rank-3' : 'rank-n')) }}">
                                        {{ $idx + 1 }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold" style="font-size:.84rem;">{{ $item->product_name }}</div>
                                    <div class="text-muted" style="font-size:.72rem;">{{ $item->sku }}</div>
                                </td>
                                <td><span class="badge bg-light text-dark" style="font-size:.72rem;">{{ $item->store_name }}</span></td>
                                <td class="text-end fw-semibold">{{ number_format($item->quantity_on_hand, 2) }} <small class="text-muted">{{ $item->unit }}</small></td>
                                <td class="text-end text-muted" style="font-size:.82rem;">{{ number_format($item->unit_cost, 2) }}</td>
                                <td class="text-end fw-bold text-success">{{ number_format($item->line_value, 0) }} <small class="text-muted fw-normal">ETB</small></td>
                                <td>
                                    <div class="pct-bar">
                                        <div class="pct-fill" style="width:{{ ($item->line_value / $maxVal) * 100 }}%"></div>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No inventory with pricing yet</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Quick Actions ─────────────────────────────────────────── --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="section-card card">
                <div class="card-header"><i class="fas fa-bolt me-2 text-warning"></i>Quick Actions</div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('delivery-receipts.create') }}" class="qa-btn btn btn-primary">
                            <i class="fas fa-plus me-1"></i> New Delivery Receipt
                        </a>
                        <a href="{{ route('transfers.create') }}" class="qa-btn btn btn-warning">
                            <i class="fas fa-exchange-alt me-1"></i> New Transfer
                        </a>
                        <a href="{{ route('inventory.index') }}" class="qa-btn btn btn-info text-white">
                            <i class="fas fa-clipboard-list me-1"></i> View Stock
                        </a>
                        @if(auth()->user()->can('inventory.edit') || auth()->user()->hasAnyRole(['admin', 'global_admin', 'store_manager', 'store_keeper']))
                        <a href="{{ route('inventory.bulk-adjust') }}" class="qa-btn btn btn-success">
                            <i class="fa-solid fa-sliders me-1"></i> Manual Stock Adjustment
                        </a>
                        @endif
                        <a href="{{ route('material-requests.index') }}" class="qa-btn btn btn-secondary">
                            <i class="fas fa-cart-flatbed me-1"></i> Material Requests
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Inventory Overview + Low Stock ───────────────────────── --}}
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="section-card card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-boxes me-2 text-primary"></i>Inventory Overview — All Stores</span>
                    <a href="{{ route('inventory.index') }}" class="btn btn-sm btn-outline-primary" style="border-radius:7px;font-size:.78rem;">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table val-table mb-0">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Store</th>
                                <th class="text-end">On Hand</th>
                                <th class="text-end">Unit Cost</th>
                                <th class="text-end">Line Value</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($allInventory ?? [] as $item)
                            @php $lineVal = (float)$item->quantity_on_hand * (float)$item->unit_cost; @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold" style="font-size:.84rem;">{{ $item->product->name ?? 'N/A' }}</div>
                                    <div class="text-muted" style="font-size:.72rem;">{{ $item->product->code ?? '' }}</div>
                                </td>
                                <td><span class="badge bg-light text-dark" style="font-size:.72rem;">{{ $item->store->name ?? 'N/A' }}</span></td>
                                <td class="text-end fw-bold">{{ number_format($item->quantity_on_hand, 2) }}</td>
                                <td class="text-end text-muted" style="font-size:.8rem;">{{ $item->unit_cost ? number_format($item->unit_cost, 2) : '—' }}</td>
                                <td class="text-end fw-semibold {{ $lineVal > 0 ? 'text-success' : 'text-muted' }}">
                                    {{ $lineVal > 0 ? number_format($lineVal, 0) . ' ETB' : '—' }}
                                </td>
                                <td>
                                    @if($item->quantity_on_hand <= $item->min_stock)
                                        <span class="badge bg-danger" style="font-size:.72rem;">Low Stock</span>
                                    @else
                                        <span class="badge bg-success" style="font-size:.72rem;">OK</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center py-4 text-muted">No inventory found</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="section-card card h-100" style="border-top: 3px solid #ef4444;">
                <div class="card-header bg-white text-danger">
                    <i class="fas fa-triangle-exclamation me-2"></i>Low Stock Alerts
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse($lowStockItems ?? [] as $item)
                        <div class="list-group-item px-3 py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold" style="font-size:.84rem;">{{ $item->product->name ?? 'N/A' }}</div>
                                    <div class="text-muted" style="font-size:.74rem;">{{ $item->store->name ?? 'N/A' }}</div>
                                </div>
                                <div class="text-end">
                                    <div class="text-danger fw-bold">{{ number_format($item->quantity_on_hand ?? $item->quantity ?? 0, 2) }}</div>
                                    <div class="text-muted" style="font-size:.72rem;">Min: {{ number_format($item->min_stock ?? $item->min_level ?? 0, 2) }}</div>
                                </div>
                            </div>
                        </div>
                        @empty
                        <div class="list-group-item text-center text-success py-4" style="font-size:.84rem;">
                            <i class="fas fa-check-circle me-2"></i>All stock levels are healthy!
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
