@extends('layouts.app')

@section('title', $fixedAsset->name . ' - Fixed Asset Details')

@section('content')
<div class="container-fluid py-3">

    {{-- Breadcrumb & Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.store-manager') }}" class="text-decoration-none">Store Manager</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('store-manager.fixed-assets.index') }}" class="text-decoration-none">Fixed Assets</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $fixedAsset->name }}</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 text-dark fw-bold">
                <i class="fa-solid {{ $fixedAsset->category_icon }} text-primary me-2"></i>{{ $fixedAsset->name }}
            </h1>
            <p class="text-muted small mb-0">Prefix: <strong class="font-monospace text-uppercase">{{ $fixedAsset->code_prefix }}</strong> • Category: {{ $fixedAsset->category }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('store-manager.fixed-assets.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Assets List
            </a>
            @if($fixedAsset->canAddUnit())
                <button type="button" class="btn btn-success btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#addExtraUnitModal">
                    <i class="fa-solid fa-plus me-1"></i> Add Unit Code ({{ $fixedAsset->units->count() }}/{{ $fixedAsset->total_quantity }})
                </button>
            @else
                <button type="button" class="btn btn-outline-danger btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#editAssetModal">
                    <i class="fa-solid fa-lock me-1"></i> Quantity Limit Reached ({{ $fixedAsset->total_quantity }}/{{ $fixedAsset->total_quantity }}) - Update Qty
                </button>
            @endif
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

    <div class="row g-4">
        {{-- Left Column: Asset Summary Info & Strict Lock Alert --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-dark">
                        <i class="fa-solid fa-info-circle text-primary me-2"></i>Asset Summary
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editAssetModal">
                        <i class="fa-solid fa-edit me-1"></i> Edit
                    </button>
                </div>
                <div class="card-body p-3">
                    <table class="table table-sm table-borderless small mb-0">
                        <tr>
                            <th class="text-muted" style="width: 40%;">Category:</th>
                            <td class="fw-bold text-dark">{{ $fixedAsset->category }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">Code Prefix:</th>
                            <td>
                                <code class="badge bg-secondary fs-6 fw-bold px-2 py-1" style="letter-spacing:1px">{{ $fixedAsset->code_prefix }}</code>
                                <div class="text-muted mt-1" style="font-size:0.72rem">Unit codes: <span class="font-monospace">{{ $fixedAsset->code_prefix }}-1 … {{ $fixedAsset->code_prefix }}-{{ $fixedAsset->total_quantity }}</span></div>
                            </td>
                        </tr>
                        <tr>
                            <th class="text-muted">Total Quantity:</th>
                            <td><span class="badge bg-dark fs-6">{{ $fixedAsset->total_quantity }} Units</span></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Current Units:</th>
                            <td class="fw-bold">{{ $fixedAsset->units->count() }} records</td>
                        </tr>
                        <tr>
                            <th class="text-muted">In Store:</th>
                            <td><span class="badge bg-success">{{ $fixedAsset->available_count }} Available</span></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Assigned:</th>
                            <td><span class="badge bg-primary">{{ $fixedAsset->assigned_count }} In Use</span></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Unit Cost:</th>
                            <td>Br {{ number_format($fixedAsset->unit_cost, 2) }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">Total Value:</th>
                            <td class="fw-bold text-success">Br {{ number_format($fixedAsset->total_value, 2) }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">Store Location:</th>
                            <td>{{ $fixedAsset->store->name ?? 'Main Store' }}</td>
                        </tr>
                        @if($fixedAsset->supplier)
                        <tr>
                            <th class="text-muted">Supplier:</th>
                            <td>{{ $fixedAsset->supplier }}</td>
                        </tr>
                        @endif
                        @if($fixedAsset->purchase_date)
                        <tr>
                            <th class="text-muted">Purchase Date:</th>
                            <td>{{ $fixedAsset->purchase_date->format('M d, Y') }}</td>
                        </tr>
                        @endif
                    </table>

                    @if($fixedAsset->description)
                        <div class="mt-3 pt-3 border-top small">
                            <strong class="text-muted d-block mb-1">Notes / Description:</strong>
                            <p class="text-dark mb-0">{{ $fixedAsset->description }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Strict Quantity Lock Card --}}
            <div class="card border-0 shadow-sm bg-light">
                <div class="card-body p-3">
                    <h6 class="fw-bold text-dark small text-uppercase mb-2">
                        <i class="fa-solid fa-lock text-warning me-1"></i>Strict Quantity Lock Status
                    </h6>
                    @if($fixedAsset->canAddUnit())
                        <div class="alert alert-info py-2 px-3 small mb-2">
                            <i class="fa-solid fa-info-circle me-1"></i>
                            You can generate <strong>{{ $fixedAsset->total_quantity - $fixedAsset->units->count() }}</strong> more unit code(s) before reaching the quantity limit.
                        </div>
                    @else
                        <div class="alert alert-warning py-2 px-3 small mb-2 text-dark">
                            <i class="fa-solid fa-lock me-1"></i>
                            <strong>Quantity Limit Reached ({{ $fixedAsset->total_quantity }}/{{ $fixedAsset->total_quantity }}):</strong> To add an extra unit code, you must first increase the total inventory quantity.
                        </div>
                    @endif
                    <div class="d-grid">
                        <button type="button" class="btn btn-outline-dark btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#editAssetModal">
                            <i class="fa-solid fa-calculator me-1"></i> Adjust Inventory Quantity
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Column: Units List & Details Grid --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-dark">
                        <i class="fa-solid fa-barcode text-primary me-2"></i>Individual Unit Codes ({{ $fixedAsset->units->count() }} Total)
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    <th>Unit Code</th>
                                    <th>Available In Store</th>
                                    <th>Specifications</th>
                                    <th>Condition</th>
                                    <th>Status / Assigned To</th>
                                    <th class="text-end pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($fixedAsset->units as $unit)
                                @php
                                    $stBadge = $unit->status_badge;
                                    $condBadge = $unit->condition_badge;
                                    $storeLocation = $unit->current_location ?: ($fixedAsset->store->name ?? 'Main Store');
                                @endphp
                                <tr>
                                    <td>
                                        <span class="badge bg-dark fw-mono px-2 py-1 fs-6">
                                            {{ $unit->unit_code }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark small">
                                            <i class="fa-solid fa-warehouse text-primary me-1"></i>{{ $storeLocation }}
                                        </div>
                                        @if($unit->isAvailable())
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-1 py-0 mt-1" style="font-size: 0.72rem;">
                                                <i class="fa-solid fa-circle-check me-1"></i>Available in Store
                                            </span>
                                        @elseif($unit->isAssigned())
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-1 py-0 mt-1" style="font-size: 0.72rem;">
                                                <i class="fa-solid fa-user me-1"></i>Deployed / In Use
                                            </span>
                                        @elseif($unit->status === 'maintenance')
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-1 py-0 mt-1" style="font-size: 0.72rem;">
                                                <i class="fa-solid fa-wrench me-1"></i>Maintenance
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="small">
                                            @if($unit->plate_number)
                                                <div><strong class="text-dark">Plate:</strong> {{ $unit->plate_number }}</div>
                                            @endif
                                            @if($unit->serial_number)
                                                <div><strong class="text-dark">Serial:</strong> {{ $unit->serial_number }}</div>
                                            @endif
                                            @if($unit->brand || $unit->model)
                                                <div class="text-muted">{{ trim("{$unit->brand} {$unit->model}") }}</div>
                                            @endif
                                            @if($unit->specifications)
                                                <div class="text-muted text-truncate" style="max-width: 220px;">{{ $unit->specifications }}</div>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge {{ $condBadge['class'] }}">{{ $condBadge['label'] }}</span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $stBadge['class'] }} mb-1 d-inline-block">
                                            <i class="fa-solid {{ $stBadge['icon'] }} me-1"></i>{{ $stBadge['label'] }}
                                        </span>
                                        @if($unit->isAssigned())
                                            <div class="small fw-bold text-dark">
                                                <i class="fa-solid fa-user-check text-primary me-1"></i>{{ $unit->assignedEmployee->full_name ?? 'Employee' }}
                                            </div>
                                            <div class="text-muted" style="font-size: 0.75rem;">
                                                Since {{ $unit->assigned_date ? $unit->assigned_date->format('M d, Y') : '' }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="btn-group btn-group-sm">
                                            {{-- Edit button: uses data attributes, shared modal populated by JS --}}
                                            <button type="button" class="btn btn-outline-secondary btn-edit-unit" title="Edit Specifications"
                                                data-unit-id="{{ $unit->id }}"
                                                data-unit-code="{{ $unit->unit_code }}"
                                                data-category="{{ $fixedAsset->category }}"
                                                data-brand="{{ $unit->brand }}"
                                                data-model="{{ $unit->model }}"
                                                data-serial="{{ $unit->serial_number }}"
                                                data-plate="{{ $unit->plate_number }}"
                                                data-chassis="{{ $unit->chassis_number }}"
                                                data-engine="{{ $unit->engine_number }}"
                                                data-year="{{ $unit->year }}"
                                                data-condition="{{ $unit->condition }}"
                                                data-status="{{ $unit->status }}"
                                                data-specifications="{{ $unit->specifications }}"
                                                data-location="{{ $unit->current_location ?: ($fixedAsset->store->name ?? 'Main Store') }}"
                                                data-default-store="{{ $fixedAsset->store->name ?? 'Main Store' }}"
                                                data-purchase-price="{{ $unit->purchase_price }}"
                                                data-warranty-expiry="{{ $unit->warranty_expiry ? $unit->warranty_expiry->format('Y-m-d') : '' }}"
                                                data-notes="{{ $unit->notes }}"
                                                data-update-url="{{ route('store-manager.fixed-assets.units.update', $unit->id) }}">
                                                <i class="fa-solid fa-edit"></i>
                                            </button>
                                            @if($unit->isAssigned())
                                                <form action="{{ route('store-manager.fixed-assets.units.return', $unit->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Return {{ $unit->unit_code }} back to store?');">
                                                    @csrf
                                                    <button type="submit" class="btn btn-outline-warning" title="Return to Store">
                                                        <i class="fa-solid fa-arrow-rotate-left"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <form action="{{ route('store-manager.fixed-assets.units.destroy', $unit->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete unit {{ $unit->unit_code }}? This will decrease total quantity to {{ $fixedAsset->total_quantity - 1 }}.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger" title="Delete Unit">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        No unit codes registered yet.
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
</div>


{{-- MODAL: Add Extra Unit (Subject to Quantity Limit) --}}
@if($fixedAsset->canAddUnit())
<div class="modal fade" id="addExtraUnitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('store-manager.fixed-assets.extra-unit', $fixedAsset->id) }}" method="POST">
                @csrf
                <div class="modal-header bg-success text-white py-3">
                    <h6 class="modal-title fw-bold">
                        <i class="fa-solid fa-plus me-2"></i>Add Unit Code under {{ $fixedAsset->name }}
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="alert alert-light border small mb-3">
                        Generating unit within quantity limit: <strong>{{ $fixedAsset->units->count() + 1 }} / {{ $fixedAsset->total_quantity }}</strong>.
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Unit Code (Leave blank for auto-generate)</label>
                        <input type="text" name="unit_code" class="form-control form-control-sm font-monospace" placeholder="e.g. {{ $fixedAsset->code_prefix }}-{{ $fixedAsset->units->count() + 1 }}">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Brand</label>
                            <input type="text" name="brand" class="form-control form-control-sm">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Model</label>
                            <input type="text" name="model" class="form-control form-control-sm">
                        </div>
                    </div>
                    @php
                        $isVehicle = in_array($fixedAsset->category, ['Vehicle', 'Heavy Machinery']);
                        $isComputer = in_array($fixedAsset->category, ['Computer & IT', 'Electronics']);
                    @endphp

                    @if($isVehicle)
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Plate Number</label>
                            <input type="text" name="plate_number" class="form-control form-control-sm" placeholder="e.g. AA-99999">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Chassis / VIN</label>
                            <input type="text" name="chassis_number" class="form-control form-control-sm font-monospace" placeholder="VIN">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Engine Number</label>
                            <input type="text" name="engine_number" class="form-control form-control-sm font-monospace" placeholder="Engine No.">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Year</label>
                            <input type="number" name="year" class="form-control form-control-sm" min="1950" max="2099" placeholder="e.g. 2024">
                        </div>
                    </div>
                    @elseif($isComputer)
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Processor / CPU</label>
                            <input type="text" name="cpu" class="form-control form-control-sm" placeholder="e.g. Core i7-12700">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">RAM</label>
                            <input type="text" name="ram" class="form-control form-control-sm" placeholder="e.g. 16GB DDR5">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Storage</label>
                            <input type="text" name="storage" class="form-control form-control-sm" placeholder="e.g. 512GB SSD">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Operating System</label>
                            <input type="text" name="os" class="form-control form-control-sm" placeholder="e.g. Windows 11">
                        </div>
                    </div>
                    @else
                    <div class="row g-2 mb-3">
                        <div class="col-12">
                            <label class="form-label small fw-bold">Serial / Identification Number</label>
                            <input type="text" name="serial_number" class="form-control form-control-sm" placeholder="SN-XXXXX">
                        </div>
                    </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Condition <span class="text-danger">*</span></label>
                        <select name="condition" class="form-select form-select-sm" required>
                            <option value="good" selected>Good Condition</option>
                            <option value="new">Brand New</option>
                            <option value="fair">Fair</option>
                            <option value="needs_repair">Needs Repair</option>
                            <option value="damaged">Damaged</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-bold">Specifications / Notes</label>
                        <textarea name="specifications" class="form-control form-control-sm" rows="2" placeholder="Additional specifications..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light p-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success fw-bold">Create Unit</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- Edit Parent Asset Modal --}}
<div class="modal fade" id="editAssetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('store-manager.fixed-assets.update', $fixedAsset->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header bg-primary text-white py-3">
                    <h6 class="modal-title fw-bold">
                        <i class="fa-solid fa-pen-to-square me-2"></i>Edit Fixed Asset & Adjust Quantity
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Asset Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm" value="{{ $fixedAsset->name }}" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Category <span class="text-danger">*</span></label>
                            <input type="text" name="category" class="form-control form-control-sm" value="{{ $fixedAsset->category }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Code Prefix <span class="text-danger">*</span></label>
                            <input type="text" name="code_prefix" class="form-control form-control-sm font-monospace text-uppercase" value="{{ $fixedAsset->code_prefix }}" required>
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
                        <input type="number" name="total_quantity" class="form-control form-control-sm fw-bold" value="{{ $fixedAsset->total_quantity }}" min="1" max="1000" required>
                        <small class="text-muted mt-1" style="font-size: 0.75rem;">
                            Current units in database: <strong>{{ $fixedAsset->units->count() }}</strong>.
                        </small>

                        @if($fixedAsset->units->count() > 1)
                        <div class="mt-2 border-top pt-2">
                            <label class="form-label small fw-semibold text-danger mb-1">If decreasing quantity, select unassigned unit(s) to remove:</label>
                            <div class="d-flex flex-wrap gap-1" style="max-height: 100px; overflow-y: auto;">
                                @foreach($fixedAsset->units->where('status', '!=', 'assigned') as $u)
                                    <div class="form-check form-check-inline small mb-0">
                                        <input class="form-check-input" type="checkbox" name="remove_unit_ids[]" value="{{ $u->id }}" id="chk_rem_{{ $u->id }}">
                                        <label class="form-check-label font-monospace" for="chk_rem_{{ $u->id }}">{{ $u->unit_code }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Unit Cost (Br)</label>
                            <input type="number" step="0.01" name="unit_cost" class="form-control form-control-sm" value="{{ $fixedAsset->unit_cost }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Store Location</label>
                            <select name="store_id" class="form-select form-select-sm">
                                <option value="">Main Store</option>
                                @foreach($stores as $st)
                                    <option value="{{ $st->id }}" @selected($fixedAsset->store_id == $st->id)>{{ $st->name }}</option>
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

{{-- ============================================================
     SHARED: Edit Unit Modal — Category-Aware with Sticky Footer
     ============================================================ --}}
<div class="modal fade" id="sharedEditUnitModal" tabindex="-1" aria-labelledby="sharedEditUnitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" style="max-height:95vh;">
        <div class="modal-content border-0 shadow-lg d-flex flex-column" style="max-height:90vh;">
            <form id="sharedEditUnitForm" method="POST" action="#" class="d-flex flex-column h-100">
                @csrf
                @method('PUT')

                {{-- ── HEADER ── --}}
                <div class="modal-header py-3 flex-shrink-0" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%);">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                             style="width:38px;height:38px;background:rgba(251,191,36,0.2);">
                            <i class="fa-solid fa-sliders text-warning"></i>
                        </div>
                        <div>
                            <h6 class="modal-title fw-bold text-white mb-0" id="sharedEditUnitModalLabel">Edit Unit</h6>
                            <div class="text-white-50 small" id="sharedEditUnitSubtitle">Loading…</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                {{-- ── CATEGORY BADGE + TABS ── --}}
                <div class="px-4 pt-3 pb-0 flex-shrink-0 bg-white border-bottom">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge rounded-pill px-3 py-2 fw-semibold" id="eu_cat_badge"
                              style="font-size:0.8rem;"></span>
                        <span class="badge bg-light text-dark border px-3 py-2 shadow-sm" style="font-size:0.8rem;">
                            <i class="fa-solid fa-warehouse text-primary me-1"></i>Available In Store: <strong id="eu_store_location_display" class="text-primary"></strong>
                        </span>
                    </div>
                    <ul class="nav nav-tabs border-0" id="editUnitTabs">
                        <li class="nav-item">
                            <button type="button" class="nav-link active fw-semibold px-3" data-eu-tab="identity">
                                <i class="fa-solid fa-id-card me-1"></i> Identity
                            </button>
                        </li>
                        <li class="nav-item" id="eu_tab_vehicle_li">
                            <button type="button" class="nav-link fw-semibold px-3" data-eu-tab="vehicle">
                                <i class="fa-solid fa-truck me-1"></i> Vehicle Info
                            </button>
                        </li>
                        <li class="nav-item" id="eu_tab_computer_li">
                            <button type="button" class="nav-link fw-semibold px-3" data-eu-tab="computer">
                                <i class="fa-solid fa-computer me-1"></i> Computer / IT
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link fw-semibold px-3" data-eu-tab="status">
                                <i class="fa-solid fa-circle-dot me-1"></i> Status
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link fw-semibold px-3" data-eu-tab="finance">
                                <i class="fa-solid fa-coins me-1"></i> Finance
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link fw-semibold px-3" data-eu-tab="notes">
                                <i class="fa-solid fa-note-sticky me-1"></i> Notes
                            </button>
                        </li>
                    </ul>
                </div>

                {{-- ── SCROLLABLE BODY ── --}}
                <div class="modal-body p-4 overflow-auto flex-grow-1">

                    {{-- TAB: Identity (all categories) --}}
                    <div class="eu-tab-pane" data-eu-pane="identity">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">
                                    Unit Code <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="eu_unit_code" name="unit_code"
                                       class="form-control font-monospace fw-bold fs-5" required
                                       style="letter-spacing:2px;">
                                <div class="form-text">This is the unique identifier for this specific unit.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">
                                    <i class="fa-solid fa-warehouse text-primary me-1"></i>Available in Store / Physical Location
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fa-solid fa-location-dot text-primary"></i></span>
                                    <input type="text" id="eu_location" name="current_location" class="form-control" list="storesDatalist"
                                           placeholder="e.g. Head Office, Main Store, Workshop, Site A"
                                           oninput="document.getElementById('eu_store_location_display').textContent = this.value || 'Main Store'; if(document.getElementById('eu_location_status')) document.getElementById('eu_location_status').value = this.value;">
                                    <datalist id="storesDatalist">
                                        @foreach($stores as $st)
                                            <option value="{{ $st->name }}">{{ $st->name }} (Active Store)</option>
                                        @endforeach
                                        <option value="Head Office">Head Office</option>
                                        <option value="Main Store">Main Store</option>
                                        <option value="Workshop">Workshop</option>
                                        <option value="Project Site">Project Site</option>
                                    </datalist>
                                </div>
                                <div class="form-text">The store branch or site where this unit code is physically stored and available.</div>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Brand / Manufacturer</label>
                                <input type="text" id="eu_brand" name="brand" class="form-control"
                                       placeholder="e.g. Hyundai, Dell, Caterpillar">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Model</label>
                                <input type="text" id="eu_model" name="model" class="form-control"
                                       placeholder="e.g. R320LC, Inspiron 15, 2024">
                            </div>
                            <div class="col-4">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Year</label>
                                <input type="number" id="eu_year" name="year" class="form-control"
                                       min="1950" max="2099" placeholder="e.g. 2024">
                            </div>
                            <div class="col-4">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Serial Number</label>
                                <input type="text" id="eu_serial" name="serial_number"
                                       class="form-control font-monospace" placeholder="SN-XXXXX">
                            </div>
                            <div class="col-4">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Warranty Expiry</label>
                                <input type="date" id="eu_warranty" name="warranty_expiry" class="form-control">
                            </div>
                        </div>
                    </div>

                    {{-- TAB: Vehicle / Heavy Machinery --}}
                    <div class="eu-tab-pane d-none" data-eu-pane="vehicle">
                        <div class="alert alert-info border-0 py-2 small mb-3">
                            <i class="fa-solid fa-circle-info me-1"></i>
                            Fields specific to <strong>vehicles and heavy machinery</strong>.
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Plate Number</label>
                                <input type="text" id="eu_plate" name="plate_number" class="form-control"
                                       placeholder="e.g. AA-99999">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Chassis / VIN Number</label>
                                <input type="text" id="eu_chassis" name="chassis_number"
                                       class="form-control font-monospace" placeholder="17-character VIN">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Engine Number</label>
                                <input type="text" id="eu_engine" name="engine_number"
                                       class="form-control font-monospace" placeholder="Engine No.">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Technical Specifications</label>
                                <textarea id="eu_specifications_vehicle" class="form-control" rows="4"
                                          placeholder="e.g. Engine: 4-cylinder diesel, 320HP, Operating Weight: 33t, Max Dig Depth: 7m…"></textarea>
                                <div class="form-text">This syncs with the main Specifications field.</div>
                            </div>
                        </div>
                    </div>

                    {{-- TAB: Computer / IT / Electronics --}}
                    <div class="eu-tab-pane d-none" data-eu-pane="computer">
                        <div class="alert alert-primary border-0 py-2 small mb-3">
                            <i class="fa-solid fa-circle-info me-1"></i>
                            Fields specific to <strong>computers, IT equipment, and electronics</strong>.
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Processor / CPU</label>
                                <input type="text" id="eu_cpu" class="form-control eu-spec-part"
                                       data-spec-key="CPU" placeholder="e.g. Intel Core i7-12700">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">RAM</label>
                                <input type="text" id="eu_ram" class="form-control eu-spec-part"
                                       data-spec-key="RAM" placeholder="e.g. 16GB DDR5">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Storage</label>
                                <input type="text" id="eu_storage" class="form-control eu-spec-part"
                                       data-spec-key="Storage" placeholder="e.g. 512GB SSD">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Display / Screen</label>
                                <input type="text" id="eu_display" class="form-control eu-spec-part"
                                       data-spec-key="Display" placeholder="e.g. 15.6 inch FHD">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Operating System</label>
                                <input type="text" id="eu_os" class="form-control eu-spec-part"
                                       data-spec-key="OS" placeholder="e.g. Windows 11 Pro">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">GPU / Graphics</label>
                                <input type="text" id="eu_gpu" class="form-control eu-spec-part"
                                       data-spec-key="GPU" placeholder="e.g. NVIDIA RTX 3060">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Other Specs / Additional</label>
                                <textarea id="eu_specifications_computer" class="form-control" rows="3"
                                          placeholder="Any other technical details…"></textarea>
                                <div class="form-text">All fields above will be combined into the Specifications field automatically.</div>
                            </div>
                        </div>
                    </div>

                    {{-- TAB: Status & Condition (all categories) --}}
                    <div class="eu-tab-pane d-none" data-eu-pane="status">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">
                                    Condition <span class="text-danger">*</span>
                                </label>
                                <select id="eu_condition" name="condition" class="form-select" required>
                                    <option value="new">🆕 Brand New</option>
                                    <option value="good">✅ Good Condition</option>
                                    <option value="fair">🟡 Fair</option>
                                    <option value="needs_repair">🔧 Needs Repair</option>
                                    <option value="damaged">❌ Damaged</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">
                                    Status <span class="text-danger">*</span>
                                </label>
                                <select id="eu_status" name="status" class="form-select" required>
                                    <option value="in_store">🏭 In Store (Available)</option>
                                    <option value="assigned">👤 Assigned to Staff</option>
                                    <option value="maintenance">🔧 Under Maintenance</option>
                                    <option value="disposed">🗑️ Disposed / Retired</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">
                                    <i class="fa-solid fa-warehouse text-primary me-1"></i>Current Location / Store
                                </label>
                                <input type="text" id="eu_location_status" class="form-control" list="storesDatalist"
                                       placeholder="e.g. Main Store, Head Office, IT Room"
                                       oninput="document.getElementById('eu_location').value = this.value; document.getElementById('eu_store_location_display').textContent = this.value || 'Main Store';">
                            </div>
                        </div>
                    </div>

                    {{-- TAB: Finance (all categories) --}}
                    <div class="eu-tab-pane d-none" data-eu-pane="finance">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Purchase Price (Br)</label>
                                <div class="input-group">
                                    <span class="input-group-text fw-bold">Br</span>
                                    <input type="number" step="0.01" id="eu_purchase_price" name="purchase_price"
                                           class="form-control" placeholder="0.00" min="0">
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Warranty Expiry Date</label>
                                <input type="date" id="eu_warranty2" class="form-control">
                                <div class="form-text">Same as the warranty field in Identity tab.</div>
                            </div>
                        </div>
                    </div>

                    {{-- TAB: Notes (all categories) --}}
                    <div class="eu-tab-pane d-none" data-eu-pane="notes">
                        <div class="row g-3">
                            {{-- Hidden real specs field that gets submitted --}}
                            <input type="hidden" id="eu_specifications" name="specifications">
                            <div class="col-12">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Full Specifications</label>
                                <textarea id="eu_specifications_raw" class="form-control" rows="4"
                                          placeholder="Free-text specifications summary…"></textarea>
                                <div class="form-text">This is the combined specifications that will be saved.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold small text-uppercase text-muted mb-1">Internal Notes</label>
                                <textarea id="eu_notes" name="notes" class="form-control" rows="3"
                                          placeholder="Maintenance history, damage reports, any extra context…"></textarea>
                            </div>
                        </div>
                    </div>

                </div>{{-- /modal-body --}}

                {{-- ── STICKY FOOTER (always visible) ── --}}
                <div class="modal-footer bg-white border-top px-4 py-3 flex-shrink-0 d-flex justify-content-between align-items-center">
                    <div class="text-muted small" id="eu_footer_category"></div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                            <i class="fa-solid fa-xmark me-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary fw-bold px-5" id="eu_save_btn">
                            <i class="fa-solid fa-floppy-disk me-1"></i>Save Changes
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<style>
/* Ensure modal tabs pane switching works */
.eu-tab-pane { animation: fadeInPane 0.18s ease; }
@keyframes fadeInPane { from { opacity:0; transform:translateY(4px); } to { opacity:1; transform:none; } }
#editUnitTabs .nav-link { border-radius: 6px 6px 0 0; color:#64748b; border:none; border-bottom:2px solid transparent; }
#editUnitTabs .nav-link.active { color:#0d6efd; border-bottom:2px solid #0d6efd; background:#f0f6ff; }
</style>
<script>
(function () {
    'use strict';

    // ── Category config ──────────────────────────────────────────────
    const CAT_CONFIG = {
        'Vehicle':         { color:'#f59e0b', bg:'#fffbeb', icon:'fa-truck',         tabs:['identity','vehicle','status','finance','notes'] },
        'Heavy Machinery': { color:'#dc2626', bg:'#fef2f2', icon:'fa-tractor',        tabs:['identity','vehicle','status','finance','notes'] },
        'Computer & IT':   { color:'#2563eb', bg:'#eff6ff', icon:'fa-computer',       tabs:['identity','computer','status','finance','notes'] },
        'Electronics':     { color:'#7c3aed', bg:'#f5f3ff', icon:'fa-microchip',      tabs:['identity','computer','status','finance','notes'] },
        'Furniture':       { color:'#059669', bg:'#f0fdf4', icon:'fa-couch',          tabs:['identity','status','finance','notes'] },
        'Tools & Equipment':{ color:'#0891b2', bg:'#ecfeff', icon:'fa-screwdriver-wrench', tabs:['identity','status','finance','notes'] },
    };
    const DEFAULT_CAT = { color:'#64748b', bg:'#f8fafc', icon:'fa-box', tabs:['identity','status','finance','notes'] };

    // ── Tab switching ────────────────────────────────────────────────
    let activeTab = 'identity';

    function showTab(name) {
        activeTab = name;
        document.querySelectorAll('.eu-tab-pane').forEach(p => p.classList.add('d-none'));
        const pane = document.querySelector('[data-eu-pane="' + name + '"]');
        if (pane) pane.classList.remove('d-none');
        document.querySelectorAll('#editUnitTabs .nav-link').forEach(b => {
            b.classList.toggle('active', b.dataset.euTab === name);
        });
    }

    document.addEventListener('click', function(e) {
        // Tab nav clicks
        const tabBtn = e.target.closest('[data-eu-tab]');
        if (tabBtn && tabBtn.closest('#editUnitTabs')) {
            showTab(tabBtn.dataset.euTab);
            return;
        }

        // Edit unit button click
        const btn = e.target.closest('.btn-edit-unit');
        if (!btn) return;
        openEditModal(btn.dataset);
    });

    function openEditModal(d) {
        const category = d.category || 'Other';
        const cfg = CAT_CONFIG[category] || DEFAULT_CAT;

        // ── Header ──
        document.getElementById('sharedEditUnitForm').action = d.updateUrl;
        document.getElementById('sharedEditUnitModalLabel').textContent = 'Edit Unit: ' + d.unitCode;
        document.getElementById('sharedEditUnitSubtitle').textContent   = 'Unit ID #' + d.unitId;

        // ── Store Location ──
        const storeLoc = d.location || d.defaultStore || 'Main Store';
        const storeDisplay = document.getElementById('eu_store_location_display');
        if (storeDisplay) storeDisplay.textContent = storeLoc;

        // ── Category badge ──
        const badge = document.getElementById('eu_cat_badge');
        badge.innerHTML = '<i class="fa-solid ' + cfg.icon + ' me-2"></i>' + category;
        badge.style.background = cfg.bg;
        badge.style.color      = cfg.color;
        badge.style.border     = '1px solid ' + cfg.color + '44';

        document.getElementById('eu_footer_category').innerHTML =
            '<i class="fa-solid ' + cfg.icon + ' me-1"></i> <strong>' + category + '</strong> Unit';

        // ── Show/hide category-specific tabs ──
        const isVehicle  = ['Vehicle','Heavy Machinery'].includes(category);
        const isComputer = ['Computer & IT','Electronics'].includes(category);
        document.getElementById('eu_tab_vehicle_li').style.display  = isVehicle  ? '' : 'none';
        document.getElementById('eu_tab_computer_li').style.display = isComputer ? '' : 'none';

        // ── Populate Identity fields ──
        document.getElementById('eu_unit_code').value = d.unitCode || '';
        document.getElementById('eu_location').value  = d.location || storeLoc;
        document.getElementById('eu_brand').value     = d.brand    || '';
        document.getElementById('eu_model').value     = d.model    || '';
        document.getElementById('eu_year').value      = d.year     || '';
        document.getElementById('eu_serial').value    = d.serial   || '';
        document.getElementById('eu_warranty').value  = d.warrantyExpiry || '';

        // ── Populate Vehicle fields ──
        document.getElementById('eu_plate').value   = d.plate   || '';
        document.getElementById('eu_chassis').value = d.chassis || '';
        document.getElementById('eu_engine').value  = d.engine  || '';

        // ── Populate Status fields ──
        setSelect('eu_condition', d.condition || 'good');
        setSelect('eu_status',    d.status    || 'in_store');
        if (document.getElementById('eu_location_status')) {
            document.getElementById('eu_location_status').value = d.location || storeLoc;
        }

        // ── Populate Finance ──
        document.getElementById('eu_purchase_price').value = d.purchasePrice || '';
        document.getElementById('eu_warranty2').value      = d.warrantyExpiry || '';

        // ── Populate Notes / Specs ──
        const specs = d.specifications || '';
        document.getElementById('eu_specifications_raw').value = specs;
        document.getElementById('eu_specifications').value     = specs;
        document.getElementById('eu_notes').value = d.notes || '';

        // For computer category, try to parse spec parts
        if (isComputer) {
            parseSpecParts(specs);
            document.getElementById('eu_specifications_computer').value = specs;
        }
        if (isVehicle) {
            document.getElementById('eu_specifications_vehicle').value = specs;
        }

        // ── Show first relevant tab ──
        showTab('identity');

        bootstrap.Modal.getOrCreateInstance(document.getElementById('sharedEditUnitModal')).show();
    }

    // ── Before form submit: collect all specs into hidden field ──────
    document.getElementById('sharedEditUnitForm').addEventListener('submit', function() {
        // Sync warranty from finance tab if changed there
        const w2 = document.getElementById('eu_warranty2').value;
        if (w2) document.getElementById('eu_warranty').value = w2;

        // Build specs from computer parts if computer category
        const specParts = document.querySelectorAll('.eu-spec-part');
        if (specParts.length > 0) {
            let lines = [];
            specParts.forEach(function(inp) {
                if (inp.value.trim()) lines.push(inp.dataset.specKey + ': ' + inp.value.trim());
            });
            const extra = document.getElementById('eu_specifications_computer');
            if (extra && extra.value.trim()) lines.push(extra.value.trim());
            if (lines.length > 0) {
                document.getElementById('eu_specifications').value = lines.join(', ');
            }
        } else {
            // For vehicle / general: use the raw field
            const raw = document.getElementById('eu_specifications_raw').value;
            const veh = document.getElementById('eu_specifications_vehicle');
            if (veh && veh.value.trim()) {
                document.getElementById('eu_specifications').value = veh.value.trim();
            } else if (raw.trim()) {
                document.getElementById('eu_specifications').value = raw.trim();
            }
        }
    });

    // Sync raw specs on input
    ['eu_specifications_raw','eu_specifications_vehicle','eu_specifications_computer'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', function() {
            document.getElementById('eu_specifications').value = this.value;
        });
    });
    // Sync warranty2 → warranty
    document.getElementById('eu_warranty2').addEventListener('change', function() {
        document.getElementById('eu_warranty').value = this.value;
    });

    function setSelect(id, value) {
        const sel = document.getElementById(id);
        if (!sel) return;
        for (let i = 0; i < sel.options.length; i++)
            sel.options[i].selected = (sel.options[i].value === value);
    }

    function parseSpecParts(specs) {
        // Try to parse "Key: Value, Key2: Value2" format
        const map = {};
        if (specs) {
            specs.split(',').forEach(function(part) {
                const idx = part.indexOf(':');
                if (idx > -1) {
                    const key = part.slice(0, idx).trim();
                    const val = part.slice(idx + 1).trim();
                    map[key] = val;
                }
            });
        }
        document.querySelectorAll('.eu-spec-part').forEach(function(inp) {
            inp.value = map[inp.dataset.specKey] || '';
        });
    }

})();
</script>
@endpush

