/**
 * Receipt OCR Scanner & Studio - Reusable client component for purchasing and credit ledger screens
 * Provides AI Vision scanning (Gemini + NIM QC Agent), live image preview with rotate/rescan,
 * extracted fiscal fields with ERCA Line 100 / TAS verification, itemized material lists,
 * and seamless VAT Included vs No VAT (TAS Excel) routing.
 */
(function (window) {
    'use strict';

    /**
     * Convert Gregorian date string (YYYY-MM-DD or DD/MM/YYYY) to Ethiopian Calendar (EC).
     */
    function gregorianToEthiopian(dateInput) {
        if (!dateInput) return null;
        let d = null;
        if (typeof dateInput === 'string') {
            const parts = dateInput.trim().split(/[-/]/);
            if (parts.length === 3) {
                if (parts[0].length === 4) {
                    // YYYY-MM-DD
                    d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                } else if (parts[2].length === 4) {
                    // DD/MM/YYYY
                    d = new Date(parseInt(parts[2], 10), parseInt(parts[1], 10) - 1, parseInt(parts[0], 10));
                }
            }
        } else if (dateInput instanceof Date) {
            d = dateInput;
        }

        if (!d || isNaN(d.getTime())) return null;

        const year = d.getFullYear();
        const month = d.getMonth() + 1;
        const day = d.getDate();

        const a = Math.floor((14 - month) / 12);
        const y = year + 4800 - a;
        const m = month + 12 * a - 3;
        const jdn = day + Math.floor((153 * m + 2) / 5) + 365 * y + Math.floor(y / 4) - Math.floor(y / 100) + Math.floor(y / 400) - 32045;

        const ethiopianEpoch = 1723856;
        const r = (jdn - ethiopianEpoch) % 1461;
        const n = (r % 365) + 365 * Math.floor(r / 1460);

        const ethYear = 4 * Math.floor((jdn - ethiopianEpoch) / 1461) + Math.floor(r / 365) - Math.floor(r / 1460);
        const ethMonth = Math.floor(n / 30) + 1;
        const ethDay = (n % 30) + 1;

        const ethMonthsEn = ['Meskerem', 'Tikimt', 'Hidar', 'Tahsas', 'Tir', 'Yakatit', 'Megabit', 'Miazia', 'Ginbot', 'Sene', 'Hamle', 'Nehase', 'Pagume'];
        const pad = (num) => String(num).padStart(2, '0');

        return {
            year: ethYear,
            month: ethMonth,
            day: ethDay,
            monthName: ethMonthsEn[ethMonth - 1] || 'Month ' + ethMonth,
            formatted: `${pad(ethDay)}/${pad(ethMonth)}/${ethYear} EC`
        };
    }

    const ReceiptOcrScanner = {
        init: function (config) {
            const fileInput = typeof config.fileInput === 'string' ? document.querySelector(config.fileInput) : config.fileInput;
            const form = typeof config.form === 'string' ? document.querySelector(config.form) : (config.form || (fileInput ? fileInput.closest('form') : null));
            const submitBtn = typeof config.submitBtn === 'string' ? document.querySelector(config.submitBtn) : (config.submitBtn || (form ? form.querySelector('button[type="submit"]') : null));
            const statusContainer = typeof config.statusContainer === 'string' ? document.querySelector(config.statusContainer) : config.statusContainer;
            const isStudioMode = config.studioMode !== false; // Default to rich OCR Studio mode

            if (!fileInput) {
                console.warn('ReceiptOcrScanner: file input element not found.');
                return;
            }

            const scanUrl = config.scanUrl || '/purchases/receipt-scan';
            const fieldMap = config.fields || {};

            let feedbackEl = statusContainer;
            if (!feedbackEl) {
                feedbackEl = document.createElement('div');
                feedbackEl.className = 'ocr-scanner-status-area my-2';
                fileInput.parentNode.insertBefore(feedbackEl, fileInput.nextSibling);
            }

            // Current state object
            let currentFile = null;
            let currentObjectUrl = null;
            let currentRotation = 0;
            let lastScanData = null;

            function runScan(file) {
                currentFile = file;

                // Create local object URL for preview
                if (currentObjectUrl) {
                    URL.revokeObjectURL(currentObjectUrl);
                    currentObjectUrl = null;
                }
                if (file.type && file.type.startsWith('image/')) {
                    currentObjectUrl = URL.createObjectURL(file);
                }

                // Check file size (10MB limit)
                if (file.size > 10 * 1024 * 1024) {
                    feedbackEl.innerHTML = `
                        <div class="alert alert-danger py-2 px-3 small border-0 mb-2">
                            <i class="fas fa-exclamation-triangle me-1"></i> File is too large (${(file.size / (1024 * 1024)).toFixed(1)} MB). Maximum allowed is 10 MB.
                        </div>`;
                    if (submitBtn) submitBtn.disabled = true;
                    return;
                }

                // Show scanning loading state
                feedbackEl.innerHTML = `
                    <div class="card border border-success border-opacity-25 bg-success bg-opacity-10 py-3 px-3 mb-2 rounded-3 shadow-xs">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="spinner-border spinner-border-sm text-success" role="status">
                                    <span class="visually-hidden">Scanning...</span>
                                </div>
                                <strong class="small text-dark">
                                    <i class="fa-solid fa-receipt text-success me-1"></i> Receipt OCR Studio &amp; AI Vision Scanner
                                </strong>
                            </div>
                            <span class="badge bg-success text-white small" style="font-size:0.7rem;">
                                <i class="fa-solid fa-microchip me-1"></i>Extracting ERCA Fiscal Data...
                            </span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: 100%"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 small text-muted" style="font-size:0.75rem;">
                            <span>Multi-Engine Parallel AI (Gemini + NIM QC Agent)</span>
                            <span>Verifying TIN &amp; FS# Rules...</span>
                        </div>
                    </div>`;

                if (submitBtn) {
                    submitBtn.disabled = true;
                }

                const formData = new FormData();
                formData.append('receipt_file', file);

                let csrfToken = null;
                const tokenMeta = document.querySelector('meta[name="csrf-token"]');
                if (tokenMeta) {
                    csrfToken = tokenMeta.getAttribute('content');
                } else if (form) {
                    const tokenInput = form.querySelector('input[name="_token"]');
                    if (tokenInput) csrfToken = tokenInput.value;
                }

                fetch(scanUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken || '',
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, status: response.status, data: data };
                    });
                })
                .then(function (res) {
                    if (!res.ok || !res.data || !res.data.success) {
                        const errMsg = (res.data && (res.data.message || res.data.error)) || 'OCR scanning could not extract details.';
                        renderFallback(feedbackEl, errMsg, submitBtn);
                        if (typeof config.onScanError === 'function') {
                            config.onScanError(errMsg);
                        }
                        return;
                    }

                    lastScanData = res.data;
                    const scanData = res.data;
                    const extracted = scanData.data || {};

                    if (isStudioMode) {
                        renderStudio(feedbackEl, scanData, file, submitBtn);
                    } else {
                        if (scanData.is_duplicate) {
                            renderDuplicateAlert(feedbackEl, scanData, submitBtn);
                        } else {
                            renderSuccessBanner(feedbackEl, scanData, submitBtn);
                        }
                    }

                    prefillMappedFields(form, fieldMap, extracted, scanData);

                    if (typeof config.onItemsExtracted === 'function' && Array.isArray(extracted.items)) {
                        config.onItemsExtracted(extracted.items, extracted);
                    }

                    if (typeof config.onScanComplete === 'function') {
                        config.onScanComplete(scanData);
                    }
                })
                .catch(function (err) {
                    console.error('OCR Scanning Request Error:', err);
                    renderFallback(feedbackEl, 'OCR connection failed. Manual entry is ready.', submitBtn);
                    if (typeof config.onScanError === 'function') {
                        config.onScanError(err.message || 'Network error');
                    }
                });
            }

            fileInput.addEventListener('change', function (e) {
                const file = e.target.files && e.target.files[0];
                if (!file) {
                    feedbackEl.innerHTML = '';
                    if (submitBtn) submitBtn.disabled = false;
                    return;
                }
                currentRotation = 0;
                runScan(file);
            });

            // Prevent form submit if duplicate flag is set on form
            if (form) {
                form.addEventListener('submit', function (e) {
                    if (form.dataset.ocrDuplicate === 'true') {
                        e.preventDefault();
                        alert('Cannot submit: This receipt is detected as a duplicate (same FS No & TIN already recorded).');
                        return false;
                    }
                });
            }

            function setHidden(name, val) {
                if (!form) return;
                let input = form.querySelector(`input[name="${name}"]`);
                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    form.appendChild(input);
                }
                input.value = (val !== undefined && val !== null) ? val : '';
            }

            function renderStudio(container, scanData, file, btn) {
                const ext = scanData.data || {};
                const isDup = Boolean(scanData.is_duplicate);
                const score = scanData.confidence_score || 95;
                const scoreBadge = score >= 85 ? 'bg-success' : (score >= 70 ? 'bg-warning text-dark' : 'bg-secondary');

                if (form) form.dataset.ocrDuplicate = isDup ? 'true' : 'false';
                if (btn) {
                    btn.disabled = isDup;
                    if (isDup) {
                        btn.classList.add('disabled');
                        btn.setAttribute('title', 'Duplicate receipt submission blocked.');
                    } else {
                        btn.classList.remove('disabled');
                        btn.removeAttribute('title');
                    }
                }

                // Initial VAT calculation
                const extractedVat = parseFloat(ext.vat_amount) || 0;
                const extractedSubtotal = parseFloat(ext.subtotal) || 0;
                const extractedTotal = parseFloat(ext.total_amount) || (extractedSubtotal + extractedVat);
                const hasInitialVat = extractedVat > 0;

                // Parse Ethiopian date
                const ecInfo = gregorianToEthiopian(ext.receipt_date_ymd || ext.receipt_date);
                const ecFormatted = ecInfo ? ecInfo.formatted : 'N/A';

                // Items list
                const items = Array.isArray(ext.items) ? ext.items : [];

                const studioId = 'studio_' + Math.random().toString(36).substring(2, 9);
                const isPdf = file.name.toLowerCase().endsWith('.pdf');

                const html = `
                    <div class="card border rounded-3 p-0 mb-3 shadow-sm bg-white overflow-hidden ocr-studio-container" id="${studioId}">
                        <!-- Studio Header Bar -->
                        <div class="card-header bg-dark text-white py-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-success text-white px-2 py-1 rounded-pill">
                                    <i class="fa-solid fa-receipt me-1"></i> Receipt OCR Studio
                                </span>
                                <span class="badge bg-primary text-white px-2 py-0.5 rounded-pill">
                                    <i class="fa-solid fa-microchip me-1"></i> ${scanData.engine || 'Multi-Engine AI'}
                                </span>
                                <span class="badge ${scoreBadge} text-white px-2 py-0.5 rounded-pill">
                                    ${score}% Verified
                                </span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                ${isDup ? `
                                    <span class="badge bg-danger text-white px-2.5 py-1">
                                        <i class="fa-solid fa-ban me-1"></i> DUPLICATE RECEIPT
                                    </span>
                                ` : `
                                    <span class="badge bg-success-subtle text-success border border-success px-2 py-0.5">
                                        <i class="fa-solid fa-circle-check me-1"></i> Verified Unique
                                    </span>
                                `}
                            </div>
                        </div>

                        ${isDup ? `
                            <div class="alert alert-danger mb-0 rounded-0 py-2 px-3 small border-0 border-bottom">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="fa-solid fa-triangle-exclamation text-danger fs-5 mt-1"></i>
                                    <div>
                                        <strong>Duplicate Detected in ERP:</strong> Receipt with FS #${ext.fs_no || 'N/A'} and TIN ${ext.supplier_tin || 'N/A'} is already recorded in the database. Duplicate submission is restricted under Ethiopian tax rules.
                                    </div>
                                </div>
                            </div>
                        ` : ''}

                        <div class="card-body p-3">
                            <div class="row g-3">
                                <!-- Left Column: Receipt Visual Preview & Controls -->
                                <div class="col-12 col-md-5">
                                    <div class="p-2 border rounded-3 bg-light text-center h-100 d-flex flex-column align-items-center justify-content-center" style="min-height: 220px;">
                                        <div class="position-relative w-100 d-flex align-items-center justify-content-center overflow-hidden" style="max-height: 240px; min-height: 180px; background:#fff; border-radius:6px;">
                                            ${isPdf ? `
                                                <div class="py-4 text-center">
                                                    <i class="fa-solid fa-file-pdf fa-4x text-danger mb-2"></i>
                                                    <div class="fw-bold text-dark small">${file.name}</div>
                                                    <span class="badge bg-secondary text-white mt-1">PDF Receipt Document</span>
                                                </div>
                                            ` : `
                                                <img src="${currentObjectUrl || ''}" alt="Receipt Preview" id="${studioId}_preview_img" class="img-fluid rounded shadow-2xs" style="max-height: 230px; object-fit: contain; transform: rotate(${currentRotation}deg); transition: transform 0.25s ease;">
                                            `}
                                        </div>

                                        <!-- Image Action Buttons -->
                                        <div class="d-flex justify-content-center gap-1 mt-2 w-100">
                                            ${!isPdf ? `
                                                <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2" style="font-size:0.75rem;" id="${studioId}_btn_rotate" title="Rotate 90°">
                                                    <i class="fa-solid fa-rotate-right me-1"></i> Rotate
                                                </button>
                                            ` : ''}
                                            <button type="button" class="btn btn-outline-primary btn-sm py-1 px-2" style="font-size:0.75rem;" id="${studioId}_btn_rescan" title="Re-scan OCR">
                                                <i class="fa-solid fa-bolt me-1"></i> Re-Scan
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-sm py-1 px-2" style="font-size:0.75rem;" id="${studioId}_btn_clear" title="Clear Receipt">
                                                <i class="fa-solid fa-trash me-1"></i> Clear
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right Column: Extracted Fields Form & Validation Badges -->
                                <div class="col-12 col-md-7">
                                    <div class="row g-2">
                                        <!-- Merchant / Vendor Name -->
                                        <div class="col-12">
                                            <label class="form-label small fw-bold text-dark mb-0 d-flex align-items-center justify-content-between">
                                                <span>Merchant / Supplier Name</span>
                                                <span class="badge bg-primary bg-opacity-10 text-primary border" style="font-size:0.65rem;">Col E</span>
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light"><i class="fa-solid fa-store text-muted"></i></span>
                                                <input type="text" class="form-control fw-semibold" id="${studioId}_vendor_name" value="${ext.vendor_name || ''}" placeholder="e.g. Supplier Name">
                                            </div>
                                        </div>

                                        <!-- Supplier TIN with 10-digit validation badge -->
                                        <div class="col-6">
                                            <label class="form-label small fw-bold text-dark mb-0 d-flex align-items-center justify-content-between">
                                                <span>Supplier TIN</span>
                                                <span class="badge bg-success bg-opacity-10 text-success border" style="font-size:0.65rem;">Col D</span>
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light"><i class="fa-solid fa-id-card text-muted"></i></span>
                                                <input type="text" class="form-control font-monospace fw-bold text-primary" id="${studioId}_supplier_tin" value="${ext.supplier_tin || ''}" placeholder="10 Digits">
                                            </div>
                                            <div id="${studioId}_tin_badge" class="mt-1" style="font-size:0.7rem;"></div>
                                        </div>

                                        <!-- FS / Receipt No with 8-digit validation badge -->
                                        <div class="col-6">
                                            <label class="form-label small fw-bold text-dark mb-0 d-flex align-items-center justify-content-between">
                                                <span>FS / Receipt #</span>
                                                <span class="badge bg-primary bg-opacity-10 text-primary border" style="font-size:0.65rem;">Col H</span>
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light"><i class="fa-solid fa-hashtag text-muted"></i></span>
                                                <input type="text" class="form-control font-monospace fw-bold text-dark" id="${studioId}_fs_no" value="${ext.fs_no || ''}" placeholder="8 Digits">
                                            </div>
                                            <div id="${studioId}_fs_badge" class="mt-1" style="font-size:0.7rem;"></div>
                                        </div>

                                        <!-- Machine / MRC No & Receipt Date -->
                                        <div class="col-6">
                                            <label class="form-label small fw-bold text-dark mb-0 d-flex align-items-center justify-content-between">
                                                <span>Machine / MRC #</span>
                                                <span class="badge bg-light text-muted border" style="font-size:0.65rem;">Col G</span>
                                            </label>
                                            <input type="text" class="form-control form-control-sm font-monospace" id="${studioId}_mrc_no" value="${ext.mrc_no || ''}" placeholder="e.g. DDB0000032">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small fw-bold text-dark mb-0 d-flex align-items-center justify-content-between">
                                                <span>Receipt Date</span>
                                                <span class="badge bg-primary bg-opacity-10 text-primary border" style="font-size:0.65rem;">Col F</span>
                                            </label>
                                            <input type="date" class="form-control form-control-sm font-monospace" id="${studioId}_receipt_date" value="${ext.receipt_date_ymd || ''}">
                                            <div class="text-muted mt-1 font-monospace" id="${studioId}_ec_display" style="font-size:0.7rem;">
                                                <i class="fa-solid fa-calendar-days text-primary me-1"></i>${ecFormatted}
                                            </div>
                                        </div>

                                        <!-- Item Description -->
                                        <div class="col-12">
                                            <label class="form-label small fw-bold text-dark mb-0 d-flex align-items-center justify-content-between">
                                                <span>Purchased Materials / Description</span>
                                                <span class="badge bg-primary bg-opacity-10 text-primary border" style="font-size:0.65rem;">Col I</span>
                                            </label>
                                            <input type="text" class="form-control form-control-sm" id="${studioId}_description" value="${ext.description || ''}" placeholder="Purchased Items description">
                                        </div>
                                    </div>
                                </div>

                                <!-- Multi-Item Materials Table (If extracted from receipt) -->
                                ${items.length > 0 ? `
                                    <div class="col-12">
                                        <div class="card border rounded-2 bg-light shadow-2xs overflow-hidden">
                                            <div class="card-header bg-white py-1.5 px-3 d-flex align-items-center justify-content-between">
                                                <span class="small fw-bold text-dark" style="font-size:0.75rem;">
                                                    <i class="fa-solid fa-list-check text-success me-1"></i> Bought Materials Itemized List (${items.length} items)
                                                </span>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success" style="font-size:0.68rem;">Verified Items</span>
                                            </div>
                                            <div class="table-responsive" style="max-height: 140px; overflow-y: auto;">
                                                <table class="table table-sm table-hover mb-0" style="font-size:0.78rem;">
                                                    <thead class="bg-light text-muted">
                                                        <tr>
                                                            <th class="ps-2">Description</th>
                                                            <th class="text-center">Qty</th>
                                                            <th class="text-end">Unit Price</th>
                                                            <th class="text-end">Total</th>
                                                            <th class="text-end pe-2">VAT</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        ${items.map(it => `
                                                            <tr>
                                                                <td class="ps-2 fw-semibold text-dark">${it.item_description || 'Item'}</td>
                                                                <td class="text-center font-monospace">${Number(it.qty || 1).toFixed(2)}</td>
                                                                <td class="text-end font-monospace">${Number(it.unit_price || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                                                                <td class="text-end font-monospace fw-bold">${Number(it.total_value || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                                                                <td class="text-end pe-2 font-monospace text-muted">${Number(it.vat || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                                                            </tr>
                                                        `).join('')}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                ` : ''}

                                <!-- ERCA TAX TREATMENT TOGGLE: VAT INCLUDED VS NO VAT (TAS EXCEL) -->
                                <div class="col-12">
                                    <div class="card border-2 border-primary-subtle rounded-3 p-2.5 bg-primary bg-opacity-10 shadow-xs">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <label class="form-label small fw-bold text-dark text-uppercase mb-0" style="letter-spacing:0.5px;">
                                                <i class="fa-solid fa-shield-halved text-primary me-1"></i> ERCA Tax Declaration Treatment:
                                            </label>
                                            <span id="${studioId}_vat_badge" class="badge bg-primary text-white px-2 py-0.5" style="font-size:0.7rem;">
                                                ${hasInitialVat ? 'VAT Section (Line 100)' : 'TAS Excel Declaration'}
                                            </span>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <label class="p-2 border rounded-2 bg-white d-flex align-items-start gap-2 cursor-pointer w-100 mb-0 shadow-2xs" id="${studioId}_opt_vat_included_label">
                                                    <input type="radio" name="${studioId}_vat_choice" value="included" class="form-check-input mt-1" ${hasInitialVat ? 'checked' : ''}>
                                                    <div>
                                                        <strong class="d-block text-success small"><i class="fa-solid fa-percent me-1"></i>VAT Included (15%)</strong>
                                                        <small class="text-muted d-block" style="font-size:0.68rem; line-height: 1.25;">15% creditable input VAT &rarr; Sent to Monthly ERCA Line 100 VAT Section</small>
                                                    </div>
                                                </label>
                                            </div>
                                            <div class="col-6">
                                                <label class="p-2 border rounded-2 bg-white d-flex align-items-start gap-2 cursor-pointer w-100 mb-0 shadow-2xs" id="${studioId}_opt_vat_none_label">
                                                    <input type="radio" name="${studioId}_vat_choice" value="none" class="form-check-input mt-1" ${!hasInitialVat ? 'checked' : ''}>
                                                    <div>
                                                        <strong class="d-block text-dark small"><i class="fa-solid fa-file-excel text-success me-1"></i>No VAT (TAS Declaration)</strong>
                                                        <small class="text-muted d-block" style="font-size:0.68rem; line-height: 1.25;">Zero VAT &rarr; Declared in Purchase TAS Excel for this month</small>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Financial Totals Summary Row -->
                                <div class="col-12">
                                    <div class="p-2 rounded-2 bg-light border">
                                        <div class="row text-center g-1 small">
                                            <div class="col-4 border-end">
                                                <span class="text-muted d-block" style="font-size:0.7rem;">Subtotal (Col M)</span>
                                                <strong class="text-dark font-monospace" id="${studioId}_subtotal_display">ETB ${Number(extractedSubtotal).toLocaleString(undefined, {minimumFractionDigits: 2})}</strong>
                                            </div>
                                            <div class="col-4 border-end">
                                                <span class="text-muted d-block" style="font-size:0.7rem;">VAT 15% (Col N)</span>
                                                <strong class="text-info font-monospace" id="${studioId}_vat_display">+ ETB ${Number(extractedVat).toLocaleString(undefined, {minimumFractionDigits: 2})}</strong>
                                            </div>
                                            <div class="col-4">
                                                <span class="text-muted d-block" style="font-size:0.7rem;">Total (Col O)</span>
                                                <strong class="text-success font-monospace" id="${studioId}_total_display">ETB ${Number(extractedTotal).toLocaleString(undefined, {minimumFractionDigits: 2})}</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                container.innerHTML = html;

                // Elements inside studio
                const previewImg = document.getElementById(`${studioId}_preview_img`);
                const btnRotate = document.getElementById(`${studioId}_btn_rotate`);
                const btnRescan = document.getElementById(`${studioId}_btn_rescan`);
                const btnClear = document.getElementById(`${studioId}_btn_clear`);

                const inputVendor = document.getElementById(`${studioId}_vendor_name`);
                const inputTin = document.getElementById(`${studioId}_supplier_tin`);
                const inputFs = document.getElementById(`${studioId}_fs_no`);
                const inputMrc = document.getElementById(`${studioId}_mrc_no`);
                const inputDate = document.getElementById(`${studioId}_receipt_date`);
                const inputDesc = document.getElementById(`${studioId}_description`);

                const tinBadge = document.getElementById(`${studioId}_tin_badge`);
                const fsBadge = document.getElementById(`${studioId}_fs_badge`);
                const ecDisplay = document.getElementById(`${studioId}_ec_display`);
                const vatBadge = document.getElementById(`${studioId}_vat_badge`);

                const optIncluded = document.querySelector(`input[name="${studioId}_vat_choice"][value="included"]`);
                const optNone = document.querySelector(`input[name="${studioId}_vat_choice"][value="none"]`);

                const dispSubtotal = document.getElementById(`${studioId}_subtotal_display`);
                const dispVat = document.getElementById(`${studioId}_vat_display`);
                const dispTotal = document.getElementById(`${studioId}_total_display`);

                function updateValidationBadges() {
                    const rawTin = (inputTin.value || '').replace(/\D/g, '');
                    const isTinValid = rawTin.length === 10;
                    if (tinBadge) {
                        tinBadge.innerHTML = isTinValid 
                            ? `<span class="badge bg-success-subtle text-success border border-success"><i class="fa-solid fa-check me-1"></i>10 Digits Valid</span>`
                            : `<span class="badge bg-danger-subtle text-danger border border-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>Expected 10 digits (${rawTin.length}/10)</span>`;
                    }

                    const rawFs = (inputFs.value || '').replace(/\D/g, '');
                    const isFsValid = rawFs.length === 8;
                    if (fsBadge) {
                        fsBadge.innerHTML = isFsValid
                            ? `<span class="badge bg-success-subtle text-success border border-success"><i class="fa-solid fa-check me-1"></i>8 Digits Valid</span>`
                            : `<span class="badge bg-danger-subtle text-danger border border-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>Expected 8 digits (${rawFs.length}/8)</span>`;
                    }
                }

                function syncCalculations() {
                    const isVatIncluded = optIncluded && optIncluded.checked;
                    let base = parseFloat(extractedSubtotal) || (parseFloat(ext.total_amount) ? parseFloat(ext.total_amount) / 1.15 : 0);
                    let vat = 0.0;
                    let tot = base;

                    if (isVatIncluded) {
                        vat = extractedVat > 0 ? extractedVat : round2(base * 0.15);
                        tot = round2(base + vat);
                    } else {
                        base = extractedTotal > 0 ? extractedTotal : base;
                        vat = 0.00;
                        tot = base;
                    }

                    if (dispSubtotal) dispSubtotal.innerText = 'ETB ' + base.toLocaleString(undefined, {minimumFractionDigits: 2});
                    if (dispVat) dispVat.innerText = (isVatIncluded ? '+ ' : '') + 'ETB ' + vat.toLocaleString(undefined, {minimumFractionDigits: 2});
                    if (dispTotal) dispTotal.innerText = 'ETB ' + tot.toLocaleString(undefined, {minimumFractionDigits: 2});

                    if (vatBadge) {
                        if (isVatIncluded) {
                            vatBadge.className = 'badge bg-success text-white px-2 py-0.5';
                            vatBadge.innerText = 'VAT Section (Line 100)';
                        } else {
                            vatBadge.className = 'badge bg-dark text-white px-2 py-0.5';
                            vatBadge.innerText = 'TAS Excel Declaration';
                        }
                    }

                    // Update hidden inputs in form
                    setHidden('vendor_name', inputVendor ? inputVendor.value : ext.vendor_name);
                    setHidden('supplier_tin', inputTin ? inputTin.value : ext.supplier_tin);
                    setHidden('fs_no', inputFs ? inputFs.value : ext.fs_no);
                    setHidden('mrc_no', inputMrc ? inputMrc.value : ext.mrc_no);
                    setHidden('receipt_date', inputDate ? inputDate.value : (ext.receipt_date_ymd || ext.receipt_date));
                    setHidden('subtotal', base.toFixed(2));
                    setHidden('vat_amount', vat.toFixed(2));
                    setHidden('total_amount', tot.toFixed(2));
                    setHidden('vat_included', isVatIncluded ? '1' : '0');
                    setHidden('ocr_items_json', JSON.stringify(items));
                    setHidden('ocr_engine', scanData.engine || 'Receipt OCR Studio');
                    setHidden('ocr_confidence', scanData.confidence || 'high');
                    setHidden('ocr_confidence_score', scanData.confidence_score || 95);

                    // Sync with Quick Pay form if present
                    const quickVatTypeSelect = document.getElementById('quickPayVatType');
                    if (quickVatTypeSelect) {
                        if (isVatIncluded) {
                            if (quickVatTypeSelect.value === 'none') {
                                quickVatTypeSelect.value = 'exclusive';
                            }
                        } else {
                            quickVatTypeSelect.value = 'none';
                        }
                        if (typeof window.recalculateQuickPayTax === 'function') {
                            window.recalculateQuickPayTax();
                        }
                    }
                }

                function round2(num) {
                    return Math.round(num * 100) / 100;
                }

                // Event Listeners
                if (btnRotate && previewImg) {
                    btnRotate.addEventListener('click', function () {
                        currentRotation = (currentRotation + 90) % 360;
                        previewImg.style.transform = `rotate(${currentRotation}deg)`;
                    });
                }

                if (btnRescan) {
                    btnRescan.addEventListener('click', function () {
                        if (currentFile) runScan(currentFile);
                    });
                }

                if (btnClear) {
                    btnClear.addEventListener('click', function () {
                        fileInput.value = '';
                        currentFile = null;
                        if (currentObjectUrl) {
                            URL.revokeObjectURL(currentObjectUrl);
                            currentObjectUrl = null;
                        }
                        container.innerHTML = '';
                        if (btn) {
                            btn.disabled = false;
                            btn.classList.remove('disabled');
                        }
                        if (form) form.dataset.ocrDuplicate = 'false';
                        setHidden('vendor_name', '');
                        setHidden('supplier_tin', '');
                        setHidden('fs_no', '');
                        setHidden('mrc_no', '');
                        setHidden('vat_included', '0');
                        setHidden('ocr_items_json', '');

                        if (typeof window.recalculateQuickPayTax === 'function') {
                            window.recalculateQuickPayTax();
                        }
                    });
                }

                if (optIncluded) optIncluded.addEventListener('change', syncCalculations);
                if (optNone) optNone.addEventListener('change', syncCalculations);

                if (inputTin) inputTin.addEventListener('input', () => { updateValidationBadges(); syncCalculations(); });
                if (inputFs) inputFs.addEventListener('input', () => { updateValidationBadges(); syncCalculations(); });
                if (inputVendor) inputVendor.addEventListener('input', syncCalculations);
                if (inputMrc) inputMrc.addEventListener('input', syncCalculations);
                if (inputDate) {
                    inputDate.addEventListener('change', () => {
                        const newEc = gregorianToEthiopian(inputDate.value);
                        if (ecDisplay) {
                            ecDisplay.innerHTML = `<i class="fa-solid fa-calendar-days text-primary me-1"></i>${newEc ? newEc.formatted : 'N/A'}`;
                        }
                        syncCalculations();
                    });
                }

                updateValidationBadges();
                syncCalculations();
            }

            function prefillMappedFields(frm, mapping, ext, fullScan) {
                if (!frm || !mapping) return;

                const setValue = function (selector, val) {
                    if (!selector || val === undefined || val === null || val === '') return;
                    const el = frm.querySelector(selector);
                    if (el) {
                        el.value = val;
                        el.dispatchEvent(new Event('input', { bubbles: true }));
                        el.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                };

                if (mapping.vendor_name) setValue(mapping.vendor_name, ext.vendor_name);
                if (mapping.supplier_tin) setValue(mapping.supplier_tin, ext.supplier_tin);
                if (mapping.buyer_tin) setValue(mapping.buyer_tin, ext.buyer_tin);
                if (mapping.fs_no) setValue(mapping.fs_no, ext.fs_no);
                if (mapping.mrc_no) setValue(mapping.mrc_no, ext.mrc_no);
                if (mapping.receipt_date) setValue(mapping.receipt_date, ext.receipt_date_ymd || ext.receipt_date);
                if (mapping.total_amount) setValue(mapping.total_amount, ext.total_amount);
                if (mapping.subtotal) setValue(mapping.subtotal, ext.subtotal);
                if (mapping.vat_amount) setValue(mapping.vat_amount, ext.vat_amount);
                if (mapping.description) setValue(mapping.description, ext.description);

                if (mapping.notes) {
                    const notesEl = frm.querySelector(mapping.notes);
                    if (notesEl && !notesEl.value) {
                        const noteText = `FS #${ext.fs_no || 'N/A'} | TIN: ${ext.supplier_tin || 'N/A'}${ext.receipt_date ? ' | Date: ' + ext.receipt_date : ''}`;
                        notesEl.value = noteText;
                        notesEl.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                }
            }

            function renderDuplicateAlert(container, scanData, btn) {
                if (form) form.dataset.ocrDuplicate = 'true';
                if (btn) {
                    btn.disabled = true;
                    btn.classList.add('disabled');
                    btn.setAttribute('title', 'Duplicate receipt submission blocked.');
                }

                const dup = scanData.existing_receipt || {};
                const warningsHtml = renderWarnings(scanData.warnings);

                container.innerHTML = `
                    <div class="alert alert-danger border-0 shadow-xs py-2.5 px-3 mb-2">
                        <div class="d-flex align-items-start gap-2">
                            <i class="fas fa-ban text-danger fa-lg mt-1"></i>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong class="text-danger small">DUPLICATE RECEIPT DETECTED — SUBMISSION BLOCKED</strong>
                                    <span class="badge bg-danger text-white">Duplicate FS No</span>
                                </div>
                                <p class="small text-dark mb-1">
                                    This receipt (<strong>FS No: ${scanData.data.fs_no || 'N/A'}</strong>, <strong>TIN: ${scanData.data.supplier_tin || 'N/A'}</strong>)
                                    is already recorded in the ERP under <strong>#${dup.receipt_number || 'N/A'}</strong> (${dup.vendor_name || 'Vendor'})
                                    for <strong>${dup.total_amount ? Number(dup.total_amount).toLocaleString() + ' ETB' : ''}</strong>.
                                </p>
                                <div class="small text-muted" style="font-size:0.75rem;">
                                    <i class="fas fa-info-circle me-1"></i> Under Ethiopian tax rules, duplicate fiscal receipts cannot be submitted.
                                </div>
                                ${warningsHtml}
                            </div>
                        </div>
                    </div>`;
            }

            function renderSuccessBanner(container, scanData, btn) {
                if (form) form.dataset.ocrDuplicate = 'false';
                if (btn) {
                    btn.disabled = false;
                    btn.classList.remove('disabled');
                    btn.removeAttribute('title');
                }

                const ext = scanData.data || {};
                const warningsHtml = renderWarnings(scanData.warnings);
                const score = scanData.confidence_score || 90;
                const scoreBadge = score >= 85 ? 'bg-success' : (score >= 70 ? 'bg-warning text-dark' : 'bg-secondary');

                container.innerHTML = `
                    <div class="card border border-success border-opacity-25 bg-success bg-opacity-10 py-2 px-3 mb-2">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas fa-circle-check text-success fa-lg"></i>
                                <div>
                                    <span class="small fw-bold text-success d-block">
                                        Receipt Scanned (${scanData.engine || 'AI Vision'})
                                    </span>
                                    <small class="text-muted" style="font-size:0.73rem;">
                                        Vendor: <strong>${ext.vendor_name || 'Extracted'}</strong> | FS: <strong>${ext.fs_no || 'N/A'}</strong> | TIN: <strong>${ext.supplier_tin || 'N/A'}</strong> | Total: <strong>${ext.total_amount ? Number(ext.total_amount).toLocaleString() + ' ETB' : '0.00'}</strong>
                                    </small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge ${scoreBadge} small" style="font-size:0.7rem;">QC Score: ${score}%</span>
                                <span class="badge bg-outline-success text-success border border-success small" style="font-size:0.7rem;">Verified Unique</span>
                            </div>
                        </div>
                        ${warningsHtml}
                    </div>`;
            }

            function renderFallback(container, errorMsg, btn) {
                if (form) form.dataset.ocrDuplicate = 'false';
                if (btn) {
                    btn.disabled = false;
                    btn.classList.remove('disabled');
                    btn.removeAttribute('title');
                }

                container.innerHTML = `
                    <div class="alert alert-warning py-2 px-3 small border-0 mb-2">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <i class="fas fa-triangle-exclamation text-warning me-1"></i>
                                <span>OCR scan notice: ${errorMsg} Form remains fully editable for manual entry.</span>
                            </div>
                            <span class="badge bg-warning text-dark small" style="font-size:0.68rem;">Manual Entry Active</span>
                        </div>
                    </div>`;
            }

            function renderWarnings(warnings) {
                if (!Array.isArray(warnings) || warnings.length === 0) return '';
                const items = warnings.map(w => `<li style="font-size:0.72rem;">${w}</li>`).join('');
                return `
                    <div class="mt-1 pt-1 border-top border-warning border-opacity-25">
                        <small class="text-warning-emphasis fw-bold d-block" style="font-size:0.72rem;">
                            <i class="fas fa-triangle-exclamation me-1"></i> Attention items for audit review:
                        </small>
                        <ul class="mb-0 ps-3 text-muted">${items}</ul>
                    </div>`;
            }
        }
    };

    window.ReceiptOcrScanner = ReceiptOcrScanner;

})(window);
