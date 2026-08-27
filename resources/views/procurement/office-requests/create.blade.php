@extends('layouts.app')

@section('title', 'New Office Material Request')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('office-requests.index') }}">Office Material Requests</a></li>
                    <li class="breadcrumb-item active" aria-current="page">New Request</li>
                </ol>
            </nav>
            <h3 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>New Office Material Requisition
            </h3>
            <p class="text-muted small mb-0">Select materials from catalog or add custom office items for Head Office</p>
        </div>
        <a href="{{ route('office-requests.index') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to List
        </a>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form action="{{ route('office-requests.store') }}" method="POST" enctype="multipart/form-data" id="officeReqForm">
        @csrf

        <div class="row g-4">
            <!-- Left Column: Requisition Meta -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-circle-info text-primary me-2"></i>Request Details</h6>
                    </div>
                    <div class="card-body p-4">
                        <!-- Office Purpose -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark small text-uppercase">Purpose / Category <span class="text-danger">*</span></label>
                            <select name="office_purpose" class="form-select @error('office_purpose') is-invalid @enderror" required>
                                <option value="" disabled selected>-- Select Office Purpose --</option>
                                @foreach($purposes as $key => $lbl)
                                    <option value="{{ $key }}" {{ old('office_purpose') === $key ? 'selected' : '' }}>{{ $lbl }}</option>
                                @endforeach
                            </select>
                            @error('office_purpose')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Urgency -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark small text-uppercase">Priority / Urgency <span class="text-danger">*</span></label>
                            <select name="urgency" class="form-select @error('urgency') is-invalid @enderror" required>
                                <option value="normal" {{ old('urgency', 'normal') === 'normal' ? 'selected' : '' }}>🟢 Normal (መደበኛ)</option>
                                <option value="urgent" {{ old('urgency') === 'urgent' ? 'selected' : '' }}>🟡 Urgent (አስቸኳይ)</option>
                                <option value="emergency" {{ old('urgency') === 'emergency' ? 'selected' : '' }}>🔴 Emergency (በጣም አስቸኳይ)</option>
                            </select>
                            @error('urgency')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Required By Date -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark small text-uppercase">Required By Date</label>
                            <input type="date" name="required_date" class="form-control @error('required_date') is-invalid @enderror" value="{{ old('required_date', now()->addDays(2)->format('Y-m-d')) }}">
                            @error('required_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Justification / Detailed Notes -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark small text-uppercase">Justification / Reason (ዝርዝር ምክንያት)</label>
                            <textarea name="justification" rows="4" class="form-control @error('justification') is-invalid @enderror" placeholder="Explain why these office supplies are needed...">{{ old('justification') }}</textarea>
                            @error('justification')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Attachment -->
                        <div class="mb-0">
                            <label class="form-label fw-bold text-dark small text-uppercase">Attachment / Quotation (Optional)</label>
                            <input type="file" name="attachment" class="form-control @error('attachment') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            <div class="form-text small">Max: 10MB (PDF, Images, Word)</div>
                            @error('attachment')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Materials & Items Table -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-list-check text-primary me-2"></i>Requested Materials &amp; Items</h6>
                            <span class="text-muted small">Select materials from dropdown or choose custom item</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm" id="addItemRowBtn">
                            <i class="fa-solid fa-plus me-1"></i> Add Another Item
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="itemsTable">
                                <thead class="table-light text-secondary text-uppercase small" style="font-size: 0.78rem;">
                                    <tr>
                                        <th style="width: 5%;" class="ps-3">#</th>
                                        <th style="width: 44%;">Select Material / Item <span class="text-danger">*</span></th>
                                        <th style="width: 16%;">Qty <span class="text-danger">*</span></th>
                                        <th style="width: 15%;">Unit <span class="text-danger">*</span></th>
                                        <th style="width: 15%;">Specifications</th>
                                        <th style="width: 5%;" class="text-center"><i class="fa-solid fa-trash text-muted"></i></th>
                                    </tr>
                                </thead>
                                <tbody id="itemsTableBody">
                                    <!-- Row 0 -->
                                    <tr class="item-row">
                                        <td class="text-muted row-number ps-3">1</td>
                                        <td>
                                            <!-- Material Select Dropdown -->
                                            <select class="form-select form-select-sm material-dropdown" required>
                                                <option value="" disabled selected>-- Select Material (እቃ ይምረጡ) --</option>
                                                <option value="CUSTOM_ITEM" style="font-weight: bold; color: #2563eb;">✏️ [ + Custom / Other Item (ሌላ እቃ ጻፍ) ]</option>
                                                <optgroup label="📦 Material Catalog">
                                                    @foreach($products as $prod)
                                                        <option value="{{ $prod->id }}" 
                                                                data-name="{{ $prod->name }}" 
                                                                data-unit="{{ strtolower($prod->unit ?? 'pcs') }}">
                                                            {{ $prod->name }} {{ $prod->code ? "({$prod->code})" : '' }}
                                                        </option>
                                                    @endforeach
                                                </optgroup>
                                            </select>
                                            
                                            <!-- Hidden & Custom inputs -->
                                            <input type="hidden" name="items[0][product_id]" class="item-product-id" value="">
                                            <input type="hidden" name="items[0][name]" class="item-name-hidden" value="">
                                            <input type="text" class="form-control form-control-sm mt-1 custom-name-input d-none" placeholder="Type custom material name (የእቃውን ስም ጻፉ)...">
                                        </td>
                                        <td>
                                            <input type="number" name="items[0][qty]" class="form-control form-control-sm text-end fw-bold" min="0.01" step="any" placeholder="10" required>
                                        </td>
                                        <td>
                                            <select name="items[0][unit]" class="form-select form-select-sm unit-select">
                                                <option value="pcs" selected>Pcs (ፍሬ)</option>
                                                <option value="pack">Pack (ፓኬት)</option>
                                                <option value="ream">Ream (ሪም)</option>
                                                <option value="box">Box (ሳጥን)</option>
                                                <option value="roll">Roll (ጥቅል)</option>
                                                <option value="kg">Kg (ኪ.ግ)</option>
                                                <option value="liter">Liter (ሊትር)</option>
                                                <option value="set">Set (ስብስብ)</option>
                                                <option value="m">Meter (ሜትር)</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="items[0][specs]" class="form-control form-control-sm" placeholder="e.g. 80gsm, Blue, etc.">
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-outline-danger btn-sm border-0 remove-row-btn" disabled>
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-light border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                        <span class="text-muted small">
                            <i class="fa-solid fa-circle-info me-1"></i> HR / Coordinator will review items and assign approved budget money.
                        </span>
                        <button type="submit" class="btn btn-success px-4 fw-bold shadow-sm rounded-pill">
                            <i class="fa-solid fa-paper-plane me-1"></i> Submit Requisition to HR
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Template HTML for new rows -->
<template id="itemRowTemplate">
    <tr class="item-row">
        <td class="text-muted row-number ps-3">__INDEX__</td>
        <td>
            <select class="form-select form-select-sm material-dropdown" required>
                <option value="" disabled selected>-- Select Material (እቃ ይምረጡ) --</option>
                <option value="CUSTOM_ITEM" style="font-weight: bold; color: #2563eb;">✏️ [ + Custom / Other Item (ሌላ እቃ ጻፍ) ]</option>
                <optgroup label="📦 Material Catalog">
                    @foreach($products as $prod)
                        <option value="{{ $prod->id }}" 
                                data-name="{{ $prod->name }}" 
                                data-unit="{{ strtolower($prod->unit ?? 'pcs') }}">
                            {{ $prod->name }} {{ $prod->code ? "({$prod->code})" : '' }}
                        </option>
                    @endforeach
                </optgroup>
            </select>
            <input type="hidden" name="items[__ROW__][product_id]" class="item-product-id" value="">
            <input type="hidden" name="items[__ROW__][name]" class="item-name-hidden" value="">
            <input type="text" class="form-control form-control-sm mt-1 custom-name-input d-none" placeholder="Type custom material name (የእቃውን ስም ጻፉ)...">
        </td>
        <td>
            <input type="number" name="items[__ROW__][qty]" class="form-control form-control-sm text-end fw-bold" min="0.01" step="any" placeholder="1" required>
        </td>
        <td>
            <select name="items[__ROW__][unit]" class="form-select form-select-sm unit-select">
                <option value="pcs" selected>Pcs (ፍሬ)</option>
                <option value="pack">Pack (ፓኬት)</option>
                <option value="ream">Ream (ሪም)</option>
                <option value="box">Box (ሳጥን)</option>
                <option value="roll">Roll (ጥቅል)</option>
                <option value="kg">Kg (ኪ.ግ)</option>
                <option value="liter">Liter (ሊትር)</option>
                <option value="set">Set (ስብስብ)</option>
                <option value="m">Meter (ሜትር)</option>
            </select>
        </td>
        <td>
            <input type="text" name="items[__ROW__][specs]" class="form-control form-control-sm" placeholder="Optional specs">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-sm border-0 remove-row-btn">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </td>
    </tr>
</template>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let rowIndex = 1;
    const tableBody = document.getElementById('itemsTableBody');
    const addBtn = document.getElementById('addItemRowBtn');
    const template = document.getElementById('itemRowTemplate').innerHTML;

    function updateRowNumbers() {
        const rows = tableBody.querySelectorAll('.item-row');
        rows.forEach((row, idx) => {
            row.querySelector('.row-number').textContent = idx + 1;
            const removeBtn = row.querySelector('.remove-row-btn');
            removeBtn.disabled = (rows.length === 1);
        });
    }

    function handleMaterialChange(selectEl) {
        const row = selectEl.closest('.item-row');
        const hiddenId = row.querySelector('.item-product-id');
        const hiddenName = row.querySelector('.item-name-hidden');
        const customInput = row.querySelector('.custom-name-input');
        const unitSelect = row.querySelector('.unit-select');

        const selectedVal = selectEl.value;
        const selectedOpt = selectEl.options[selectEl.selectedIndex];

        if (selectedVal === 'CUSTOM_ITEM') {
            hiddenId.value = '';
            hiddenName.value = customInput.value.trim();
            customInput.classList.remove('d-none');
            customInput.focus();
            customInput.required = true;
        } else if (selectedVal && selectedVal !== '') {
            const prodName = selectedOpt.getAttribute('data-name') || selectedOpt.textContent.trim();
            const prodUnit = (selectedOpt.getAttribute('data-unit') || '').toLowerCase().trim();

            hiddenId.value = selectedVal;
            hiddenName.value = prodName;
            customInput.classList.add('d-none');
            customInput.required = false;
            customInput.value = '';

            // Auto-select unit if matches
            if (prodUnit && unitSelect) {
                for (let i = 0; i < unitSelect.options.length; i++) {
                    const optVal = unitSelect.options[i].value.toLowerCase();
                    if (optVal === prodUnit || prodUnit.includes(optVal)) {
                        unitSelect.selectedIndex = i;
                        break;
                    }
                }
            }
        } else {
            hiddenId.value = '';
            hiddenName.value = '';
            customInput.classList.add('d-none');
            customInput.required = false;
        }
    }

    // Bind change on material dropdowns
    tableBody.addEventListener('change', function(e) {
        if (e.target.classList.contains('material-dropdown')) {
            handleMaterialChange(e.target);
        }
    });

    // Custom input typing updates hidden name
    tableBody.addEventListener('input', function(e) {
        if (e.target.classList.contains('custom-name-input')) {
            const row = e.target.closest('.item-row');
            const hiddenName = row.querySelector('.item-name-hidden');
            if (hiddenName) {
                hiddenName.value = e.target.value.trim();
            }
        }
    });

    // Add row
    addBtn.addEventListener('click', function() {
        const rowHtml = template.replace(/__ROW__/g, rowIndex).replace(/__INDEX__/g, rowIndex + 1);
        const tempDiv = document.createElement('tbody');
        tempDiv.innerHTML = rowHtml;
        const newRow = tempDiv.firstElementChild;

        tableBody.appendChild(newRow);
        rowIndex++;
        updateRowNumbers();
    });

    // Remove row
    tableBody.addEventListener('click', function(e) {
        if (e.target.closest('.remove-row-btn')) {
            const row = e.target.closest('.item-row');
            if (tableBody.querySelectorAll('.item-row').length > 1) {
                row.remove();
                updateRowNumbers();
            }
        }
    });

    // Initialize first row
    const firstSelect = tableBody.querySelector('.material-dropdown');
    if (firstSelect && firstSelect.value) {
        handleMaterialChange(firstSelect);
    }
});
</script>
@endpush
@endsection
