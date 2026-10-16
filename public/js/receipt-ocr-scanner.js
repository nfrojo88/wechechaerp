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
                let score = scanData.confidence_score || 95;
                let isVerified = !isDup; // Auto-verified if valid
                let currentZoom = 1.0;

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

                function round2(num) {
                    return Math.round((Number(num) || 0) * 100) / 100;
                }

                function escapeHtml(str) {
                    return (str || '').toString()
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                }

                // Initial VAT calculation
                let initialVat = parseFloat(ext.vat_amount) || 0;
                let initialSubtotal = parseFloat(ext.subtotal) || 0;
                let initialTotal = parseFloat(ext.total_amount) || (initialSubtotal + initialVat);
                const hasInitialVat = initialVat > 0;

                // Today's Date for Encoding Date
                const todayObj = new Date();
                const todayDateYmd = todayObj.toISOString().slice(0, 10);
                const todayEc = gregorianToEthiopian(todayDateYmd);
                const todayEcFormatted = todayEc ? todayEc.formatted : 'N/A';

                // Initial Purchase Type (3 for VAT Included, 6 for Non-VAT/Exempt, or custom)
                let initialPurcType = ext.purchase_type || (hasInitialVat ? 3 : 6);

                // Purchaser TIN & Encoded By defaults
                const defaultPurchaserTin = (config && config.purchaserTin) ? config.purchaserTin : (ext.purchaser_tin || ext.buyer_tin || '0038480010');
                const defaultEncodedBy = (config && config.encodedBy) ? config.encodedBy : (ext.encoded_by || 'wechacha');

                // Parse Ethiopian date
                const ecInfo = gregorianToEthiopian(ext.receipt_date_ymd || ext.receipt_date);
                let ecFormatted = ecInfo ? ecInfo.formatted : 'N/A';

                // Initial item list with defensive fallback
                let items = Array.isArray(ext.items) && ext.items.length > 0
                    ? ext.items.map(it => ({
                        item_description: it.item_description || ext.description || 'Material Item',
                        qty: Number(it.qty) || 1,
                        unit_price: Number(it.unit_price) || (Number(it.total_value) || initialSubtotal || 0),
                        total_value: Number(it.total_value) || round2((Number(it.qty) || 1) * (Number(it.unit_price) || 0)),
                        vat: Number(it.vat) || (hasInitialVat ? round2((Number(it.total_value) || 0) * 0.15) : 0),
                        value_after_vat: Number(it.value_after_vat) || round2((Number(it.total_value) || 0) + (Number(it.vat) || 0)),
                        uom: it.uom || '9'
                    }))
                    : [{
                        item_description: ext.description || ext.vendor_name || 'Material Item',
                        qty: 1,
                        unit_price: initialSubtotal || initialTotal || 0,
                        total_value: initialSubtotal || initialTotal || 0,
                        vat: initialVat || (hasInitialVat ? round2((initialSubtotal || initialTotal || 0) * 0.15) : 0),
                        value_after_vat: initialTotal || (initialSubtotal + initialVat),
                        uom: '9'
                    }];

                const studioId = 'studio_' + Math.random().toString(36).substring(2, 9);
                const isPdf = file.name.toLowerCase().endsWith('.pdf');

                const html = `
                    <div class="card border rounded-3 p-0 mb-3 shadow-sm bg-white overflow-hidden ocr-studio-container" id="${studioId}">
                        <!-- Studio Header Bar -->
                        <div class="card-header bg-dark text-white py-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge bg-success text-white px-2.5 py-1 rounded-pill">
                                    <i class="fa-solid fa-receipt me-1"></i> Receipt OCR Studio
                                </span>
                                <span class="badge bg-primary text-white px-2 py-0.5 rounded-pill">
                                    <i class="fa-solid fa-microchip me-1"></i> ${scanData.engine || 'Multi-Engine AI'}
                                </span>
                                <span class="badge bg-info text-white px-2 py-0.5 rounded-pill" id="${studioId}_confidence_badge">
                                    ${score}% Confidence
                                </span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <div id="${studioId}_verification_status_badge">
                                    <span class="badge bg-success text-white px-2.5 py-1">
                                        <i class="fa-solid fa-circle-check me-1"></i> Verified &amp; Ready for VAT Section
                                    </span>
                                </div>
                                ${isDup ? `
                                    <span class="badge bg-danger text-white px-2.5 py-1">
                                        <i class="fa-solid fa-ban me-1"></i> DUPLICATE RECEIPT
                                    </span>
                                ` : ''}
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
                                <!-- Left Column: Receipt Visual Preview & Zoom / Rotation Controls -->
                                <div class="col-12 col-md-5">
                                    <div class="p-2 border rounded-3 bg-light text-center h-100 d-flex flex-column align-items-center justify-content-between" style="min-height: 270px;">
                                        <div class="position-relative w-100 d-flex align-items-center justify-content-center overflow-hidden" style="max-height: 270px; min-height: 210px; background:#fff; border-radius:6px;">
                                            ${isPdf ? `
                                                <div class="py-4 text-center">
                                                    <i class="fa-solid fa-file-pdf fa-4x text-danger mb-2"></i>
                                                    <div class="fw-bold text-dark small">${escapeHtml(file.name)}</div>
                                                    <span class="badge bg-secondary text-white mt-1">PDF Receipt Document</span>
                                                </div>
                                            ` : `
                                                <img src="${currentObjectUrl || ''}" alt="Receipt Preview" id="${studioId}_preview_img" class="img-fluid rounded shadow-2xs" style="max-height: 260px; object-fit: contain; transform: rotate(${currentRotation}deg) scale(${currentZoom}); transition: transform 0.2s ease;">
                                            `}
                                        </div>

                                        <!-- Image Controls (Rotate, Zoom, Re-scan, Clear) -->
                                        <div class="d-flex flex-wrap justify-content-center gap-1 mt-2 w-100">
                                            ${!isPdf ? `
                                                <div class="btn-group btn-group-sm me-1 shadow-2xs">
                                                    <button type="button" class="btn btn-outline-secondary btn-xs py-1 px-2" id="${studioId}_btn_zoom_in" title="Zoom In">
                                                        <i class="fa-solid fa-magnifying-glass-plus"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-secondary btn-xs py-1 px-2" id="${studioId}_btn_zoom_out" title="Zoom Out">
                                                        <i class="fa-solid fa-magnifying-glass-minus"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-secondary btn-xs py-1 px-2" id="${studioId}_btn_zoom_reset" title="Reset Zoom">
                                                        <i class="fa-solid fa-arrows-rotate"></i>
                                                    </button>
                                                </div>
                                                <button type="button" class="btn btn-outline-secondary btn-xs py-1 px-2" id="${studioId}_btn_rotate" title="Rotate 90°">
                                                    <i class="fa-solid fa-rotate-right me-1"></i> Rotate
                                                </button>
                                            ` : ''}
                                            <button type="button" class="btn btn-outline-primary btn-xs py-1 px-2" id="${studioId}_btn_rescan" title="Re-scan OCR">
                                                <i class="fa-solid fa-bolt me-1"></i> Re-Scan
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-xs py-1 px-2" id="${studioId}_btn_clear" title="Clear Receipt">
                                                <i class="fa-solid fa-trash me-1"></i> Clear
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right Column: 9-Column ERCA Purchase TAS Form (Editable) -->
                                <div class="col-12 col-md-7">
                                    <div class="card border rounded-3 shadow-2xs bg-white mb-2 overflow-hidden">
                                        <div class="card-header bg-dark text-white py-1.5 px-3 d-flex align-items-center justify-content-between flex-wrap gap-1">
                                            <span class="small fw-bold" style="font-size:0.78rem;">
                                                <i class="fa-solid fa-file-invoice me-1 text-success"></i> ERCA Purchase TAS Declaration (9 Columns)
                                            </span>
                                            <span class="badge bg-primary bg-opacity-25 text-white border border-primary border-opacity-50" style="font-size:0.65rem;">
                                                <i class="fa-solid fa-pen-to-square me-1"></i> Editable Fields
                                            </span>
                                        </div>
                                        <div class="card-body p-2.5">
                                            <div class="row g-2">
                                                <!-- Col 1: Purchaser TIN -->
                                                <div class="col-12 col-sm-4">
                                                    <label class="form-label small fw-bold text-dark mb-0 d-flex justify-content-between">
                                                        <span>1. Purchaser TIN</span>
                                                        <span class="badge bg-light text-muted border" style="font-size:0.65rem;">Buyer</span>
                                                    </label>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text bg-light"><i class="fa-solid fa-building text-secondary"></i></span>
                                                        <input type="text" class="form-control font-monospace fw-bold" id="${studioId}_purchaser_tin" value="${escapeHtml(defaultPurchaserTin)}" placeholder="0038480010">
                                                    </div>
                                                    <small class="text-muted" style="font-size:0.65rem;">Buyer TIN</small>
                                                </div>

                                                <!-- Col 2: Seller TIN -->
                                                <div class="col-12 col-sm-4">
                                                    <label class="form-label small fw-bold text-dark mb-0 d-flex justify-content-between">
                                                        <span>2. Seller TIN</span>
                                                        <span class="badge bg-success bg-opacity-10 text-success border" style="font-size:0.65rem;">Supplier</span>
                                                    </label>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text bg-light"><i class="fa-solid fa-id-card text-muted"></i></span>
                                                        <input type="text" class="form-control font-monospace fw-bold text-primary" id="${studioId}_seller_tin" value="${escapeHtml(ext.supplier_tin || '')}" placeholder="10 Digits" maxlength="15">
                                                    </div>
                                                    <div id="${studioId}_tin_badge" class="mt-0.5" style="font-size:0.68rem;"></div>
                                                </div>

                                                <!-- Col 3: Amount -->
                                                <div class="col-12 col-sm-4">
                                                    <label class="form-label small fw-bold text-dark mb-0 d-flex justify-content-between">
                                                        <span>3. Amount (ETB)</span>
                                                        <span class="badge bg-success bg-opacity-10 text-success border" style="font-size:0.65rem;">Total</span>
                                                    </label>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text bg-light font-monospace fw-bold text-success">ETB</span>
                                                        <input type="number" step="0.01" min="0" class="form-control font-monospace fw-bold text-success text-end" id="${studioId}_amount" value="${Number(initialTotal).toFixed(2)}">
                                                    </div>
                                                    <small class="text-muted" style="font-size:0.65rem;">Total Purchase Value</small>
                                                </div>

                                                <!-- Col 4: Receipt No (FS #) -->
                                                <div class="col-12 col-sm-4">
                                                    <label class="form-label small fw-bold text-dark mb-0 d-flex justify-content-between">
                                                        <span>4. Receipt No (FS #)</span>
                                                        <span class="badge bg-primary bg-opacity-10 text-primary border" style="font-size:0.65rem;">FS No</span>
                                                    </label>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text bg-light"><i class="fa-solid fa-hashtag text-muted"></i></span>
                                                        <input type="text" class="form-control font-monospace fw-bold text-dark" id="${studioId}_receipt_no" value="${escapeHtml(ext.fs_no || '')}" placeholder="8 Digits">
                                                    </div>
                                                    <div id="${studioId}_fs_badge" class="mt-0.5" style="font-size:0.68rem;"></div>
                                                </div>

                                                <!-- Col 5: Receipt Date -->
                                                <div class="col-12 col-sm-4">
                                                    <label class="form-label small fw-bold text-dark mb-0 d-flex justify-content-between">
                                                        <span>5. Receipt Date</span>
                                                        <span class="badge bg-primary bg-opacity-10 text-primary border" style="font-size:0.65rem;">Purchase Date</span>
                                                    </label>
                                                    <input type="date" class="form-control form-control-sm font-monospace" id="${studioId}_receipt_date" value="${ext.receipt_date_ymd || ext.receipt_date || ''}">
                                                    <div class="text-muted font-monospace mt-0.5" id="${studioId}_ec_display" style="font-size:0.68rem;">
                                                        <i class="fa-solid fa-calendar-days text-primary me-1"></i>${ecFormatted}
                                                    </div>
                                                </div>

                                                <!-- Col 6: Encoding Date -->
                                                <div class="col-12 col-sm-4">
                                                    <label class="form-label small fw-bold text-dark mb-0 d-flex justify-content-between">
                                                        <span>6. Encoding Date</span>
                                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border" style="font-size:0.65rem;">Entry Date</span>
                                                    </label>
                                                    <input type="date" class="form-control form-control-sm font-monospace" id="${studioId}_encoding_date" value="${todayDateYmd}">
                                                    <div class="text-muted font-monospace mt-0.5" id="${studioId}_encoding_ec_display" style="font-size:0.68rem;">
                                                        <i class="fa-solid fa-clock text-secondary me-1"></i>${todayEcFormatted}
                                                    </div>
                                                </div>

                                                <!-- Col 7: MRC -->
                                                <div class="col-12 col-sm-4">
                                                    <label class="form-label small fw-bold text-dark mb-0 d-flex justify-content-between">
                                                        <span>7. MRC (Machine No)</span>
                                                        <span class="badge bg-light text-muted border" style="font-size:0.65rem;">MRC</span>
                                                    </label>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text bg-light"><i class="fa-solid fa-cash-register text-muted"></i></span>
                                                        <input type="text" class="form-control font-monospace" id="${studioId}_mrc" value="${escapeHtml(ext.mrc_no || '')}" placeholder="e.g. DDB0000032">
                                                    </div>
                                                    <small class="text-muted" style="font-size:0.65rem;">Machine Reg Code</small>
                                                </div>

                                                <!-- Col 8: PurcType -->
                                                <div class="col-12 col-sm-4">
                                                    <label class="form-label small fw-bold text-dark mb-0 d-flex justify-content-between">
                                                        <span>8. PurcType</span>
                                                        <span class="badge bg-warning bg-opacity-10 text-dark border" style="font-size:0.65rem;">Category</span>
                                                    </label>
                                                    <select class="form-select form-select-sm fw-semibold" id="${studioId}_purc_type">
                                                        <option value="3" ${initialPurcType == 3 ? 'selected' : ''}>3 - Goods (15% VAT - Line 100)</option>
                                                        <option value="6" ${initialPurcType == 6 ? 'selected' : ''}>6 - Non-VAT / Exempt (TAS Excel)</option>
                                                        <option value="1" ${initialPurcType == 1 ? 'selected' : ''}>1 - Raw Materials</option>
                                                        <option value="2" ${initialPurcType == 2 ? 'selected' : ''}>2 - Direct Operating Expenses</option>
                                                        <option value="4" ${initialPurcType == 4 ? 'selected' : ''}>4 - Capital Assets</option>
                                                        <option value="5" ${initialPurcType == 5 ? 'selected' : ''}>5 - Administrative Expenses</option>
                                                    </select>
                                                    <small class="text-muted" style="font-size:0.65rem;">ERCA Purchase Type Code</small>
                                                </div>

                                                <!-- Col 9: Encoded By -->
                                                <div class="col-12 col-sm-4">
                                                    <label class="form-label small fw-bold text-dark mb-0 d-flex justify-content-between">
                                                        <span>9. Encoded By</span>
                                                        <span class="badge bg-info bg-opacity-10 text-info border" style="font-size:0.65rem;">Encoder</span>
                                                    </label>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text bg-light"><i class="fa-solid fa-user-pen text-muted"></i></span>
                                                        <input type="text" class="form-control font-monospace" id="${studioId}_encoded_by" value="${escapeHtml(defaultEncodedBy)}" placeholder="e.g. wechacha">
                                                    </div>
                                                    <small class="text-muted" style="font-size:0.65rem;">User / Operator Name</small>
                                                </div>

                                                <!-- Extra Merchant Name & Item Description -->
                                                <div class="col-12 col-sm-6">
                                                    <label class="form-label small fw-bold text-dark mb-0">Merchant / Supplier Name</label>
                                                    <input type="text" class="form-control form-control-sm fw-semibold" id="${studioId}_vendor_name" value="${escapeHtml(ext.vendor_name || '')}" placeholder="Supplier registered name">
                                                </div>
                                                <div class="col-12 col-sm-6">
                                                    <label class="form-label small fw-bold text-dark mb-0">Item / Commodity Description</label>
                                                    <input type="text" class="form-control form-control-sm" id="${studioId}_description" value="${escapeHtml(ext.description || '')}" placeholder="Purchased goods description">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Live 9-Column ERCA Purchase TAS Output Format Preview -->
                                    <div class="card border rounded-2 shadow-2xs overflow-hidden mb-2">
                                        <div class="card-header bg-dark text-white py-1 px-3 d-flex align-items-center justify-content-between">
                                            <span class="small fw-bold" style="font-size:0.75rem;">
                                                <i class="fa-solid fa-table-list me-1 text-success"></i> ERCA Purchase TAS Output Format Preview (9 Columns)
                                            </span>
                                            <span class="badge bg-success bg-opacity-25 text-white border border-success" style="font-size:0.65rem;">e-Tax Compliant</span>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-sm text-center mb-0" style="font-size:0.7rem;">
                                                <thead class="bg-light text-dark small fw-bold text-uppercase">
                                                    <tr>
                                                        <th class="py-1">Purchaser TIN</th>
                                                        <th class="py-1">Seller TIN</th>
                                                        <th class="py-1">Amount</th>
                                                        <th class="py-1">Receipt No</th>
                                                        <th class="py-1">Receipt Date</th>
                                                        <th class="py-1">Encoding Date</th>
                                                        <th class="py-1">MRC</th>
                                                        <th class="py-1">PurcType</th>
                                                        <th class="py-1">Encoded By</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr class="font-monospace fw-bold align-middle bg-white">
                                                        <td id="${studioId}_tbl_purchaser_tin" class="text-secondary">${escapeHtml(defaultPurchaserTin)}</td>
                                                        <td id="${studioId}_tbl_seller_tin" class="text-primary">${escapeHtml(ext.supplier_tin || '-')}</td>
                                                        <td id="${studioId}_tbl_amount" class="text-success text-end pe-2">${Number(initialTotal).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                                                        <td id="${studioId}_tbl_receipt_no" class="text-dark">${escapeHtml(ext.fs_no || '-')}</td>
                                                        <td id="${studioId}_tbl_receipt_date" class="text-muted small">${ecFormatted}</td>
                                                        <td id="${studioId}_tbl_encoding_date" class="text-muted small">${todayEcFormatted}</td>
                                                        <td id="${studioId}_tbl_mrc" class="text-muted">${escapeHtml(ext.mrc_no || '-')}</td>
                                                        <td id="${studioId}_tbl_purctype"><span class="badge bg-primary" id="${studioId}_tbl_purctype_badge">${initialPurcType}</span></td>
                                                        <td id="${studioId}_tbl_encoded_by" class="text-dark">${escapeHtml(defaultEncodedBy)}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <!-- Multi-Item Materials Table with Full Row-by-Row Editing -->
                                <div class="col-12">
                                    <div class="card border rounded-2 bg-light shadow-2xs overflow-hidden">
                                        <div class="card-header bg-white py-1.5 px-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="small fw-bold text-dark" style="font-size:0.78rem;">
                                                    <i class="fa-solid fa-list-check text-success me-1"></i> Itemized Purchased Materials (<span id="${studioId}_item_count">${items.length}</span> item(s))
                                                </span>
                                                <span class="badge bg-primary bg-opacity-10 text-primary border" style="font-size:0.68rem;">Editable Rows</span>
                                            </div>
                                            <div class="d-flex align-items-center gap-1.5">
                                                <button type="button" class="btn btn-xs btn-outline-secondary py-0.5 px-2" style="font-size:0.7rem;" id="${studioId}_btn_recalc_items" title="Recalculate Row Totals">
                                                    <i class="fa-solid fa-calculator me-1"></i> Recalculate Rows
                                                </button>
                                                <button type="button" class="btn btn-xs btn-outline-primary py-0.5 px-2 fw-semibold" style="font-size:0.7rem;" id="${studioId}_btn_add_item">
                                                    <i class="fa-solid fa-plus me-1"></i> Add Item Row
                                                </button>
                                            </div>
                                        </div>
                                        <div class="table-responsive" style="max-height: 180px; overflow-y: auto;">
                                            <table class="table table-sm table-hover align-middle mb-0" id="${studioId}_items_table" style="font-size:0.78rem;">
                                                <thead class="bg-light text-muted small text-uppercase">
                                                    <tr>
                                                        <th class="ps-2" style="width: 38%;">Item Description (Col I)</th>
                                                        <th class="text-center" style="width: 12%;">Qty (Col K)</th>
                                                        <th class="text-end" style="width: 18%;">Unit Price (Col L)</th>
                                                        <th class="text-end" style="width: 18%;">Total (Col M)</th>
                                                        <th class="text-end" style="width: 14%;">VAT 15% (Col N)</th>
                                                        <th class="text-center pe-2" style="width: 5%;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="${studioId}_items_tbody">
                                                    <!-- Injected dynamically via renderItemsTable -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

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
                                                        <strong class="d-block text-success small"><i class="fa-solid fa-percent me-1"></i>VAT Included (15% Input VAT)</strong>
                                                        <small class="text-muted d-block" style="font-size:0.68rem; line-height: 1.25;">15% creditable input VAT &rarr; Saved into Monthly ERCA Line 100 VAT Section (PurcType 3)</small>
                                                    </div>
                                                </label>
                                            </div>
                                            <div class="col-6">
                                                <label class="p-2 border rounded-2 bg-white d-flex align-items-start gap-2 cursor-pointer w-100 mb-0 shadow-2xs" id="${studioId}_opt_vat_none_label">
                                                    <input type="radio" name="${studioId}_vat_choice" value="none" class="form-check-input mt-1" ${!hasInitialVat ? 'checked' : ''}>
                                                    <div>
                                                        <strong class="d-block text-dark small"><i class="fa-solid fa-file-excel text-success me-1"></i>No VAT (Purchase TAS Excel)</strong>
                                                        <small class="text-muted d-block" style="font-size:0.68rem; line-height: 1.25;">Zero VAT &rarr; Declared in 9-Column Purchase TAS Excel report (PurcType 6)</small>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Financial Totals Breakdown Bar -->
                                <div class="col-12">
                                    <div class="p-2.5 rounded-3 bg-light border">
                                        <div class="row g-2 align-items-center">
                                            <div class="col-12 col-md-4">
                                                <label class="form-label small fw-bold text-muted mb-1 d-flex justify-content-between">
                                                    <span>Subtotal / Taxable (Col M)</span>
                                                    <span class="badge bg-light text-muted border">ETB</span>
                                                </label>
                                                <input type="number" step="0.01" class="form-control form-control-sm font-monospace fw-bold text-dark text-end" id="${studioId}_subtotal_input" value="${Number(initialSubtotal).toFixed(2)}">
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <label class="form-label small fw-bold text-info mb-1 d-flex justify-content-between">
                                                    <span>15% Input VAT (Col N)</span>
                                                    <span class="badge bg-info-subtle text-info border border-info">15%</span>
                                                </label>
                                                <input type="number" step="0.01" class="form-control form-control-sm font-monospace fw-bold text-info text-end" id="${studioId}_vat_input" value="${Number(initialVat).toFixed(2)}">
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <label class="form-label small fw-bold text-success mb-1 d-flex justify-content-between">
                                                    <span>Total Receipt Amount (Col O)</span>
                                                    <span class="badge bg-success-subtle text-success border border-success">Total</span>
                                                </label>
                                                <input type="number" step="0.01" class="form-control form-control-sm font-monospace fw-bold text-success text-end" id="${studioId}_total_input" value="${Number(initialTotal).toFixed(2)}">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Live Verification & Math Discrepancy Box with Auto-Fix -->
                                <div class="col-12">
                                    <div id="${studioId}_validation_box" class="p-3 rounded-3 border bg-light">
                                        <!-- Rendered dynamically by runVerificationChecks -->
                                    </div>
                                </div>

                                <!-- Verify & Confirm Action Button -->
                                <div class="col-12 text-end">
                                    <button type="button" class="btn btn-sm btn-outline-success fw-bold px-3 shadow-2xs" id="${studioId}_btn_verify_confirm">
                                        <i class="fa-solid fa-shield-check me-1"></i> Confirm &amp; Mark as Verified
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                container.innerHTML = html;

                // Elements inside studio
                const previewImg = document.getElementById(`${studioId}_preview_img`);
                const btnRotate = document.getElementById(`${studioId}_btn_rotate`);
                const btnZoomIn = document.getElementById(`${studioId}_btn_zoom_in`);
                const btnZoomOut = document.getElementById(`${studioId}_btn_zoom_out`);
                const btnZoomReset = document.getElementById(`${studioId}_btn_zoom_reset`);
                const btnRescan = document.getElementById(`${studioId}_btn_rescan`);
                const btnClear = document.getElementById(`${studioId}_btn_clear`);

                // 9 TAS Column inputs
                const inputPurchaserTin = document.getElementById(`${studioId}_purchaser_tin`);
                const inputSellerTin = document.getElementById(`${studioId}_seller_tin`);
                const inputAmount = document.getElementById(`${studioId}_amount`);
                const inputReceiptNo = document.getElementById(`${studioId}_receipt_no`);
                const inputReceiptDate = document.getElementById(`${studioId}_receipt_date`);
                const inputEncodingDate = document.getElementById(`${studioId}_encoding_date`);
                const inputMrc = document.getElementById(`${studioId}_mrc`);
                const selectPurcType = document.getElementById(`${studioId}_purc_type`);
                const inputEncodedBy = document.getElementById(`${studioId}_encoded_by`);

                // Extra inputs
                const inputVendor = document.getElementById(`${studioId}_vendor_name`);
                const inputDesc = document.getElementById(`${studioId}_description`);
                const inputSubtotal = document.getElementById(`${studioId}_subtotal_input`);
                const inputVat = document.getElementById(`${studioId}_vat_input`);
                const inputTotal = document.getElementById(`${studioId}_total_input`);

                // Badges & displays
                const tinBadge = document.getElementById(`${studioId}_tin_badge`);
                const fsBadge = document.getElementById(`${studioId}_fs_badge`);
                const ecDisplay = document.getElementById(`${studioId}_ec_display`);
                const encodingEcDisplay = document.getElementById(`${studioId}_encoding_ec_display`);
                const vatBadge = document.getElementById(`${studioId}_vat_badge`);
                const validationBox = document.getElementById(`${studioId}_validation_box`);
                const verificationStatusBadge = document.getElementById(`${studioId}_verification_status_badge`);
                const btnVerifyConfirm = document.getElementById(`${studioId}_btn_verify_confirm`);
                const confidenceBadge = document.getElementById(`${studioId}_confidence_badge`);

                // Live TAS table preview elements
                const tblPurchaserTin = document.getElementById(`${studioId}_tbl_purchaser_tin`);
                const tblSellerTin = document.getElementById(`${studioId}_tbl_seller_tin`);
                const tblAmount = document.getElementById(`${studioId}_tbl_amount`);
                const tblReceiptNo = document.getElementById(`${studioId}_tbl_receipt_no`);
                const tblReceiptDate = document.getElementById(`${studioId}_tbl_receipt_date`);
                const tblEncodingDate = document.getElementById(`${studioId}_tbl_encoding_date`);
                const tblMrc = document.getElementById(`${studioId}_tbl_mrc`);
                const tblPurcTypeBadge = document.getElementById(`${studioId}_tbl_purctype_badge`);
                const tblEncodedBy = document.getElementById(`${studioId}_tbl_encoded_by`);

                const optIncluded = document.querySelector(`input[name="${studioId}_vat_choice"][value="included"]`);
                const optNone = document.querySelector(`input[name="${studioId}_vat_choice"][value="none"]`);
                const itemsTbody = document.getElementById(`${studioId}_items_tbody`);
                const itemCountSpan = document.getElementById(`${studioId}_item_count`);
                const btnAddItem = document.getElementById(`${studioId}_btn_add_item`);
                const btnRecalcItems = document.getElementById(`${studioId}_btn_recalc_items`);

                function updateImageTransform() {
                    if (previewImg) {
                        previewImg.style.transform = `rotate(${currentRotation}deg) scale(${currentZoom})`;
                    }
                }

                // Render Itemized Rows
                function renderItemsTable() {
                    if (!itemsTbody) return;
                    if (itemCountSpan) itemCountSpan.innerText = items.length;

                    if (items.length === 0) {
                        itemsTbody.innerHTML = `
                            <tr>
                                <td colspan="6" class="text-center text-muted py-2 small">
                                    No material items added. Click "Add Item Row" above to itemize bought goods.
                                </td>
                            </tr>
                        `;
                        return;
                    }

                    const isVatIncluded = optIncluded && optIncluded.checked;

                    itemsTbody.innerHTML = items.map((it, idx) => `
                        <tr class="align-middle">
                            <td class="ps-2">
                                <input type="text" class="form-control form-control-xs py-1 px-2 ocr-item-desc" data-idx="${idx}" value="${escapeHtml(it.item_description || '')}" placeholder="Material description">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" class="form-control form-control-xs py-1 px-1 text-center font-monospace ocr-item-qty" data-idx="${idx}" value="${it.qty !== undefined ? it.qty : 1}">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" class="form-control form-control-xs py-1 px-1 text-end font-monospace ocr-item-price" data-idx="${idx}" value="${it.unit_price !== undefined ? it.unit_price : 0}">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" class="form-control form-control-xs py-1 px-1 text-end font-monospace fw-bold ocr-item-total" data-idx="${idx}" value="${it.total_value !== undefined ? it.total_value : 0}">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" class="form-control form-control-xs py-1 px-1 text-end font-monospace text-info ocr-item-vat" data-idx="${idx}" value="${it.vat !== undefined ? it.vat : 0}" ${!isVatIncluded ? 'readonly style="background:#f8f9fa;"' : ''}>
                            </td>
                            <td class="text-center pe-2">
                                <button type="button" class="btn btn-xs btn-outline-danger py-0.5 px-1.5 ocr-item-del-btn" data-idx="${idx}" title="Remove Item">
                                    <i class="fa-solid fa-trash" style="font-size:0.7rem;"></i>
                                </button>
                            </td>
                        </tr>
                    `).join('');

                    // Bind row input events
                    itemsTbody.querySelectorAll('.ocr-item-desc').forEach(el => {
                        el.addEventListener('input', (e) => {
                            const i = parseInt(e.target.dataset.idx);
                            if (items[i]) items[i].item_description = e.target.value;
                            syncCalculations();
                        });
                    });

                    itemsTbody.querySelectorAll('.ocr-item-qty').forEach(el => {
                        el.addEventListener('input', (e) => {
                            const i = parseInt(e.target.dataset.idx);
                            if (items[i]) {
                                items[i].qty = parseFloat(e.target.value) || 0;
                                items[i].total_value = round2(items[i].qty * (items[i].unit_price || 0));
                                if (optIncluded && optIncluded.checked) {
                                    items[i].vat = round2(items[i].total_value * 0.15);
                                } else {
                                    items[i].vat = 0.00;
                                }
                                items[i].value_after_vat = round2(items[i].total_value + items[i].vat);

                                const row = e.target.closest('tr');
                                if (row) {
                                    const totInput = row.querySelector('.ocr-item-total');
                                    const vatInput = row.querySelector('.ocr-item-vat');
                                    if (totInput) totInput.value = items[i].total_value;
                                    if (vatInput) vatInput.value = items[i].vat;
                                }
                                recalculateTotalsFromItems();
                            }
                        });
                    });

                    itemsTbody.querySelectorAll('.ocr-item-price').forEach(el => {
                        el.addEventListener('input', (e) => {
                            const i = parseInt(e.target.dataset.idx);
                            if (items[i]) {
                                items[i].unit_price = parseFloat(e.target.value) || 0;
                                items[i].total_value = round2((items[i].qty || 1) * items[i].unit_price);
                                if (optIncluded && optIncluded.checked) {
                                    items[i].vat = round2(items[i].total_value * 0.15);
                                } else {
                                    items[i].vat = 0.00;
                                }
                                items[i].value_after_vat = round2(items[i].total_value + items[i].vat);

                                const row = e.target.closest('tr');
                                if (row) {
                                    const totInput = row.querySelector('.ocr-item-total');
                                    const vatInput = row.querySelector('.ocr-item-vat');
                                    if (totInput) totInput.value = items[i].total_value;
                                    if (vatInput) vatInput.value = items[i].vat;
                                }
                                recalculateTotalsFromItems();
                            }
                        });
                    });

                    itemsTbody.querySelectorAll('.ocr-item-total').forEach(el => {
                        el.addEventListener('input', (e) => {
                            const i = parseInt(e.target.dataset.idx);
                            if (items[i]) {
                                items[i].total_value = parseFloat(e.target.value) || 0;
                                if (optIncluded && optIncluded.checked) {
                                    items[i].vat = round2(items[i].total_value * 0.15);
                                } else {
                                    items[i].vat = 0.00;
                                }
                                items[i].value_after_vat = round2(items[i].total_value + items[i].vat);

                                const row = e.target.closest('tr');
                                if (row) {
                                    const vatInput = row.querySelector('.ocr-item-vat');
                                    if (vatInput) vatInput.value = items[i].vat;
                                }
                                recalculateTotalsFromItems();
                            }
                        });
                    });

                    itemsTbody.querySelectorAll('.ocr-item-vat').forEach(el => {
                        el.addEventListener('input', (e) => {
                            const i = parseInt(e.target.dataset.idx);
                            if (items[i]) {
                                items[i].vat = parseFloat(e.target.value) || 0;
                                items[i].value_after_vat = round2(items[i].total_value + items[i].vat);
                                recalculateTotalsFromItems();
                            }
                        });
                    });

                    itemsTbody.querySelectorAll('.ocr-item-del-btn').forEach(el => {
                        el.addEventListener('click', (e) => {
                            const btn = e.target.closest('button');
                            const i = parseInt(btn.dataset.idx);
                            if (!isNaN(i) && items[i]) {
                                items.splice(i, 1);
                                renderItemsTable();
                                recalculateTotalsFromItems();
                            }
                        });
                    });
                }

                function recalculateTotalsFromItems() {
                    const isVatIncluded = optIncluded && optIncluded.checked;
                    let sumSubtotal = round2(items.reduce((s, it) => s + (parseFloat(it.total_value) || 0), 0));
                    let sumVat = isVatIncluded ? round2(items.reduce((s, it) => s + (parseFloat(it.vat) || 0), 0)) : 0.00;
                    if (isVatIncluded && sumVat === 0.0 && sumSubtotal > 0) {
                        sumVat = round2(sumSubtotal * 0.15);
                    }
                    let sumTotal = round2(sumSubtotal + sumVat);

                    if (inputSubtotal) inputSubtotal.value = sumSubtotal.toFixed(2);
                    if (inputVat) inputVat.value = sumVat.toFixed(2);
                    if (inputTotal) inputTotal.value = sumTotal.toFixed(2);
                    if (inputAmount) inputAmount.value = sumTotal.toFixed(2);

                    syncCalculations();
                }

                function autoFixMath() {
                    const isVatIncluded = optIncluded && optIncluded.checked;
                    items.forEach(it => {
                        it.qty = parseFloat(it.qty) || 1;
                        it.unit_price = parseFloat(it.unit_price) || 0;
                        it.total_value = round2(it.qty * it.unit_price);
                        it.vat = isVatIncluded ? round2(it.total_value * 0.15) : 0.00;
                        it.value_after_vat = round2(it.total_value + it.vat);
                    });

                    renderItemsTable();
                    recalculateTotalsFromItems();

                    isVerified = true;
                    runVerificationChecks();
                }

                function updateValidationBadges() {
                    const rawTin = (inputSellerTin ? inputSellerTin.value : '').replace(/\D/g, '');
                    const isTinValid = rawTin.length === 10;
                    if (tinBadge) {
                        tinBadge.innerHTML = isTinValid
                            ? `<span class="badge bg-success-subtle text-success border border-success"><i class="fa-solid fa-check me-1"></i>10 Digits Valid</span>`
                            : `<span class="badge bg-danger-subtle text-danger border border-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>Expected 10 digits (${rawTin.length}/10)</span>`;
                    }

                    const rawFs = (inputReceiptNo ? inputReceiptNo.value : '').replace(/\D/g, '');
                    const isFsValid = rawFs.length === 8;
                    if (fsBadge) {
                        fsBadge.innerHTML = isFsValid
                            ? `<span class="badge bg-success-subtle text-success border border-success"><i class="fa-solid fa-check me-1"></i>8 Digits Valid</span>`
                            : `<span class="badge bg-danger-subtle text-danger border border-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>Expected 8 digits (${rawFs.length}/8)</span>`;
                    }
                }

                function updateTasTablePreview() {
                    const pTin = inputPurchaserTin ? inputPurchaserTin.value : defaultPurchaserTin;
                    const sTin = inputSellerTin ? inputSellerTin.value : '';
                    const amt = parseFloat(inputAmount ? inputAmount.value : (inputTotal ? inputTotal.value : 0)) || 0;
                    const rNo = inputReceiptNo ? inputReceiptNo.value : '';
                    const rDate = inputReceiptDate ? inputReceiptDate.value : '';
                    const eDate = inputEncodingDate ? inputEncodingDate.value : todayDateYmd;
                    const mrcVal = inputMrc ? inputMrc.value : '';
                    const pType = selectPurcType ? selectPurcType.value : (optIncluded && optIncluded.checked ? '3' : '6');
                    const encBy = inputEncodedBy ? inputEncodedBy.value : defaultEncodedBy;

                    const rDateEc = gregorianToEthiopian(rDate);
                    const eDateEc = gregorianToEthiopian(eDate);

                    if (tblPurchaserTin) tblPurchaserTin.innerText = pTin || '-';
                    if (tblSellerTin) tblSellerTin.innerText = sTin || '-';
                    if (tblAmount) tblAmount.innerText = amt.toLocaleString(undefined, {minimumFractionDigits: 2});
                    if (tblReceiptNo) tblReceiptNo.innerText = rNo || '-';
                    if (tblReceiptDate) tblReceiptDate.innerText = rDateEc ? rDateEc.formatted : (rDate || '-');
                    if (tblEncodingDate) tblEncodingDate.innerText = eDateEc ? eDateEc.formatted : (eDate || '-');
                    if (tblMrc) tblMrc.innerText = mrcVal || '-';
                    if (tblPurcTypeBadge) {
                        tblPurcTypeBadge.innerText = pType;
                        tblPurcTypeBadge.className = (pType === '3') ? 'badge bg-success' : 'badge bg-primary';
                    }
                    if (tblEncodedBy) tblEncodedBy.innerText = encBy || '-';
                }

                function runVerificationChecks() {
                    updateValidationBadges();
                    updateTasTablePreview();

                    const isVatIncluded = optIncluded && optIncluded.checked;
                    const rawTin = (inputSellerTin ? inputSellerTin.value : '').replace(/\D/g, '');
                    const rawFs = (inputReceiptNo ? inputReceiptNo.value : '').replace(/\D/g, '');
                    const subVal = parseFloat(inputSubtotal ? inputSubtotal.value : 0) || 0;
                    const vatVal = parseFloat(inputVat ? inputVat.value : 0) || 0;
                    const totVal = parseFloat(inputTotal ? inputTotal.value : 0) || 0;
                    const amtVal = parseFloat(inputAmount ? inputAmount.value : 0) || 0;

                    const warnings = [];

                    if (!inputPurchaserTin || !inputPurchaserTin.value.trim()) {
                        warnings.push(`Purchaser TIN (Col 1) is required.`);
                    }
                    if (rawTin.length !== 10) {
                        warnings.push(`Seller TIN (Col 2) is invalid: Expected 10 digits (currently ${rawTin.length}).`);
                    }
                    if (amtVal <= 0) {
                        warnings.push(`Amount (Col 3) must be greater than zero.`);
                    }
                    if (rawFs.length !== 8) {
                        warnings.push(`Receipt / FS No (Col 4) is invalid: Expected 8 digits (currently ${rawFs.length}).`);
                    }
                    if (!inputReceiptDate || !inputReceiptDate.value) {
                        warnings.push(`Receipt Date (Col 5) is missing.`);
                    }
                    if (!inputEncodingDate || !inputEncodingDate.value) {
                        warnings.push(`Encoding Date (Col 6) is missing.`);
                    }
                    if (!selectPurcType || !selectPurcType.value) {
                        warnings.push(`PurcType (Col 8) is required.`);
                    }

                    // Arithmetic consistency checks
                    if (items.length > 0) {
                        const itemsSum = round2(items.reduce((s, it) => s + (parseFloat(it.total_value) || 0), 0));
                        if (Math.abs(itemsSum - subVal) > 0.05) {
                            warnings.push(`Items total sum (ETB ${itemsSum.toFixed(2)}) does not match Subtotal (ETB ${subVal.toFixed(2)}).`);
                        }
                    }

                    if (isVatIncluded) {
                        const expectedVat = round2(subVal * 0.15);
                        if (subVal > 0 && Math.abs(expectedVat - vatVal) > 0.10) {
                            warnings.push(`15% VAT check: Subtotal x 15% (ETB ${expectedVat.toFixed(2)}) != VAT Amount (ETB ${vatVal.toFixed(2)}).`);
                        }
                    }

                    if (Math.abs(round2(subVal + vatVal) - totVal) > 0.05) {
                        warnings.push(`Total check: Subtotal (ETB ${subVal.toFixed(2)}) + VAT (ETB ${vatVal.toFixed(2)}) != Grand Total (ETB ${totVal.toFixed(2)}).`);
                    }

                    if (Math.abs(totVal - amtVal) > 0.05) {
                        warnings.push(`Amount discrepancy: Amount input (ETB ${amtVal.toFixed(2)}) != Grand Total (ETB ${totVal.toFixed(2)}).`);
                    }

                    if (validationBox) {
                        if (warnings.length > 0) {
                            validationBox.className = 'p-3 rounded-3 border border-warning bg-warning bg-opacity-10 text-dark';
                            validationBox.innerHTML = `
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                    <div class="fw-bold text-danger">
                                        <i class="fa-solid fa-triangle-exclamation me-1"></i> ${warnings.length} TAS Discrepancy / Warning(s) Detected:
                                    </div>
                                    <button type="button" class="btn btn-xs btn-warning text-dark fw-bold px-2.5 py-1 shadow-2xs" id="${studioId}_btn_autofix">
                                        <i class="fa-solid fa-wand-magic-sparkles me-1"></i> Auto-Fix Math &amp; Balance
                                    </button>
                                </div>
                                <ul class="mb-0 small ps-3">
                                    ${warnings.map(w => `<li class="mb-0.5">${w}</li>`).join('')}
                                </ul>
                            `;

                            const btnFix = document.getElementById(`${studioId}_btn_autofix`);
                            if (btnFix) btnFix.addEventListener('click', autoFixMath);
                        } else {
                            validationBox.className = 'p-3 rounded-3 border border-success bg-success bg-opacity-10 text-success';
                            validationBox.innerHTML = `
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div class="fw-bold">
                                        <i class="fa-solid fa-circle-check me-1"></i> All Checks Passed: 9-Column TAS fields and arithmetic verified for ERCA declaration!
                                    </div>
                                    <span class="badge bg-success text-white px-2 py-1">
                                        <i class="fa-solid fa-shield-check me-1"></i> 100% ERCA Compliant
                                    </span>
                                </div>
                            `;
                        }
                    }

                    // Update Verification Button & Header Status
                    if (verificationStatusBadge) {
                        if (isVerified && warnings.length === 0) {
                            verificationStatusBadge.innerHTML = `
                                <span class="badge bg-success text-white px-2.5 py-1">
                                    <i class="fa-solid fa-circle-check me-1"></i> Verified &amp; Ready for VAT / TAS
                                </span>
                            `;
                        } else if (isVerified) {
                            verificationStatusBadge.innerHTML = `
                                <span class="badge bg-warning text-dark px-2.5 py-1">
                                    <i class="fa-solid fa-user-check me-1"></i> User Verified (With Notes)
                                </span>
                            `;
                        } else {
                            verificationStatusBadge.innerHTML = `
                                <span class="badge bg-secondary text-white px-2.5 py-1">
                                    <i class="fa-solid fa-hourglass-half me-1"></i> Verification Pending
                                </span>
                            `;
                        }
                    }

                    if (btnVerifyConfirm) {
                        if (isVerified) {
                            btnVerifyConfirm.className = 'btn btn-sm btn-success fw-bold px-3 shadow-2xs';
                            btnVerifyConfirm.innerHTML = '<i class="fa-solid fa-shield-check me-1"></i> Verified by User (Click to Un-verify)';
                        } else {
                            btnVerifyConfirm.className = 'btn btn-sm btn-outline-success fw-bold px-3 shadow-2xs';
                            btnVerifyConfirm.innerHTML = '<i class="fa-solid fa-check me-1"></i> Confirm &amp; Mark as Verified';
                        }
                    }

                    if (confidenceBadge) {
                        confidenceBadge.innerText = (isVerified ? '100% Verified' : `${score}% Confidence`);
                    }
                }

                function syncCalculations() {
                    const isVatIncluded = optIncluded && optIncluded.checked;
                    let base = parseFloat(inputSubtotal ? inputSubtotal.value : 0) || 0;
                    let vat = isVatIncluded ? (parseFloat(inputVat ? inputVat.value : 0) || 0) : 0.00;
                    let tot = parseFloat(inputTotal ? inputTotal.value : 0) || round2(base + vat);
                    let amt = parseFloat(inputAmount ? inputAmount.value : 0) || tot;

                    // Sync PurcType dropdown with VAT toggle if needed
                    if (selectPurcType) {
                        if (isVatIncluded && selectPurcType.value === '6') {
                            selectPurcType.value = '3';
                        } else if (!isVatIncluded && selectPurcType.value === '3') {
                            selectPurcType.value = '6';
                        }
                    }

                    if (vatBadge) {
                        if (isVatIncluded) {
                            vatBadge.className = 'badge bg-success text-white px-2 py-0.5';
                            vatBadge.innerText = 'VAT Section (Line 100)';
                        } else {
                            vatBadge.className = 'badge bg-dark text-white px-2 py-0.5';
                            vatBadge.innerText = 'TAS Excel Declaration';
                        }
                    }

                    // Update all 9 TAS columns + metadata in form hidden inputs
                    setHidden('purchaser_tin', inputPurchaserTin ? inputPurchaserTin.value : defaultPurchaserTin);
                    setHidden('buyer_tin', inputPurchaserTin ? inputPurchaserTin.value : defaultPurchaserTin);
                    setHidden('seller_tin', inputSellerTin ? inputSellerTin.value : ext.supplier_tin);
                    setHidden('supplier_tin', inputSellerTin ? inputSellerTin.value : ext.supplier_tin);
                    setHidden('vendor_tin', inputSellerTin ? inputSellerTin.value : ext.supplier_tin);
                    setHidden('amount', amt.toFixed(2));
                    setHidden('total_amount', tot.toFixed(2));
                    setHidden('subtotal', base.toFixed(2));
                    setHidden('vat_amount', vat.toFixed(2));
                    setHidden('receipt_no', inputReceiptNo ? inputReceiptNo.value : ext.fs_no);
                    setHidden('fs_no', inputReceiptNo ? inputReceiptNo.value : ext.fs_no);
                    setHidden('receipt_date', inputReceiptDate ? inputReceiptDate.value : (ext.receipt_date_ymd || ext.receipt_date));
                    setHidden('encoding_date', inputEncodingDate ? inputEncodingDate.value : todayDateYmd);
                    setHidden('mrc', inputMrc ? inputMrc.value : ext.mrc_no);
                    setHidden('mrc_no', inputMrc ? inputMrc.value : ext.mrc_no);
                    setHidden('purc_type', selectPurcType ? selectPurcType.value : (isVatIncluded ? '3' : '6'));
                    setHidden('purchase_type', selectPurcType ? selectPurcType.value : (isVatIncluded ? '3' : '6'));
                    setHidden('encoded_by', inputEncodedBy ? inputEncodedBy.value : defaultEncodedBy);
                    setHidden('vendor_name', inputVendor ? inputVendor.value : ext.vendor_name);
                    setHidden('description', inputDesc ? inputDesc.value : ext.description);
                    setHidden('vat_included', isVatIncluded ? '1' : '0');
                    setHidden('is_verified', isVerified ? '1' : '0');
                    setHidden('ocr_items_json', JSON.stringify(items));
                    setHidden('ocr_engine', scanData.engine || 'Receipt OCR Studio');
                    setHidden('ocr_confidence', isVerified ? 'high' : (scanData.confidence || 'high'));
                    setHidden('ocr_confidence_score', isVerified ? 100 : (scanData.confidence_score || 95));

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

                    runVerificationChecks();
                }

                // Initial Table Render
                renderItemsTable();

                // Image Control Event Listeners
                if (btnRotate) {
                    btnRotate.addEventListener('click', function () {
                        currentRotation = (currentRotation + 90) % 360;
                        updateImageTransform();
                    });
                }

                if (btnZoomIn) {
                    btnZoomIn.addEventListener('click', function () {
                        currentZoom = Math.min(2.5, currentZoom + 0.2);
                        updateImageTransform();
                    });
                }

                if (btnZoomOut) {
                    btnZoomOut.addEventListener('click', function () {
                        currentZoom = Math.max(0.5, currentZoom - 0.2);
                        updateImageTransform();
                    });
                }

                if (btnZoomReset) {
                    btnZoomReset.addEventListener('click', function () {
                        currentZoom = 1.0;
                        currentRotation = 0;
                        updateImageTransform();
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
                        setHidden('seller_tin', '');
                        setHidden('purchaser_tin', '');
                        setHidden('fs_no', '');
                        setHidden('receipt_no', '');
                        setHidden('mrc_no', '');
                        setHidden('mrc', '');
                        setHidden('amount', '');
                        setHidden('subtotal', '');
                        setHidden('vat_amount', '');
                        setHidden('total_amount', '');
                        setHidden('purc_type', '');
                        setHidden('purchase_type', '');
                        setHidden('encoded_by', '');
                        setHidden('vat_included', '0');
                        setHidden('is_verified', '0');
                        setHidden('ocr_items_json', '');

                        if (typeof window.recalculateQuickPayTax === 'function') {
                            window.recalculateQuickPayTax();
                        }
                    });
                }

                // Add Item Row
                if (btnAddItem) {
                    btnAddItem.addEventListener('click', function () {
                        items.push({
                            item_description: '',
                            qty: 1,
                            unit_price: 0,
                            total_value: 0,
                            vat: 0,
                            value_after_vat: 0,
                            uom: '9'
                        });
                        renderItemsTable();
                        syncCalculations();
                    });
                }

                // Recalculate Row Totals Button
                if (btnRecalcItems) {
                    btnRecalcItems.addEventListener('click', function () {
                        const isVatIncluded = optIncluded && optIncluded.checked;
                        items.forEach(it => {
                            it.total_value = round2((it.qty || 1) * (it.unit_price || 0));
                            it.vat = isVatIncluded ? round2(it.total_value * 0.15) : 0.00;
                            it.value_after_vat = round2(it.total_value + it.vat);
                        });
                        renderItemsTable();
                        recalculateTotalsFromItems();
                    });
                }

                // Verify Confirm Button
                if (btnVerifyConfirm) {
                    btnVerifyConfirm.addEventListener('click', function () {
                        isVerified = !isVerified;
                        syncCalculations();
                    });
                }

                // Tax Choice Listeners
                if (optIncluded) {
                    optIncluded.addEventListener('change', () => {
                        const sub = parseFloat(inputSubtotal ? inputSubtotal.value : 0) || 0;
                        const newVat = round2(sub * 0.15);
                        if (inputVat) inputVat.value = newVat.toFixed(2);
                        if (inputTotal) inputTotal.value = round2(sub + newVat).toFixed(2);
                        if (inputAmount) inputAmount.value = round2(sub + newVat).toFixed(2);
                        if (selectPurcType) selectPurcType.value = '3';
                        items.forEach(it => {
                            it.vat = round2(it.total_value * 0.15);
                            it.value_after_vat = round2(it.total_value + it.vat);
                        });
                        renderItemsTable();
                        syncCalculations();
                    });
                }

                if (optNone) {
                    optNone.addEventListener('change', () => {
                        const sub = parseFloat(inputSubtotal ? inputSubtotal.value : 0) || 0;
                        if (inputVat) inputVat.value = '0.00';
                        if (inputTotal) inputTotal.value = sub.toFixed(2);
                        if (inputAmount) inputAmount.value = sub.toFixed(2);
                        if (selectPurcType) selectPurcType.value = '6';
                        items.forEach(it => {
                            it.vat = 0.00;
                            it.value_after_vat = it.total_value;
                        });
                        renderItemsTable();
                        syncCalculations();
                    });
                }

                // PurcType selector listener
                if (selectPurcType) {
                    selectPurcType.addEventListener('change', () => {
                        if (selectPurcType.value === '3' && optIncluded) {
                            optIncluded.checked = true;
                        } else if (selectPurcType.value === '6' && optNone) {
                            optNone.checked = true;
                        }
                        syncCalculations();
                    });
                }

                // 9 TAS Column field listeners for instant live editing & preview sync
                if (inputPurchaserTin) inputPurchaserTin.addEventListener('input', syncCalculations);
                if (inputSellerTin) inputSellerTin.addEventListener('input', syncCalculations);
                if (inputReceiptNo) inputReceiptNo.addEventListener('input', syncCalculations);
                if (inputMrc) inputMrc.addEventListener('input', syncCalculations);
                if (inputEncodedBy) inputEncodedBy.addEventListener('input', syncCalculations);
                if (inputVendor) inputVendor.addEventListener('input', syncCalculations);
                if (inputDesc) inputDesc.addEventListener('input', syncCalculations);

                if (inputAmount) {
                    inputAmount.addEventListener('input', () => {
                        const amt = parseFloat(inputAmount.value) || 0;
                        const isVatIncluded = optIncluded && optIncluded.checked;
                        if (isVatIncluded) {
                            const sub = round2(amt / 1.15);
                            const vat = round2(amt - sub);
                            if (inputSubtotal) inputSubtotal.value = sub.toFixed(2);
                            if (inputVat) inputVat.value = vat.toFixed(2);
                        } else {
                            if (inputSubtotal) inputSubtotal.value = amt.toFixed(2);
                            if (inputVat) inputVat.value = '0.00';
                        }
                        if (inputTotal) inputTotal.value = amt.toFixed(2);
                        syncCalculations();
                    });
                }

                if (inputSubtotal) {
                    inputSubtotal.addEventListener('input', () => {
                        const isVatIncluded = optIncluded && optIncluded.checked;
                        const sub = parseFloat(inputSubtotal.value) || 0;
                        const vat = isVatIncluded ? round2(sub * 0.15) : 0.00;
                        const tot = round2(sub + vat);
                        if (inputVat) inputVat.value = vat.toFixed(2);
                        if (inputTotal) inputTotal.value = tot.toFixed(2);
                        if (inputAmount) inputAmount.value = tot.toFixed(2);
                        syncCalculations();
                    });
                }

                if (inputVat) {
                    inputVat.addEventListener('input', () => {
                        const sub = parseFloat(inputSubtotal ? inputSubtotal.value : 0) || 0;
                        const vat = parseFloat(inputVat.value) || 0;
                        const tot = round2(sub + vat);
                        if (inputTotal) inputTotal.value = tot.toFixed(2);
                        if (inputAmount) inputAmount.value = tot.toFixed(2);
                        syncCalculations();
                    });
                }

                if (inputTotal) {
                    inputTotal.addEventListener('input', () => {
                        if (inputAmount) inputAmount.value = inputTotal.value;
                        syncCalculations();
                    });
                }

                if (inputReceiptDate) {
                    inputReceiptDate.addEventListener('change', () => {
                        const newEc = gregorianToEthiopian(inputReceiptDate.value);
                        if (ecDisplay) {
                            ecDisplay.innerHTML = `<i class="fa-solid fa-calendar-days text-primary me-1"></i>${newEc ? newEc.formatted : 'N/A'}`;
                        }
                        syncCalculations();
                    });
                }

                if (inputEncodingDate) {
                    inputEncodingDate.addEventListener('change', () => {
                        const newEc = gregorianToEthiopian(inputEncodingDate.value);
                        if (encodingEcDisplay) {
                            encodingEcDisplay.innerHTML = `<i class="fa-solid fa-clock text-secondary me-1"></i>${newEc ? newEc.formatted : 'N/A'}`;
                        }
                        syncCalculations();
                    });
                }

                // Initial validation & sync
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
