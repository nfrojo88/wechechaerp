@extends('layouts.app')

@section('title', 'Log Daily Material Consumption (ዕለታዊ የዕቃዎች ፍጆታ መመዝገቢያ)')

@section('content')
<div class="container-fluid py-3">

    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">
                <i class="fa-solid fa-file-circle-plus text-success me-2"></i>Log Daily Material Consumption (ዕለታዊ ፍጆታ መዝግብ)
            </h1>
            <p class="text-muted small mb-0">
                Record materials issued from store for site work. System automatically validates available inventory and executes stock deduction.
            </p>
        </div>
        <a href="{{ route('material-usages.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Consumption List
        </a>
    </div>

    {{-- Error Alerts --}}
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><strong>Please correct the errors below:</strong>
            <ul class="mb-0 mt-1 ps-3">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form action="{{ route('material-usages.store') }}" method="POST" id="dailyConsumptionForm">
        @csrf

        {{-- Section 1: General Usage Information --}}
        <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
            <div class="card-header bg-success bg-gradient text-white py-3 px-4">
                <h5 class="card-title fw-bold mb-0">
                    <i class="fa-solid fa-clipboard-list me-2"></i>1. Daily Consumption Header Details
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-secondary text-uppercase">
                            Consumption Log # <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-hashtag text-muted"></i></span>
                            <input type="text" name="usage_no" class="form-control fw-bold font-monospace bg-light" value="{{ old('usage_no', $suggestedUsageNo) }}" required readonly>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-secondary text-uppercase">
                            Date of Consumption <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-regular fa-calendar text-muted"></i></span>
                            <input type="date" name="usage_date" class="form-control fw-semibold" value="{{ old('usage_date', date('Y-m-d')) }}" required>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-secondary text-uppercase">
                            Issuing Store <span class="text-danger">*</span>
                        </label>
                        <select name="store_id" id="storeSelect" class="form-select fw-semibold" required onchange="loadStoreInventoryAndRebuild(this.value)">
                            <option value="">-- Choose Store --</option>
                            @foreach($stores as $st)
                                <option value="{{ $st->id }}" {{ old('store_id', $selectedStoreId) == $st->id ? 'selected' : '' }}>
                                    🏪 {{ $st->name }} ({{ $st->code ?? 'STORE' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-secondary text-uppercase">
                            Project Site <span class="text-danger">*</span>
                        </label>
                        <select name="project_id" class="form-select fw-semibold" required>
                            <option value="">-- Choose Project Site --</option>
                            @foreach($projects as $pj)
                                <option value="{{ $pj->id }}" {{ old('project_id') == $pj->id ? 'selected' : '' }}>
                                    🏗️ {{ $pj->name }} ({{ $pj->code ?? 'PRJ' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-secondary text-uppercase">
                            Received / Consumed By (ተረካቢ / ሰራተኛ ስም)
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-user-tag text-muted"></i></span>
                            <input type="text" name="consumed_by_name" class="form-control" placeholder="e.g., Foreman Ahmed / Subcontractor XYZ" value="{{ old('consumed_by_name') }}">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-secondary text-uppercase">
                            Site Activity / Purpose (የስራው ዓይነት / ቦታ)
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-trowel-bricks text-muted"></i></span>
                            <input type="text" name="activity_type" class="form-control" placeholder="e.g., Column casting 3rd floor, Blockwork Zone B" value="{{ old('activity_type') }}">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-secondary text-uppercase">
                            External Slip / SIV Reference # <small class="text-muted">(Optional)</small>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-file-lines text-muted"></i></span>
                            <input type="text" name="slip_number" class="form-control" placeholder="e.g., SIV-2026-098" value="{{ old('slip_number') }}">
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold small text-secondary text-uppercase">
                            General Notes / Summary
                        </label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Provide any additional site or store keeper remarks...">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 2: Consumed Materials Multi-Item Table --}}
        <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
            <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2 border-bottom">
                <div>
                    <h5 class="card-title fw-bold text-dark mb-0">
                        <i class="fa-solid fa-cubes-stacked text-primary me-2"></i>2. Itemized Consumed Materials
                    </h5>
                    <small class="text-muted">Select items from store inventory and specify quantity issued/consumed.</small>
                </div>
                <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm fw-bold" onclick="addConsumptionRow()">
                    <i class="fa-solid fa-plus me-1"></i> Add Material Item (እቃ ጨምር)
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0" id="consumptionItemsTable">
                        <thead class="table-light small text-secondary text-uppercase">
                            <tr>
                                <th style="width: 35%;">Material / Product <span class="text-danger">*</span></th>
                                <th style="width: 18%;">Available Stock</th>
                                <th style="width: 18%;">Qty Consumed <span class="text-danger">*</span></th>
                                <th style="width: 24%;">Purpose / Site Notes</th>
                                <th style="width: 5%;" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="consumptionItemsTbody">
                            {{-- Row 0: options populated dynamically by JS after store inventory loads --}}
                            <tr class="consumption-row" data-row-index="0">
                                <td>
                                    <select name="items[0][product_id]" class="form-select product-select fw-semibold" required onchange="onProductSelectChange(this, 0)">
                                        <option value="">-- Select Store First --</option>
                                    </select>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border stock-badge px-2 py-1 font-monospace" id="stockBadge_0">
                                        Select Store
                                    </span>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.001" min="0.001" name="items[0][quantity]" class="form-control fw-bold text-dark qty-input" placeholder="0.00" required oninput="validateRowQty(0)">
                                        <span class="input-group-text bg-light unit-label" id="unitLabel_0">pcs</span>
                                    </div>
                                    <small class="text-danger d-none qty-warning-msg" id="qtyWarning_0">Exceeds available stock!</small>
                                </td>
                                <td>
                                    <input type="text" name="items[0][remarks]" class="form-control form-control-sm" placeholder="e.g., Grid 4 column concrete">
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeConsumptionRow(this)" title="Remove Item">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Footer Controls & Submission --}}
            <div class="card-footer bg-light p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" name="auto_confirm" value="1" id="autoConfirmToggle" checked>
                    <label class="form-check-label fw-bold text-dark small" for="autoConfirmToggle">
                        <i class="fa-solid fa-bolt text-warning me-1"></i>Auto-Confirm &amp; Deduct Store Inventory Immediately (ወዲያውኑ ከስቶር ቀንስ)
                    </label>
                    <div class="text-muted small ps-4">When enabled, store stock on hand will be deducted instantly upon saving.</div>
                </div>

                <div class="d-flex gap-2">
                    <a href="{{ route('material-usages.index') }}" class="btn btn-secondary rounded-pill px-4">Cancel</a>
                    <button type="submit" class="btn btn-success fw-bold rounded-pill px-4 shadow-sm" id="btnSubmitConsumption">
                        <i class="fa-solid fa-check-double me-1"></i> Save Daily Consumption (ፍጆታ መዝግብ)
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- Dynamic Script for Row Management & Live Stock Fetching --}}
<script>
    let currentRowIndex = 1;
    let storeInventoryCache = {};
    // currentStoreProducts = products currently in stock at the selected store
    let currentStoreProducts = [];

    document.addEventListener('DOMContentLoaded', function() {
        const storeSelect = document.getElementById('storeSelect');
        if (storeSelect && storeSelect.value) {
            loadStoreInventory(storeSelect.value);
        }
    });

    /**
     * Fetch store inventory via AJAX, cache it, then rebuild all product dropdowns.
     */
    function loadStoreInventory(storeId) {
        if (!storeId) {
            currentStoreProducts = [];
            rebuildAllProductSelects();
            return;
        }

        if (storeInventoryCache[storeId]) {
            currentStoreProducts = storeInventoryCache[storeId];
            rebuildAllProductSelects();
            return;
        }

        // Show loading state on all dropdowns
        document.querySelectorAll('.product-select').forEach(sel => {
            sel.innerHTML = '<option value="">⏳ Loading store inventory...</option>';
            sel.disabled = true;
        });

        fetch(`/material-usages/store-products/${storeId}`)
            .then(res => res.json())
            .then(data => {
                storeInventoryCache[storeId] = data.products || [];
                currentStoreProducts = storeInventoryCache[storeId];
                rebuildAllProductSelects();
            })
            .catch(err => {
                console.warn('Store inventory fetch error:', err);
                currentStoreProducts = [];
                rebuildAllProductSelects();
            });
    }

    /**
     * Build <option> HTML for a single product from store inventory.
     */
    function buildProductOptions(selectedProductId = null) {
        let html = '<option value="">-- Choose Available Material --</option>';
        if (currentStoreProducts.length === 0) {
            html = '<option value="">⚠️ No items in stock at this store</option>';
            return html;
        }
        currentStoreProducts.forEach(p => {
            const stockLabel = p.stock_on_hand > 0
                ? `✅ In Stock: ${parseFloat(p.stock_on_hand).toLocaleString()} ${p.unit}`
                : `⚠️ Out of Stock`;
            const selected = selectedProductId && parseInt(selectedProductId) === p.id ? 'selected' : '';
            html += `<option value="${p.id}" data-unit="${p.unit}" data-stock="${p.stock_on_hand}" ${selected}>📦 ${p.name} (${p.item_code || 'PRD'}) [${p.unit}] — ${stockLabel}</option>`;
        });
        return html;
    }

    /**
     * Rebuild all existing product dropdowns with only store-inventory items.
     */
    function rebuildAllProductSelects() {
        document.querySelectorAll('.consumption-row').forEach(row => {
            const idx = row.dataset.rowIndex;
            const select = row.querySelector('.product-select');
            if (!select) return;

            const prevSelected = select.value;
            select.innerHTML = buildProductOptions(prevSelected);
            select.disabled = false;

            // Update stock badge for current selection
            onProductSelectChange(select, idx);
        });
    }

    /**
     * When the store dropdown changes, reload inventory and rebuild all product dropdowns.
     */
    function loadStoreInventoryAndRebuild(storeId) {
        // Reset selections in all rows
        document.querySelectorAll('.product-select').forEach(sel => {
            sel.value = '';
        });
        document.querySelectorAll('.stock-badge').forEach(badge => {
            badge.className = 'badge bg-light text-dark border stock-badge px-2 py-1 font-monospace';
            badge.innerText = storeId ? '⏳ Loading...' : 'Select Store';
        });
        loadStoreInventory(storeId);
    }

    function onProductSelectChange(selectElem, rowIndex) {
        const productId = parseInt(selectElem.value);
        const stockBadge = document.getElementById('stockBadge_' + rowIndex);
        const unitLabel = document.getElementById('unitLabel_' + rowIndex);

        if (!productId) {
            if (stockBadge) {
                stockBadge.className = 'badge bg-light text-dark border stock-badge px-2 py-1 font-monospace';
                stockBadge.innerText = 'Select Material';
                stockBadge.dataset.availableQty = '0';
            }
            if (unitLabel) unitLabel.innerText = 'pcs';
            return;
        }

        const selectedOption = selectElem.options[selectElem.selectedIndex];
        const unit = selectedOption ? (selectedOption.dataset.unit || 'pcs') : 'pcs';
        const stockOnHand = selectedOption ? parseFloat(selectedOption.dataset.stock || 0) : 0;

        if (unitLabel) unitLabel.innerText = unit;

        if (stockBadge) {
            stockBadge.dataset.availableQty = stockOnHand;
            if (stockOnHand > 0) {
                stockBadge.className = 'badge bg-success-subtle text-success border border-success-subtle stock-badge px-2 py-1 font-monospace';
                stockBadge.innerHTML = `<i class="fa-solid fa-boxes-stacked me-1"></i>${stockOnHand.toLocaleString()} ${unit} in Stock`;
            } else {
                stockBadge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle stock-badge px-2 py-1 font-monospace';
                stockBadge.innerHTML = `<i class="fa-solid fa-triangle-exclamation me-1"></i>0 ${unit} (Out of Stock)`;
            }
        }

        validateRowQty(rowIndex);
    }

    function validateRowQty(rowIndex) {
        const row = document.querySelector(`.consumption-row[data-row-index="${rowIndex}"]`);
        if (!row) return;

        const qtyInput = row.querySelector('.qty-input');
        const stockBadge = document.getElementById('stockBadge_' + rowIndex);
        const warningMsg = document.getElementById('qtyWarning_' + rowIndex);

        if (!qtyInput || !stockBadge) return;

        const requested = parseFloat(qtyInput.value) || 0;
        const available = parseFloat(stockBadge.dataset.availableQty) || 0;

        if (requested > available && available > 0) {
            qtyInput.classList.add('is-invalid');
            if (warningMsg) warningMsg.classList.remove('d-none');
        } else {
            qtyInput.classList.remove('is-invalid');
            if (warningMsg) warningMsg.classList.add('d-none');
        }
    }

    function addConsumptionRow() {
        const storeId = document.getElementById('storeSelect').value;
        if (!storeId) {
            alert('Please select a store first before adding items.');
            return;
        }

        const tbody = document.getElementById('consumptionItemsTbody');
        const tr = document.createElement('tr');
        tr.className = 'consumption-row';
        tr.dataset.rowIndex = currentRowIndex;

        const optionsHtml = buildProductOptions();

        tr.innerHTML = `
            <td>
                <select name="items[${currentRowIndex}][product_id]" class="form-select product-select fw-semibold" required onchange="onProductSelectChange(this, ${currentRowIndex})">
                    ${optionsHtml}
                </select>
            </td>
            <td>
                <span class="badge bg-light text-dark border stock-badge px-2 py-1 font-monospace" id="stockBadge_${currentRowIndex}" data-available-qty="0">
                    Select Material
                </span>
            </td>
            <td>
                <div class="input-group input-group-sm">
                    <input type="number" step="0.001" min="0.001" name="items[${currentRowIndex}][quantity]" class="form-control fw-bold text-dark qty-input" placeholder="0.00" required oninput="validateRowQty(${currentRowIndex})">
                    <span class="input-group-text bg-light unit-label" id="unitLabel_${currentRowIndex}">pcs</span>
                </div>
                <small class="text-danger d-none qty-warning-msg" id="qtyWarning_${currentRowIndex}">Exceeds available stock!</small>
            </td>
            <td>
                <input type="text" name="items[${currentRowIndex}][remarks]" class="form-control form-control-sm" placeholder="e.g., Purpose / Activity notes">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeConsumptionRow(this)" title="Remove Item">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </td>
        `;

        tbody.appendChild(tr);
        currentRowIndex++;
    }

    function removeConsumptionRow(button) {
        const rows = document.querySelectorAll('.consumption-row');
        if (rows.length <= 1) {
            alert('At least one consumed material item is required.');
            return;
        }
        button.closest('tr').remove();
    }
</script>
@endsection

