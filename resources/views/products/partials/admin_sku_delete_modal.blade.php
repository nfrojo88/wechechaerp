@if(auth()->check() && auth()->user()->isGlobalAdmin())
<!-- Modal: Global Admin Complete Material Delete by SKU -->
<div class="modal fade" id="adminSkuDeleteModal" tabindex="-1" aria-labelledby="adminSkuDeleteModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <!-- Modal Header -->
            <div class="modal-header bg-danger text-white py-3 px-4">
                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-white bg-opacity-25 text-white p-2.5 rounded-3 fs-5">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </span>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="modal-title fw-bold mb-0" id="adminSkuDeleteModalLabel">Complete Material Deletion by SKU</h5>
                            <span class="badge bg-black bg-opacity-25 text-white text-uppercase font-monospace" style="font-size:0.68rem; letter-spacing:0.05em;">
                                <i class="fa-solid fa-shield-halved me-1"></i> Global Admin Only
                            </span>
                        </div>
                        <small class="text-white-50">Permanently purge materials from catalog, store inventory, price history, and ledger references.</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body p-4 bg-white">
                <!-- Critical Warning Notice -->
                <div class="alert alert-danger border-danger border-opacity-25 bg-danger bg-opacity-10 rounded-3 p-3 mb-3 d-flex align-items-start gap-3">
                    <i class="fa-solid fa-radiation text-danger fs-4 mt-0.5"></i>
                    <div>
                        <strong class="text-danger d-block">Irreversible Permanent Purge</strong>
                        <span class="small text-dark">
                            Unlike regular archiving (soft-delete), this action will <strong>completely and permanently eradicate</strong> the material record, its store inventory balances, movement history, price snapshots, and equipment mappings from the database.
                        </span>
                    </div>
                </div>

                <form method="POST" action="{{ route('products.complete-delete-by-sku') }}" id="adminCompleteDeleteSkuForm">
                    @csrf

                    <!-- SKU Input Group -->
                    <div class="mb-3">
                        <label for="adminDeleteSkuInput" class="form-label small fw-bold text-dark text-uppercase mb-1">
                            <i class="fa-solid fa-barcode text-primary me-1"></i> Material SKU Code <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light font-monospace fw-bold text-secondary">
                                <i class="fa-solid fa-hashtag"></i>
                            </span>
                            <input type="text" name="sku" id="adminDeleteSkuInput" 
                                   class="form-control form-control-lg font-monospace fw-bold text-dark" 
                                   placeholder="e.g. MAT-1002 or FA-TRUC-001 (or multiple comma-separated)" 
                                   required autocomplete="off" oninput="handleSkuInputDebounce(this.value)">
                            <button type="button" class="btn btn-outline-secondary px-3" onclick="triggerManualSkuLookup()" title="Inspect SKU Details">
                                <i class="fa-solid fa-magnifying-glass me-1"></i> Inspect
                            </button>
                        </div>
                        <div class="form-text text-muted small">
                            Enter the exact material SKU code. Multiple SKUs can be separated by commas or newlines.
                        </div>
                    </div>

                    <!-- Live SKU Lookup Preview Card -->
                    <div id="skuLookupContainer" class="mb-3" style="display: none;">
                        <div class="card border rounded-3 overflow-hidden shadow-sm">
                            <div class="card-header py-2 px-3 bg-light d-flex justify-content-between align-items-center">
                                <span class="small fw-bold text-muted text-uppercase" style="font-size:0.75rem;">
                                    <i class="fa-solid fa-circle-info text-info me-1"></i> Material Inspection Preview
                                </span>
                                <span id="skuLookupStatusBadge" class="badge bg-secondary small">Checking...</span>
                            </div>
                            <div class="card-body p-3" id="skuLookupCardBody">
                                <!-- Dynamic content populated by JS -->
                            </div>
                        </div>
                    </div>

                    <!-- Confirmation Guard Input -->
                    <div class="mb-3 p-3 rounded-3 border border-danger-subtle bg-danger bg-opacity-10">
                        <label for="adminDeleteConfirmInput" class="form-label small fw-bold text-danger text-uppercase mb-1">
                            <i class="fa-solid fa-lock text-danger me-1"></i> Type Confirmation to Unlock Delete <span class="text-danger">*</span>
                        </label>
                        <p class="small text-muted mb-2">
                            To prevent accidental deletions, please type <code class="fw-bold text-danger">DELETE</code> or the target SKU code below:
                        </p>
                        <input type="text" name="confirm_sku" id="adminDeleteConfirmInput" 
                               class="form-control font-monospace fw-bold" 
                               placeholder="Type DELETE or exact SKU here..." 
                               required autocomplete="off" oninput="validateDeleteConfirmation()">
                        <div id="confirmFeedbackText" class="small mt-1 text-muted">Waiting for confirmation input...</div>
                    </div>

                    <!-- Modal Actions -->
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <button type="button" class="btn btn-outline-secondary px-4 fw-semibold" data-bs-dismiss="modal">
                            <i class="fa-solid fa-xmark me-1"></i> Cancel
                        </button>
                        <button type="submit" id="adminDeleteSubmitBtn" class="btn btn-danger fw-bold px-4 py-2 shadow-sm" disabled>
                            <i class="fa-solid fa-trash-can me-1"></i> Permanently Delete Material
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let skuLookupDebounceTimer = null;

