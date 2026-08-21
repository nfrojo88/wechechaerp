@extends('layouts.app')

@section('title', 'Fixed Assets Management - Store Manager')

@section('content')
<div class="container-fluid py-3">

    {{-- Page Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 mb-1 text-dark fw-bold">
                <i class="fa-solid fa-truck-monster text-primary me-2"></i>Fixed Assets & Equipment
            </h1>
            <p class="text-muted small mb-0">Centralized store inventory with auto-generated unit codes & strict quantity locks.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary btn-sm fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#newAssetModal">
                <i class="fa-solid fa-plus me-1"></i> New Fixed Asset
            </button>
        </div>
    </div>

    {{-- Session Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error') || $errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><strong>Error:</strong>
            <ul class="mb-0 ps-3 small">
                @if(session('error')) <li>{{ session('error') }}</li> @endif
                @foreach($errors->all() as $err) <li>{{ $err }}</li> @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Top KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 border-start border-primary border-4">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-bold">Total Assets / Units</div>
                            <div class="h4 mb-0 fw-bold text-dark">{{ $kpi['total_assets'] }} <span class="fs-6 text-muted fw-normal">({{ $kpi['total_units'] }} Units)</span></div>
                        </div>
                        <div class="avatar-sm rounded-circle bg-primary-subtle text-primary p-2 text-center">
                            <i class="fa-solid fa-boxes-stacked fs-5"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">Valuation: <strong>Br {{ number_format($kpi['total_valuation'], 2) }}</strong></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 border-start border-success border-4">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-bold">In Store (Available)</div>
                            <div class="h4 mb-0 fw-bold text-success">{{ $kpi['in_store_units'] }}</div>
                        </div>
                        <div class="avatar-sm rounded-circle bg-success-subtle text-success p-2 text-center">
                            <i class="fa-solid fa-warehouse fs-5"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">Ready for employee assignment</div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 border-start border-info border-4">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-bold">Assigned to Staff</div>
                            <div class="h4 mb-0 fw-bold text-primary">{{ $kpi['assigned_units'] }}</div>
                        </div>
                        <div class="avatar-sm rounded-circle bg-info-subtle text-info p-2 text-center">
                            <i class="fa-solid fa-user-check fs-5"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">Linked to active employees</div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 border-start border-warning border-4">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-bold">Maintenance / Disposed</div>
                            <div class="h4 mb-0 fw-bold text-warning">{{ $kpi['maintenance_units'] }} <span class="fs-6 text-muted fw-normal">/ {{ $kpi['disposed_units'] }}</span></div>
                        </div>
                        <div class="avatar-sm rounded-circle bg-warning-subtle text-warning p-2 text-center">
                            <i class="fa-solid fa-wrench fs-5"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">Under repair or decommissioned</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Container Card --}}
    <div class="card border-0 shadow-sm">
        {{-- Card Header with Tabs & Filters --}}
        <div class="card-header bg-white py-3 border-bottom">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                {{-- Status Tabs --}}
                <ul class="nav nav-pills card-header-pills small">
                    <li class="nav-item">
                        <a class="nav-link {{ $tab === 'all' ? 'active' : '' }}" href="{{ route('store-manager.fixed-assets.index', ['tab' => 'all']) }}">
                            All Assets ({{ $kpi['total_assets'] }})
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $tab === 'in_store' ? 'active' : '' }}" href="{{ route('store-manager.fixed-assets.index', ['tab' => 'in_store']) }}">
                            <i class="fa-solid fa-warehouse me-1"></i> In Store ({{ $kpi['in_store_units'] }})
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $tab === 'assigned' ? 'active' : '' }}" href="{{ route('store-manager.fixed-assets.index', ['tab' => 'assigned']) }}">
                            <i class="fa-solid fa-user-check me-1"></i> Assigned ({{ $kpi['assigned_units'] }})
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $tab === 'maintenance' ? 'active' : '' }}" href="{{ route('store-manager.fixed-assets.index', ['tab' => 'maintenance']) }}">
                            <i class="fa-solid fa-wrench me-1"></i> Maintenance ({{ $kpi['maintenance_units'] }})
                        </a>
                    </li>
                </ul>

                {{-- Search & Filters Form --}}
                <form action="{{ route('store-manager.fixed-assets.index') }}" method="GET" class="d-flex flex-wrap align-items-center gap-2">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    
                    <div class="input-group input-group-sm" style="width: 220px;">
                        <input type="text" name="search" class="form-control" placeholder="Search code, plate, SN..." value="{{ request('search') }}">
                        <button class="btn btn-outline-secondary" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </div>

                    <select name="status" class="form-select form-select-sm fw-semibold text-dark border-primary" style="width: 170px;" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="in_store" @selected(request('status', $tab === 'in_store' ? 'in_store' : '') == 'in_store') class="text-success fw-bold">✓ In Store (Available Only)</option>
                        <option value="assigned" @selected(request('status', $tab === 'assigned' ? 'assigned' : '') == 'assigned') class="text-primary">Assigned (In Use)</option>
                        <option value="maintenance" @selected(request('status', $tab === 'maintenance' ? 'maintenance' : '') == 'maintenance') class="text-warning">Under Maintenance</option>
                        <option value="disposed" @selected(request('status') == 'disposed') class="text-danger">Disposed / Retired</option>
                    </select>

                    <select name="category" class="form-select form-select-sm" style="width: 140px;" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" @selected(request('category') == $cat)>{{ $cat }}</option>
                        @endforeach
                    </select>

                    <select name="store_id" class="form-select form-select-sm" style="width: 130px;" onchange="this.form.submit()">
                        <option value="">All Stores</option>
                        @foreach($stores as $st)
                            <option value="{{ $st->id }}" @selected(request('store_id') == $st->id)>{{ $st->name }}</option>
                        @endforeach
                    </select>

                    @if(request()->hasAny(['search', 'category', 'store_id', 'status']))
                        <a href="{{ route('store-manager.fixed-assets.index') }}" class="btn btn-sm btn-outline-secondary" title="Clear Filters">
                            <i class="fa-solid fa-xmark"></i> Clear
                        </a>
                    @endif
                </form>
            </div>
        </div>

        {{-- Assets List Table --}}
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th style="width: 35px;"></th>
                            <th>Fixed Asset / Category</th>
                            <th>Prefix</th>
                            <th>Quantity & Limit</th>
                            <th>Store Location</th>
                            <th>Unit Cost</th>
                            <th>Availability Status</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($fixedAssets as $asset)
                        @php
                            $uCount = $asset->units->count();
                            $inStoreCount = $asset->units->where('status', 'in_store')->count();
                            $assignedCount = $asset->units->where('status', 'assigned')->count();
                            $maintCount = $asset->units->where('status', 'maintenance')->count();
                            $collapseId = 'assetUnitsCollapse_' . $asset->id;
                        @endphp
                        {{-- Parent Asset Row --}}
                        <tr class="border-bottom">
                            <td class="text-center">
                                <button class="btn btn-sm btn-link text-secondary p-0 text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="false" title="Click to view {{ $uCount }} units">
                                    <i class="fa-solid fa-chevron-down toggle-icon" id="icon_{{ $collapseId }}"></i>
                                </button>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm rounded-circle bg-light border p-2 text-center me-3 text-primary">
                                        <i class="fa-solid {{ $asset->category_icon }} fs-5"></i>
                                    </div>
                                    <div>
                                        <a href="{{ route('store-manager.fixed-assets.show', $asset->id) }}" class="fw-bold text-dark text-decoration-none hover-primary">
                                            {{ $asset->name }}
                                        </a>
                                        <div class="small text-muted">
                                            <span class="badge bg-light text-dark border">{{ $asset->category }}</span>
                                            @if($asset->supplier) • <span title="Supplier"><i class="fa-solid fa-truck text-muted me-1"></i>{{ $asset->supplier }}</span> @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-purple-subtle text-purple border border-purple-subtle fw-mono px-2 py-1">
                                    {{ $asset->code_prefix }}
                                </span>
                            </td>
                            <td>
                                <div>
                                    <strong class="fs-6 text-dark">{{ $uCount }}</strong>
                                    <span class="text-muted">/ {{ $asset->total_quantity }} Max</span>
                                </div>
                                <div class="progress mt-1" style="height: 5px; width: 90px;">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $asset->total_quantity > 0 ? ($uCount / $asset->total_quantity) * 100 : 100 }}%"></div>
                                </div>
                            </td>
                            <td>
                                <div class="small text-dark fw-semibold">
                                    <i class="fa-solid fa-warehouse text-muted me-1"></i>{{ $asset->store->name ?? 'Main Store' }}
                                </div>
                            </td>
                            <td>
                                <div class="small fw-semibold text-dark">Br {{ number_format($asset->unit_cost, 2) }}</div>
                                <div class="text-muted small">Total: Br {{ number_format($asset->total_value, 2) }}</div>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    @if($inStoreCount > 0)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                            <i class="fa-solid fa-check me-1"></i>{{ $inStoreCount }} In Store
                                        </span>
                                    @endif
                                    @if($assignedCount > 0)
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                            <i class="fa-solid fa-user-check me-1"></i>{{ $assignedCount }} Assigned
                                        </span>
                                    @endif
                                    @if($maintCount > 0)
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                                            <i class="fa-solid fa-wrench me-1"></i>{{ $maintCount }} Maint.
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-end pe-3">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('store-manager.fixed-assets.show', $asset->id) }}" class="btn btn-outline-primary" title="View Units & Details">
                                        <i class="fa-solid fa-eye me-1"></i> View ({{ $uCount }})
                                    </a>
                                    <button type="button" class="btn btn-outline-secondary" onclick="openEditAssetModal({{ json_encode($asset) }})" title="Edit Asset & Quantity">
                                        <i class="fa-solid fa-sliders"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        {{-- Collapsible Child Units Table --}}
                        <tr class="collapse bg-light-subtle" id="{{ $collapseId }}">
                            <td colspan="8" class="p-3 bg-light border-bottom">
                                <div class="card border border-light-subtle shadow-none">
                                    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                                        <span class="small fw-bold text-dark">
                                            <i class="fa-solid fa-barcode text-primary me-2"></i>Unit Codes under <strong>{{ $asset->name }}</strong> (Prefix: <span class="font-monospace text-uppercase">{{ $asset->code_prefix }}</span>)
                                        </span>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('store-manager.fixed-assets.show', $asset->id) }}" class="btn btn-outline-primary btn-sm py-0 px-2 small">
                                                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Manage All Units
                                            </a>
                                        </div>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover mb-0 align-middle">
                                                <thead class="table-light small text-muted">
                                                    <tr>
                                                        <th class="ps-3">Unit Code</th>
                                                        <th>Available In Store</th>
                                                        <th>Brand / Model</th>
                                                        <th>Serial Number</th>
                                                        <th>Plate / VIN</th>
                                                        <th>Condition</th>
                                                        <th>Status / Assigned To</th>
                                                        <th class="text-end pe-3">Quick Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse($asset->units as $unit)
                                                    @php
                                                        $badge = $unit->status_badge;
                                                        $cond = $unit->condition_badge;
                                                        $unitStore = $unit->current_location ?: ($asset->store->name ?? 'Main Store');
                                                    @endphp
                                                    <tr>
                                                        <td class="ps-3">
                                                            <span class="badge bg-dark fw-mono px-2 py-1">
                                                                {{ $unit->unit_code }}
                                                            </span>
                                                        </td>
                                                        <td class="small">
                                                            <div class="fw-semibold text-dark">
                                                                <i class="fa-solid fa-warehouse text-primary me-1"></i>{{ $unitStore }}
                                                            </div>
                                                            @if($unit->isAvailable())
                                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-1 py-0" style="font-size:0.7rem;">
                                                                    <i class="fa-solid fa-circle-check me-1"></i>In Store
                                                                </span>
                                                            @endif
                                                        </td>
                                                        <td class="small">{{ trim("{$unit->brand} {$unit->model}") ?: '—' }}</td>
                                                        <td class="small font-monospace">{{ $unit->serial_number ?: '—' }}</td>
                                                        <td class="small font-monospace">{{ $unit->plate_number ?: '—' }}</td>
                                                        <td><span class="badge {{ $cond['class'] }}">{{ $cond['label'] }}</span></td>
                                                        <td>
                                                            <span class="badge {{ $badge['class'] }}">
                                                                <i class="fa-solid {{ $badge['icon'] }} me-1"></i>{{ $badge['label'] }}
                                                            </span>
                                                            @if($unit->isAssigned())
                                                                <div class="small text-muted mt-1">
                                                                    <i class="fa-solid fa-user text-primary me-1"></i>{{ $unit->assignedEmployee->full_name ?? 'Employee' }}
                                                                </div>
                                                            @endif
                                                        </td>
                                                        <td class="text-end pe-3">
                                                            <div class="btn-group btn-group-sm">
                                                                <button type="button" class="btn btn-outline-secondary py-0 px-2" onclick="openEditUnitModal({{ json_encode($unit) }}, '{{ addslashes($asset->category) }}', '{{ addslashes($unitStore) }}')" title="Edit Unit Specs">
                                                                    <i class="fa-solid fa-pencil"></i>
                                                                </button>
                                                                @if($unit->isAvailable())
                                                                    <button type="button" class="btn btn-outline-primary py-0 px-2" onclick="openAssignUnitModal({{ json_encode($unit) }})" title="Assign to Staff">
                                                                        <i class="fa-solid fa-user-plus"></i>
                                                                    </button>
                                                                @elseif($unit->isAssigned())
                                                                    <form action="{{ route('store-manager.fixed-assets.units.return', $unit->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Return {{ $unit->unit_code }} back to store inventory?');">
                                                                        @csrf
                                                                        <button type="submit" class="btn btn-outline-warning py-0 px-2" title="Return to Store">
                                                                            <i class="fa-solid fa-arrow-rotate-left"></i>
                                                                        </button>
                                                                    </form>
                                                                @endif
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    @empty
                                                    <tr>
                                                        <td colspan="8" class="text-center py-3 text-muted small">No unit codes generated yet.</td>
                                                    </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-truck-monster fa-3x mb-3 text-muted opacity-50 d-block"></i>
                                <h5>No Fixed Assets Found</h5>
                                <p class="small text-muted mb-3">Get started by creating your first centralized fixed asset.</p>
                                <button type="button" class="btn btn-primary btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#newAssetModal">
                                    <i class="fa-solid fa-plus me-1"></i> Add Fixed Asset
                                </button>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($fixedAssets->hasPages())
            <div class="card-footer bg-white py-3 border-top d-flex justify-content-between align-items-center">
                <div class="small text-muted">
                    Showing {{ $fixedAssets->firstItem() }} to {{ $fixedAssets->lastItem() }} of {{ $fixedAssets->total() }} fixed assets
                </div>
                {{ $fixedAssets->links() }}
            </div>
            @endif
        </div>
    </div>
