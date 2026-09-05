@extends('layouts.app')

@php
    $productsJson  = $products->map(fn($p)  => ['id'=>$p->id,'name'=>$p->name,'unit'=>$p->unit])->values();
    $equipmentJson = $equipmentList->map(fn($e) => ['id'=>$e->id,'name'=>$e->name,'unit'=>$e->unit])->values();
@endphp

@section('title', 'Edit Standard Work – ' . $standardWork->name)

@push('styles')
<style>
.resource-card { border-left: 4px solid transparent; }
.resource-card.material          { border-left-color: #22c55e; }
.resource-card.manpower          { border-left-color: #3b82f6; }
.resource-card.sci-manpower      { border-left-color: #8b5cf6; }
.resource-card.equipment         { border-left-color: #f59e0b; }

.section-header-icon {
    width: 36px; height: 36px;
    border-radius: 8px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 16px; flex-shrink: 0;
}

.resource-table thead th {
    font-size: 11px; font-weight: 700; letter-spacing: .5px;
    text-transform: uppercase; color: #6b7280;
    background: #f9fafb; padding: 10px 12px;
}
.resource-table td { padding: 8px 12px; vertical-align: middle; }
.resource-table tbody tr:hover { background: #fafafa; }

.qty-input { font-variant-numeric: tabular-nums; }
.row-remove-btn { opacity: .4; transition: opacity .15s; }
.row-remove-btn:hover { opacity: 1; }
</style>
@endpush

@section('content')
<div class="d-flex align-items-center mb-4">
    <a href="{{ route('standard-works.show', $standardWork) }}" class="btn btn-sm btn-outline-secondary me-3">
        <i class="fa-solid fa-arrow-left"></i>
    </a>
    <div>
        <h1 class="page-title mb-1">
            <i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Edit Standard Work
        </h1>
        <p class="text-muted small mb-0">Update work details and resource conversion rates for <strong>{{ $standardWork->name }}</strong>.</p>
    </div>
</div>

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show mb-4">
    <i class="fa-solid fa-circle-exclamation me-2"></i>
    <strong>Please fix the following errors:</strong>
    <ul class="mb-0 mt-1">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<form method="POST" action="{{ route('standard-works.update', $standardWork) }}" id="standardWorkForm">
    @csrf
    @method('PUT')

    {{-- ── Work Information Card ── --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent py-3">
            <h5 class="mb-0"><i class="fa-solid fa-info-circle me-2 text-primary"></i>Work Information</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">

                {{-- Work Name --}}
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Work Name <span class="text-danger">*</span></label>
                    <input type="text" name="name"
                           class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $standardWork->name) }}"
                           placeholder="e.g. Plain Concrete Grade C-20" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Unit --}}
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Unit of Measure <span class="text-danger">*</span></label>
                    <select name="unit" id="workUnitInput" class="form-select @error('unit') is-invalid @enderror" required>
                        <option value="">— Select Unit —</option>
                        @php $u = old('unit', $standardWork->unit); @endphp
                        <option value="m³"      @selected($u == 'm³')>m³ (Cubic Meter)</option>
                        <option value="m²"      @selected($u == 'm²')>m² (Square Meter)</option>
                        <option value="lm"      @selected($u == 'lm')>lm (Linear Meter)</option>
                        <option value="kg"      @selected($u == 'kg')>kg (Kilogram)</option>
                        <option value="ton"     @selected($u == 'ton')>ton</option>
                        <option value="pcs"     @selected($u == 'pcs')>pcs (Pieces)</option>
                        <option value="lump sum" @selected($u == 'lump sum')>lump sum</option>
                        <option value="hr"      @selected($u == 'hr')>hr (Hour)</option>
                        <option value="day"     @selected($u == 'day')>day (Day)</option>
                        <option value="lit"     @selected($u == 'lit')>lit (Liter)</option>
                    </select>
                    @error('unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Description --}}
                <div class="col-md-9">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="description" rows="2" class="form-control"
                              placeholder="Optional notes about this standard work…">{{ old('description', $standardWork->description) }}</textarea>
                </div>

            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════
         MATERIAL SECTION
    ══════════════════════════════════════════ --}}
    <div class="card border-0 shadow-sm mb-4 resource-card material">
        <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <span class="section-header-icon bg-success bg-opacity-10 text-success">
                    <i class="fa-solid fa-cubes"></i>
                </span>
                <div>
                    <h5 class="mb-0">Material Lines
                        <span class="badge bg-success bg-opacity-10 text-success ms-2 small" id="mat-count">1 row</span>
                    </h5>
                    <p class="text-muted small mb-0">Leave quantity as <strong>0</strong> if not used</p>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-success" onclick="addRow('materials')">
                <i class="fa-solid fa-plus me-1"></i>Add Material
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 resource-table">
                    <thead>
                        <tr>
                            <th>Material Name</th>
                            <th style="width:180px">Quantity <span class="unit-per-label text-muted fw-normal" style="font-size:11px"></span></th>
                            <th style="width:130px">Unit</th>
                            <th style="width:50px"></th>
                        </tr>
                    </thead>
                    <tbody id="materials-body">
                        @php
                            $mats = old('materials', $standardWork->materials->toArray());
                        @endphp
                        @forelse($mats as $i => $mat)
                        <tr>
                            <td>
                                <select name="materials[{{ $i }}][material_name]"
                                        class="form-select form-select-sm mat-select"
                                        onchange="fillUnit(this,'materials')" data-idx="{{ $i }}">
                                    <option value="">— Select Material —</option>
                                    @foreach($products as $p)
                                    <option value="{{ $p->name }}" data-unit="{{ $p->unit }}"
                                        @selected(($mat['material_name'] ?? '') === $p->name)>
                                        {{ $p->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="number" step="0.001" min="0" name="materials[{{ $i }}][quantity]"
                                       class="form-control form-control-sm qty-input"
                                       value="{{ $mat['quantity'] ?? 0 }}" placeholder="0.000"></td>
                            <td><input type="text" name="materials[{{ $i }}][unit]"
                                       class="form-control form-control-sm mat-unit-{{ $i }}"
                                       value="{{ $mat['unit'] ?? '' }}" placeholder="auto-filled"></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger row-remove-btn"
                                        onclick="removeRow(this,'mat-count')">
                                <i class="fa-solid fa-times"></i></button></td>
                        </tr>
                        @empty
                        <tr>
                            <td>
                                <select name="materials[0][material_name]"
                                        class="form-select form-select-sm mat-select"
                                        onchange="fillUnit(this,'materials')" data-idx="0">
                                    <option value="">— Select Material —</option>
                                    @foreach($products as $p)
                                    <option value="{{ $p->name }}" data-unit="{{ $p->unit }}">{{ $p->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="number" step="0.001" min="0" name="materials[0][quantity]"
                                       class="form-control form-control-sm qty-input" value="0" placeholder="0.000"></td>
                            <td><input type="text" name="materials[0][unit]"
                                       class="form-control form-control-sm mat-unit-0"
                                       placeholder="auto-filled" readonly></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger row-remove-btn"
                                        onclick="removeRow(this,'mat-count')">
                                <i class="fa-solid fa-times"></i></button></td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════
         MANPOWER + SCIENTIFIC MANPOWER (MERGED)
    ══════════════════════════════════════════ --}}
    <div class="card border-0 shadow-sm mb-4" style="border-left:4px solid #3b82f6;">

        {{-- ── Card Header ── --}}
        <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <span class="section-header-icon bg-primary bg-opacity-10 text-primary">
                    <i class="fa-solid fa-users-gear"></i>
                </span>
                <div>
                    <h5 class="mb-0 text-primary">Manpower &amp; Productivity</h5>
                    <p class="text-muted small mb-0">Define crew breakdown, scientific staff, and crew output rates</p>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openManageRoles()">
                <i class="fa-solid fa-gears me-1"></i>Manage Roles
            </button>
        </div>

        {{-- ── Productivity Row ── --}}
        <div class="card-body border-bottom pb-3">
            <div class="row g-3 align-items-end">
                <div class="col-12">
                    <p class="fw-semibold mb-1 text-muted small" style="letter-spacing:.4px;text-transform:uppercase;">
                        <i class="fa-solid fa-gauge-high me-1 text-primary"></i>Manpower &amp; Equipment Productivity Output Rates
                        <span class="text-danger">*</span>
                    </p>
                </div>
                {{-- Min Rate --}}
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1">
                        Min Output Rate <span class="text-danger">*</span>
                        <span class="text-muted prod-unit-label">({{ old('unit', $standardWork->unit) }}/day)</span>
                    </label>
                    <input type="number" step="0.001" min="0" name="min_productivity"
                           id="minProductivity"
                           class="form-control @error('min_productivity') is-invalid @enderror"
                           value="{{ old('min_productivity', $standardWork->min_productivity) }}"
                           placeholder="e.g. 1.5" required>
                    @error('min_productivity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                {{-- Max Rate --}}
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1">
                        Max Output Rate <span class="text-danger">*</span>
                        <span class="text-muted prod-unit-label">({{ old('unit', $standardWork->unit) }}/day)</span>
                    </label>
                    <input type="number" step="0.001" min="0" name="max_productivity"
                           id="maxProductivity"
                           class="form-control @error('max_productivity') is-invalid @enderror"
                           value="{{ old('max_productivity', $standardWork->max_productivity) }}"
                           placeholder="e.g. 3.0" required>
                    @error('max_productivity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                {{-- Visible calculated default productivity --}}
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1 text-primary">
                        Default Output Rate <small class="text-muted fw-normal">(Auto Average)</small>
                        <span class="text-muted prod-unit-label">({{ old('unit', $standardWork->unit) }}/day)</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-primary bg-opacity-10 text-primary border-primary border-opacity-25">
                            <i class="fa-solid fa-calculator"></i>
                        </span>
                        <input type="number" step="0.001" name="default_productivity" id="defaultProductivity"
                               class="form-control bg-light fw-bold text-primary border-primary border-opacity-25"
                               value="{{ old('default_productivity', $standardWork->default_productivity) }}"
                               placeholder="Auto-calculated default" readonly>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Labour Tab (no Scientific) ── --}}
        <div class="card-body p-0">
            <div class="d-flex justify-content-between align-items-center px-3 pt-3 pb-2 border-bottom">
                <span class="fw-semibold text-primary small">
                    <i class="fa-solid fa-person-digging me-1"></i>Labour
                    <span class="badge bg-primary bg-opacity-10 text-primary ms-1" id="mp-count">1 row</span>
                </span>
                <button type="button" class="btn btn-sm btn-outline-primary"
                        onclick="addRow('manpower')">
                    <i class="fa-solid fa-plus me-1"></i>Add Role
                </button>
            </div>

            <div>
                {{-- ── Labour Rows ── --}}
                <div id="labour-panel">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 resource-table">
                            <thead>
                                <tr>
                                    <th>Role / Trade</th>
                                    <th style="width:160px">Qty <span class="unit-per-label text-muted fw-normal" style="font-size:11px"></span></th>
                                    <th style="width:110px">Unit</th>
                                    <th style="width:44px"></th>
                                </tr>
                            </thead>
                            <tbody id="manpower-body">
                                @php
                                    $mps = old('manpower', $standardWork->manpower->toArray());
                                @endphp
                                @forelse($mps as $i => $mp)
                                <tr>
                                    <td>
                                        <select name="manpower[{{ $i }}][role]" class="form-select form-select-sm mp-role-select">
                                            <option value="">— Select Role —</option>
                                            @foreach($manpowerRoles as $mpRole)
                                                <option value="{{ $mpRole->name }}" data-unit="{{ $mpRole->default_unit }}"
                                                    {{ ($mp['role'] ?? '') == $mpRole->name ? 'selected' : '' }}>
                                                    {{ $mpRole->name }}
                                                </option>
                                            @endforeach
                                            <option value="__custom__">+ Type custom role...</option>
                                        </select>
                                        <input type="text" name="manpower[{{ $i }}][role]"
                                               class="form-control form-control-sm mt-1 mp-custom-input d-none"
                                               value="{{ $mp['role'] ?? '' }}" placeholder="Enter role name">
                                    </td>
                                    <td><input type="number" step="0.001" min="0" name="manpower[{{ $i }}][quantity]"
                                               class="form-control form-control-sm qty-input"
                                               value="{{ $mp['quantity'] ?? 0 }}" placeholder="0.000"></td>
                                    <td>
                                        <select name="manpower[{{ $i }}][unit]" class="form-select form-select-sm mp-unit-select">
                                            <option value="">— Unit —</option>
                                            <option value="day"  @selected(($mp['unit'] ?? '') == 'day')>day</option>
                                            <option value="hr"   @selected(($mp['unit'] ?? '') == 'hr')>hr</option>
                                            <option value="pcs"  @selected(($mp['unit'] ?? '') == 'pcs')>pcs</option>
                                        </select>
                                    </td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger row-remove-btn"
                                                onclick="removeRow(this,'mp-count')">
                                        <i class="fa-solid fa-times"></i></button></td>
                                </tr>
                                @empty
                                <tr>
                                    <td>
                                        <select name="manpower[0][role]" class="form-select form-select-sm mp-role-select">
                                            <option value="">— Select Role —</option>
                                            @foreach($manpowerRoles as $mpRole)
                                                <option value="{{ $mpRole->name }}" data-unit="{{ $mpRole->default_unit }}">
                                                    {{ $mpRole->name }}
                                                </option>
                                            @endforeach
                                            <option value="__custom__">+ Type custom role...</option>
                                        </select>
                                        <input type="text" name="manpower[0][role]"
                                               class="form-control form-control-sm mt-1 mp-custom-input d-none"
                                               placeholder="Enter role name">
                                    </td>
                                    <td><input type="number" step="0.001" min="0" name="manpower[0][quantity]"
                                               class="form-control form-control-sm qty-input" value="0" placeholder="0.000"></td>
                                    <td>
                                        <select name="manpower[0][unit]" class="form-select form-select-sm mp-unit-select">
                                            <option value="">— Unit —</option>
                                            <option value="day">day</option>
                                            <option value="hr">hr</option>
                                            <option value="pcs">pcs</option>
                                        </select>
                                    </td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger row-remove-btn"
                                                onclick="removeRow(this,'mp-count')">
                                        <i class="fa-solid fa-times"></i></button></td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════
         EQUIPMENT SECTION
    ══════════════════════════════════════════ --}}
    <div class="card border-0 shadow-sm mb-4 resource-card equipment">
        <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <span class="section-header-icon bg-warning bg-opacity-10 text-warning">
                    <i class="fa-solid fa-tractor"></i>
                </span>
                <div>
                    <h5 class="mb-0">Equipment Lines
                        <span class="badge bg-warning bg-opacity-10 text-warning ms-2 small" id="eq-count">1 row</span>
                    </h5>
                    <p class="text-muted small mb-0">Leave quantity as <strong>0</strong> if not used</p>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-warning" onclick="addRow('equipment')">
                <i class="fa-solid fa-plus me-1"></i>Add Equipment
            </button>
        </div>
        {{-- ── Equipment Productivity Row ── --}}
        <div class="card-body border-bottom pb-3">
            <div class="row g-3 align-items-end">
                <div class="col-12">
                    <p class="fw-semibold mb-1 text-muted small" style="letter-spacing:.4px;text-transform:uppercase;">
                        <i class="fa-solid fa-gauge-high me-1 text-warning"></i>Equipment Productivity Output Rates
                        <span class="text-danger">*</span>
                    </p>
                </div>
                {{-- Min Rate --}}
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1">
                        Min Equipment Output Rate <span class="text-danger">*</span>
                        <span class="text-muted prod-unit-label">({{ old('unit', $standardWork->unit) }}/day)</span>
                    </label>
                    <input type="number" step="0.001" min="0" name="min_equipment_productivity"
                           id="minEquipmentProductivity"
                           class="form-control @error('min_equipment_productivity') is-invalid @enderror"
                           value="{{ old('min_equipment_productivity', $standardWork->min_equipment_productivity) }}"
                           placeholder="e.g. 1.5" required>
                    @error('min_equipment_productivity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                {{-- Max Rate --}}
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1">
                        Max Equipment Output Rate <span class="text-danger">*</span>
                        <span class="text-muted prod-unit-label">({{ old('unit', $standardWork->unit) }}/day)</span>
                    </label>
                    <input type="number" step="0.001" min="0" name="max_equipment_productivity"
                           id="maxEquipmentProductivity"
                           class="form-control @error('max_equipment_productivity') is-invalid @enderror"
                           value="{{ old('max_equipment_productivity', $standardWork->max_equipment_productivity) }}"
                           placeholder="e.g. 3.0" required>
                    @error('max_equipment_productivity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                {{-- Visible calculated default equipment productivity --}}
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1 text-dark">
                        Default Equipment Output Rate <small class="text-muted fw-normal">(Auto Average)</small>
                        <span class="text-muted prod-unit-label">({{ old('unit', $standardWork->unit) }}/day)</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-warning bg-opacity-10 text-warning border-warning border-opacity-25">
                            <i class="fa-solid fa-calculator"></i>
                        </span>
                        <input type="number" step="0.001" name="default_equipment_productivity" id="defaultEquipmentProductivity"
                               class="form-control bg-light fw-bold text-dark border-warning border-opacity-25"
                               value="{{ old('default_equipment_productivity', $standardWork->default_equipment_productivity) }}"
                               placeholder="Auto-calculated default" readonly>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 resource-table">
                    <thead>
                        <tr>
                            <th>Equipment Name</th>
                            <th style="width:180px">Quantity <span class="unit-per-label text-muted fw-normal" style="font-size:11px"></span></th>
                            <th style="width:130px">Unit</th>
                            <th style="width:50px"></th>
                        </tr>
                    </thead>
                    <tbody id="equipment-body">
                        @php
                            $eqs = old('equipment', $standardWork->equipment->toArray());
                        @endphp
                        @forelse($eqs as $i => $eq)
                        <tr>
                            <td>
                                <select name="equipment[{{ $i }}][equipment_name]"
                                        class="form-select form-select-sm eq-select"
                                        onchange="fillUnit(this,'equipment')" data-idx="{{ $i }}">
                                    <option value="">— Select Equipment —</option>
                                    @foreach($equipmentList as $e)
                                    <option value="{{ $e->name }}" data-unit="{{ $e->unit }}"
                                        @selected(($eq['equipment_name'] ?? '') === $e->name)>
                                        {{ $e->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="number" step="0.001" min="0" name="equipment[{{ $i }}][quantity]"
                                       class="form-control form-control-sm qty-input"
                                       value="{{ $eq['quantity'] ?? 0 }}" placeholder="0.000"></td>
                            <td><input type="text" name="equipment[{{ $i }}][unit]"
                                       class="form-control form-control-sm eq-unit-{{ $i }}"
                                       value="{{ $eq['unit'] ?? '' }}" placeholder="auto-filled"></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger row-remove-btn"
                                        onclick="removeRow(this,'eq-count')">
                                <i class="fa-solid fa-times"></i></button></td>
                        </tr>
                        @empty
                        <tr>
                            <td>
                                <select name="equipment[0][equipment_name]"
                                        class="form-select form-select-sm eq-select"
                                        onchange="fillUnit(this,'equipment')" data-idx="0">
                                    <option value="">— Select Equipment —</option>
                                    @foreach($equipmentList as $e)
                                    <option value="{{ $e->name }}" data-unit="{{ $e->unit }}">{{ $e->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="number" step="0.001" min="0" name="equipment[0][quantity]"
                                       class="form-control form-control-sm qty-input" value="0" placeholder="0.000"></td>
                            <td><input type="text" name="equipment[0][unit]"
                                       class="form-control form-control-sm eq-unit-0"
                                       placeholder="auto-filled" readonly></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger row-remove-btn"
                                        onclick="removeRow(this,'eq-count')">
                                <i class="fa-solid fa-times"></i></button></td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── Actions ── --}}
    <div class="d-flex justify-content-end gap-2 mb-5">
        <a href="{{ route('standard-works.show', $standardWork) }}" class="btn btn-secondary">
            <i class="fa-solid fa-xmark me-1"></i>Cancel
        </a>
        <button type="submit" class="btn btn-primary" id="submitBtn">
            <i class="fa-solid fa-floppy-disk me-1"></i>Update Standard Work
        </button>
    </div>
</form>

{{-- Modal for Managing Manpower Roles --}}
<div class="modal fade" id="manageRolesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="fa-solid fa-users-gear text-primary me-2"></i>Manage Manpower Roles
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="card bg-light border-0 mb-3">
                    <div class="card-body p-3">
                        <h6 class="fw-semibold mb-2" style="font-size:13px;">Add Predefined Role</h6>
                        <div class="row g-2">
                            <div class="col-7">
                                <input type="text" id="newRoleName" class="form-control form-control-sm" placeholder="Role Name (e.g. Mason, Welder)">
                            </div>
                            <div class="col-3">
                                <select id="newRoleUnit" class="form-select form-select-sm">
                                    <option value="day">day</option>
                                    <option value="hr">hr</option>
                                </select>
                            </div>
                            <div class="col-2">
                                <button type="button" class="btn btn-sm btn-primary w-100" onclick="saveNewRole()">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div id="roleAddError" class="text-danger small mt-1 d-none"></div>
                    </div>
                </div>

                <h6 class="fw-semibold text-muted mb-2" style="font-size:12px; letter-spacing:0.5px; text-transform:uppercase;">Existing Predefined Roles</h6>
                <div class="list-group list-group-flush border rounded-3 overflow-hidden" id="rolesListGroup" style="max-height: 220px; overflow-y: auto;">
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script type="application/json" id="standard-products-data">{!! json_encode($productsJson ?? []) !!}</script>
<script type="application/json" id="standard-equipment-data">{!! json_encode($equipmentJson ?? []) !!}</script>
<script type="application/json" id="standard-roles-data">{!! json_encode($manpowerRoles ?? []) !!}</script>
<script>
    const PRODUCTS      = JSON.parse(document.getElementById('standard-products-data').textContent || '[]');
    const EQUIPMENT     = JSON.parse(document.getElementById('standard-equipment-data').textContent || '[]');
    let MANPOWER_ROLES  = JSON.parse(document.getElementById('standard-roles-data').textContent || '[]');

    const counts = {
        materials: parseInt('{{ count($mats) ?: 1 }}', 10),
        manpower:  parseInt('{{ count($mps) ?: 1 }}', 10),
        equipment: parseInt('{{ count($eqs) ?: 1 }}', 10)
    };

    function fillUnit(selectEl, section) {
        const chosen = selectEl.options[selectEl.selectedIndex];
        const unit   = chosen ? (chosen.dataset.unit || '') : '';
        const row    = selectEl.closest('tr');
        const unitInput = row.querySelector('input[name*="[unit]"]');
        if (unitInput) {
            unitInput.value    = unit;
            unitInput.readOnly = !!unit;
        }
    }

    const configs = {
        materials: { countId: 'mat-count' },
        manpower:  { countId: 'mp-count'  },
        equipment: { countId: 'eq-count'  },
    };

    function updateCount(countId, tbody) {
        const el = document.getElementById(countId);
        if (!el) return;
        const n = tbody.querySelectorAll('tr').length;
        el.textContent = n + (n === 1 ? ' row' : ' rows');
    }

    function buildOptions(list) {
        return list.map(item =>
            `<option value="${item.name}" data-unit="${item.unit}">${item.name}</option>`
        ).join('');
    }

    function buildManpowerOptions() {
        let opts = '<option value="">— Select Role —</option>';
        MANPOWER_ROLES.forEach(r => {
            opts += `<option value="${r.name}" data-unit="${r.default_unit}">${r.name}</option>`;
        });
        opts += '<option value="__custom__">+ Type custom role...</option>';
        return opts;
    }

    function addRow(section) {
        const idx   = counts[section]++;
        const cfg   = configs[section];
        const tbody = document.getElementById(section + '-body');
        const row   = document.createElement('tr');

        let firstCell, thirdCell;

        if (section === 'materials') {
            firstCell = `<select name="materials[${idx}][material_name]"
                                 class="form-select form-select-sm mat-select"
                                 onchange="fillUnit(this,'materials')" data-idx="${idx}">
                             <option value="">— Select Material —</option>
                             ${buildOptions(PRODUCTS)}
                         </select>`;
            thirdCell = `<input type="text" name="materials[${idx}][unit]"
                                class="form-control form-control-sm" placeholder="auto-filled" readonly>`;
        } else if (section === 'equipment') {
            firstCell = `<select name="equipment[${idx}][equipment_name]"
                                 class="form-select form-select-sm eq-select"
                                 onchange="fillUnit(this,'equipment')" data-idx="${idx}">
                             <option value="">— Select Equipment —</option>
                             ${buildOptions(EQUIPMENT)}
                         </select>`;
            thirdCell = `<input type="text" name="equipment[${idx}][unit]"
                                class="form-control form-control-sm" placeholder="auto-filled" readonly>`;
        } else if (false) {
            // scientific_manpower removed
        } else {
            firstCell = `<select name="manpower[${idx}][role]" class="form-select form-select-sm mp-role-select">
                             ${buildManpowerOptions()}
                         </select>
                         <input type="text" name="manpower[${idx}][role]" class="form-control form-control-sm mt-1 mp-custom-input d-none" placeholder="Enter role name">`;
            thirdCell = `<select name="manpower[${idx}][unit]" class="form-select form-select-sm mp-unit-select">
                             <option value="">— Unit —</option>
                             <option value="day">day</option>
                             <option value="hr">hr</option>
                             <option value="pcs">pcs</option>
                         </select>`;
        }

        row.innerHTML = `
            <td>${firstCell}</td>
            <td><input type="number" step="0.001" min="0" name="${section}[${idx}][quantity]"
                       class="form-control form-control-sm qty-input" value="0" placeholder="0.000"></td>
            <td>${thirdCell}</td>
            <td><button type="button" class="btn btn-sm btn-outline-danger row-remove-btn"
                        onclick="removeRow(this,'${cfg.countId}')">
                    <i class="fa-solid fa-times"></i>
                </button></td>`;
        tbody.appendChild(row);
        updateCount(cfg.countId, tbody);
    }

    function removeRow(btn, countId) {
        const row   = btn.closest('tr');
        const tbody = row.parentElement;
        if (tbody.querySelectorAll('tr').length > 1) {
            row.remove();
        } else {
            row.querySelectorAll('input').forEach(i => {
                i.value = i.type === 'number' ? '0' : '';
            });
            row.querySelectorAll('select').forEach(s => s.selectedIndex = 0);
        }
        updateCount(countId, tbody);
    }

    const workUnitInput = document.getElementById('workUnitInput');
    function updateUnitLabels() {
        const unit = workUnitInput.value.trim();
        const perText  = unit ? `(per 1 ${unit})` : '';
        const prodText = unit ? `(${unit}/day)` : '(unit/day)';
        document.querySelectorAll('.unit-per-label').forEach(el => el.textContent = perText);
        document.querySelectorAll('.prod-unit-label').forEach(el => el.textContent = prodText);
    }
    if (workUnitInput) {
        workUnitInput.addEventListener('change', updateUnitLabels);
    }

    const minProdInput     = document.getElementById('minProductivity');
    const maxProdInput     = document.getElementById('maxProductivity');
    const defaultProdInput = document.getElementById('defaultProductivity');

    function calcAverageProductivity() {
        if (!minProdInput || !maxProdInput || !defaultProdInput) return;
        const minVal = parseFloat(minProdInput.value);
        const maxVal = parseFloat(maxProdInput.value);

        if (!isNaN(minVal) && !isNaN(maxVal)) {
            const avg = (minVal + maxVal) / 2;
            defaultProdInput.value = Math.round(avg * 1000) / 1000;
        } else if (!isNaN(minVal)) {
            defaultProdInput.value = minVal;
        } else if (!isNaN(maxVal)) {
            defaultProdInput.value = maxVal;
        } else {
            defaultProdInput.value = '';
        }
    }

    if (minProdInput && maxProdInput) {
        minProdInput.addEventListener('input', calcAverageProductivity);
        maxProdInput.addEventListener('input', calcAverageProductivity);
    }

    /* ─── Auto-calculate Default Equipment Output as average of Min and Max Equipment Rate ─── */
    const minEqProdInput     = document.getElementById('minEquipmentProductivity');
    const maxEqProdInput     = document.getElementById('maxEquipmentProductivity');
    const defaultEqProdInput = document.getElementById('defaultEquipmentProductivity');

    function calcAverageEquipmentProductivity() {
        if (!minEqProdInput || !maxEqProdInput || !defaultEqProdInput) return;
        const minVal = parseFloat(minEqProdInput.value);
        const maxVal = parseFloat(maxEqProdInput.value);

        if (!isNaN(minVal) && !isNaN(maxVal)) {
            const avg = (minVal + maxVal) / 2;
            defaultEqProdInput.value = Math.round(avg * 1000) / 1000;
        } else if (!isNaN(minVal)) {
            defaultEqProdInput.value = minVal;
        } else if (!isNaN(maxVal)) {
            defaultEqProdInput.value = maxVal;
        } else {
            defaultEqProdInput.value = '';
        }
    }

    if (minEqProdInput && maxEqProdInput) {
        minEqProdInput.addEventListener('input', calcAverageEquipmentProductivity);
        maxEqProdInput.addEventListener('input', calcAverageEquipmentProductivity);
    }

    function addRowToActiveTab() {
        addRow('manpower');
    }

    document.addEventListener('DOMContentLoaded', () => {
        updateUnitLabels();
        calcAverageProductivity();
        calcAverageEquipmentProductivity();
        ['materials','manpower','equipment'].forEach(s => {
            const tbody = document.getElementById(s + '-body');
            if (tbody) updateCount(configs[s].countId, tbody);
        });
    });

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('mp-role-select')) {
            const tr = e.target.closest('tr');
            const customInput = tr.querySelector('.mp-custom-input');
            const unitSelect  = tr.querySelector('.mp-unit-select');

            if (e.target.value === '__custom__') {
                customInput.classList.remove('d-none');
                customInput.required = true;
                customInput.focus();
            } else {
                customInput.classList.add('d-none');
                customInput.required = false;
                customInput.value = e.target.value;
                const selectedOpt = e.target.options[e.target.selectedIndex];
                const defUnit = selectedOpt ? selectedOpt.dataset.unit : '';
                if (defUnit && unitSelect) unitSelect.value = defUnit;
            }
        }

    });

    let manageModal = null;

    function openManageRoles() {
        if (!manageModal) {
            manageModal = new bootstrap.Modal(document.getElementById('manageRolesModal'));
        }
        renderModalRolesList();
        manageModal.show();
    }

    function renderModalRolesList() {
        const container = document.getElementById('rolesListGroup');
        if (!container) return;
        if (MANPOWER_ROLES.length === 0) {
            container.innerHTML = `<div class="p-3 text-muted text-center small">No roles added yet.</div>`;
            return;
        }

        let html = '';
        MANPOWER_ROLES.forEach(role => {
            html += `
                <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                    <div>
                        <span class="fw-semibold text-dark" style="font-size:13px;">${role.name}</span>
                        <span class="badge bg-secondary bg-opacity-10 text-secondary ms-1 small" style="font-size:10px;">${role.default_unit}</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="deleteRole(${role.id})">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </div>`;
        });
        container.innerHTML = html;
    }

    function saveNewRole() {
        const name = document.getElementById('newRoleName').value.trim();
        const unit = document.getElementById('newRoleUnit').value;
        const errEl = document.getElementById('roleAddError');
        errEl.classList.add('d-none');

        if (!name) {
            errEl.textContent = 'Please enter a role name.';
            errEl.classList.remove('d-none');
            return;
        }

        fetch("{{ route('manpower-roles.store') }}", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": "{{ csrf_token() }}"
            },
            body: JSON.stringify({ name: name, default_unit: unit })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                MANPOWER_ROLES.push(data.role);
                MANPOWER_ROLES.sort((a,b) => a.name.localeCompare(b.name));
                document.getElementById('newRoleName').value = '';
                renderModalRolesList();
                syncRoleSelectDropdowns();
            } else {
                errEl.textContent = data.message || 'Error saving role.';
                errEl.classList.remove('d-none');
            }
        })
        .catch(err => {
            errEl.textContent = 'Server error occurred.';
            errEl.classList.remove('d-none');
        });
    }

    function deleteRole(roleId) {
        if (!confirm('Are you sure you want to delete this role?')) return;

        fetch(`/manpower-roles/${roleId}`, {
            method: "DELETE",
            headers: {
                "X-CSRF-TOKEN": "{{ csrf_token() }}"
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                MANPOWER_ROLES = MANPOWER_ROLES.filter(r => r.id !== roleId);
                renderModalRolesList();
                syncRoleSelectDropdowns();
            }
        });
    }

    function syncRoleSelectDropdowns() {
        document.querySelectorAll('.mp-role-select').forEach(select => {
            const currentVal = select.value;
            select.innerHTML = buildManpowerOptions();
            select.value = currentVal;
        });
    }
</script>
@endpush
