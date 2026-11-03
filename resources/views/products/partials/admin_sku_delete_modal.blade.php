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
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="adminDeleteSkuInput" class="form-label small fw-bold text-dark text-uppercase mb-0">
                                <i class="fa-solid fa-barcode text-danger me-1"></i> Material SKU Code(s) <span class="text-danger">*</span>
                            </label>
                            <div class="d-flex align-items-center gap-2">
                                <span id="adminDeleteSkuCountBadge" class="badge bg-secondary-subtle text-secondary border font-monospace" style="font-size:0.75rem;">
                                    0 SKUs detected
                                </span>
                                <button type="button" class="btn btn-link btn-sm text-decoration-none p-0 text-muted" onclick="clearDeleteSkuInput()" title="Clear input">
                                    <i class="fa-solid fa-eraser me-1"></i>Clear
                                </button>
                            </div>
                        </div>
                        <div class="position-relative">
                            <textarea name="sku" id="adminDeleteSkuInput" rows="4"
                                      class="form-control font-monospace fw-bold text-dark" 
                                      placeholder="Paste single or multiple SKUs here, e.g.:&#10;1 FA-BUST&#10;2 FA-BALD&#10;FA-B RUS&#10;or comma-separated: FA-BUST, FA-BALD" 
                                      required autocomplete="off" oninput="handleSkuInputDebounce(this.value)"></textarea>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <div class="form-text text-muted small">
                                Supports numbered lists (e.g. <code>1 FA-BUST</code>), line-by-line paste, or comma-separated SKUs.
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm px-2 py-0.5" onclick="triggerManualSkuLookup()" title="Inspect first SKU Details">
                                <i class="fa-solid fa-magnifying-glass me-1"></i> Inspect First SKU
                            </button>
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

function parseSkuList(text) {
    if (!text) return [];
    return text.split(/[\r\n,]+/)
        .map(s => s.trim())
        .map(s => s.replace(/^[0-9]+[\.\t\s]+/, '').trim())
        .filter(s => s.length > 0 && s.toUpperCase() !== 'DELETE' && s.toUpperCase() !== 'CONVERT');
}

function updateDeleteSkuCount() {
    const skuInput = document.getElementById('adminDeleteSkuInput');
    const badge = document.getElementById('adminDeleteSkuCountBadge');
    if (!skuInput || !badge) return;

    const skus = parseSkuList(skuInput.value);
    const count = skus.length;
    badge.textContent = count === 1 ? '1 SKU detected' : `${count} SKUs detected`;
    badge.className = count > 0 
        ? 'badge bg-danger bg-opacity-10 text-danger border border-danger-subtle font-monospace' 
        : 'badge bg-secondary-subtle text-secondary border font-monospace';
}

function clearDeleteSkuInput() {
    const skuInput = document.getElementById('adminDeleteSkuInput');
    if (skuInput) {
        skuInput.value = '';
        updateDeleteSkuCount();
        validateDeleteConfirmation();
    }
    const container = document.getElementById('skuLookupContainer');
    if (container) container.style.display = 'none';
}

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

    updateDeleteSkuCount();

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
    updateDeleteSkuCount();
    validateDeleteConfirmation();

    const skus = parseSkuList(value);
    if (skus.length === 0) {
        const container = document.getElementById('skuLookupContainer');
        if (container) container.style.display = 'none';
        return;
    }

    const firstSku = skus[0];
    skuLookupDebounceTimer = setTimeout(() => {
        lookupSkuDetails(firstSku);
    }, 400);
}

function triggerManualSkuLookup() {
    const skuInput = document.getElementById('adminDeleteSkuInput');
    const skus = parseSkuList(skuInput?.value || '');
    if (skus.length === 0) {
        alert('Please enter or paste at least one SKU code first.');
        return;
    }
    lookupSkuDetails(skus[0]);
}

function lookupSkuDetails(sku) {
    const container = document.getElementById('skuLookupContainer');
    const badge = document.getElementById('skuLookupStatusBadge');
    const body = document.getElementById('skuLookupCardBody');

    if (!container || !badge || !body) return;

    // Clean SKU if numbered prefix present
    sku = sku.replace(/^[0-9]+[\.\t\s]+/, '').trim();

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

    const skus = parseSkuList(skuInput.value);
    const firstSku = skus[0] || '';
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
        feedbackText.innerHTML = `<i class="fa-solid fa-circle-xmark me-1"></i> Type 'DELETE' or '${firstSku}' to unlock.`;
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
