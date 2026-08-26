@extends('layouts.app')
@section('title', 'New Office Supply Request')

@section('content')
<div class="container-fluid py-3">
    <!-- Breadcrumb & Back -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.index') ? route('office-requests.index') : url('/office-requests') }}" class="text-decoration-none">Office Requests</a></li>
                    <li class="breadcrumb-item active" aria-current="page">New Request</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold text-dark mb-0">
                <i class="fa-solid fa-pen-to-square text-primary me-2"></i>New Office Supply Request
                <span class="fs-6 fw-normal text-muted ms-2">(የቢሮ እቃ መጠየቂያ ቅጽ)</span>
            </h1>
        </div>
        <a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.index') ? route('office-requests.index') : url('/office-requests') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to List
        </a>
    </div>

    <!-- Instructions Callout -->
    <div class="alert alert-info border-0 shadow-sm rounded-3 d-flex align-items-start gap-3 mb-4">
        <i class="fa-solid fa-circle-info fs-4 text-info mt-1"></i>
        <div>
            <div class="fw-bold">Secretary Office Requisition Workflow</div>
            <div class="small">
                This request will be routed directly to <strong>HR Manager & Coordinator</strong> for approval.
                Once approved, materials will be fulfilled from office stores or forwarded to procurement.
            </div>
        </div>
    </div>

    <form action="{{ \Illuminate\Support\Facades\Route::has('office-requests.store') ? route('office-requests.store') : url('/office-requests') }}" method="POST" id="officeRequestForm">
        @csrf

        <div class="row g-4">
            <!-- Left Column: Purpose & Request Info -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-tag text-primary me-2"></i>General Information</h6>
                    </div>
                    <div class="card-body">
                        <!-- Office Purpose / Category -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark">
                                Office Purpose / Category <span class="text-danger">*</span>
                            </label>
                            <select name="office_purpose" class="form-select @error('office_purpose') is-invalid @enderror" required>
                                <option value="">-- Select Category --</option>
                                @foreach($purposes as $key => $label)
                                    <option value="{{ $key }}" {{ old('office_purpose') == $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('office_purpose')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text small">Select what these materials will be used for in the office.</div>
                        </div>

                        <!-- Priority -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark">Priority <span class="text-danger">*</span></label>
                            <select name="priority" class="form-select @error('priority') is-invalid @enderror" required>
                                <option value="normal" {{ old('priority') == 'normal' ? 'selected' : '' }}>Normal (መደበኛ)</option>
                                <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>High (አስቸኳይ)</option>
                                <option value="urgent" {{ old('priority') == 'urgent' ? 'selected' : '' }}>Urgent (በጣም አስቸኳይ)</option>
                            </select>
                        </div>

                        <!-- Required By Date -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark">Required By Date (የሚፈለግበት ቀን)</label>
                            <input type="date" name="required_date" class="form-control @error('required_date') is-invalid @enderror" value="{{ old('required_date', date('Y-m-d', strtotime('+3 days'))) }}">
                            @error('required_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Target Location (Strictly Head Office) -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark">Target Location / Department (ቦታ)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-primary"><i class="fa-solid fa-building"></i></span>
                                <input type="text" class="form-control bg-light fw-bold text-dark" value="Head Office (ዋና ቢሮ)" readonly>
                            </div>
                            <div class="form-text small">All office material requests are designated exclusively for Head Office.</div>
                        </div>

                        <!-- Justification / Note -->
                        <div class="mb-0">
                            <label class="form-label fw-semibold text-dark">Justification / Reason (ዝርዝር ማብራሪያ)</label>
                            <textarea name="justification" rows="3" class="form-control" placeholder="e.g. Supplies needed for monthly executive meetings and front desk documentation...">{{ old('justification') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Material Items Table -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-dark">
                            <i class="fa-solid fa-list-check text-primary me-2"></i>Requested Office Supplies / Materials
                        </h6>
                        <button type="button" class="btn btn-sm btn-success" id="addRowBtn">
                            <i class="fa-solid fa-plus me-1"></i> Add Material
                        </button>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0" id="itemsTable">
                                <thead class="table-light text-secondary text-uppercase small" style="font-size: 0.75rem;">
                                    <tr>
                                        <th style="min-width: 250px;">Material / Item <span class="text-danger">*</span></th>
                                        <th style="width: 120px;">Qty <span class="text-danger">*</span></th>
                                        <th style="width: 120px;">Unit</th>
                                        <th>Specification / Notes</th>
                                        <th style="width: 50px;" class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="itemsBody">
                                    <tr class="item-row" data-index="0">
                                        <td>
                                            <select name="items[0][product_id]" class="form-select form-select-sm product-select" required>
                                                <option value="">-- Select Material / Item --</option>
                                                @foreach($products as $prod)
                                                    <option value="{{ $prod->id }}" data-unit="{{ $prod->unit ?? 'pcs' }}" data-price="{{ $prod->latest_marketing_price ?? 0 }}">
                                                        {{ $prod->name }} {{ $prod->code ? "({$prod->code})" : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" step="any" min="0.01" name="items[0][quantity]" class="form-control form-control-sm item-qty text-end" placeholder="0" required>
                                        </td>
                                        <td>
                                            <input type="text" name="items[0][unit]" class="form-control form-control-sm item-unit" placeholder="pcs" readonly>
                                        </td>
                                        <td>
                                            <input type="text" name="items[0][specifications]" class="form-control form-control-sm" placeholder="Brand, size, color, etc.">
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn" disabled>
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card-footer bg-white border-top py-3 d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="addAnotherItem">
                            <i class="fa-solid fa-plus me-1"></i> Add Another Row
                        </button>
                        <div class="d-flex gap-2">
                            <a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.index') ? route('office-requests.index') : url('/office-requests') }}" class="btn btn-light border px-3">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4 shadow-sm" id="submitBtn">
                                <i class="fa-solid fa-paper-plane me-1"></i> Submit to HR & Coordinator
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Product options template for JavaScript -->
<template id="productOptionsTemplate">
    <option value="">-- Select Material / Item --</option>
    @foreach($products as $prod)
        <option value="{{ $prod->id }}" data-unit="{{ $prod->unit ?? 'pcs' }}" data-price="{{ $prod->latest_marketing_price ?? 0 }}">
            {{ $prod->name }} {{ $prod->code ? "({$prod->code})" : '' }}
        </option>
    @endforeach
</template>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    let rowIndex = 1;
    const itemsBody = document.getElementById('itemsBody');
    const optionsHtml = document.getElementById('productOptionsTemplate').innerHTML;

    function bindEvents(row) {
        const select = row.querySelector('.product-select');
        const unitInput = row.querySelector('.item-unit');
        const removeBtn = row.querySelector('.remove-row-btn');

        if (select) {
            select.addEventListener('change', function () {
                const selectedOpt = select.options[select.selectedIndex];
                if (selectedOpt && selectedOpt.dataset.unit) {
                    unitInput.value = selectedOpt.dataset.unit;
                } else {
                    unitInput.value = 'pcs';
                }
            });
        }

        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                const allRows = itemsBody.querySelectorAll('.item-row');
                if (allRows.length > 1) {
                    row.remove();
                    updateRemoveButtons();
                }
            });
        }
    }

    function updateRemoveButtons() {
        const allRows = itemsBody.querySelectorAll('.item-row');
        allRows.forEach((r, idx) => {
            const btn = r.querySelector('.remove-row-btn');
            if (btn) {
                btn.disabled = allRows.length === 1;
            }
        });
    }

    function addRow() {
        const tr = document.createElement('tr');
        tr.className = 'item-row';
        tr.dataset.index = rowIndex;
        tr.innerHTML = `
            <td>
                <select name="items[${rowIndex}][product_id]" class="form-select form-select-sm product-select" required>
                    ${optionsHtml}
                </select>
            </td>
            <td>
                <input type="number" step="any" min="0.01" name="items[${rowIndex}][quantity]" class="form-control form-control-sm item-qty text-end" placeholder="0" required>
            </td>
            <td>
                <input type="text" name="items[${rowIndex}][unit]" class="form-control form-control-sm item-unit" placeholder="pcs" readonly>
            </td>
            <td>
                <input type="text" name="items[${rowIndex}][specifications]" class="form-control form-control-sm" placeholder="Brand, size, color, etc.">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </td>
        `;
        itemsBody.appendChild(tr);
        bindEvents(tr);
        updateRemoveButtons();
        rowIndex++;
    }

    // Initialize first row
    const firstRow = itemsBody.querySelector('.item-row');
    if (firstRow) {
        bindEvents(firstRow);
        updateRemoveButtons();
    }

    document.getElementById('addRowBtn').addEventListener('click', addRow);
    document.getElementById('addAnotherItem').addEventListener('click', addRow);
});
</script>
@endpush
@endsection
