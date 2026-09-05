@extends('layouts.app')
@section('title', 'Purchase Manager Dashboard')

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-shopping-cart text-primary me-2"></i>Purchase Manager Dashboard</h1>
        <a href="{{ route('purchase-requests.create') }}" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50 me-1"></i> New Request
        </a>
    </div>

    <!-- KPI Cards -->
    <div class="row">
        <!-- Pending PRs -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Pending Requests</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $kpi['pending_prs'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-invoice fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active POs -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Active Orders (PO)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $kpi['active_pos'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-truck-loading fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Spend -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Total Spend (Delivered)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">ETB {{ number_format($kpi['total_spend'] ?? 0, 2) }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vendors -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Registered Vendors</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $kpi['vendors'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Quick Actions -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow mb-4 h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Procurement Tools</h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="{{ route('price-intelligence.index') }}" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="fas fa-chart-line text-success me-3"></i>
                            <div>
                                <h6 class="mb-0 fw-bold">Price Intelligence</h6>
                                <small class="text-muted">Compare vendor pricing and market trends</small>
                            </div>
                        </a>
                        <a href="{{ route('material-demand.index') }}" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="fas fa-cubes text-info me-3"></i>
                            <div>
                                <h6 class="mb-0 fw-bold">Material Demand & Forecast</h6>
                                <small class="text-muted">Analyze upcoming material needs</small>
                            </div>
                        </a>
                        <a href="{{ route('products.index') }}" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="fas fa-box text-warning me-3"></i>
                            <div>
                                <h6 class="mb-0 fw-bold">Material Catalog</h6>
                                <small class="text-muted">Manage products and materials</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent POs -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow mb-4 h-100">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Purchase Orders</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>PO Number</th>
                                    <th>Supplier</th>
                                    <th>Date</th>
                                    <th>Total (ETB)</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentPOs ?? [] as $po)
                                <tr>
                                    <td><a href="#">{{ $po->po_number }}</a></td>
                                    <td>{{ $po->supplier->name ?? 'N/A' }}</td>
                                    <td>{{ \Carbon\Carbon::parse($po->po_date)->format('d M, Y') }}</td>
                                    <td>{{ number_format($po->grand_total, 2) }}</td>
                                    <td>
                                        @if($po->status == 'delivered')
                                            <span class="badge bg-success">Delivered</span>
                                        @elseif($po->status == 'confirmed')
                                            <span class="badge bg-primary">Confirmed</span>
                                        @else
                                            <span class="badge bg-secondary">{{ ucfirst($po->status) }}</span>
                                        @endif
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
