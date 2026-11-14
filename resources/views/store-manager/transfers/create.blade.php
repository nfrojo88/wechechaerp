@extends('layouts.app')

@section('title', 'Create Transfer - Store Manager')

@section('content')
@push('styles')
@include('layouts._store_mobile')
<style>
.fa-unit-card {
    transition: all 0.15s ease;
    border: 1px solid #e2e8f0;
    cursor: pointer;
}
.fa-unit-card:hover {
    border-color: #0dcaf0;
    background-color: #f8fafc;
}
.fa-unit-card.selected {
    border-color: #0d6efd;
    background-color: #eff6ff;
}
</style>
@endpush

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h4><i class="fas fa-exchange-alt me-2 text-primary"></i>Create Transfer</h4>
            <p class="text-muted">Create a transfer request with item &amp; individual equipment unit code selection</p>
        </div>
    </div>

    {{-- Session Alerts --}}
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

    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-4">
            <form action="{{ route('store-manager.transfers.store') }}" method="POST" id="transferForm">
                @csrf
                <div class="row mb-3 g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-dark">From Store *</label>
                        <select name="from_store_id" id="from_store_id" class="form-select form-select-sm" required>
                            <option value="">Select Source Store</option>
                            @foreach($stores as $store)
                            <option value="{{ $store->id }}" {{ old('from_store_id', request('store_id', request('from_store_id'))) == $store->id ? 'selected' : '' }}>
                                {{ $store->name }} ({{ $store->type ?? 'Store' }})
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-dark">To Store *</label>
                        <select name="to_store_id" id="to_store_id" class="form-select form-select-sm" required>
                            <option value="">Select Destination Store</option>
                            @foreach($stores as $store)
                            <option value="{{ $store->id }}" {{ old('to_store_id') == $store->id ? 'selected' : '' }}>
                                {{ $store->name }} ({{ $store->type ?? 'Store' }})
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row mb-3 g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-dark">Required Date</label>
                        <input type="date" name="required_date" class="form-control form-control-sm" value="{{ old('required_date', today()->toDateString()) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-dark">Reason</label>
                        <input type="text" name="reason" class="form-control form-control-sm" placeholder="Transfer reason (e.g. Site concrete works, Equipment relocation)">
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-boxes-stacked me-2 text-primary"></i>Transfer Items</h5>
                    <small class="text-muted">Selecting Fixed Assets unlocks individual unit code selection</small>
                </div>

                <div id="items-container">
                    {{-- Row 0 --}}
                    <div class="item-block border rounded-3 p-3 mb-3 bg-white shadow-xs" data-row-index="0">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-5">
                                <label class="form-label small fw-bold text-muted mb-1">Product / Material *</label>
                                <select name="items[0][product_id]" class="form-select form-select-sm product-select" required>
                                    <option value="">Select Product / Equipment</option>
                                    @foreach($products as $product)
                                    <option value="{{ $product->id }}" 
                                            data-unit="{{ $product->unit }}" 
                                            data-is-fa="{{ !empty($product->is_fixed_asset) ? '1' : '0' }}"
                                            {{ old('items.0.product_id', request('product_id')) == $product->id ? 'selected' : '' }}>
                                        {{ $product->name }} ({{ $product->code ?? $product->sku ?? 'PRD' }}) {{ !empty($product->is_fixed_asset) ? '★ [Fixed Asset]' : '' }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-muted mb-1">Quantity *</label>
                                <input type="number" name="items[0][quantity]" class="form-control form-control-sm quantity-input" placeholder="Quantity" step="0.001" min="0.001" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold text-muted mb-1">Unit</label>
                                <input type="text" name="items[0][unit]" class="form-control form-control-sm unit-input" placeholder="pcs">
                            </div>
                            <div class="col-md-2 text-end">
                                <button type="button" class="btn btn-outline-danger btn-sm remove-item" title="Remove item row">
                                    <i class="fas fa-times me-1"></i>Remove
                                </button>
                            </div>
                        </div>

                        {{-- DYNAMIC FIXED ASSET UNIT CODES BOX --}}
                        <div class="fa-units-wrapper d-none mt-3 pt-3 border-top">
                            <div class="card border-info border-opacity-25 bg-info bg-opacity-10 p-3 rounded-3 shadow-none">
                                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-barcode text-primary fs-5"></i>
                                        <div>
                                            <strong class="text-dark small d-block">Select Individual Unit Codes to Transfer:</strong>
                                            <span class="text-muted" style="font-size: 0.72rem;">Only units available in the selected origin store are listed</span>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <div class="input-group input-group-sm fa-qty-select-group d-none" style="width: 170px;">
                                            <span class="input-group-text bg-white small px-2 text-muted fw-semibold" style="font-size: 0.75rem;">Qty</span>
                                            <input type="number" class="form-control form-control-sm text-center fa-quick-qty-input" placeholder="e.g. 5" min="1" step="1">
                                            <button type="button" class="btn btn-outline-primary fa-apply-qty-btn fw-semibold px-2" title="Select this quantity of units" style="font-size: 0.75rem;">
                                                Select
                                            </button>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-primary select-all-fa-btn py-1 px-2.5 fw-semibold shadow-xs" style="font-size: 0.78rem;">
                                            <i class="fa-solid fa-check-double me-1"></i>Select All
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary deselect-all-fa-btn py-1 px-2.5 fw-semibold shadow-xs d-none" style="font-size: 0.78rem;">
                                            <i class="fa-solid fa-xmark me-1"></i>Deselect All
                                        </button>
                                        <span class="badge bg-primary px-2.5 py-1.5 unit-codes-count">0 Selected</span>
                                    </div>
                                </div>
                                <div class="fa-units-list-container" style="max-height: 420px; overflow-y: auto; padding: 4px;">
                                    {{-- Dynamically populated via AJAX --}}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" id="add-item" class="btn btn-sm btn-outline-primary fw-semibold mt-1">
                    <i class="fas fa-plus me-1"></i>Add Another Item
                </button>

                <hr class="my-4">

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('store-manager.transfers.index') }}" class="btn btn-light border px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm" id="submitTransferBtn">
                        <i class="fas fa-paper-plane me-1"></i>Create Transfer &amp; Send to General Service
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
let itemIndex = 1;

