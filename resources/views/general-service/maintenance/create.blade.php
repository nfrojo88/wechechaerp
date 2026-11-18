@extends('layouts.app')
@section('title', 'New Maintenance & Service Request — General Service')

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- Page Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ Route::has('general-service.maintenance.index') ? route('general-service.maintenance.index') : url('/general-service/maintenance') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-arrow-left me-1"></i>Back
            </a>
            <div>
                <h1 class="h4 mb-0 fw-bold d-flex align-items-center gap-2" style="color:var(--brand-800)">
                    <i class="fa-solid fa-screwdriver-wrench text-warning"></i>
                    <span>New Maintenance &amp; Asset Service Request</span>
                </h1>
                <p class="text-muted small mb-0">Initiate service for fixed asset units, request spare parts from store, and request repair budget.</p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ Route::has('general-service.maintenance.index') ? route('general-service.maintenance.index') : url('/general-service/maintenance') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="fa-solid fa-list-check me-1"></i>All Maintenance Requests
            </a>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 p-3 mb-4">
            <div class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i>Please fix the following errors:</div>
            <ul class="mb-0 ps-3 small">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form action="{{ Route::has('general-service.maintenance.store') ? route('general-service.maintenance.store') : url('/general-service/maintenance') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="row g-4">

            {{-- Left Column: Asset Selection & Service Fault Details --}}
            <div class="col-lg-7">

                {{-- 1. Fixed Asset Selection Card --}}
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-0 py-3 px-4 rounded-top-4 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 rounded-3 bg-primary bg-opacity-10 text-primary">
                                <i class="fa-solid fa-truck-monster fs-5"></i>
                            </div>
                            <h5 class="mb-0 fw-bold text-dark">1. Select Fixed Asset / Equipment</h5>
                        </div>
                        <span class="badge bg-light text-muted border small">Company Asset Catalog</span>
                    </div>

                    <div class="card-body p-4 pt-2">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase text-secondary" style="letter-spacing:0.5px;">
                                Choose Fixed Asset Unit <small class="text-muted fw-normal">(Optional if unlisted/general asset)</small>
                            </label>
                            <select name="fixed_asset_unit_id" id="fixed_asset_unit_select" class="form-select rounded-3" onchange="onFixedAssetUnitChanged(this)">
                                <option value="">— Select Fixed Asset Unit or choose Unlisted Asset —</option>
                                @foreach($fixedAssetUnits as $unit)
                                    @php
                                        $parentName = $unit->parentAsset->name ?? 'Asset';
                                        $label = "{$unit->unit_code} — {$parentName}";
                                        if ($unit->plate_number) $label .= " [Plate: {$unit->plate_number}]";
                                        if ($unit->brand || $unit->model) $label .= " ({$unit->brand} {$unit->model})";
                                        if ($unit->assignedEmployee) $label .= " • Assigned: {$unit->assignedEmployee->full_name}";
                                    @endphp
                                    <option value="{{ $unit->id }}"
                                            data-name="{{ $parentName }}"
                                            data-code="{{ $unit->unit_code }}"
                                            data-employee-id="{{ $unit->assigned_to_employee_id ?? '' }}"
                                            data-employee-name="{{ $unit->assignedEmployee->full_name ?? '' }}"
                                            data-brand="{{ $unit->brand ?? '' }}"
                                            data-model="{{ $unit->model ?? '' }}"
                                            data-plate="{{ $unit->plate_number ?? '' }}"
                                            data-location="{{ $unit->current_location ?? '' }}"
                                            {{ old('fixed_asset_unit_id') == $unit->id ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Asset Details Fields --}}
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label fw-bold small text-uppercase text-secondary" style="letter-spacing:0.5px;">
                                    Asset / Equipment Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="asset_name" id="asset_name_input" class="form-control rounded-3 fw-semibold" placeholder="e.g. Toyota Hilux Pickup, Excavator CAT 320, Office Generator" value="{{ old('asset_name') }}" required>
                            </div>

                            <div class="col-md-5">
                                <label class="form-label fw-bold small text-uppercase text-secondary" style="letter-spacing:0.5px;">
                                    Asset Code / Tag #
                                </label>
                                <input type="text" name="asset_code" id="asset_code_input" class="form-control rounded-3 font-monospace" placeholder="e.g. AST-001-02, VEH-04" value="{{ old('asset_code') }}">
                            </div>

                            <div class="col-md-7">
                                <label class="form-label fw-bold small text-uppercase text-secondary" style="letter-spacing:0.5px;">
                                    Assigned Employee / Operator
                                </label>
                                <select name="employee_id" id="employee_select" class="form-select rounded-3">
                                    <option value="">— Select Operator / Custodian Employee —</option>
                                    @foreach($employees as $emp)
                                        <option value="{{ $emp->id }}" {{ old('employee_id') == $emp->id ? 'selected' : '' }}>
                                            {{ $emp->full_name }} {{ $emp->employee_code ? "({$emp->employee_code})" : '' }} [{{ $emp->department ?? 'Staff' }}]
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-5">
                                <label class="form-label fw-bold small text-uppercase text-secondary" style="letter-spacing:0.5px;">
                                    Assign Technician / Staff
                                </label>
                                <select name="assigned_to_user_id" class="form-select rounded-3">
                                    <option value="">— Assign to General Service Staff —</option>
                                    @foreach(($staff ?? []) as $st)
                                        <option value="{{ $st->id }}" {{ (old('assigned_to_user_id') == $st->id || auth()->id() == $st->id) ? 'selected' : '' }}>
                                            {{ $st->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Quick Unit Preview Banner --}}
                        <div id="unit_preview_banner" class="alert alert-info bg-opacity-10 border border-info border-opacity-25 rounded-3 p-3 mt-3 d-none">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-circle-info text-info fs-5"></i>
                                <div class="small">
                                    <strong>Unit Information:</strong>
                                    <span id="unit_preview_text" class="text-muted"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 2. Fault Description & Issue Details --}}
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-0 py-3 px-4 rounded-top-4 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 rounded-3 bg-warning bg-opacity-10 text-warning">
                                <i class="fa-solid fa-wrench fs-5"></i>
                            </div>
                            <h5 class="mb-0 fw-bold text-dark">2. Service &amp; Fault Details</h5>
                        </div>
                    </div>

                    <div class="card-body p-4 pt-2">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-uppercase text-secondary" style="letter-spacing:0.5px;">
                                    Service / Issue Type <span class="text-danger">*</span>
                                </label>
                                <select name="issue_type" class="form-select rounded-3" required>
                                    <option value="needs_repair" {{ old('issue_type') == 'needs_repair' ? 'selected' : '' }}>Needs Repair (አጠቃላይ ጥገና)</option>
                                    <option value="breakdown" {{ old('issue_type') == 'breakdown' ? 'selected' : '' }}>Breakdown / Stopped (ስራ ያቆመ)</option>
                                    <option value="service_due" {{ (old('issue_type') == 'service_due' || old('issue_type') == 'routine_service') ? 'selected' : '' }}>Routine Service / Oil Change (ወቅታዊ ሰርቪስ)</option>
                                    <option value="damage" {{ old('issue_type') == 'damage' ? 'selected' : '' }}>Physical Damage (ጉዳት የደረሰበት)</option>
                                    <option value="malfunction" {{ old('issue_type') == 'malfunction' ? 'selected' : '' }}>Malfunction (ብልሽት ያለበት)</option>
                                    <option value="other" {{ old('issue_type') == 'other' ? 'selected' : '' }}>Other Service Request</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-uppercase text-secondary" style="letter-spacing:0.5px;">
                                    Urgency Level <span class="text-danger">*</span>
                                </label>
                                <select name="urgency" class="form-select rounded-3" required>
                                    <option value="normal" {{ old('urgency') == 'normal' ? 'selected' : '' }}>🔵 Normal Priority</option>
                                    <option value="urgent" {{ old('urgency') == 'urgent' ? 'selected' : '' }}>🟠 Urgent Priority</option>
                                    <option value="critical" {{ old('urgency') == 'critical' ? 'selected' : '' }}>🔴 Critical / Breakdown</option>
                                    <option value="low" {{ old('urgency') == 'low' ? 'selected' : '' }}>🟢 Low Priority</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase text-secondary" style="letter-spacing:0.5px;">
                                Detailed Fault &amp; Service Description <span class="text-danger">*</span>
                            </label>
                            <textarea name="description" class="form-control rounded-3" rows="4" placeholder="Describe the symptom, parts affected, required maintenance work, technician findings..." required>{{ old('description') }}</textarea>
                        </div>

                        <div class="mb-0">
                            <label class="form-label fw-bold small text-uppercase text-secondary" style="letter-spacing:0.5px;">
                                Initial General Service Diagnosis / Notes
                            </label>
                            <textarea name="admin_notes" class="form-control rounded-3" rows="2" placeholder="Internal technical notes, garage / contractor assignment, workshop instructions...">{{ old('admin_notes') }}</textarea>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Right Column: Workflow Overview & Actions --}}
            <div class="col-lg-5">

                <div class="card border-0 shadow-sm rounded-4 mb-4 border-top border-4 border-primary">
                    <div class="card-header bg-white border-0 py-3 px-4 rounded-top-4">
                        <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-diagram-project text-primary me-2"></i>Maintenance Workflow (7 Stages)</h6>
                        <small class="text-muted">Structured approval &amp; verification lifecycle</small>
                    </div>
                    <div class="card-body p-4 pt-1">
                        <div class="timeline-stepper d-flex flex-column gap-3">
                            <div class="d-flex gap-3 align-items-start">
                                <span class="badge bg-primary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">1</span>
                                <div>
                                    <strong class="d-block text-dark small">Ticket Creation (Current Step)</strong>
                                    <span class="text-muted small">Asset marked <code>in_maintenance</code>. Ticket submitted to GS inspection queue.</span>
                                </div>
                            </div>
                            <div class="d-flex gap-3 align-items-start text-muted">
                                <span class="badge bg-secondary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">2</span>
                                <div>
                                    <strong class="d-block small">GS Inspection &amp; Verification</strong>
                                    <span class="small">GS inspects the fault, writes diagnosis, and confirms maintenance (Accept or Reject).</span>
                                </div>
                            </div>
                            <div class="d-flex gap-3 align-items-start text-muted">
                                <span class="badge bg-secondary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">3</span>
                                <div>
                                    <strong class="d-block small">GM First Review</strong>
                                    <span class="small">GM reviews asset maintenance history and diagnosis to grant initial permission.</span>
                                </div>
                            </div>
                            <div class="d-flex gap-3 align-items-start text-muted">
                                <span class="badge bg-secondary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">4</span>
                                <div>
                                    <strong class="d-block small">GS Fills Costs &amp; Tech Details</strong>
                                    <span class="small">GS adds itemized spare parts, labor cost, workshop details, and proformas.</span>
                                </div>
                            </div>
                            <div class="d-flex gap-3 align-items-start text-muted">
                                <span class="badge bg-secondary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">5-7</span>
                                <div>
                                    <strong class="d-block small">Lock, GM Decision &amp; Routing</strong>
                                    <span class="small">Ticket locked, GM gives final sign-off, money routed to Finance Head &amp; parts to store.</span>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info border-0 rounded-3 mt-3 mb-0 py-2 small">
                            <i class="fa-solid fa-circle-info me-1"></i>
                            <strong>Policy Notice:</strong> Spare parts and labor expenditure are added in Stage 4 after initial GM permission.
                        </div>
                    </div>
                </div>

                {{-- Action Card --}}
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold rounded-3 shadow-sm py-2.5 mb-2">
                            <i class="fa-solid fa-paper-plane me-2"></i>Create Ticket &amp; Send to GS Queue
                        </button>
                        <a href="{{ route('general-service.maintenance.index') }}" class="btn btn-outline-secondary w-100 rounded-3">
                            <i class="fa-solid fa-arrow-left me-1"></i>Cancel &amp; Return to List
                        </a>
                    </div>
                </div>

            </div>

        </div>
    </form>