function openSkuDeleteModal(sku = '', name = '') {
    const modalEl = document.getElementById('adminSkuDeleteModal');
    if (!modalEl) return;

    const skuInput = document.getElementById('adminDeleteSkuInput');
    const confirmInput = document.getElementById('adminDeleteConfirmInput');
    const submitBtn = document.getElementById('adminDeleteSubmitBtn');
    const feedbackText = document.getElementById('confirmFeedbackText');

    if (skuInput) {
        skuInput.value = sku;
    }
    if (confirmInput) {
        confirmInput.value = '';
    }
    if (submitBtn) {
        submitBtn.disabled = true;
    }
    if (feedbackText) {
        feedbackText.className = 'small mt-1 text-muted';
        feedbackText.innerHTML = 'Waiting for confirmation input...';
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    if (sku) {
        lookupSkuDetails(sku);
    } else {
        const container = document.getElementById('skuLookupContainer');
        if (container) container.style.display = 'none';
    }
}

function handleSkuInputDebounce(value) {
    clearTimeout(skuLookupDebounceTimer);
    validateDeleteConfirmation();

    const trimmed = (value || '').trim();
    if (!trimmed) {
        const container = document.getElementById('skuLookupContainer');
        if (container) container.style.display = 'none';
        return;
    }

    // Only lookup the first SKU if comma-separated
    const firstSku = trimmed.split(/[\r\n,]+/)[0].trim();
    if (!firstSku) return;

    skuLookupDebounceTimer = setTimeout(() => {
        lookupSkuDetails(firstSku);
    }, 400);
}

function triggerManualSkuLookup() {
    const skuInput = document.getElementById('adminDeleteSkuInput');
    const val = (skuInput?.value || '').trim();
    if (!val) {
        alert('Please enter a SKU code first.');
        return;
    }
    const firstSku = val.split(/[\r\n,]+/)[0].trim();
    lookupSkuDetails(firstSku);
}

function lookupSkuDetails(sku) {
    const container = document.getElementById('skuLookupContainer');
    const badge = document.getElementById('skuLookupStatusBadge');
    const body = document.getElementById('skuLookupCardBody');

    if (!container || !badge || !body) return;

    container.style.display = 'block';
    badge.className = 'badge bg-warning text-dark small';
    badge.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Searching...';
    body.innerHTML = '<div class="text-muted small py-2 text-center"><i class="fa-solid fa-spinner fa-spin me-1"></i> Inspecting material catalog and store inventory...</div>';

    fetch(`{{ route('products.lookup-by-sku') }}?sku=${encodeURIComponent(sku)}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.found && data.products && data.products.length > 0) {
            badge.className = 'badge bg-success small';
            badge.innerHTML = `<i class="fa-solid fa-check me-1"></i>${data.count} Match Found`;

            let html = '';
            data.products.forEach(p => {
                const stockBadge = p.stock_on_hand > 0 
                    ? `<span class="badge bg-warning text-dark border"><i class="fa-solid fa-boxes-stacked me-1"></i>Stock: ${p.stock_on_hand} ${p.unit}</span>`
                    : `<span class="badge bg-light text-muted border">0 Stock</span>`;

                const statusBadge = p.is_soft_deleted 
                    ? `<span class="badge bg-secondary">Archived</span>`
                    : `<span class="badge bg-success">Active</span>`;

                html += `
                    <div class="p-2 border rounded bg-light mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold text-dark fs-6">${escapeHtml(p.name)}</span>
                            <div class="d-flex gap-1 align-items-center">
                                ${statusBadge}
                                ${stockBadge}
                            </div>
                        </div>
                        <div class="small text-muted d-flex flex-wrap gap-3">
                            <span><strong>SKU:</strong> <code class="text-primary fw-bold">${escapeHtml(p.sku)}</code></span>
                            <span><strong>Category:</strong> ${escapeHtml(p.category)}</span>
                            <span><strong>Unit:</strong> ${escapeHtml(p.unit)}</span>
                            <span><strong>Unit Price:</strong> ${p.unit_price > 0 ? 'ETB ' + p.unit_price.toFixed(2) : '—'}</span>
                        </div>
                    </div>
                `;
            });
            body.innerHTML = html;
        } else {
            badge.className = 'badge bg-secondary small';
            badge.innerHTML = '<i class="fa-solid fa-circle-question me-1"></i>Not in Catalog';
            body.innerHTML = `
                <div class="alert alert-warning py-2 px-3 small mb-0 border-warning">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                    ${escapeHtml(data.message || `No material currently matches SKU '${sku}'. Purge will still cleanse any lingering inventory or asset records with this SKU.`)}
                </div>
            `;
        }
    })
    .catch(err => {
        badge.className = 'badge bg-danger small';
        badge.innerHTML = 'Error';
        body.innerHTML = `<div class="small text-danger">Lookup failed: ${escapeHtml(err.message)}</div>`;
    });
}

function validateDeleteConfirmation() {
    const skuInput = document.getElementById('adminDeleteSkuInput');
    const confirmInput = document.getElementById('adminDeleteConfirmInput');
    const submitBtn = document.getElementById('adminDeleteSubmitBtn');
    const feedbackText = document.getElementById('confirmFeedbackText');

    if (!skuInput || !confirmInput || !submitBtn || !feedbackText) return;

    const rawSku = (skuInput.value || '').trim();
    const firstSku = rawSku.split(/[\r\n,]+/)[0].trim();
    const typed = (confirmInput.value || '').trim();

    if (!typed) {
        submitBtn.disabled = true;
        feedbackText.className = 'small mt-1 text-muted';
        feedbackText.innerHTML = 'Waiting for confirmation input...';
        return;
    }

    const matchesDelete = typed.toUpperCase() === 'DELETE';
    const matchesSku = firstSku && typed.toLowerCase() === firstSku.toLowerCase();

    if (matchesDelete || matchesSku) {
        submitBtn.disabled = false;
        feedbackText.className = 'small mt-1 text-success fw-bold';
        feedbackText.innerHTML = '<i class="fa-solid fa-check-circle me-1"></i> Confirmation accepted! Ready to permanently delete.';
    } else {
        submitBtn.disabled = true;
        feedbackText.className = 'small mt-1 text-danger';
        feedbackText.innerHTML = `<i class="fa-solid fa-circle-xmark me-1"></i> Does not match 'DELETE' or '${firstSku}'.`;
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}
</script>
@endif