// Re-usable products options HTML
const productsOptionsHtml = `@foreach($products as $product)<option value="{{ $product->id }}" data-unit="{{ $product->unit }}" data-is-fa="{{ !empty($product->is_fixed_asset) ? '1' : '0' }}">{{ $product->name }} ({{ $product->code ?? $product->sku ?? 'PRD' }}) {{ !empty($product->is_fixed_asset) ? '★ [Fixed Asset]' : '' }}</option>@endforeach`;

// Add new row
$('#add-item').click(function() {
    let rowHtml = `
    <div class="item-block border rounded-3 p-3 mb-3 bg-white shadow-xs" data-row-index="${itemIndex}">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-bold text-muted mb-1">Product / Material *</label>
                <select name="items[${itemIndex}][product_id]" class="form-select form-select-sm product-select" required>
                    <option value="">Select Product / Equipment</option>
                    ${productsOptionsHtml}
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">Quantity *</label>
                <input type="number" name="items[${itemIndex}][quantity]" class="form-control form-control-sm quantity-input" placeholder="Quantity" step="0.001" min="0.001" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Unit</label>
                <input type="text" name="items[${itemIndex}][unit]" class="form-control form-control-sm unit-input" placeholder="pcs">
            </div>
            <div class="col-md-2 text-end">
                <button type="button" class="btn btn-outline-danger btn-sm remove-item" title="Remove item row">
                    <i class="fas fa-times me-1"></i>Remove
                </button>
            </div>
        </div>

        {{-- Dynamic Fixed Asset Unit Codes Box --}}
        <div class="fa-units-wrapper d-none mt-3 pt-3 border-top">
            <div class="card border-info border-opacity-25 bg-info bg-opacity-10 p-3 rounded-3 shadow-none">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-barcode text-primary fs-5"></i>
                        <div>
                            <strong class="text-dark small d-block">Select Individual Unit Codes to Transfer:</strong>
                            <span class="text-muted" style="font-size: 0.72rem;">Only units available in the selected origin store are listed</span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="input-group input-group-sm fa-qty-select-group d-none" style="width: 170px;">
                            <span class="input-group-text bg-white small px-2 text-muted fw-semibold" style="font-size: 0.75rem;">Qty</span>
                            <input type="number" class="form-control form-control-sm text-center fa-quick-qty-input" placeholder="e.g. 5" min="1" step="1">
                            <button type="button" class="btn btn-outline-primary fa-apply-qty-btn fw-semibold px-2" title="Select this quantity of units" style="font-size: 0.75rem;">
                                Select
                            </button>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary select-all-fa-btn py-1 px-2.5 fw-semibold shadow-xs" style="font-size: 0.78rem;">
                            <i class="fa-solid fa-check-double me-1"></i>Select All
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary deselect-all-fa-btn py-1 px-2.5 fw-semibold shadow-xs d-none" style="font-size: 0.78rem;">
                            <i class="fa-solid fa-xmark me-1"></i>Deselect All
                        </button>
                        <span class="badge bg-primary px-2.5 py-1.5 unit-codes-count">0 Selected</span>
                    </div>
                </div>
                <div class="fa-units-list-container" style="max-height: 420px; overflow-y: auto; padding: 4px;">
                    {{-- Dynamically populated via AJAX --}}
                </div>
            </div>
        </div>
    </div>`;

    $('#items-container').append(rowHtml);
    itemIndex++;
});

