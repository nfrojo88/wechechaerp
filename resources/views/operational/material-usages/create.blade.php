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
            <div class="card-header bg-white py-3 px-4 border-bottom">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div>
                        <h5 class="card-title fw-bold text-dark mb-0">
                            <i class="fa-solid fa-cubes-stacked text-primary me-2"></i>2. Itemized Consumed Materials (የተፈጁ ዕቃዎች ዝርዝር)
                        </h5>
                        <small class="text-muted">Select items from store inventory and specify quantity issued/consumed.</small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-light text-secondary border px-3 py-2 rounded-pill font-monospace" id="materialCountBadge">
                            <i class="fa-solid fa-boxes-stacked me-1 text-primary"></i> <span id="materialTotalCount">0</span> Materials Available (A-Z)
                        </span>
                        <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm fw-bold" onclick="addConsumptionRow()">
                            <i class="fa-solid fa-plus me-1"></i> Add Material Item (እቃ ጨምር)
                        </button>
                    </div>
                </div>

                {{-- Unlocked Material Search Toolbar in Section 2 --}}
                <div class="p-3 bg-light rounded-3 border position-relative" id="sectionSearchContainer">
                    <div class="row align-items-center g-2">
                        <div class="col-md-7 col-lg-7">
                            <label for="sectionMaterialSearch" class="form-label fw-bold small text-secondary text-uppercase mb-1">
                                <i class="fa-solid fa-magnifying-glass text-primary me-1"></i> Quick Material Search &amp; Add (እቃ ፈልግና ጨምር)
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-primary">
                                    <i class="fa-solid fa-search"></i>
                                </span>
                                <input type="text" 
                                       id="sectionMaterialSearch" 
                                       class="form-control border-start-0 ps-1 fw-semibold" 
                                       placeholder="🔍 Type material name or code to search & quick-add... (e.g. Cement, Rebar, Sand)" 
                                       autocomplete="off"
                                       disabled>
                                <button class="btn btn-outline-secondary d-none" type="button" id="clearMaterialSearchBtn" onclick="clearSectionMaterialSearch()" title="Clear search">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                            <div class="form-text small text-muted" id="searchHelpText">
                                <i class="fa-solid fa-circle-info text-primary me-1"></i>Materials ordered alphabetically (A-Z). Select an Issuing Store in Section 1 to unlock search.
                            </div>
                        </div>

                        <div class="col-md-5 col-lg-5 text-md-end pt-md-3">
                            <span id="searchStatusNotice" class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2 rounded-pill font-monospace">
                                <i class="fa-solid fa-lock me-1"></i> Select store above to unlock search
                            </span>
                        </div>
                    </div>

                    {{-- Floating Live Search Autocomplete Results --}}
                    <div id="sectionSearchResults" class="list-group shadow-lg position-absolute w-100 start-0 px-3 mt-1 d-none" style="z-index: 1060; max-height: 320px; overflow-y: auto;">
                        {{-- Populated dynamically via JS --}}
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0" id="consumptionItemsTable">
                        <thead class="table-light small text-secondary text-uppercase">
                            <tr>
                                <th style="width: 38%;">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>Material / Product <span class="text-danger">*</span></span>
                                        <span class="badge bg-light text-primary border rounded-pill" style="font-size: 0.7rem; font-weight: 600;">
                                            <i class="fa-solid fa-arrow-down-a-z me-1"></i>Alphabetical (A-Z)
                                        </span>
                                    </div>
                                </th>
                                <th style="width: 17%;">Available Stock</th>
                                <th style="width: 17%;">Qty Consumed <span class="text-danger">*</span></th>
                                <th style="width: 23%;">Purpose / Site Notes</th>
                                <th style="width: 5%;" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="consumptionItemsTbody">
                            {{-- Row 0: options populated dynamically by JS after store inventory loads --}}
                            <tr class="consumption-row" data-row-index="0" id="consumptionRow_0">
                                <td>
                                    <div class="product-picker-container" id="pickerContainer_0">
                                        <div class="input-group input-group-sm mb-1">
                                            <span class="input-group-text bg-light text-muted border-end-0 py-1" style="font-size: 0.75rem;">
                                                <i class="fa-solid fa-filter"></i>
                                            </span>
                                            <input type="text" 
                                                   class="form-control form-control-sm border-start-0 py-1 row-filter-input" 
                                                   id="rowFilter_0" 
                                                   placeholder="Filter dropdown (A-Z)..." 
                                                   oninput="filterRowOptions(this, 0)" 
                                                   autocomplete="off" 
                                                   disabled
                                                   style="font-size: 0.8rem;">
                                            <button type="button" 
                                                    class="btn btn-outline-secondary btn-sm py-1 d-none clear-row-filter-btn" 
                                                    id="clearFilterBtn_0" 
                                                    onclick="clearRowFilter(0)" 
                                                    title="Clear filter">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </div>
                                        <select name="items[0][product_id]" id="productSelect_0" class="form-select form-select-sm product-select fw-semibold" required onchange="onProductSelectChange(this, 0)">
                                            <option value="">-- Select Store First --</option>
                                        </select>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border stock-badge px-2 py-1 font-monospace" id="stockBadge_0" data-available-qty="0">
                                        Select Store
                                    </span>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.001" min="0.001" name="items[0][quantity]" id="qtyInput_0" class="form-control fw-bold text-dark qty-input" placeholder="0.00" required oninput="validateRowQty(0)">
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