</div>

{{-- ==================== MODALS ==================== --}}

{{-- 1. MODAL: New Fixed Asset with Auto-Code Generation Preview --}}
<div class="modal fade" id="newAssetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('store-manager.fixed-assets.store') }}" method="POST" id="newAssetForm">
                @csrf
                <div class="modal-header bg-primary text-white py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="fa-solid fa-plus-circle me-2"></i>Add New Fixed Asset
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Asset Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="new_asset_name" class="form-control form-control-sm" placeholder="e.g. Dell Latitude Laptop, Sinotruck 4x4" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Category <span class="text-danger">*</span></label>
                            <select name="category" id="new_asset_category" class="form-select form-select-sm" required>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat }}">{{ $cat }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Code Prefix <span class="text-danger">*</span></label>
                            <input type="text" name="code_prefix" id="new_asset_prefix" class="form-control form-control-sm font-monospace text-uppercase" placeholder="e.g. COMP, TRUCK" maxlength="10" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Quantity (Strict Unit Lock) <span class="text-danger">*</span></label>
                            <input type="number" name="total_quantity" id="new_asset_qty" class="form-control form-control-sm fw-bold" value="1" min="1" max="500" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Unit Cost (Br)</label>
                            <input type="number" step="0.01" name="unit_cost" class="form-control form-control-sm" placeholder="0.00" value="0">
                        </div>
                    </div>

                    {{-- Live Code Generation Preview Box --}}
                    <div class="alert alert-light border mt-3 mb-3 p-3">
                        <div class="d-flex align-items-center mb-1 text-primary fw-bold small">
                            <i class="fa-solid fa-wand-magic-sparkles me-2"></i>Auto-Generated Unit Codes Preview:
                        </div>
                        <div id="codePreviewList" class="d-flex flex-wrap gap-1 mt-2">
                            <span class="badge bg-dark fw-mono px-2 py-1">AST-1</span>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Brand / Manufacturer</label>
                            <input type="text" name="brand" class="form-control form-control-sm" placeholder="e.g. Dell, Toyota, Caterpillar">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Model</label>
                            <input type="text" name="model" class="form-control form-control-sm" placeholder="e.g. XPS 15, Hilux, D8T">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Store Location</label>
                            <select name="store_id" class="form-select form-select-sm">
                                <option value="">Main Store</option>
                                @foreach($stores as $st)
                                    <option value="{{ $st->id }}">{{ $st->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Supplier</label>
                            <input type="text" name="supplier" class="form-control form-control-sm" placeholder="e.g. Nyala Motors, Dell Ethiopia">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold">
                        <i class="fa-solid fa-check me-1"></i> Save Asset & Generate Units
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- 2. DYNAMIC SHARED MODAL: Edit Unit Specifications (Category-Aware with Sticky Footer) --}}
<div class="modal fade" id="sharedEditUnitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" style="max-height:95vh;">
        <div class="modal-content border-0 shadow-lg d-flex flex-column" style="max-height:90vh;">
            <form id="sharedEditUnitForm" method="POST" class="d-flex flex-column h-100">
                @csrf
                @method('PUT')
                <div class="modal-header py-3 flex-shrink-0" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%);">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:38px;height:38px;background:rgba(251,191,36,0.2);">
                            <i class="fa-solid fa-sliders text-warning"></i>
                        </div>
                        <div>
                            <h6 class="modal-title fw-bold text-white mb-0">Edit Unit: <span id="dyn_unit_code_header"></span></h6>
                            <div class="text-white-50 small" id="dyn_unit_cat_sub"></div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                {{-- Category Badge & Tabs --}}
                <div class="px-4 pt-3 pb-0 flex-shrink-0 bg-white border-bottom">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge bg-purple-subtle text-purple border border-purple-subtle px-3 py-1 fw-semibold" id="dyn_cat_pill"></span>
                        <span class="badge bg-light text-dark border px-3 py-1 shadow-sm" style="font-size:0.8rem;">
                            <i class="fa-solid fa-warehouse text-primary me-1"></i>Available In Store: <strong id="dyn_store_location_display" class="text-primary"></strong>
                        </span>
                    </div>
                    <ul class="nav nav-tabs border-0" id="indexEditUnitTabs">
                        <li class="nav-item">
                            <button type="button" class="nav-link active fw-semibold px-3" data-ieu-tab="identity">
                                <i class="fa-solid fa-id-card me-1"></i> Identity
                            </button>
                        </li>
                        <li class="nav-item" id="dyn_tab_vehicle_li">
                            <button type="button" class="nav-link fw-semibold px-3" data-ieu-tab="vehicle">
                                <i class="fa-solid fa-truck me-1"></i> Vehicle Info
                            </button>
                        </li>
                        <li class="nav-item" id="dyn_tab_computer_li">
                            <button type="button" class="nav-link fw-semibold px-3" data-ieu-tab="computer">
                                <i class="fa-solid fa-computer me-1"></i> Computer / IT
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link fw-semibold px-3" data-ieu-tab="status">
                                <i class="fa-solid fa-circle-dot me-1"></i> Status
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link fw-semibold px-3" data-ieu-tab="finance">
                                <i class="fa-solid fa-coins me-1"></i> Finance
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link fw-semibold px-3" data-ieu-tab="notes">
                                <i class="fa-solid fa-note-sticky me-1"></i> Notes
                            </button>
                        </li>
                    </ul>
                </div>

                {{-- Scrollable Body --}}
                <div class="modal-body p-4 overflow-auto flex-grow-1">

                    {{-- TAB: Identity --}}
                    <div class="ieu-tab-pane" data-ieu-pane="identity">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Unit Code <span class="text-danger">*</span></label>
                                <input type="text" name="unit_code" id="dyn_unit_code" class="form-control font-monospace fw-bold fs-5" required style="letter-spacing:2px;">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">
                                    <i class="fa-solid fa-warehouse text-primary me-1"></i>Available in Store / Physical Location
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fa-solid fa-location-dot text-primary"></i></span>
                                    <input type="text" name="current_location" id="dyn_unit_location" class="form-control" list="indexStoresDatalist"
                                           placeholder="e.g. Head Office, Main Store, Workshop, Site A"
                                           oninput="document.getElementById('dyn_store_location_display').textContent = this.value || 'Main Store'; if(document.getElementById('dyn_unit_location_status')) document.getElementById('dyn_unit_location_status').value = this.value;">
                                    <datalist id="indexStoresDatalist">
                                        @foreach($stores as $st)
                                            <option value="{{ $st->name }}">{{ $st->name }} (Active Store)</option>
                                        @endforeach
                                        <option value="Head Office">Head Office</option>
                                        <option value="Main Store">Main Store</option>
                                        <option value="Workshop">Workshop</option>
                                        <option value="Project Site">Project Site</option>
                                    </datalist>
                                </div>
                                <div class="form-text">The warehouse or site where this unit is physically located and available.</div>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Brand</label>
                                <input type="text" name="brand" id="dyn_unit_brand" class="form-control" placeholder="e.g. Hyundai, Dell, Caterpillar">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Model</label>
                                <input type="text" name="model" id="dyn_unit_model" class="form-control" placeholder="e.g. R320LC, Inspiron 15, 2024">
                            </div>
                            <div class="col-4">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Year</label>
                                <input type="number" name="year" id="dyn_unit_year" class="form-control" min="1950" max="2099" placeholder="e.g. 2024">
                            </div>
                            <div class="col-4">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Serial Number</label>
                                <input type="text" name="serial_number" id="dyn_unit_serial" class="form-control font-monospace" placeholder="SN-XXXXX">
                            </div>
                            <div class="col-4">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Warranty Expiry</label>
                                <input type="date" name="warranty_expiry" id="dyn_unit_warranty" class="form-control">
                            </div>
                        </div>
                    </div>

                    {{-- TAB: Vehicle --}}
                    <div class="ieu-tab-pane d-none" data-ieu-pane="vehicle">
                        <div class="alert alert-info border-0 py-2 small mb-3">
                            <i class="fa-solid fa-circle-info me-1"></i> Fields specific to <strong>vehicles & heavy machinery</strong>.
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Plate Number</label>
                                <input type="text" name="plate_number" id="dyn_unit_plate" class="form-control" placeholder="e.g. AA-99999">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Chassis / VIN</label>
                                <input type="text" name="chassis_number" id="dyn_unit_chassis" class="form-control font-monospace" placeholder="17-character VIN">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Engine Number</label>
                                <input type="text" name="engine_number" id="dyn_unit_engine" class="form-control font-monospace" placeholder="Engine No.">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Vehicle Specifications</label>
                                <textarea id="dyn_unit_specs_veh" class="form-control" rows="3" placeholder="Technical vehicle specifications..."></textarea>
                            </div>
                        </div>
                    </div>

                    {{-- TAB: Computer / IT --}}
                    <div class="ieu-tab-pane d-none" data-ieu-pane="computer">
                        <div class="alert alert-primary border-0 py-2 small mb-3">
                            <i class="fa-solid fa-circle-info me-1"></i> Fields specific to <strong>computers & IT equipment</strong>.
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">CPU</label>
                                <input type="text" id="dyn_cpu" class="form-control dyn-spec-part" data-spec-key="CPU" placeholder="e.g. Intel i7-12700">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">RAM</label>
                                <input type="text" id="dyn_ram" class="form-control dyn-spec-part" data-spec-key="RAM" placeholder="e.g. 16GB DDR5">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Storage</label>
                                <input type="text" id="dyn_storage" class="form-control dyn-spec-part" data-spec-key="Storage" placeholder="e.g. 512GB SSD">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">OS</label>
                                <input type="text" id="dyn_os" class="form-control dyn-spec-part" data-spec-key="OS" placeholder="e.g. Windows 11">
                            </div>
                        </div>
                    </div>

                    {{-- TAB: Status & Condition --}}
                    <div class="ieu-tab-pane d-none" data-ieu-pane="status">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Condition <span class="text-danger">*</span></label>
                                <select name="condition" id="dyn_unit_condition" class="form-select" required>
                                    <option value="new">🆕 Brand New</option>
                                    <option value="good">✅ Good</option>
                                    <option value="fair">🟡 Fair</option>
                                    <option value="needs_repair">🔧 Needs Repair</option>
                                    <option value="damaged">❌ Damaged</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Status <span class="text-danger">*</span></label>
                                <select name="status" id="dyn_unit_status" class="form-select" required>
                                    <option value="in_store">🏭 In Store (Available)</option>
                                    <option value="assigned">👤 Assigned to Staff</option>
                                    <option value="maintenance">🔧 Under Maintenance</option>
                                    <option value="disposed">🗑️ Disposed / Retired</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Current Location</label>
                                <input type="text" name="current_location" id="dyn_unit_location" class="form-control" placeholder="e.g. Main Store, Construction Site A">
                            </div>
                        </div>
                    </div>

                    {{-- TAB: Finance --}}
                    <div class="ieu-tab-pane d-none" data-ieu-pane="finance">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Purchase Price (Br)</label>
                                <input type="number" step="0.01" name="purchase_price" id="dyn_unit_price" class="form-control" placeholder="0.00" min="0">
                            </div>
                        </div>
                    </div>

                    {{-- TAB: Notes --}}
                    <div class="ieu-tab-pane d-none" data-ieu-pane="notes">
                        <div class="row g-3">
                            <input type="hidden" name="specifications" id="dyn_unit_specs">
                            <div class="col-12">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Full Specifications</label>
                                <textarea id="dyn_unit_specs_raw" class="form-control" rows="3" placeholder="Combined technical specifications..."></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Notes</label>
                                <textarea name="notes" id="dyn_unit_notes" class="form-control" rows="3" placeholder="Maintenance history, damage reports..."></textarea>
                            </div>
                        </div>
                    </div>

                </div>

                {{-- Sticky Footer --}}
                <div class="modal-footer bg-white border-top px-4 py-3 flex-shrink-0 d-flex justify-content-between align-items-center">
                    <div class="text-muted small" id="dyn_footer_cat"></div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                            <i class="fa-solid fa-xmark me-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary fw-bold px-5">
                            <i class="fa-solid fa-floppy-disk me-1"></i>Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- 3. DYNAMIC SHARED MODAL: Assign Unit Directly --}}
