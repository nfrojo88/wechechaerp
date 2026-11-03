@if(auth()->check() && auth()->user()->isGlobalAdmin())
<!-- Modal: Global Admin Convert Fixed Asset to Consumable by SKU -->
<div class="modal fade" id="adminSkuConvertModal" tabindex="-1" aria-labelledby="adminSkuConvertModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <!-- Modal Header -->
            <div class="modal-header bg-warning text-dark py-3 px-4" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #fff !important;">
                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-white bg-opacity-25 text-white p-2.5 rounded-3 fs-5">
                        <i class="fa-solid fa-arrow-right-arrow-left"></i>
                    </span>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="modal-title fw-bold mb-0 text-white" id="adminSkuConvertModalLabel">Convert Fixed Asset to Consumable</h5>
                            <span class="badge bg-black bg-opacity-25 text-white text-uppercase font-monospace" style="font-size:0.68rem; letter-spacing:0.05em;">
                                <i class="fa-solid fa-shield-halved me-1"></i> Global Admin Only
                            </span>
                        </div>
                        <small class="text-white-50">Bulk change material category to Consumable, remove serialized asset tags, and preserve store inventory.</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body p-4 bg-white">
                <!-- Informational Safety Notice -->
                <div class="alert alert-warning border-warning border-opacity-25 bg-warning bg-opacity-10 rounded-3 p-3 mb-3 d-flex align-items-start gap-3">
                    <i class="fa-solid fa-circle-check text-warning fs-4 mt-0.5" style="color: #d97706 !important;"></i>
                    <div>
                        <strong class="text-dark d-block">Inventory Preservation Guarantee</strong>
                        <span class="small text-muted">
                            This action safely converts the selected product(s) to <strong>Consumable</strong>. 
                            <strong>Store inventory quantities on hand will NOT be lost or decreased.</strong> Serialized asset units (tags) are cleaned, reserved quantities are unlocked, and safety guards prevent automatic re-creation as fixed assets.
                        </span>
                    </div>
                </div>

                <form method="POST" action="{{ route('products.convert-to-consumable-by-sku') }}" id="adminConvertSkuForm">
                    @csrf

                    <!-- SKU Input Group -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="adminConvertSkuInput" class="form-label small fw-bold text-dark text-uppercase mb-0">
                                <i class="fa-solid fa-barcode text-warning me-1" style="color:#d97706 !important;"></i> Material SKU Code(s) <span class="text-danger">*</span>
                            </label>
                            <div class="d-flex align-items-center gap-2">
                                <span id="adminConvertSkuCountBadge" class="badge bg-secondary-subtle text-secondary border font-monospace" style="font-size:0.75rem;">
                                    0 SKUs detected
                                </span>
                                <button type="button" class="btn btn-link btn-sm text-decoration-none p-0 text-muted" onclick="clearConvertSkuInput()" title="Clear input">
                                    <i class="fa-solid fa-eraser me-1"></i>Clear
                                </button>
                            </div>
                        </div>
                        <div class="position-relative">
                            <textarea name="sku" id="adminConvertSkuInput" rows="4"
                                      class="form-control font-monospace fw-bold text-dark" 
                                      placeholder="Paste single or multiple SKUs here, e.g.:&#10;1 FA-BUST&#10;2 FA-BALD&#10;FA-B RUS&#10;or comma-separated: FA-BUST, FA-BALD" 
                                      required autocomplete="off" oninput="handleConvertSkuInputDebounce(this.value)"></textarea>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <div class="form-text text-muted small">
                                Supports numbered lists (e.g. <code>1 FA-BUST</code>), line-by-line paste, or comma-separated SKUs.
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm px-2 py-0.5" onclick="triggerManualConvertSkuLookup()" title="Inspect first SKU Details">
                                <i class="fa-solid fa-magnifying-glass me-1"></i> Inspect First SKU
                            </button>
                        </div>
                    </div>

                    <!-- Live SKU Lookup Preview Card -->
                    <div id="convertSkuLookupContainer" class="mb-3" style="display: none;">
                        <div class="card border rounded-3 overflow-hidden shadow-sm">
                            <div class="card-header py-2 px-3 bg-light d-flex justify-content-between align-items-center">
                                <span class="small fw-bold text-muted text-uppercase" style="font-size:0.75rem;">
                                    <i class="fa-solid fa-circle-info text-info me-1"></i> Material Inspection Preview
                                </span>
                                <span id="convertSkuLookupStatusBadge" class="badge bg-secondary small">Checking...</span>
                            </div>
                            <div class="card-body p-3" id="convertSkuLookupCardBody">
                                <!-- Dynamic content populated by JS -->
                            </div>
                        </div>
                    </div>

                    <!-- Confirmation Guard Input -->
                    <div class="mb-3 p-3 rounded-3 border border-warning-subtle bg-warning bg-opacity-10">
                        <label for="adminConvertConfirmInput" class="form-label small fw-bold text-dark text-uppercase mb-1">
                            <i class="fa-solid fa-lock text-warning me-1" style="color:#d97706 !important;"></i> Type Confirmation to Unlock Conversion <span class="text-danger">*</span>
                        </label>
                        <p class="small text-muted mb-2">
                            To confirm changing these materials to Consumable, please type <code class="fw-bold text-warning" style="color:#b45309 !important;">CONVERT</code> below:
                        </p>
                        <input type="text" name="confirm_action" id="adminConvertConfirmInput" 
                               class="form-control font-monospace fw-bold" 
                               placeholder="Type CONVERT here..." 
                               required autocomplete="off" oninput="validateConvertConfirmation()">
                        <div id="convertConfirmFeedbackText" class="small mt-1 text-muted">Waiting for confirmation input...</div>
                    </div>

                    <!-- Modal Actions -->
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <button type="button" class="btn btn-outline-secondary px-4 fw-semibold" data-bs-dismiss="modal">
                            <i class="fa-solid fa-xmark me-1"></i> Cancel
                        </button>
                        <button type="submit" id="adminConvertSubmitBtn" class="btn btn-warning text-dark fw-bold px-4 py-2 shadow-sm" style="background:#f59e0b; border-color:#d97706;" disabled>
                            <i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Convert to Consumable
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let convertSkuLookupDebounceTimer = null;