<style>
.row-filter-input:focus {
    box-shadow: none;
    border-color: #0d6efd;
}
#sectionSearchResults .list-group-item:hover {
    background-color: #f1f5f9;
    cursor: pointer;
}
.highlight-flash {
    animation: flashRowBg 1.4s ease-out;
}
@keyframes flashRowBg {
    0% { background-color: rgba(25, 135, 84, 0.22); }
    100% { background-color: transparent; }
}
</style>

{{-- Dynamic Script for Row Management, Live Stock Fetching & Alphabetical Material Search --}}
<script>
    let currentRowIndex = 1;
    let storeInventoryCache = {};
    // currentStoreProducts = products currently in stock at the selected store, sorted alphabetically
    let currentStoreProducts = [];

    document.addEventListener('DOMContentLoaded', function() {
        const storeSelect = document.getElementById('storeSelect');
        if (storeSelect && storeSelect.value) {
            loadStoreInventory(storeSelect.value);
        }

        initSectionSearch();
    });

    /**
     * Helper to safely escape HTML special characters.
     */
    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * Strictly sort products alphabetically (A-Z) by name.
     */
    function sortProductsAlphabetically(products) {
        return (products || []).slice().sort((a, b) => {
            const nameA = (a.name || '').trim();
            const nameB = (b.name || '').trim();
            return nameA.localeCompare(nameB, undefined, { numeric: true, sensitivity: 'base' });
        });
    }

    /**
     * Update Section 2 search UI state based on store selection.
     */
    function updateSearchUiState(isUnlocked, totalCount = 0) {
        const searchInput = document.getElementById('sectionMaterialSearch');
        const statusNotice = document.getElementById('searchStatusNotice');
        const countSpan = document.getElementById('materialTotalCount');
        const helpText = document.getElementById('searchHelpText');

        if (countSpan) countSpan.innerText = totalCount;

        if (isUnlocked) {
            if (searchInput) {
                searchInput.disabled = false;
                searchInput.placeholder = '🔍 Search materials alphabetically... (e.g. Cement, Rebar, Sand)';
            }
            if (statusNotice) {
                statusNotice.className = 'badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill font-monospace';
                statusNotice.innerHTML = `<i class="fa-solid fa-lock-open me-1"></i> Search Unlocked — ${totalCount} items (A-Z)`;
            }
            if (helpText) {
                helpText.innerHTML = `<i class="fa-solid fa-check-circle text-success me-1"></i>Materials ordered alphabetically (A-Z). Type above or filter within each row.`;
            }
            // Enable all in-row filters
            document.querySelectorAll('.row-filter-input').forEach(inp => inp.disabled = false);
        } else {
            if (searchInput) {
                searchInput.disabled = true;
                searchInput.value = '';
                searchInput.placeholder = 'Type material name or code to search...';
            }
            if (statusNotice) {
                statusNotice.className = 'badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2 rounded-pill font-monospace';
                statusNotice.innerHTML = `<i class="fa-solid fa-lock me-1"></i> Select store above to unlock search`;
            }
            if (helpText) {
                helpText.innerHTML = `<i class="fa-solid fa-circle-info text-primary me-1"></i>Materials ordered alphabetically (A-Z). Select an Issuing Store in Section 1 to unlock search.`;
            }
            clearSectionMaterialSearch();
            // Disable in-row filters
            document.querySelectorAll('.row-filter-input').forEach(inp => {
                inp.disabled = true;
                inp.value = '';
            });
        }
    }

    /**
     * Fetch store inventory via AJAX, sort alphabetically (A-Z), cache it, then rebuild dropdowns.
     */
    function loadStoreInventory(storeId) {
        if (!storeId) {
            currentStoreProducts = [];
            updateSearchUiState(false, 0);
            rebuildAllProductSelects();
            return;
        }

        if (storeInventoryCache[storeId]) {
            currentStoreProducts = storeInventoryCache[storeId];
            updateSearchUiState(true, currentStoreProducts.length);
            rebuildAllProductSelects();
            return;
        }

        // Show loading state on all dropdowns and search input
        document.querySelectorAll('.product-select').forEach(sel => {
            sel.innerHTML = '<option value="">⏳ Loading store inventory (A-Z)...</option>';
            sel.disabled = true;
        });

        const statusNotice = document.getElementById('searchStatusNotice');
        if (statusNotice) {
            statusNotice.className = 'badge bg-info-subtle text-info border border-info-subtle px-3 py-2 rounded-pill font-monospace';
            statusNotice.innerHTML = `<i class="fa-solid fa-spinner fa-spin me-1"></i> Loading Store Inventory...`;
        }

        fetch(`/material-usages/store-products/${storeId}`)
            .then(res => res.json())
            .then(data => {
                // Ensure strict alphabetical sorting A-Z
                storeInventoryCache[storeId] = sortProductsAlphabetically(data.products || []);
                currentStoreProducts = storeInventoryCache[storeId];
                updateSearchUiState(true, currentStoreProducts.length);
                rebuildAllProductSelects();
            })
            .catch(err => {
                console.warn('Store inventory fetch error:', err);
                currentStoreProducts = [];
                updateSearchUiState(false, 0);
                rebuildAllProductSelects();
            });
    }

    /**
     * Build <option> HTML for products, optionally filtered by keyword, ordered alphabetically.
     */
    function buildProductOptions(selectedProductId = null, filterQuery = '') {
        if (!currentStoreProducts || currentStoreProducts.length === 0) {
            return '<option value="">⚠️ No items found in stock for this store</option>';
        }

        let products = currentStoreProducts;
        if (filterQuery && filterQuery.trim().length > 0) {
            const q = filterQuery.trim().toLowerCase();
            products = currentStoreProducts.filter(p => 
                (p.name && p.name.toLowerCase().includes(q)) || 
                (p.item_code && p.item_code.toLowerCase().includes(q)) ||
                (p.unit && p.unit.toLowerCase().includes(q))
            );
        }

        let defaultLabel = filterQuery 
            ? `-- ${products.length} Matching Materials Found (A-Z) --` 
            : '-- Choose Available Material (A-Z) --';

        let html = `<option value="">${defaultLabel}</option>`;
        if (products.length === 0) {
            return `<option value="">⚠️ No materials match "${escapeHtml(filterQuery)}"</option>`;
        }

        products.forEach(p => {
            const stockLabel = p.stock_on_hand > 0
                ? `✅ In Stock: ${parseFloat(p.stock_on_hand).toLocaleString()} ${p.unit}`
                : `⚠️ Out of Stock`;
            const selected = selectedProductId && parseInt(selectedProductId) === parseInt(p.id) ? 'selected' : '';
            html += `<option value="${p.id}" data-unit="${escapeHtml(p.unit)}" data-stock="${p.stock_on_hand}" ${selected}>📦 ${escapeHtml(p.name)} (${escapeHtml(p.item_code || 'PRD')}) [${escapeHtml(p.unit)}] — ${stockLabel}</option>`;
        });

        return html;
    }

    /**
     * Rebuild all existing product dropdowns with alphabetical inventory items.
     */
    function rebuildAllProductSelects() {
        document.querySelectorAll('.consumption-row').forEach(row => {
            const idx = row.dataset.rowIndex;
            const select = row.querySelector('.product-select');
            const rowFilter = document.getElementById('rowFilter_' + idx);
            if (!select) return;

            const prevSelected = select.value;
            const filterQuery = rowFilter ? rowFilter.value : '';
            select.innerHTML = buildProductOptions(prevSelected, filterQuery);
            select.disabled = false;

            // Re-apply previous selection if it exists in options
            if (prevSelected) {
                select.value = prevSelected;
            }

            // Update stock badge for current selection
            onProductSelectChange(select, idx);
        });
    }

    /**
     * Filter dropdown options for a specific row in real-time.
     */
    function filterRowOptions(inputElem, rowIndex) {
        const query = (inputElem.value || '').trim();
        const select = document.getElementById('productSelect_' + rowIndex);
        const clearBtn = document.getElementById('clearFilterBtn_' + rowIndex);
        if (!select) return;

        if (clearBtn) {
            clearBtn.classList.toggle('d-none', query.length === 0);
        }

        const currentSelectedId = select.value;
        select.innerHTML = buildProductOptions(currentSelectedId, query);

        // If previous selection still in options, keep it selected
        if (currentSelectedId) {
            select.value = currentSelectedId;
        }

        onProductSelectChange(select, rowIndex);
    }

    /**
     * Clear in-row filter for a specific row.
     */
    function clearRowFilter(rowIndex) {
        const filterInput = document.getElementById('rowFilter_' + rowIndex);
        const clearBtn = document.getElementById('clearFilterBtn_' + rowIndex);
        if (filterInput) filterInput.value = '';
        if (clearBtn) clearBtn.classList.add('d-none');

        const select = document.getElementById('productSelect_' + rowIndex);
        if (select) {
            const currentSelectedId = select.value;
            select.innerHTML = buildProductOptions(currentSelectedId);
            if (currentSelectedId) select.value = currentSelectedId;
            onProductSelectChange(select, rowIndex);
        }
    }

    /**
     * When the store dropdown changes, reload inventory and rebuild all product dropdowns.
     */
    function loadStoreInventoryAndRebuild(storeId) {
        // Reset selections in all rows
        document.querySelectorAll('.product-select').forEach(sel => {
            sel.value = '';
        });
        document.querySelectorAll('.row-filter-input').forEach(inp => {
            inp.value = '';
        });
        document.querySelectorAll('.clear-row-filter-btn').forEach(btn => {
            btn.classList.add('d-none');
        });
        document.querySelectorAll('.stock-badge').forEach(badge => {
            badge.className = 'badge bg-light text-dark border stock-badge px-2 py-1 font-monospace';
            badge.innerText = storeId ? '⏳ Loading...' : 'Select Store';
            badge.dataset.availableQty = '0';
        });

        clearSectionMaterialSearch();
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

    /**
     * Add a new consumption table row, optionally pre-selecting a product ID.
     */
    function addConsumptionRow(preSelectedProductId = null) {
        const storeId = document.getElementById('storeSelect').value;
        if (!storeId) {
            alert('Please select an Issuing Store first before adding items.');
            const storeSelect = document.getElementById('storeSelect');
            if (storeSelect) storeSelect.focus();
            return null;
        }

        const tbody = document.getElementById('consumptionItemsTbody');
        const tr = document.createElement('tr');
        tr.className = 'consumption-row';
        tr.dataset.rowIndex = currentRowIndex;
        tr.id = 'consumptionRow_' + currentRowIndex;

        const optionsHtml = buildProductOptions(preSelectedProductId);
        const targetIndex = currentRowIndex;

        tr.innerHTML = `
            <td>
                <div class="product-picker-container" id="pickerContainer_${targetIndex}">
                    <div class="input-group input-group-sm mb-1">
                        <span class="input-group-text bg-light text-muted border-end-0 py-1" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-filter"></i>
                        </span>
                        <input type="text" 
                               class="form-control form-control-sm border-start-0 py-1 row-filter-input" 
                               id="rowFilter_${targetIndex}" 
                               placeholder="Filter dropdown (A-Z)..." 
                               oninput="filterRowOptions(this, ${targetIndex})" 
                               autocomplete="off" 
                               style="font-size: 0.8rem;">
                        <button type="button" 
                                class="btn btn-outline-secondary btn-sm py-1 d-none clear-row-filter-btn" 
                                id="clearFilterBtn_${targetIndex}" 
                                onclick="clearRowFilter(${targetIndex})" 
                                title="Clear filter">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <select name="items[${targetIndex}][product_id]" 
                            id="productSelect_${targetIndex}" 
                            class="form-select form-select-sm product-select fw-semibold" 
                            required 
                            onchange="onProductSelectChange(this, ${targetIndex})">
                        ${optionsHtml}
                    </select>
                </div>
            </td>
            <td>
                <span class="badge bg-light text-dark border stock-badge px-2 py-1 font-monospace" id="stockBadge_${targetIndex}" data-available-qty="0">
                    Select Material
                </span>
            </td>
            <td>
                <div class="input-group input-group-sm">
                    <input type="number" step="0.001" min="0.001" name="items[${targetIndex}][quantity]" id="qtyInput_${targetIndex}" class="form-control fw-bold text-dark qty-input" placeholder="0.00" required oninput="validateRowQty(${targetIndex})">
                    <span class="input-group-text bg-light unit-label" id="unitLabel_${targetIndex}">pcs</span>
                </div>
                <small class="text-danger d-none qty-warning-msg" id="qtyWarning_${targetIndex}">Exceeds available stock!</small>
            </td>
            <td>
                <input type="text" name="items[${targetIndex}][remarks]" class="form-control form-control-sm" placeholder="e.g., Purpose / Activity notes">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeConsumptionRow(this)" title="Remove Item">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </td>
        `;

        tbody.appendChild(tr);
        currentRowIndex++;

        if (preSelectedProductId) {
            const newSelect = document.getElementById('productSelect_' + targetIndex);
            if (newSelect) {
                newSelect.value = preSelectedProductId;
                onProductSelectChange(newSelect, targetIndex);
            }
        }

        return targetIndex;
    }

    function removeConsumptionRow(button) {
        const rows = document.querySelectorAll('.consumption-row');
        if (rows.length <= 1) {
            alert('At least one consumed material item is required.');
            return;
        }
        button.closest('tr').remove();
    }

    /**
     * =========================================================================
     * Section 2 Quick Material Search & Live Autocomplete Functionality
     * =========================================================================
     */
    function initSectionSearch() {
        const searchInput = document.getElementById('sectionMaterialSearch');
        const resultsContainer = document.getElementById('sectionSearchResults');
        const clearBtn = document.getElementById('clearMaterialSearchBtn');

        if (!searchInput) return;

        searchInput.addEventListener('input', function() {
            const q = this.value.trim().toLowerCase();

            if (clearBtn) {
                clearBtn.classList.toggle('d-none', q.length === 0);
            }

            if (!q) {
                resultsContainer.innerHTML = '';
                resultsContainer.classList.add('d-none');
                return;
            }

            if (!currentStoreProducts || currentStoreProducts.length === 0) {
                resultsContainer.innerHTML = `
                    <div class="list-group-item list-group-item-light text-muted py-3 text-center">
                        <i class="fa-solid fa-triangle-exclamation text-warning me-1"></i> No store inventory loaded. Please select an issuing store above.
                    </div>
                `;
                resultsContainer.classList.remove('d-none');
                return;
            }

            // Filter across material name, item code, and unit (all sorted A-Z)
            const matches = currentStoreProducts.filter(p => 
                (p.name && p.name.toLowerCase().includes(q)) ||
                (p.item_code && p.item_code.toLowerCase().includes(q)) ||
                (p.unit && p.unit.toLowerCase().includes(q))
            );

            if (matches.length === 0) {
                resultsContainer.innerHTML = `
                    <div class="list-group-item list-group-item-light text-muted py-3 text-center">
                        <i class="fa-solid fa-circle-question text-muted me-1"></i> No materials found matching "<strong>${escapeHtml(this.value)}</strong>" in this store inventory.
                    </div>
                `;
                resultsContainer.classList.remove('d-none');
                return;
            }

            let html = '';
            matches.slice(0, 25).forEach(p => {
                const inStock = p.stock_on_hand > 0;
                const stockBadge = inStock
                    ? `<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-boxes-stacked me-1"></i>${parseFloat(p.stock_on_hand).toLocaleString()} ${escapeHtml(p.unit)} in Stock</span>`
                    : `<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fa-solid fa-triangle-exclamation me-1"></i>0 ${escapeHtml(p.unit)} (Out of Stock)</span>`;

                html += `
                    <a href="javascript:void(0)" class="list-group-item list-group-item-action py-2 px-3 border-bottom d-flex justify-content-between align-items-center" onclick="addOrSelectSearchedProduct(${p.id})">
                        <div>
                            <div class="fw-bold text-dark">
                                <i class="fa-solid fa-box-open text-primary me-2"></i>${escapeHtml(p.name)}
                                <span class="badge bg-light text-secondary border font-monospace ms-2">${escapeHtml(p.item_code || 'PRD')}</span>
                            </div>
                            <small class="text-muted">Unit: <strong>${escapeHtml(p.unit)}</strong></small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            ${stockBadge}
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-bold">
                                <i class="fa-solid fa-plus me-1"></i> Add / Pick
                            </button>
                        </div>
                    </a>
                `;
            });

            resultsContainer.innerHTML = html;
            resultsContainer.classList.remove('d-none');
        });

        // Keyboard shortcut: Pressing Enter picks the first matching material; Escape clears search
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                clearSectionMaterialSearch();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const firstItem = resultsContainer.querySelector('a.list-group-item');
                if (firstItem) {
                    firstItem.click();
                }
            }
        });

        // Hide search results when clicking outside
        document.addEventListener('click', function(e) {
            const container = document.getElementById('sectionSearchContainer');
            if (resultsContainer && container && !container.contains(e.target)) {
                resultsContainer.classList.add('d-none');
            }
        });
    }

    /**
     * Clear Section 2 material search input and hide results.
     */
    function clearSectionMaterialSearch() {
        const searchInput = document.getElementById('sectionMaterialSearch');
        const resultsContainer = document.getElementById('sectionSearchResults');
        const clearBtn = document.getElementById('clearMaterialSearchBtn');

        if (searchInput) searchInput.value = '';
        if (clearBtn) clearBtn.classList.add('d-none');
        if (resultsContainer) {
            resultsContainer.innerHTML = '';
            resultsContainer.classList.add('d-none');
        }
    }

    /**
     * Pick a product from the Section 2 search results and populate or add a consumption row.
     */
    function addOrSelectSearchedProduct(productId) {
        const product = currentStoreProducts.find(p => parseInt(p.id) === parseInt(productId));
        if (!product) return;

        // Check if an existing row has no material selected yet
        let targetRow = null;
        let targetRowIndex = null;
        const rows = document.querySelectorAll('.consumption-row');
        for (let r of rows) {
            const sel = r.querySelector('.product-select');
            if (sel && (!sel.value || sel.value === '')) {
                targetRow = r;
                targetRowIndex = r.dataset.rowIndex;
                break;
            }
        }

        // If all existing rows already have a material, append a new row
        if (!targetRow) {
            targetRowIndex = addConsumptionRow(productId);
        } else {
            const sel = targetRow.querySelector('.product-select');
            if (sel) {
                // Ensure product exists in options (in case filter was active)
                sel.innerHTML = buildProductOptions(product.id);
                sel.value = product.id;
                onProductSelectChange(sel, targetRowIndex);
            }
        }

        // Clear section search box
        clearSectionMaterialSearch();

        if (targetRowIndex !== null && targetRowIndex !== undefined) {
            // Focus quantity input for fast entry
            const qtyInput = document.getElementById('qtyInput_' + targetRowIndex);
            if (qtyInput) {
                qtyInput.focus();
                qtyInput.select();
            }

            // Flash visual feedback highlight on the row
            const rowElem = document.getElementById('consumptionRow_' + targetRowIndex);
            if (rowElem) {
                rowElem.classList.remove('highlight-flash');
                void rowElem.offsetWidth; // Trigger reflow
                rowElem.classList.add('highlight-flash');
            }
        }
    }
</script>
@endsection