// Remove item row
$(document).on('click', '.remove-item', function() {
    if ($('.item-block').length > 1) {
        $(this).closest('.item-block').remove();
    } else {
        alert('A transfer must contain at least one item.');
    }
});

// Handle product selection change
$(document).on('change', '.product-select', function() {
    const $block = $(this).closest('.item-block');
    const selectedOpt = $(this).find('option:selected');
    const unit = selectedOpt.data('unit') || 'pcs';
    const isFa = selectedOpt.data('is-fa') == '1';

    $block.find('.unit-input').val(unit);
    handleFixedAssetUnitBox($block, isFa);
});

// Handle From Store change -> reload unit codes for all fixed asset rows
$('#from_store_id').on('change', function() {
    $('.item-block').each(function() {
        const $block = $(this);
        const isFa = $block.find('.product-select option:selected').data('is-fa') == '1';
        if (isFa) {
            handleFixedAssetUnitBox($block, true);
        }
    });
});

// Load / toggle Fixed Asset Unit Code box
function handleFixedAssetUnitBox($block, isFa) {
    const $wrapper = $block.find('.fa-units-wrapper');
    const $container = $block.find('.fa-units-list-container');
    const $qtyInput = $block.find('.quantity-input');
    const $countBadge = $block.find('.unit-codes-count');
    const $qtyGroup = $block.find('.fa-qty-select-group');
    const $quickQtyInput = $block.find('.fa-quick-qty-input');
    const rowIndex = $block.data('row-index');

    if (!isFa) {
        $wrapper.addClass('d-none');
        $container.empty();
        $qtyGroup.addClass('d-none');
        $qtyInput.prop('readonly', false).removeAttr('max').attr('placeholder', 'Quantity').attr('step', '0.001').attr('min', '0.001');
        return;
    }

    $wrapper.removeClass('d-none');
    $qtyInput.prop('readonly', false).val('').removeAttr('max').attr('placeholder', 'Enter qty or check codes').attr('step', '1').attr('min', '1');
    $countBadge.text('0 Selected');
    $quickQtyInput.val('');
    $qtyGroup.addClass('d-none');
    $block.find('.select-all-fa-btn').removeClass('d-none');
    $block.find('.deselect-all-fa-btn').addClass('d-none');

    const fromStoreId = $('#from_store_id').val();
    const productId = $block.find('.product-select').val();

    if (!fromStoreId) {
        $block.find('.select-all-fa-btn').addClass('d-none');
        $block.find('.deselect-all-fa-btn').addClass('d-none');
        $qtyGroup.addClass('d-none');
        $container.html(`
            <div class="alert alert-warning py-2 px-3 mb-0 small border-0">
                <i class="fa-solid fa-circle-exclamation me-1"></i>
                Please select <strong>"From Store"</strong> at the top to load available unit codes for this equipment.
            </div>
        `);
        return;
    }

    if (!productId) {
        $block.find('.select-all-fa-btn').addClass('d-none');
        $block.find('.deselect-all-fa-btn').addClass('d-none');
        $qtyGroup.addClass('d-none');
        $container.empty();
        return;
    }

    $container.html(`
        <div class="text-center py-3 text-muted">
            <i class="fa-solid fa-spinner fa-spin me-2 text-primary"></i>Loading available unit codes from store...
        </div>
    `);

    const ajaxUrl = "{{ Route::has('store-manager.fixed-assets.available-ajax') ? route('store-manager.fixed-assets.available-ajax') : (Route::has('fixed-assets.available-ajax') ? route('fixed-assets.available-ajax') : url('store-manager/fixed-assets/available-ajax')) }}";

    fetch(`${ajaxUrl}?store_id=${fromStoreId}&product_id=${productId}`)
        .then(res => res.json())
        .then(data => {
            if (!data.units || data.units.length === 0) {
                $block.find('.select-all-fa-btn').addClass('d-none');
                $block.find('.deselect-all-fa-btn').addClass('d-none');
                $qtyGroup.addClass('d-none');
                $container.html(`
                    <div class="alert alert-info py-2 px-3 mb-0 small border-0">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        No specific individual tag codes found for this asset in the selected store. You can enter the transfer quantity directly in the Quantity box above to transfer from store inventory.
                    </div>
                `);
                return;
            }

            const totalAvail = data.units.length;
            $qtyGroup.removeClass('d-none');
            $quickQtyInput.attr('max', totalAvail).attr('placeholder', `Max ${totalAvail}`);
            $qtyInput.attr('max', totalAvail).attr('title', `Up to ${totalAvail} units available in this store`);
            $block.find('.select-all-fa-btn').removeClass('d-none');
            $block.find('.deselect-all-fa-btn').addClass('d-none');

            let html = '<div class="row g-2">';
            data.units.forEach(u => {
                html += `
                <div class="col-md-6 col-lg-4">
                    <label class="d-flex align-items-start p-2 rounded bg-white shadow-xs fa-unit-card gap-2 mb-0" style="cursor: pointer; transition: all 0.15s ease;">
                        <input type="checkbox" class="form-check-input mt-1 fa-unit-checkbox" 
                               name="items[${rowIndex}][unit_codes][]" 
                               value="${u.unit_code}" 
                               data-unit-id="${u.id}">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="badge bg-dark font-monospace px-2 py-1">${u.unit_code}</span>
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-1" style="font-size:0.68rem;">In Store</span>
                            </div>
                            <div class="text-muted mt-1" style="font-size:0.75rem;">
                                ${u.brand || u.model ? `<span>${u.brand || ''} ${u.model || ''}</span>` : ''}
                                ${u.serial_number ? `<span>&bull; SN: ${u.serial_number}</span>` : ''}
                                ${u.plate_number ? `<span>&bull; Plate: ${u.plate_number}</span>` : ''}
                            </div>
                        </div>
                    </label>
                </div>`;
            });
            html += '</div>';

            $container.html(html);
        })
        .catch(err => {
            $block.find('.select-all-fa-btn').addClass('d-none');
            $block.find('.deselect-all-fa-btn').addClass('d-none');
            $qtyGroup.addClass('d-none');
            $container.html(`
                <div class="text-danger small py-2">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>Failed to fetch units: ${err.message}
                </div>
            `);
        });
}