</div>

@push('scripts')
<script>
function onFixedAssetUnitChanged(selectEl) {
    const selectedOption = selectEl.options[selectEl.selectedIndex];
    const assetNameInput = document.getElementById('asset_name_input');
    const assetCodeInput = document.getElementById('asset_code_input');
    const employeeSelect = document.getElementById('employee_select');
    const previewBanner = document.getElementById('unit_preview_banner');
    const previewText = document.getElementById('unit_preview_text');

    if (!selectEl.value) {
        if (previewBanner) previewBanner.classList.add('d-none');
        return;
    }

    const name = selectedOption.getAttribute('data-name');
    const code = selectedOption.getAttribute('data-code');
    const empId = selectedOption.getAttribute('data-employee-id');
    const empName = selectedOption.getAttribute('data-employee-name');
    const brand = selectedOption.getAttribute('data-brand');
    const model = selectedOption.getAttribute('data-model');
    const plate = selectedOption.getAttribute('data-plate');
    const location = selectedOption.getAttribute('data-location');

    if (name && assetNameInput) assetNameInput.value = name;
    if (code && assetCodeInput) assetCodeInput.value = code;
    if (empId && employeeSelect) employeeSelect.value = empId;

    if (previewBanner && previewText) {
        let details = [];
        if (code) details.push(`Tag: ${code}`);
        if (plate) details.push(`Plate: ${plate}`);
        if (brand || model) details.push(`Brand/Model: ${brand} ${model}`.trim());
        if (location) details.push(`Location: ${location}`);
        if (empName) details.push(`Operator: ${empName}`);

        previewText.innerText = details.join(' • ');
        previewBanner.classList.remove('d-none');
    }
}