function parseConvertSkuList(text) {
    if (!text) return [];
    return text.split(/[\r\n,]+/)
        .map(s => s.trim())
        .map(s => s.replace(/^[0-9]+[\.\t\s]+/, '').trim())
        .filter(s => s.length > 0 && s.toUpperCase() !== 'DELETE' && s.toUpperCase() !== 'CONVERT');
}

function updateConvertSkuCount() {
    const skuInput = document.getElementById('adminConvertSkuInput');
    const badge = document.getElementById('adminConvertSkuCountBadge');
    if (!skuInput || !badge) return;

    const skus = parseConvertSkuList(skuInput.value);
    const count = skus.length;
    badge.textContent = count === 1 ? '1 SKU detected' : `${count} SKUs detected`;
    badge.className = count > 0 
        ? 'badge bg-warning bg-opacity-10 text-dark border border-warning-subtle font-monospace' 
        : 'badge bg-secondary-subtle text-secondary border font-monospace';
}

function clearConvertSkuInput() {
    const skuInput = document.getElementById('adminConvertSkuInput');
    if (skuInput) {
        skuInput.value = '';
        updateConvertSkuCount();
        validateConvertConfirmation();
    }
    const container = document.getElementById('convertSkuLookupContainer');
    if (container) container.style.display = 'none';
}

function openSkuConvertModal(sku = '', name = '') {
    const modalEl = document.getElementById('adminSkuConvertModal');
    if (!modalEl) return;

    const skuInput = document.getElementById('adminConvertSkuInput');
    const confirmInput = document.getElementById('adminConvertConfirmInput');
    const submitBtn = document.getElementById('adminConvertSubmitBtn');
    const feedbackText = document.getElementById('convertConfirmFeedbackText');

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

    updateConvertSkuCount();

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    if (sku) {
        lookupConvertSkuDetails(sku);
    } else {
        const container = document.getElementById('convertSkuLookupContainer');
        if (container) container.style.display = 'none';
    }
}

