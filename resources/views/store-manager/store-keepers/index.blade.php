@extends('layouts.app')

@section('title', 'Assign Store Keepers — Store Manager Hub')

@section('content')
<style>
/* ── Page Header Gradient ── */
.hub-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0d9488 100%);
    border-radius: 16px;
    padding: 24px 28px;
    color: #fff;
    margin-bottom: 24px;
    box-shadow: 0 4px 20px rgba(0,0,0,.08);
}
.hub-header h4 { font-size: 1.4rem; font-weight: 700; margin: 0; }
.hub-header p { margin: 4px 0 0; opacity: .8; font-size: .88rem; }

/* ── KPI Metric Cards ── */
.kpi-card {
    border-radius: 14px;
    border: none;
    background: #fff;
    box-shadow: 0 2px 12px rgba(0,0,0,.06);
    transition: transform .15s ease, box-shadow .15s ease;
    overflow: hidden;
}
.kpi-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.1); }
.kpi-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

/* ── Tab Pills ── */
.nav-pills-custom .nav-link {
    border-radius: 10px;
    font-weight: 600;
    font-size: .88rem;
    padding: 10px 20px;
    color: #475569;
    background: #fff;
    border: 1px solid #e2e8f0;
    transition: all .2s;
}
.nav-pills-custom .nav-link.active {
    background: #0d9488;
    color: #fff;
    border-color: #0d9488;
    box-shadow: 0 4px 12px rgba(13, 148, 136, .25);
}

/* ── Store Table & Cards ── */
.store-table th {
    font-size: .75rem;
    text-transform: uppercase;
    letter-spacing: .6px;
    color: #64748b;
    font-weight: 700;
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
    padding: 12px 14px;
}
.store-table td {
    padding: 14px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}
.store-table tr:hover td {
    background: #f8fafc;
}
.avatar-badge {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: .85rem;
}
</style>