function toggleMaterialSection(switchEl) {
    const container = document.getElementById('material_section_container');
    if (container) {
        if (switchEl.checked) {
            container.classList.remove('d-none');
        } else {
            container.classList.add('d-none');
        }
    }
}

function toggleMoneySection(switchEl) {
    const container = document.getElementById('money_section_container');
    if (container) {
        if (switchEl.checked) {
            container.classList.remove('d-none');
        } else {
            container.classList.add('d-none');
        }
    }
}

let materialRowCounter = 1;

function handleProductSelect(selectEl, idx) {
    const row = selectEl.closest('.material-item-row');
    const customInput = row.querySelector('.custom-product-name');
    const unitInput = row.querySelector('.unit-field');

    if (selectEl.value === 'custom') {
        customInput.classList.remove('d-none');
        customInput.required = true;
        customInput.focus();
    } else {
        customInput.classList.add('d-none');
        customInput.required = false;

        const selectedOption = selectEl.options[selectEl.selectedIndex];
        const unit = selectedOption.getAttribute('data-unit');
        if (unit && unitInput) {
            unitInput.value = unit;
        }
    }
}

function addMaterialRow() {
    const container = document.getElementById('material_rows_container');
    const idx = materialRowCounter++;

    const div = document.createElement('div');
    div.className = 'material-item-row p-2 rounded-3 border bg-light mb-2 position-relative';
    div.setAttribute('data-idx', idx);

    div.innerHTML = `
        <button type="button" class="btn-close position-absolute top-0 end-0 m-1" style="font-size:0.65rem;" onclick="this.closest('.material-item-row').remove()"></button>
        <div class="row g-2">
            <div class="col-12">
                <select name="items[${idx}][product_id]" class="form-select form-select-sm" onchange="handleProductSelect(this, ${idx})">
                    <option value="">— Select Catalog Product —</option>
                    <option value="custom">✏️ + Custom / Unlisted Spare Part</option>
                    @foreach(($products ?? []) as $p)
                        <option value="{{ $p->id }}" data-unit="{{ $p->unit ?? 'pcs' }}">{{ $p->name }} {{ $p->sku ? "({$p->sku})" : '' }}</option>
                    @endforeach
                </select>
                <input type="text" name="items[${idx}][custom_name]" class="form-control form-control-sm custom-product-name mt-1 d-none" placeholder="Type custom spare part name...">
            </div>
            <div class="col-6">
                <input type="number" step="0.01" min="0.01" name="items[${idx}][quantity]" class="form-control form-control-sm" placeholder="Qty" value="1">
            </div>
            <div class="col-6">
                <input type="text" name="items[${idx}][unit]" class="form-control form-control-sm unit-field" placeholder="Unit" value="pcs">
            </div>
            <div class="col-12">
                <input type="text" name="items[${idx}][notes]" class="form-control form-control-sm" placeholder="Specs, part number, notes...">
            </div>
        </div>
    `;

    container.appendChild(div);
}

function toggleDirectMoneyBank(toggle) {
    const f = document.getElementById('money_bank_fields');
    const b = document.getElementById('money_bank_badge');
    if (f) {
        if (toggle.checked) f.classList.remove('d-none');
        else f.classList.add('d-none');
    }
    if (b) {
        if (toggle.checked) {
            b.className = 'badge bg-success text-white border border-success px-2 py-0.5 rounded-pill font-monospace small';
            b.innerHTML = '<i class="fa-solid fa-lock-open me-1"></i>Unlocked';
        } else {
            b.className = 'badge bg-secondary-subtle text-secondary border px-2 py-0.5 rounded-pill font-monospace small';
            b.innerHTML = '<i class="fa-solid fa-lock me-1"></i>Optional';
        }
    }
}
</script>
@endpush
@endsection
