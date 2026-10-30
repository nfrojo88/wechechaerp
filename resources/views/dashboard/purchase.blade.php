@extends('layouts.app')
@section('title', 'Purchase Manager Dashboard')

@section('content')
<div class="wc-container py-3 py-md-4">
    <x-page-header
        title="Purchase Manager Dashboard"
        subtitle="Procurement workflow, vendor management, purchase orders &amp; spend intelligence"
        badge="Procurement"
    >
        <x-slot:actions>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="{{ route('procurement.my-queue') }}" class="btn btn-outline-primary btn-sm shadow-xs" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-list-check me-1"></i> My Sourcing Queue
                </a>
                <a href="{{ route('purchase-requests.create') }}" class="btn btn-primary btn-sm shadow-xs" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fas fa-plus me-1"></i> New Request
                </a>
            </div>
        </x-slot:actions>
    </x-page-header>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <!-- Pending PRs -->
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Pending Requests"
                :value="$kpi['pending_prs'] ?? 0"
                subtitle="Awaiting action"
                icon="fa-solid fa-file-invoice"
                tone="warning"
            />
        </div>

        <!-- Active POs -->
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Active Orders (PO)"
                :value="$kpi['active_pos'] ?? 0"
                subtitle="In production / transit"
                icon="fa-solid fa-truck-loading"
                tone="success"
            />
        </div>

        <!-- Total Spend -->
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Total Spend (Delivered)"
                :value="'ETB ' . number_format($kpi['total_spend'] ?? 0, 2)"
                subtitle="Completed purchase volume"
                icon="fa-solid fa-dollar-sign"
                tone="info"
            />
        </div>

        <!-- Vendors -->
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Registered Vendors"
                :value="$kpi['vendors'] ?? 0"
                subtitle="Active suppliers"
                icon="fa-solid fa-users"
                tone="primary"
            />
        </div>
    </div>

    <div class="row g-4">
        <!-- Quick Actions / Procurement Tools -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100 bg-white">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="m-0 fw-bold text-dark"><i class="fa-solid fa-toolbox text-primary me-2"></i>Procurement Tools</h6>
                </div>
                <div class="card-body p-3">
                    <div class="d-flex flex-column gap-2">
                        <a href="{{ route('price-intelligence.index') }}" class="btn btn-outline-light text-start p-3 border d-flex align-items-center rounded-3 text-dark text-decoration-none" style="min-height: 54px;">
                            <i class="fas fa-chart-line text-success fs-5 me-3 flex-shrink-0"></i>
                            <div>
                                <h6 class="mb-0 fw-bold fs-6">Price Intelligence</h6>
                                <small class="text-muted">Compare vendor pricing and market trends</small>
                            </div>
                        </a>
                        <a href="{{ route('material-demand.index') }}" class="btn btn-outline-light text-start p-3 border d-flex align-items-center rounded-3 text-dark text-decoration-none" style="min-height: 54px;">
                            <i class="fas fa-cubes text-info fs-5 me-3 flex-shrink-0"></i>
                            <div>
                                <h6 class="mb-0 fw-bold fs-6">Material Demand &amp; Forecast</h6>
                                <small class="text-muted">Analyze upcoming material needs</small>
                            </div>
                        </a>
                        <a href="{{ route('products.index') }}" class="btn btn-outline-light text-start p-3 border d-flex align-items-center rounded-3 text-dark text-decoration-none" style="min-height: 54px;">
                            <i class="fas fa-box text-warning fs-5 me-3 flex-shrink-0"></i>
                            <div>
                                <h6 class="mb-0 fw-bold fs-6">Material Catalog</h6>
                                <small class="text-muted">Manage products and materials</small>
                            </div>
                        </a>
                        <a href="{{ route('procurement.my-queue') }}" class="btn btn-outline-light text-start p-3 border d-flex align-items-center rounded-3 text-dark text-decoration-none" style="min-height: 54px;">
                            <i class="fas fa-list-check text-primary fs-5 me-3 flex-shrink-0"></i>
                            <div>
                                <h6 class="mb-0 fw-bold fs-6">Stage Workflows</h6>
                                <small class="text-muted">Direct buy, proforma and GM approvals</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent POs -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100 bg-white">
                <div class="card-header bg-white py-3 border-bottom d-flex flex-row align-items-center justify-content-between flex-wrap gap-2">
                    <h6 class="m-0 fw-bold text-dark"><i class="fas fa-truck-loading text-success me-2"></i>Recent Purchase Orders</h6>
                    <a href="{{ route('purchase-orders.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3" style="min-height: 34px; display: inline-flex; align-items: center;">
                        View All <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive wc-table-mobile-cards">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">PO Number</th>
                                    <th>Supplier</th>
                                    <th>Date</th>
                                    <th class="text-end">Total (ETB)</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentPOs ?? [] as $po)
                                <tr>
                                    <td data-label="PO Number" class="ps-3 fw-bold">
                                        <a href="{{ route('purchase-orders.show', $po->id) }}" class="text-primary text-decoration-none">
                                            {{ $po->po_number }}
                                        </a>
                                    </td>
                                    <td data-label="Supplier">{{ $po->supplier->name ?? 'N/A' }}</td>
                                    <td data-label="Date" class="small text-muted">{{ \Carbon\Carbon::parse($po->po_date)->format('d M, Y') }}</td>
                                    <td data-label="Total" class="text-end font-tabular fw-bold">{{ number_format($po->grand_total, 2) }}</td>
                                    <td data-label="Status">
                                        <x-status-badge :status="$po->status" />
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No recent purchase orders found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