function handleConvertSkuInputDebounce(value) {
    clearTimeout(convertSkuLookupDebounceTimer);
    updateConvertSkuCount();
    validateConvertConfirmation();

    const skus = parseConvertSkuList(value);
    if (skus.length === 0) {
        const container = document.getElementById('convertSkuLookupContainer');
        if (container) container.style.display = 'none';
        return;
    }

    const firstSku = skus[0];
    convertSkuLookupDebounceTimer = setTimeout(() => {
        lookupConvertSkuDetails(firstSku);
    }, 400);
}

function triggerManualConvertSkuLookup() {
    const skuInput = document.getElementById('adminConvertSkuInput');
    const skus = parseConvertSkuList(skuInput?.value || '');
    if (skus.length === 0) {
        alert('Please enter or paste at least one SKU code first.');
        return;
    }
    lookupConvertSkuDetails(skus[0]);
}

function lookupConvertSkuDetails(sku) {
    const container = document.getElementById('convertSkuLookupContainer');
    const badge = document.getElementById('convertSkuLookupStatusBadge');
    const body = document.getElementById('convertSkuLookupCardBody');

    if (!container || !badge || !body) return;

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
                const isFa = (p.category || '').toLowerCase() === 'fixed asset';
                const catBadge = isFa
                    ? `<span class="badge bg-warning text-dark border"><i class="fa-solid fa-building me-1"></i>Fixed Asset (Eligible)</span>`
                    : `<span class="badge bg-info text-dark border"><i class="fa-solid fa-box me-1"></i>${escapeHtml(p.category)}</span>`;

                const stockBadge = p.stock_on_hand > 0 
                    ? `<span class="badge bg-success-subtle text-success border"><i class="fa-solid fa-boxes-stacked me-1"></i>Stock On Hand: ${p.stock_on_hand} ${p.unit}</span>`
                    : `<span class="badge bg-light text-muted border">0 Stock</span>`;

                html += `
                    <div class="p-2 border rounded bg-light mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold text-dark fs-6">${escapeHtml(p.name)}</span>
                            <div class="d-flex gap-1 align-items-center">
                                ${catBadge}
                                ${stockBadge}
                            </div>
                        </div>
                        <div class="small text-muted d-flex flex-wrap gap-3">
                            <span><strong>SKU:</strong> <code class="text-primary fw-bold">${escapeHtml(p.sku)}</code></span>
                            <span><strong>Target Category:</strong> <span class="badge bg-primary-subtle text-primary fw-bold">Consumable</span></span>
                            <span><strong>Unit:</strong> ${escapeHtml(p.unit)}</span>
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
                    ${escapeHtml(data.message || `No material currently matches SKU '${sku}'.`)}
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

function validateConvertConfirmation() {
    const skuInput = document.getElementById('adminConvertSkuInput');
    const confirmInput = document.getElementById('adminConvertConfirmInput');
    const submitBtn = document.getElementById('adminConvertSubmitBtn');
    const feedbackText = document.getElementById('convertConfirmFeedbackText');

    if (!skuInput || !confirmInput || !submitBtn || !feedbackText) return;

    const skus = parseConvertSkuList(skuInput.value);
    const firstSku = skus[0] || '';
    const typed = (confirmInput.value || '').trim();

    if (!typed) {
        submitBtn.disabled = true;
        feedbackText.className = 'small mt-1 text-muted';
        feedbackText.innerHTML = 'Waiting for confirmation input...';
        return;
    }

    const matchesConvert = typed.toUpperCase() === 'CONVERT';
    const matchesSku = firstSku && typed.toLowerCase() === firstSku.toLowerCase();

    if (matchesConvert || matchesSku) {
        submitBtn.disabled = false;
        feedbackText.className = 'small mt-1 text-success fw-bold';
        feedbackText.innerHTML = '<i class="fa-solid fa-check-circle me-1"></i> Confirmation accepted! Ready to convert.';
    } else {
        submitBtn.disabled = true;
        feedbackText.className = 'small mt-1 text-danger';
        feedbackText.innerHTML = `<i class="fa-solid fa-circle-xmark me-1"></i> Type 'CONVERT' or '${firstSku}' to unlock.`;
    }
}
</script>
@endif