<div class="container-fluid py-2">

    {{-- Flash Notifications --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-circle-check fs-5 text-success"></i>
                <div><strong>Success:</strong> {{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-start gap-2">
                <i class="fa-solid fa-triangle-exclamation fs-5 text-danger mt-1"></i>
                <div>
                    <strong>Please check the following errors:</strong>
                    <ul class="mb-0 mt-1 small">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- ── Header Banner ── --}}
    <div class="hub-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <h4><i class="fa-solid fa-users-gear me-2"></i>Store Keeper Assignment Hub</h4>
                <p>Assign and manage Store Keepers across central warehouses, site storage yards, and project stores</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button type="button" class="btn btn-light fw-bold px-3 py-2 rounded-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#createStoreModal">
                    <i class="fa-solid fa-plus-circle me-1 text-teal"></i> Create New Store / Yard
                </button>
                <a href="{{ route('store-manager.inventory.all') }}" class="btn btn-outline-light px-3 py-2 rounded-3">
                    <i class="fa-solid fa-boxes me-1"></i> Inventory Stock
                </a>
            </div>
        </div>
    </div>

    {{-- ── KPI Cards ── --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="kpi-card p-3 d-flex align-items-center gap-3">
                <div class="kpi-icon bg-primary bg-opacity-10 text-primary">
                    <i class="fa-solid fa-warehouse"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.72rem;">Total Stores</div>
                    <div class="fs-4 fw-bold text-dark">{{ $totalStoresCount }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card p-3 d-flex align-items-center gap-3">
                <div class="kpi-icon bg-success bg-opacity-10 text-success">
                    <i class="fa-solid fa-user-check"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.72rem;">Assigned Stores</div>
                    <div class="fs-4 fw-bold text-success">{{ $assignedStoresCount }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card p-3 d-flex align-items-center gap-3">
                <div class="kpi-icon bg-warning bg-opacity-10 text-warning">
                    <i class="fa-solid fa-user-slash"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.72rem;">Unassigned (Needs Keeper)</div>
                    <div class="fs-4 fw-bold {{ $unassignedStoresCount > 0 ? 'text-danger' : 'text-muted' }}">{{ $unassignedStoresCount }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card p-3 d-flex align-items-center gap-3">
                <div class="kpi-icon bg-info bg-opacity-10 text-info">
                    <i class="fa-solid fa-id-badge"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.72rem;">Store Keepers Pool</div>
                    <div class="fs-4 fw-bold text-info">{{ $totalStoreKeepersCount }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Nav Tabs & Filters ── --}}
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
        <ul class="nav nav-pills nav-pills-custom gap-2" id="storeHubTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="stores-tab" data-bs-toggle="pill" data-bs-target="#storesView" type="button" role="tab">
                    <i class="fa-solid fa-store me-1"></i> Stores &amp; Assigned Keepers ({{ $stores->count() }})
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="keepers-tab" data-bs-toggle="pill" data-bs-target="#keepersView" type="button" role="tab">
                    <i class="fa-solid fa-users me-1"></i> Store Keepers Directory ({{ $storeKeepers->count() }})
                </button>
            </li>
        </ul>

        {{-- Filter & Search Form --}}
        <form method="GET" action="{{ route('store-manager.store-keepers.index') }}" class="d-flex align-items-center gap-2 flex-wrap">
            <div class="input-group input-group-sm" style="width: 220px;">
                <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Search store or keeper..." value="{{ request('search') }}">
            </div>

            <select name="type" class="form-select form-select-sm" style="width: 140px;" onchange="this.form.submit()">
                <option value="">All Types</option>
                <option value="site" {{ request('type') == 'site' ? 'selected' : '' }}>Site Store</option>
                <option value="warehouse" {{ request('type') == 'warehouse' ? 'selected' : '' }}>Warehouse</option>
                <option value="yard" {{ request('type') == 'yard' ? 'selected' : '' }}>Yard</option>
            </select>

            <select name="status" class="form-select form-select-sm" style="width: 140px;" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="assigned" {{ request('status') == 'assigned' ? 'selected' : '' }}>Assigned</option>
                <option value="unassigned" {{ request('status') == 'unassigned' ? 'selected' : '' }}>Unassigned</option>
            </select>

            @if(request()->anyFilled(['search', 'type', 'status', 'project_id']))
                <a href="{{ route('store-manager.store-keepers.index') }}" class="btn btn-outline-secondary btn-sm" title="Clear Filters">
                    <i class="fa-solid fa-times"></i>
                </a>
            @endif
        </form>
    </div>

    {{-- ── Tab Contents ── --}}
    <div class="tab-content" id="storeHubTabsContent">

        {{-- TAB 1: Stores & Assigned Keepers Table --}}
        <div class="tab-pane fade show active" id="storesView" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="table-responsive">
                    <table class="table store-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Store Code &amp; Name</th>
                                <th>Type &amp; Project</th>
                                <th>Primary Store Keeper</th>
                                <th>Additional Keepers</th>
                                <th>Items on Hand</th>
                                <th>Status</th>
                                <th class="pe-4 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($stores as $store)
                                @php
                                    $primaryKeeper = $store->manager;
                                    $additionalKeepers = $store->users->where('id', '!=', $store->manager_id);
                                    $typeBadge = match($store->type) {
                                        'warehouse' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                        'yard'      => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                                        default     => 'bg-info-subtle text-info border border-info-subtle',
                                    };
                                @endphp
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-badge bg-dark bg-opacity-10 text-dark">
                                                <i class="fa-solid fa-warehouse"></i>
                                            </div>
                                            <div>
                                                <span class="badge bg-light text-dark border font-monospace me-1">{{ $store->code }}</span>
                                                <strong class="text-dark">{{ $store->name }}</strong>
                                                @if($store->address)
                                                    <div class="small text-muted"><i class="fa-solid fa-location-dot me-1"></i>{{ $store->address }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="badge {{ $typeBadge }} text-uppercase mb-1">{{ ucfirst($store->type) }}</span>
                                        @if($store->project)
                                            <div class="small text-muted"><i class="fa-solid fa-diagram-project me-1"></i>{{ $store->project->name }}</div>
                                        @else
                                            <div class="small text-muted"><i class="fa-solid fa-building me-1"></i>Central / Head Office</div>
                                        @endif
                                    </td>

                                    <td>
                                        @if($primaryKeeper)
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="avatar-badge bg-success text-white">
                                                    {{ strtoupper(substr($primaryKeeper->name, 0, 1)) }}
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark">{{ $primaryKeeper->name }}</div>
                                                    <div class="small text-muted">{{ $primaryKeeper->email }}</div>
                                                </div>
                                            </div>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger p-2 rounded-3">
                                                <i class="fa-solid fa-triangle-exclamation me-1"></i> Not Assigned
                                            </span>
                                        @endif
                                    </td>

                                    <td>
                                        @if($additionalKeepers->count() > 0)
                                            <div class="d-flex flex-wrap gap-1">
                                                @foreach($additionalKeepers as $ak)
                                                    <span class="badge bg-light text-dark border" title="{{ $ak->email }}">
                                                        <i class="fa-solid fa-user me-1 text-secondary"></i>{{ $ak->name }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>

                                    <td>
                                        <span class="fw-bold text-dark font-monospace">{{ number_format($store->inventory_count) }}</span>
                                        <span class="text-muted small">lines</span>
                                    </td>

                                    <td>
                                        @if($store->is_active)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Active</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary px-2 py-1">Inactive</span>
                                        @endif
                                    </td>

                                    <td class="pe-4 text-end">
                                        <div class="d-inline-flex gap-1">
                                            <button type="button" class="btn btn-sm btn-primary rounded-3 px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#assignModal{{ $store->id }}">
                                                <i class="fa-solid fa-user-pen me-1"></i> Assign Keeper
                                            </button>
                                            <button type="button" class="btn btn-sm btn-light border rounded-3 px-2" data-bs-toggle="modal" data-bs-target="#editStoreModal{{ $store->id }}" title="Edit Store Information">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-warehouse fa-3x mb-3 text-secondary opacity-50"></i>
                                        <h6>No stores found matching your criteria.</h6>
                                        <p class="small mb-0">Click <strong>"Create New Store"</strong> above to add a warehouse or site store.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- TAB 2: Store Keepers Staff Directory --}}
        <div class="tab-pane fade" id="keepersView" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-id-badge text-teal me-2"></i>Store Keepers Staff Pool &amp; Active Assignments</h6>
                </div>
                <div class="table-responsive">
                    <table class="table store-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Store Keeper Name</th>
                                <th>Email &amp; Contact</th>
                                <th>Role</th>
                                <th>Currently Assigned Store</th>
                                <th>Status</th>
                                <th class="pe-4 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($storeKeepers as $keeper)
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-badge bg-primary text-white">
                                                {{ strtoupper(substr($keeper->name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <strong class="text-dark">{{ $keeper->name }}</strong>
                                                <div class="small text-muted">User ID: #{{ $keeper->id }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div><i class="fa-solid fa-envelope me-1 text-muted"></i>{{ $keeper->email }}</div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle">Store Keeper</span>
                                    </td>
                                    <td>
                                        @if($keeper->store)
                                            <div class="d-flex align-items-center gap-1">
                                                <span class="badge bg-light text-dark border font-monospace">{{ $keeper->store->code }}</span>
                                                <strong class="text-dark">{{ $keeper->store->name }}</strong>
                                                @if($keeper->store->manager_id == $keeper->id)
                                                    <span class="badge bg-success-subtle text-success ms-1">Primary Keeper</span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning-emphasis">
                                                <i class="fa-solid fa-circle-pause me-1"></i> Unassigned Pool
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($keeper->is_active)
                                            <span class="badge bg-success-subtle text-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="pe-4 text-end">
                                        @if($keeper->store)
                                            <form method="POST" action="{{ route('store-manager.stores.unassign', ['store' => $keeper->store->id, 'user' => $keeper->id]) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to unassign {{ $keeper->name }} from {{ $keeper->store->name }}?');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-3 px-2" title="Unassign Keeper">
                                                    <i class="fa-solid fa-user-xmark me-1"></i> Unassign
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        No store keeper staff members found in the directory.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

</div>

{{-- ── Modals: Assign Store Keeper for each Store ── --}}
@foreach($stores as $store)
    <div class="modal fade" id="assignModal{{ $store->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <form method="POST" action="{{ route('store-manager.store-keepers.update') }}">
                    @csrf
                    <input type="hidden" name="store_id" value="{{ $store->id }}">

                    <div class="modal-header bg-dark text-white py-3 px-4">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-teal p-2 rounded-3 fs-6">
                                <i class="fa-solid fa-user-tag text-white"></i>
                            </span>
                            <div>
                                <h5 class="modal-title fw-bold text-white mb-0">Assign Store Keeper</h5>
                                <span class="text-muted small">Store: <strong>[{{ $store->code }}] {{ $store->name }}</strong></span>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body p-4 bg-white">
                        <div class="p-3 bg-light rounded-3 mb-4 border">
                            <div class="row g-2 small">
                                <div class="col-md-6">
                                    <span class="text-muted d-block">Store Name &amp; Code:</span>
                                    <strong class="text-dark">[{{ $store->code }}] {{ $store->name }}</strong>
                                </div>
                                <div class="col-md-6">
                                    <span class="text-muted d-block">Store Type:</span>
                                    <strong class="text-dark">{{ ucfirst($store->type) }} ({{ $store->project ? $store->project->name : 'Central Warehouse' }})</strong>
                                </div>
                            </div>
                        </div>

                        {{-- Primary Store Keeper --}}
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-dark">
                                <i class="fa-solid fa-user-shield text-primary me-1"></i> Primary Store Keeper (Lead Custodian)
                            </label>
                            <select name="primary_keeper_id" class="form-select form-select-lg rounded-3">
                                <option value="">-- Select Primary Store Keeper (Or Leave Unassigned) --</option>
                                @foreach($storeKeepers as $u)
                                    <option value="{{ $u->id }}" {{ $store->manager_id == $u->id ? 'selected' : '' }}>
                                        {{ $u->name }} ({{ $u->email }}) @if($u->store_id && $u->store_id != $store->id) [Assigned to {{ $u->store ? $u->store->name : 'Store #' . $u->store_id }}] @endif
                                    </option>
                                @endforeach
                                @if($store->manager && !$storeKeepers->contains('id', $store->manager->id))
                                    <option value="{{ $store->manager->id }}" selected>
                                        {{ $store->manager->name }} ({{ $store->manager->email }}) [Currently Assigned]
                                    </option>
                                @endif
                            </select>
                            <small class="text-muted d-block mt-1">
                                The primary store keeper receives direct material transfers, incoming shipments, and material issue authorizations for this store.
                            </small>
                        </div>

                        {{-- Additional Assistant Keepers --}}
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark">
                                <i class="fa-solid fa-users text-secondary me-1"></i> Additional Assistant Store Keepers (Optional)
                            </label>
                            <div class="p-3 border rounded-3 bg-light" style="max-height: 180px; overflow-y: auto;">
                                @php
                                    $currentAssignedIds = $store->users->pluck('id')->toArray();
                                @endphp
                                @forelse($storeKeepers as $u)
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="additional_keeper_ids[]" value="{{ $u->id }}" id="keeper_{{ $store->id }}_{{ $u->id }}"
                                            {{ in_array($u->id, $currentAssignedIds) && $store->manager_id != $u->id ? 'checked' : '' }}>
                                        <label class="form-check-label small text-dark" for="keeper_{{ $store->id }}_{{ $u->id }}">
                                            <strong>{{ $u->name }}</strong> <span class="text-muted">({{ $u->email }})</span>
                                            @if($u->store_id && $u->store_id != $store->id)
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-1" style="font-size:0.7rem;">Assigned elsewhere</span>
                                            @endif
                                        </label>
                                    </div>
                                @empty
                                    <div class="text-muted small py-2 text-center">
                                        <i class="fa-solid fa-circle-exclamation me-1"></i> No users with Store Keeper role found.
                                    </div>
                                @endforelse
                            </div>
                            <small class="text-muted d-block mt-1">Assistant keepers will also have permission to record physical receipts and inventory movements for this store.</small>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-bold text-dark">Assignment Notes (Optional)</label>
                            <textarea name="assignment_notes" class="form-control rounded-3" rows="2" placeholder="e.g., Assigned for Phase 2 site mobilization..."></textarea>
                        </div>
                    </div>

                    <div class="modal-footer bg-light border-0 py-3 px-4">
                        <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold rounded-3 px-4">
                            <i class="fa-solid fa-check me-1"></i> Save Store Keeper Assignment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit Store Modal --}}
    <div class="modal fade" id="editStoreModal{{ $store->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <form method="POST" action="{{ route('store-manager.stores.quick-update', $store->id) }}">
                    @csrf
                    <div class="modal-header bg-dark text-white py-3 px-4">
                        <h5 class="modal-title fw-bold text-white mb-0"><i class="fa-solid fa-pen-to-square me-2"></i>Edit Store: {{ $store->code }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4 bg-white">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Store Name *</label>
                            <input type="text" name="name" class="form-control rounded-3" value="{{ $store->name }}" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Store Code *</label>
                                <input type="text" name="code" class="form-control rounded-3 font-monospace" value="{{ $store->code }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Store Type *</label>
                                <select name="type" class="form-select rounded-3" required>
                                    <option value="site" {{ $store->type == 'site' ? 'selected' : '' }}>Site Store</option>
                                    <option value="warehouse" {{ $store->type == 'warehouse' ? 'selected' : '' }}>Central Warehouse</option>
                                    <option value="yard" {{ $store->type == 'yard' ? 'selected' : '' }}>Storage Yard</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Project (Optional)</label>
                            <select name="project_id" class="form-select rounded-3">
                                <option value="">-- No Specific Project (Central / HQ) --</option>
                                @foreach($projects as $p)
                                    <option value="{{ $p->id }}" {{ $store->project_id == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Address / Location</label>
                            <input type="text" name="address" class="form-control rounded-3" value="{{ $store->address }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Notes</label>
                            <textarea name="notes" class="form-control rounded-3" rows="2">{{ $store->notes }}</textarea>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="active{{ $store->id }}" {{ $store->is_active ? 'checked' : '' }}>
                            <label class="form-check-label small fw-bold" for="active{{ $store->id }}">Store is Active</label>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0 py-3 px-4">
                        <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold rounded-3 px-4">Update Store</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

{{-- ── Create New Store Modal ── --}}
<div class="modal fade" id="createStoreModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST" action="{{ route('store-manager.stores.quick-create') }}">
                @csrf
                <div class="modal-header bg-teal text-white py-3 px-4">
                    <h5 class="modal-title fw-bold text-white mb-0"><i class="fa-solid fa-plus-circle me-2"></i>Create New Store / Warehouse</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label class="form-label small fw-bold">Store Name *</label>
                            <input type="text" name="name" class="form-control rounded-3" placeholder="e.g., Ayat Site Central Store" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">Store Code *</label>
                            <input type="text" name="code" class="form-control rounded-3 font-monospace text-uppercase" placeholder="e.g., ST-AYAT-01" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Store Type *</label>
                            <select name="type" class="form-select rounded-3" required>
                                <option value="site">Site Store (Construction Site)</option>
                                <option value="warehouse">Central Warehouse</option>
                                <option value="yard">Storage Yard</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Associated Project</label>
                            <select name="project_id" class="form-select rounded-3">
                                <option value="">-- Central / No Project --</option>
                                @foreach($projects as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Initial Assigned Primary Store Keeper (Optional)</label>
                        <select name="primary_keeper_id" class="form-select rounded-3">
                            <option value="">-- Assign Later --</option>
                            @foreach($storeKeepers as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Location / Address</label>
                        <input type="text" name="address" class="form-control rounded-3" placeholder="e.g., Ayat Zone 3, Block B Yard">
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold">Notes</label>
                        <textarea name="notes" class="form-control rounded-3" rows="2" placeholder="Optional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3 px-4">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold rounded-3 px-4">
                        <i class="fa-solid fa-plus-circle me-1"></i> Create Store
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
