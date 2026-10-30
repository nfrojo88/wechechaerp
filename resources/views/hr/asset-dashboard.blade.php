@extends('layouts.app')

@section('title', 'Fixed Asset & Equipment Management Dashboard')

@section('content')
<div class="container-fluid px-3 px-md-4 py-3">

    {{-- Page Header --}}
    <x-page-header 
        title="Asset Management Dashboard" 
        subtitle="Track company equipment, tools, electronics, and custody assignments across all departments">
        <x-slot:actions>
            <div class="dropdown d-inline-block">
                <button class="btn btn-sm btn-outline-info rounded-pill px-3 shadow-xs dropdown-toggle" type="button" id="reportsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-chart-pie me-1"></i>Asset Reports
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3" aria-labelledby="reportsDropdown">
                    <li><a class="dropdown-item py-2" href="{{ route('asset-reports.utilization') }}"><i class="fa-solid fa-chart-line text-primary me-2"></i>Utilization Report</a></li>
                    <li><a class="dropdown-item py-2" href="{{ route('asset-reports.damage') }}"><i class="fa-solid fa-triangle-exclamation text-danger me-2"></i>Damage Report</a></li>
                    <li><a class="dropdown-item py-2" href="{{ route('asset-reports.employee-allocation') }}"><i class="fa-solid fa-users text-success me-2"></i>Employee Allocation</a></li>
                </ul>
            </div>
            <a href="{{ route('assets.export') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-download me-1"></i>Export All Assets
            </a>
            <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i>Print
            </button>
        </x-slot:actions>
    </x-page-header>

    {{-- KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Total Asset Units" 
                value="{{ number_format($totalAssets) }}" 
                icon="fa-solid fa-boxes-stacked" 
                variant="primary" 
                helpText="All company inventory assignments" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Active Assignments" 
                value="{{ number_format($activeAssets) }}" 
                icon="fa-solid fa-computer" 
                variant="success" 
                helpText="Currently in use or deployed" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Returned to Storage" 
                value="{{ number_format($returnedAssets) }}" 
                icon="fa-solid fa-arrow-rotate-left" 
                variant="warning" 
                helpText="Available for immediate reallocation" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Damaged / Write-Offs" 
                value="{{ number_format($damagedAssets) }}" 
                icon="fa-solid fa-triangle-exclamation" 
                variant="{{ $damagedAssets > 0 ? 'danger' : 'success' }}" 
                helpText="{{ $damagedAssets > 0 ? 'Requires maintenance or survey' : 'No damaged assets reported' }}" />
        </div>
    </div>

    {{-- Portfolio Valuation & Category Distribution --}}
    <div class="row g-3 mb-4">
        {{-- Asset Value Summary --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-bottom py-3 px-4">
                    <h6 class="mb-0 fw-bold" style="color: var(--brand-900);">
                        <i class="fa-solid fa-wallet text-primary me-2"></i>Asset Value Distribution
                    </h6>
                </div>
                <div class="card-body p-4">
                    @php
                        $totalValue = $activeAssetsValue + $damagedAssetsValue;
                        $activePercent = $totalValue > 0 ? ($activeAssetsValue / $totalValue) * 100 : 0;
                        $damagedPercent = $totalValue > 0 ? ($damagedAssetsValue / $totalValue) * 100 : 0;
                    @endphp
                    <div class="row g-3 text-center mb-3 font-tabular">
                        <div class="col-6">
                            <span class="text-muted small d-block mb-1">Active Assets Value</span>
                            <h4 class="mb-0 text-success fw-bold">ETB {{ number_format($activeAssetsValue, 2) }}</h4>
                        </div>
                        <div class="col-6">
                            <span class="text-muted small d-block mb-1">Damaged Assets Value</span>
                            <h4 class="mb-0 text-danger fw-bold">ETB {{ number_format($damagedAssetsValue, 2) }}</h4>
                        </div>
                    </div>

                    <div class="progress rounded-pill mb-3" style="height: 16px;">
                        <div class="progress-bar bg-success" style="width: {{ $activePercent }}%" role="progressbar" title="{{ round($activePercent) }}% Active">
                            {{ round($activePercent) }}%
                        </div>
                        <div class="progress-bar bg-danger" style="width: {{ $damagedPercent }}%" role="progressbar" title="{{ round($damagedPercent) }}% Damaged">
                            {{ round($damagedPercent) }}%
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center bg-light p-3 rounded-3">
                        <span class="text-muted small fw-semibold">Total Portfolio Value:</span>
                        <strong class="font-tabular fs-5 text-dark">ETB {{ number_format($totalValue, 2) }}</strong>
                    </div>
                </div>
            </div>
        </div>

        {{-- Asset Categories --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold" style="color: var(--brand-900);">
                        <i class="fa-solid fa-list-check text-primary me-2"></i>Assets by Category
                    </h6>
                    <span class="badge bg-light text-dark border font-monospace">{{ $assetsByCategory->count() }} Categories</span>
                </div>
                <div class="card-body p-0">
                    @if($assetsByCategory->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-uppercase small text-muted" style="letter-spacing: 0.5px;">
                                <tr>
                                    <th class="ps-4 py-2.5">Category</th>
                                    <th class="py-2.5 text-center">Unit Count</th>
                                    <th class="pe-4 py-2.5 text-end">Total Valuation</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($assetsByCategory as $category)
                                <tr>
                                    <td class="ps-4 py-2.5 fw-semibold text-dark">
                                        {{ $category->category ?? 'Uncategorized' }}
                                    </td>
                                    <td class="py-2.5 text-center font-tabular">
                                        <span class="badge bg-light text-dark border px-2.5 py-1 rounded-pill">{{ $category->total_count }}</span>
                                    </td>
                                    <td class="pe-4 py-2.5 text-end font-tabular fw-bold text-dark">
                                        ETB {{ number_format($category->total_value, 2) }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="p-4 text-center text-muted small">No assets categorized yet.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Department Breakdown & Recent Activity --}}
    <div class="row g-3 mb-4">
        {{-- Top Departments --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-bottom py-3 px-4">
                    <h6 class="mb-0 fw-bold" style="color: var(--brand-900);">
                        <i class="fa-solid fa-sitemap text-primary me-2"></i>Assets by Department
                    </h6>
                </div>
                <div class="card-body p-0">
                    @if($assetsByDepartment->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-uppercase small text-muted" style="letter-spacing: 0.5px;">
                                <tr>
                                    <th class="ps-4 py-2.5">Department</th>
                                    <th class="py-2.5 text-center">Total Assigned</th>
                                    <th class="pe-4 py-2.5 text-center">Active in Use</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($assetsByDepartment as $dept)
                                <tr>
                                    <td class="ps-4 py-2.5 fw-semibold text-dark">{{ $dept->department }}</td>
                                    <td class="py-2.5 text-center font-tabular">{{ $dept->total_count }}</td>
                                    <td class="pe-4 py-2.5 text-center font-tabular">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">
                                            {{ $dept->active_count }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="p-4 text-center text-muted small">No departmental allocations found.</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Recent Activity --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-bottom py-3 px-4">
                    <h6 class="mb-0 fw-bold" style="color: var(--brand-900);">
                        <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Recent Custody Activity
                    </h6>
                </div>
                <div class="card-body p-3">
                    @if($recentActivity->count() > 0)
                    <div class="d-flex flex-column gap-2">
                        @foreach($recentActivity as $activity)
                        <div class="p-2.5 rounded-3 bg-light border d-flex align-items-center gap-3">
                            <div>
                                @if($activity->status === 'assigned')
                                    <span class="badge bg-primary-subtle text-primary p-2 rounded-circle"><i class="fa-solid fa-circle-plus"></i></span>
                                @elseif($activity->status === 'returned')
                                    <span class="badge bg-warning-subtle text-warning p-2 rounded-circle"><i class="fa-solid fa-circle-check"></i></span>
                                @elseif($activity->status === 'damaged')
                                    <span class="badge bg-danger-subtle text-danger p-2 rounded-circle"><i class="fa-solid fa-circle-exclamation"></i></span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary p-2 rounded-circle"><i class="fa-solid fa-tag"></i></span>
                                @endif
                            </div>
                            <div class="flex-grow-1">
                                <div class="small text-dark">
                                    <strong>{{ $activity->employee->full_name }}</strong>
                                    @if($activity->status === 'assigned')
                                        was assigned
                                    @elseif($activity->status === 'returned')
                                        returned
                                    @elseif($activity->status === 'damaged')
                                        reported damage on
                                    @else
                                        updated
                                    @endif
                                    <strong>{{ $activity->product->name }}</strong>
                                </div>
                                <small class="text-muted">{{ $activity->updated_at->diffForHumans() }}</small>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div class="p-4 text-center text-muted small">No recent activity recorded.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- All Assets Table --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="mb-0 fw-bold" style="color: var(--brand-900);">
                <i class="fa-solid fa-table me-2 text-primary"></i>All Asset Assignments
            </h6>
            <div>
                <input type="text" class="form-control form-control-sm rounded-pill px-3" id="searchTable" placeholder="Search employee or asset..." style="width: 220px;">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 wc-table-mobile-cards" id="assetsTable">
                    <thead class="table-light text-uppercase small text-muted" style="letter-spacing: 0.5px;">
                        <tr>
                            <th class="ps-4 py-3">Employee</th>
                            <th class="py-3">Department</th>
                            <th class="py-3">Asset Name</th>
                            <th class="py-3 text-center">Type</th>
                            <th class="py-3">Assigned Date</th>
                            <th class="py-3 text-center">Status</th>
                            <th class="py-3 text-end">Unit Price</th>
                            <th class="pe-4 py-3 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($allAssets as $asset)
                        <tr>
                            <td class="ps-4 py-3" data-label="Employee">
                                <a href="{{ route('employees.show', $asset->employee) }}" class="fw-bold text-decoration-none" style="color: var(--brand-900);">
                                    {{ $asset->employee->full_name }}
                                </a>
                            </td>
                            <td class="py-3" data-label="Department">{{ $asset->employee->department }}</td>
                            <td class="py-3 fw-semibold text-dark" data-label="Asset">{{ $asset->product->name }}</td>
                            <td class="py-3 text-center" data-label="Type">
                                <span class="badge bg-light text-dark border rounded-pill px-2.5 py-1">{{ $asset->product->type ?? 'General' }}</span>
                            </td>
                            <td class="py-3 font-tabular" data-label="Date">{{ $asset->assigned_date->format('d M Y') }}</td>
                            <td class="py-3 text-center" data-label="Status">
                                @if($asset->status === 'assigned')
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1">Assigned</span>
                                @elseif($asset->status === 'in_use')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">In Use</span>
                                @elseif($asset->status === 'returned')
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2.5 py-1">Returned</span>
                                @elseif($asset->status === 'damaged')
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1">Damaged</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1">{{ ucfirst($asset->status) }}</span>
                                @endif
                            </td>
                            <td class="py-3 text-end font-tabular fw-bold text-dark" data-label="Unit Price">
                                ETB {{ number_format($asset->product->unit_cost ?? 0, 2) }}
                            </td>
                            <td class="pe-4 py-3 text-end" data-label="Action">
                                <a href="{{ route('employees.show', $asset->employee) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-1 shadow-xs" title="View Employee">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <x-empty-state 
                                    icon="fa-solid fa-computer" 
                                    title="No Assets Assigned Yet" 
                                    message="Assigned company equipment, tools, and electronics will appear in this registry." />
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
document.getElementById('searchTable')?.addEventListener('keyup', function() {
    const searchValue = this.value.toLowerCase();
    const rows = document.querySelectorAll('#assetsTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchValue) ? '' : 'none';
    });
});
</script>
@endpush
@endsection