// Helper to select N units by quantity within an item block
function applyUnitQuantityToBlock($block, qty, updateMainQty = true) {
    const $checkboxes = $block.find('.fa-unit-checkbox');
    const total = $checkboxes.length;
    if (total === 0) return;

    let targetQty = parseInt(qty, 10);
    if (isNaN(targetQty) || targetQty <= 0) {
        $checkboxes.prop('checked', false);
        $block.find('.fa-unit-card').removeClass('selected border-primary bg-primary bg-opacity-10');
        if (updateMainQty) {
            $block.find('.quantity-input').val('');
        }
        $block.find('.fa-quick-qty-input').val('');
        $block.find('.unit-codes-count').text('0 Selected');
        $block.find('.select-all-fa-btn').removeClass('d-none');
        $block.find('.deselect-all-fa-btn').addClass('d-none');
        return;
    }

    if (targetQty > total) {
        targetQty = total;
    }

    $checkboxes.each(function(index) {
        const shouldCheck = (index < targetQty);
        $(this).prop('checked', shouldCheck);
        $(this).closest('.fa-unit-card').toggleClass('selected border-primary bg-primary bg-opacity-10', shouldCheck);
    });

    if (updateMainQty) {
        $block.find('.quantity-input').val(targetQty);
    }
    $block.find('.fa-quick-qty-input').val(targetQty);
    $block.find('.unit-codes-count').text(`${targetQty} Selected`);

    if (targetQty === total) {
        $block.find('.select-all-fa-btn').addClass('d-none');
        $block.find('.deselect-all-fa-btn').removeClass('d-none');
    } else {
        $block.find('.select-all-fa-btn').removeClass('d-none');
        $block.find('.deselect-all-fa-btn').addClass('d-none');
    }
}