<div class="modal fade" id="sharedAssignUnitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <form id="sharedAssignUnitForm" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white py-2">
                    <h6 class="modal-title fw-bold">
                        <i class="fa-solid fa-user-plus me-1"></i> Assign <span id="dyn_assign_code_header"></span>
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Select Employee <span class="text-danger">*</span></label>
                        <select name="employee_id" class="form-select form-select-sm" required>
                            <option value="">-- Choose Employee --</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-bold">Assignment Notes</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="e.g. Project Site Laptop">
                    </div>
                </div>
                <div class="modal-footer bg-light p-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold">Assign Unit</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- 4. DYNAMIC SHARED MODAL: Edit Parent Asset & Quantity Lock --}}
<div class="modal fade" id="sharedEditAssetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="sharedEditAssetForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header bg-primary text-white py-3">
                    <h6 class="modal-title fw-bold">
                        <i class="fa-solid fa-pen-to-square me-2"></i>Edit Asset & Adjust Quantity
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Asset Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="dyn_edit_asset_name" class="form-control form-control-sm" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Category <span class="text-danger">*</span></label>
                            <input type="text" name="category" id="dyn_edit_asset_cat" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Code Prefix <span class="text-danger">*</span></label>
                            <input type="text" name="code_prefix" id="dyn_edit_asset_prefix" class="form-control form-control-sm font-monospace text-uppercase" required>
                        </div>
                    </div>

                    {{-- STRICT QUANTITY LOCK CONTROLS --}}
                    <div class="card border-warning bg-warning-subtle mb-3 p-2">
                        <div class="d-flex align-items-center mb-1 text-dark fw-bold small">
                            <i class="fa-solid fa-lock text-warning me-2"></i>Strict Quantity Lock Setting
                        </div>
                        <label class="form-label small fw-bold text-dark mb-1">
                            Inventory Quantity <span class="text-danger">*</span>
                        </label>
                        <input type="number" name="total_quantity" id="dyn_edit_asset_qty" class="form-control form-control-sm fw-bold" min="1" max="1000" required>
                        <small class="text-muted mt-1" style="font-size: 0.75rem;">
                            Current units in database: <strong id="dyn_edit_asset_cur_units"></strong>.
                        </small>
                    </div>

                    <div class="row g-2 mb-0">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Unit Cost (Br)</label>
                            <input type="number" step="0.01" name="unit_cost" id="dyn_edit_asset_cost" class="form-control form-control-sm">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Store Location</label>
                            <select name="store_id" id="dyn_edit_asset_store" class="form-select form-select-sm">
                                <option value="">Main Store</option>
                                @foreach($stores as $st)
                                    <option value="{{ $st->id }}">{{ $st->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light p-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// Code Prefix Auto-Generator for Create Modal
document.addEventListener('DOMContentLoaded', function() {
    const nameInput = document.getElementById('asset_name_input');
    const prefixInput = document.getElementById('code_prefix_input');
    const qtyInput = document.getElementById('total_quantity_input');
    const previewContainer = document.getElementById('unit_codes_preview');

    function updateCodePreview() {
        if (!previewContainer) return;
        const prefix = (prefixInput.value || 'ITEM').toUpperCase().trim();
        const qty = parseInt(qtyInput.value) || 1;
        const limit = Math.min(qty, 6);
        let previewHtml = '';
        for (let i = 1; i <= limit; i++) {
            previewHtml += `<span class="badge bg-dark fw-mono px-2 py-1">${prefix}-${i}</span> `;
        }
        if (qty > 6) {
            previewHtml += `<span class="text-muted small">... and ${qty - 6} more (up to ${prefix}-${qty})</span>`;
        }
        previewContainer.innerHTML = previewHtml;
    }

    if (nameInput && prefixInput && qtyInput) {
        nameInput.addEventListener('input', updateCodePreview);
        prefixInput.addEventListener('input', updateCodePreview);
        qtyInput.addEventListener('input', updateCodePreview);
    }
});

// Dynamic Edit Unit Modal Opener (Category-Aware)
function openEditUnitModal(unit, category, defaultStore) {
    category = category || 'Other';
    const isVehicle = ['Vehicle', 'Heavy Machinery'].includes(category);
    const isComputer = ['Computer & IT', 'Electronics'].includes(category);

    const storeLocation = unit.current_location || defaultStore || 'Main Store';

    document.getElementById('dyn_unit_code_header').textContent = unit.unit_code;
    document.getElementById('dyn_unit_cat_sub').textContent = category + ' Unit #' + unit.id;
    document.getElementById('dyn_footer_cat').textContent = category;
    if (document.getElementById('dyn_cat_pill')) {
        document.getElementById('dyn_cat_pill').textContent = category;
    }
    if (document.getElementById('dyn_store_location_display')) {
        document.getElementById('dyn_store_location_display').textContent = storeLocation;
    }

    // Show/hide category tabs
    document.getElementById('dyn_tab_vehicle_li').style.display = isVehicle ? '' : 'none';
    document.getElementById('dyn_tab_computer_li').style.display = isComputer ? '' : 'none';

    // Populate Identity
    document.getElementById('dyn_unit_code').value = unit.unit_code;
    document.getElementById('dyn_unit_location').value = storeLocation;
    document.getElementById('dyn_unit_brand').value = unit.brand || '';
    document.getElementById('dyn_unit_model').value = unit.model || '';
    document.getElementById('dyn_unit_year').value = unit.year || '';
    document.getElementById('dyn_unit_serial').value = unit.serial_number || '';
    document.getElementById('dyn_unit_warranty').value = unit.warranty_expiry ? unit.warranty_expiry.substring(0, 10) : '';

    // Vehicle fields
    document.getElementById('dyn_unit_plate').value = unit.plate_number || '';
    document.getElementById('dyn_unit_chassis').value = unit.chassis_number || '';
    document.getElementById('dyn_unit_engine').value = unit.engine_number || '';

    // Status fields
    document.getElementById('dyn_unit_condition').value = unit.condition || 'good';
    document.getElementById('dyn_unit_status').value = unit.status || 'in_store';
    if (document.getElementById('dyn_unit_location_status')) {
        document.getElementById('dyn_unit_location_status').value = storeLocation;
    }

    // Finance
    document.getElementById('dyn_unit_price').value = unit.purchase_price || '';

    // Notes / Specs
    const specs = unit.specifications || '';
    document.getElementById('dyn_unit_specs').value = specs;
    document.getElementById('dyn_unit_specs_raw').value = specs;
    document.getElementById('dyn_unit_specs_veh').value = specs;
    document.getElementById('dyn_unit_notes').value = unit.notes || '';

    const form = document.getElementById('sharedEditUnitForm');
    form.action = "{{ url('store-manager/fixed-assets/units') }}/" + unit.id;

    // Reset tab to identity
    document.querySelectorAll('.ieu-tab-pane').forEach(p => p.classList.add('d-none'));
    const firstPane = document.querySelector('[data-ieu-pane="identity"]');
    if (firstPane) firstPane.classList.remove('d-none');
    document.querySelectorAll('#indexEditUnitTabs .nav-link').forEach(b => {
        b.classList.toggle('active', b.dataset.ieuTab === 'identity');
    });

    bootstrap.Modal.getOrCreateInstance(document.getElementById('sharedEditUnitModal')).show();
}

// Tab switcher for index edit modal
document.addEventListener('click', function(e) {
    const tabBtn = e.target.closest('[data-ieu-tab]');
    if (tabBtn && tabBtn.closest('#indexEditUnitTabs')) {
        const tab = tabBtn.dataset.ieuTab;
        document.querySelectorAll('.ieu-tab-pane').forEach(p => p.classList.add('d-none'));
        const pane = document.querySelector('[data-ieu-pane="' + tab + '"]');
        if (pane) pane.classList.remove('d-none');
        document.querySelectorAll('#indexEditUnitTabs .nav-link').forEach(b => {
            b.classList.toggle('active', b.dataset.ieuTab === tab);
        });
    }
});

// Sync index modal specs before submit
document.getElementById('sharedEditUnitForm').addEventListener('submit', function() {
    const raw = document.getElementById('dyn_unit_specs_raw').value;
    const veh = document.getElementById('dyn_unit_specs_veh').value;
    if (veh && veh.trim()) {
        document.getElementById('dyn_unit_specs').value = veh.trim();
    } else if (raw && raw.trim()) {
        document.getElementById('dyn_unit_specs').value = raw.trim();
    }
});

// Dynamic Assign Unit Modal Opener
function openAssignUnitModal(unit) {
    document.getElementById('dyn_assign_code_header').textContent = unit.unit_code;
    const form = document.getElementById('sharedAssignUnitForm');
    form.action = "{{ url('store-manager/fixed-assets/units') }}/" + unit.id + "/assign";

    new bootstrap.Modal(document.getElementById('sharedAssignUnitModal')).show();
}

// Dynamic Edit Parent Asset Modal Opener
function openEditAssetModal(asset) {
    document.getElementById('dyn_edit_asset_name').value = asset.name || '';
    document.getElementById('dyn_edit_asset_cat').value = asset.category || '';
    document.getElementById('dyn_edit_asset_prefix').value = asset.code_prefix || '';
    document.getElementById('dyn_edit_asset_qty').value = asset.total_quantity || 1;
    document.getElementById('dyn_edit_asset_cur_units').textContent = (asset.units ? asset.units.length : (asset.total_quantity || 0)) + ' records';
    document.getElementById('dyn_edit_asset_cost').value = asset.unit_cost || 0;
    document.getElementById('dyn_edit_asset_store').value = asset.store_id || '';

    const form = document.getElementById('sharedEditAssetForm');
    form.action = "{{ url('store-manager/fixed-assets') }}/" + asset.id;

    new bootstrap.Modal(document.getElementById('sharedEditAssetModal')).show();
}
</script>
@endpush
