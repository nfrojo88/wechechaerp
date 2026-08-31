@extends('layouts.app')
@section('title', 'New Maintenance & Service Request — General Service')

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- Page Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('general-service.maintenance.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs">
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
            <a href="{{ route('general-service.maintenance.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
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

    <form action="{{ route('general-service.maintenance.store') }}" method="POST" enctype="multipart/form-data">
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
                                    @foreach($staff as $st)
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
                                    <option value="routine_service" {{ old('issue_type') == 'routine_service' ? 'selected' : '' }}>Routine Service / Oil Change (ወቅታዊ ሰርቪስ)</option>
                                    <option value="service_due" {{ old('issue_type') == 'service_due' ? 'selected' : '' }}>Service Due (የደረሰ ሰርቪስ)</option>
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

            {{-- Right Column: Direct Ask Material & Ask Money Options --}}
            <div class="col-lg-5">

                {{-- 3. Ask Material / Spare Parts Box --}}
                <div class="card border-0 shadow-sm rounded-4 mb-4" style="border-top: 4px solid #3b82f6 !important;">
                    <div class="card-header bg-white border-0 py-3 px-4 rounded-top-4">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="ask_material" id="ask_material_switch" value="1" {{ old('ask_material') ? 'checked' : '' }} onchange="toggleMaterialSection(this)">
                            <label class="form-check-label fw-bold text-dark fs-6" for="ask_material_switch">
                                <i class="fa-solid fa-boxes-stacked text-primary me-1"></i> Ask Material / Spare Parts
                            </label>
                        </div>
                        <small class="text-muted d-block mt-1">Request spare parts from Store Manager or Procurement immediately.</small>
                    </div>

                    <div id="material_section_container" class="card-body p-4 pt-0 {{ old('ask_material') ? '' : 'd-none' }}">
                        <hr class="my-2 text-muted">

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-secondary">Destination Store</label>
                                <select name="destination_store_id" class="form-select form-select-sm rounded-3">
                                    @foreach($stores as $st)
                                        <option value="{{ $st->id }}">{{ $st->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-secondary">Required By Date</label>
                                <input type="date" name="required_date" class="form-control form-control-sm rounded-3" value="{{ now()->addDays(2)->format('Y-m-d') }}">
                            </div>
                        </div>

                        {{-- Item rows --}}
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label fw-bold small text-secondary mb-0">Requested Spare Parts / Items</label>
                                <button type="button" class="btn btn-xs btn-outline-primary rounded-pill px-2 py-0" onclick="addMaterialRow()">
                                    <i class="fa-solid fa-plus me-1"></i>Add Row
                                </button>
                            </div>

                            <div id="material_rows_container">
                                <div class="material-item-row p-2 rounded-3 border bg-light mb-2" data-idx="0">
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <select name="items[0][product_id]" class="form-select form-select-sm" onchange="handleProductSelect(this, 0)">
                                                <option value="">— Select Catalog Product —</option>
                                                <option value="custom">✏️ + Custom / Unlisted Spare Part</option>
                                                @foreach($products as $p)
                                                    <option value="{{ $p->id }}" data-unit="{{ $p->unit ?? 'pcs' }}">{{ $p->name }} {{ $p->sku ? "({$p->sku})" : '' }}</option>
                                                @endforeach
                                            </select>
                                            <input type="text" name="items[0][custom_name]" class="form-control form-control-sm custom-product-name mt-1 d-none" placeholder="Type custom spare part name...">
                                        </div>
                                        <div class="col-6">
                                            <input type="number" step="0.01" min="0.01" name="items[0][quantity]" class="form-control form-control-sm" placeholder="Qty" value="1">
                                        </div>
                                        <div class="col-6">
                                            <input type="text" name="items[0][unit]" class="form-control form-control-sm unit-field" placeholder="Unit" value="pcs">
                                        </div>
                                        <div class="col-12">
                                            <input type="text" name="items[0][notes]" class="form-control form-control-sm" placeholder="Specs, part number, notes...">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label fw-bold small text-secondary">Material Notes for Store Manager</label>
                            <textarea name="material_notes" class="form-control form-control-sm rounded-3" rows="2" placeholder="Notes for Store Manager / Purchasing officer...">{{ old('material_notes') }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- 4. Ask Money / Repair Budget Box --}}
                <div class="card border-0 shadow-sm rounded-4 mb-4" style="border-top: 4px solid #10b981 !important;">
                    <div class="card-header bg-white border-0 py-3 px-4 rounded-top-4">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="ask_money" id="ask_money_switch" value="1" {{ old('ask_money') ? 'checked' : '' }} onchange="toggleMoneySection(this)">
                            <label class="form-check-label fw-bold text-dark fs-6" for="ask_money_switch">
                                <i class="fa-solid fa-hand-holding-dollar text-success me-1"></i> Ask Money (Expense Request)
                            </label>
                        </div>
                        <small class="text-muted d-block mt-1">Request funds for technician labour, garage service, or local cash purchase.</small>
                    </div>

                    <div id="money_section_container" class="card-body p-4 pt-0 {{ old('ask_money') ? '' : 'd-none' }}">
                        <hr class="my-2 text-muted">

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">Requested Amount (ETB) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text fw-bold bg-light">ETB</span>
                                <input type="number" step="0.01" min="1" name="money_amount" class="form-control fw-bold text-success" placeholder="0.00" value="{{ old('money_amount') }}">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">Purpose &amp; Quotation Details</label>
                            <textarea name="money_description" class="form-control form-control-sm rounded-3" rows="2" placeholder="Technician quotation, garage service details, labour fees...">{{ old('money_description') }}</textarea>
                        </div>

                        <div class="mb-0">
                            <label class="form-label fw-bold small text-secondary">Proforma / Quotation Attachment <small class="text-muted">(Optional)</small></label>
                            <input type="file" name="money_attachment" class="form-control form-control-sm rounded-3" accept="image/jpeg,image/png,image/jpg,application/pdf,image/webp">
                        </div>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-warning btn-lg fw-bold text-dark rounded-pill shadow-sm">
                                <i class="fa-solid fa-paper-plane me-2"></i>Create Service Request &amp; Start Workflow
                            </button>
                            <a href="{{ route('general-service.maintenance.index') }}" class="btn btn-light rounded-pill text-muted">
                                Cancel
                            </a>
                        </div>
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
                    @foreach($products as $p)
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
</script>
@endpush
@endsection