// Click "Select All" button for fixed asset units
$(document).on('click', '.select-all-fa-btn', function() {
    const $block = $(this).closest('.item-block');
    const $checkboxes = $block.find('.fa-unit-checkbox');
    $checkboxes.prop('checked', true);
    $block.find('.fa-unit-card').addClass('selected border-primary bg-primary bg-opacity-10');

    const count = $checkboxes.length;
    $block.find('.quantity-input').val(count > 0 ? count : '');
    $block.find('.fa-quick-qty-input').val(count > 0 ? count : '');
    $block.find('.unit-codes-count').text(`${count} Selected`);

    $(this).addClass('d-none');
    $block.find('.deselect-all-fa-btn').removeClass('d-none');
});

// Click "Deselect All" button for fixed asset units
$(document).on('click', '.deselect-all-fa-btn', function() {
    const $block = $(this).closest('.item-block');
    const $checkboxes = $block.find('.fa-unit-checkbox');
    $checkboxes.prop('checked', false);
    $block.find('.fa-unit-card').removeClass('selected border-primary bg-primary bg-opacity-10');

    $block.find('.quantity-input').val('');
    $block.find('.fa-quick-qty-input').val('');
    $block.find('.unit-codes-count').text(`0 Selected`);

    $(this).addClass('d-none');
    $block.find('.select-all-fa-btn').removeClass('d-none');
});

// Click "Select" button next to Qty input in FA toolbar
$(document).on('click', '.fa-apply-qty-btn', function() {
    const $block = $(this).closest('.item-block');
    const qty = $block.find('.fa-quick-qty-input').val();
    applyUnitQuantityToBlock($block, qty, true);
});

// Press Enter in quick qty input
$(document).on('keydown', '.fa-quick-qty-input', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const $block = $(this).closest('.item-block');
        const qty = $(this).val();
        applyUnitQuantityToBlock($block, qty, true);
    }
});

