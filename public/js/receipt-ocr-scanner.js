/**
 * Receipt OCR Scanner - Reusable client component for purchasing screens
 * Handles AJAX scanning against /purchases/receipt-scan, prefilling form inputs,
 * displaying duplicate warning badges, blocking submissions on duplicate,
 * and maintaining manual form fallback on failure.
 */
(function (window) {
    'use strict';

    const ReceiptOcrScanner = {
        init: function (config) {
            const fileInput = typeof config.fileInput === 'string' ? document.querySelector(config.fileInput) : config.fileInput;
            const form = typeof config.form === 'string' ? document.querySelector(config.form) : (config.form || (fileInput ? fileInput.closest('form') : null));
            const submitBtn = typeof config.submitBtn === 'string' ? document.querySelector(config.submitBtn) : (config.submitBtn || (form ? form.querySelector('button[type="submit"]') : null));
            const statusContainer = typeof config.statusContainer === 'string' ? document.querySelector(config.statusContainer) : config.statusContainer;

            if (!fileInput) {
                console.warn('ReceiptOcrScanner: file input element not found.');
                return;
            }

            const scanUrl = config.scanUrl || '/purchases/receipt-scan';
            const fieldMap = config.fields || {};

            // Ensure status container exists
            let feedbackEl = statusContainer;
            if (!feedbackEl) {
                feedbackEl = document.createElement('div');
                feedbackEl.className = 'ocr-scanner-status-area my-2';
                fileInput.parentNode.insertBefore(feedbackEl, fileInput.nextSibling);
            }

            fileInput.addEventListener('change', function (e) {
                const file = e.target.files && e.target.files[0];
                if (!file) {
                    feedbackEl.innerHTML = '';
                    if (submitBtn) submitBtn.disabled = false;
                    return;
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
                    <div class="card border border-primary border-opacity-25 bg-primary bg-opacity-10 py-2 px-3 mb-2">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <div class="spinner-border spinner-border-sm text-primary" role="status">
                                    <span class="visually-hidden">Scanning...</span>
                                </div>
                                <span class="small fw-semibold text-primary">
                                    <i class="fas fa-wand-magic-sparkles me-1"></i> AI Vision Scanner: Extracting receipt data & verifying ERCA fiscal rules...
                                </span>
                            </div>
                            <span class="badge bg-primary text-white small" style="font-size:0.7rem;">OCR Processing</span>
                        </div>
                    </div>`;

                if (submitBtn) {
                    submitBtn.disabled = true;
                }

                // Prepare FormData
                const formData = new FormData();
                formData.append('receipt_file', file);

                // Get CSRF token
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

                    const scanData = res.data;
                    const extracted = scanData.data || {};

                    // Handle Duplicate Detection
                    if (scanData.is_duplicate) {
                        renderDuplicateAlert(feedbackEl, scanData, submitBtn);
                    } else {
                        renderSuccessBanner(feedbackEl, scanData, submitBtn);
                    }

                    // Auto-prefill mapped form fields
                    prefillMappedFields(form, fieldMap, extracted, scanData);

                    // Call custom item extractor if present
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

                // Smart append for notes
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