// Typing in main Quantity input for a fixed asset row
$(document).on('input change', '.quantity-input', function() {
    const $block = $(this).closest('.item-block');
    const isFa = $block.find('.product-select option:selected').data('is-fa') == '1';
    if (!isFa) return;

    const val = $(this).val();
    applyUnitQuantityToBlock($block, val, false);
});

// When unit checkbox is clicked -> update quantity input and selected counter
$(document).on('change', '.fa-unit-checkbox', function() {
    const $block = $(this).closest('.item-block');
    const $label = $(this).closest('.fa-unit-card');
    $label.toggleClass('selected border-primary bg-primary bg-opacity-10', this.checked);

    const total = $block.find('.fa-unit-checkbox').length;
    const count = $block.find('.fa-unit-checkbox:checked').length;
    $block.find('.quantity-input').val(count > 0 ? count : '');
    $block.find('.fa-quick-qty-input').val(count > 0 ? count : '');
    $block.find('.unit-codes-count').text(`${count} Selected`);

    if (total > 0 && count === total) {
        $block.find('.select-all-fa-btn').addClass('d-none');
        $block.find('.deselect-all-fa-btn').removeClass('d-none');
    } else {
        $block.find('.select-all-fa-btn').removeClass('d-none');
        $block.find('.deselect-all-fa-btn').addClass('d-none');
    }
});

// Form submission validation: Ensure valid quantity or unit codes
$('#transferForm').on('submit', function(e) {
    let isValid = true;
    let errorMsg = '';

    $('.item-block').each(function() {
        const $block = $(this);
        const isFa = $block.find('.product-select option:selected').data('is-fa') == '1';
        const prodName = $block.find('.product-select option:selected').text().trim();
        const totalCheckboxes = $block.find('.fa-unit-checkbox').length;
        const count = $block.find('.fa-unit-checkbox:checked').length;
        const qtyVal = parseFloat($block.find('.quantity-input').val()) || 0;

        if (isFa && totalCheckboxes > 0 && count === 0) {
            if (qtyVal > 0) {
                // Automatically select first qtyVal checkboxes for convenience
                const toCheck = Math.min(Math.floor(qtyVal), totalCheckboxes);
                $block.find('.fa-unit-checkbox').slice(0, toCheck).prop('checked', true);
                $block.find('.unit-codes-count').text(`${toCheck} Selected`);
            } else {
                isValid = false;
                errorMsg = `Please select at least one unit code or enter quantity for: "${prodName}".`;
                $block.find('.fa-units-wrapper')[0].scrollIntoView({ behavior: 'smooth' });
                return false;
            }
        } else if (qtyVal <= 0 && count === 0) {
            isValid = false;
            errorMsg = `Please enter a valid transfer quantity for: "${prodName}".`;
            $block.find('.quantity-input').focus();
            return false;
        }

        // OPTIMIZATION: Serialize checked unit codes into a single input to stay well within PHP max_input_vars limit
        const checkedCodes = [];
        $block.find('.fa-unit-checkbox:checked').each(function() {
            checkedCodes.push($(this).val());
        });

        $block.find('.fa-serialized-codes').remove();
        if (checkedCodes.length > 0) {
            $block.find('.fa-unit-checkbox').removeAttr('name');
            $block.append(`<input type="hidden" class="fa-serialized-codes" name="items[${$block.data('row-index') || 0}][unit_codes]" value='${JSON.stringify(checkedCodes)}'>`);
        }
    });

    if (!isValid) {
        e.preventDefault();
        alert(errorMsg);
        return false;
    }

    const $btn = $('#submitTransferBtn');
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Processing Transfer...');
});

// Auto-trigger product change on load if pre-selected via query string
$(document).ready(function() {
    if ($('.item-block[data-row-index="0"] .product-select').val()) {
        $('.item-block[data-row-index="0"] .product-select').trigger('change');
    }
});
</script>
@endpush
@endsection
